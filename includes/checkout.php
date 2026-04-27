<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Step 1: Add gift wrap selector to checkout fields.
 * Hook: woocommerce_checkout_fields
 */
add_filter( 'woocommerce_checkout_fields', 'gwu_add_checkout_field' );

function gwu_add_checkout_field( $fields ) {

    $query = new WP_Query([
        'post_type'      => 'gift_wrap_option',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'   => 'is_active',
                'value' => '1',
            ],
        ],
    ]);

   // error_log( 'GWU wraps found: ' . $query->found_posts ); // remove before PR

    if ( empty( $query->posts ) ) {
        return $fields;
    }

    $options = [ '' => __( 'No gift wrap', 'gift-wrap-upsell-plugin' ) ];

    foreach ( $query->posts as $post ) {
        $surcharge = (float) get_post_meta( $post->ID, 'surcharge', true );
        $label     = get_the_title( $post );

        if ( $surcharge > 0 ) {
            $label .= ' (+' . wp_strip_all_tags( wc_price( $surcharge ) ) . ')';
        }

        $options[ (string) $post->ID ] = $label;
    }

    $fields['order']['gwu_wrap_id'] = [
        'type'     => 'select',
        'label'    => __( 'Gift wrap', 'gift-wrap-upsell-plugin' ),
        'options'  => $options,
        'required' => false,
        'priority' => 5,
        // Restore previously selected value from session
        'default'  => absint( WC()->session->get( 'gwu_wrap_id' ) ) ?: '',
    ];

    return $fields;
}


/**
 * Step 2: Save wrap selection to WC session.
 * Hook: woocommerce_checkout_update_order_review
 * WC sends the entire form as a URL-encoded string — we parse it ourselves.
 */
add_action( 'woocommerce_checkout_update_order_review', 'gwu_save_wrap_to_session' );

function gwu_save_wrap_to_session( $posted_data ) {

    parse_str( $posted_data, $output );

    $wrap_id = isset( $output['gwu_wrap_id'] ) ? absint( $output['gwu_wrap_id'] ) : 0;

    // Initialize session cookie if it doesn't exist yet
    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    WC()->session->set( 'gwu_wrap_id', $wrap_id );

    // error_log( 'GWU saving to session: ' . $wrap_id );                          // remove before PR
    // error_log( 'GWU session after set: ' . WC()->session->get( 'gwu_wrap_id' ) ); // remove before PR
}


/**
 * Step 3: Read from session and add surcharge as a cart fee.
 * Hook: woocommerce_cart_calculate_fees
 * Never touch cart totals directly — always use WC()->cart->add_fee().
 */
add_action( 'woocommerce_cart_calculate_fees', 'gwu_apply_wrap_fee' );

function gwu_apply_wrap_fee() {

    $wrap_id = absint( WC()->session->get( 'gwu_wrap_id' ) );

    // error_log( 'GWU DEBUG session wrap_id in fee: ' . $wrap_id ); // remove before PR

    if ( ! $wrap_id ) {
        return;
    }

    $post = get_post( $wrap_id );

    // Always re-validate from DB — never trust session data blindly
    if (
        ! ( $post instanceof WP_Post )        ||
        $post->post_type   !== 'gift_wrap_option' ||
        $post->post_status !== 'publish'          ||
        ! get_post_meta( $wrap_id, 'is_active', true )
    ) {
        WC()->session->set( 'gwu_wrap_id', 0 ); // Clear bad/stale data
        return;
    }

    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );

    if ( $surcharge <= 0 ) {
        return;
    }

    WC()->cart->add_fee(
        get_the_title( $post ), // e.g. "Christmas Wrap" — shown in cart totals
        $surcharge,
        true                    // taxable — set false if wrap fees are tax-exempt
    );
}



/**
 * Step 4: Save wrap ID to order meta on order creation.
 * Hook: woocommerce_checkout_order_created
 * HPOS-safe: always use $order->update_meta_data() + $order->save()
 * Never use update_post_meta( $order_id, ... ) for orders.
 */
add_action( 'woocommerce_checkout_order_created', 'gwu_save_wrap_to_order' );

function gwu_save_wrap_to_order( $order ) {

    $wrap_id = absint( WC()->session->get( 'gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return;
    }

    // Validate wrap still exists and is active before saving
    $post = get_post( $wrap_id );

    if (
        ! ( $post instanceof WP_Post )            ||
        $post->post_type   !== 'gift_wrap_option' ||
        $post->post_status !== 'publish'          ||
        ! get_post_meta( $wrap_id, 'is_active', true )
    ) {
        return;
    }

    // HPOS-safe way to write order meta
    $order->update_meta_data( '_gwu_wrap_id', $wrap_id );
    $order->save();

    // Clear session after saving to order — wrap is now committed
    WC()->session->set( 'gwu_wrap_id', 0 );
}


/**
 * Step 5: Display selected wrap on the order received (thank-you) page.
 * Hook: woocommerce_order_details_after_order_table
 * Read meta via $order->get_meta() — HPOS safe, never get_post_meta() for orders.
 */
add_action( 'woocommerce_order_details_after_order_table', 'gwu_display_wrap_on_thankyou' );

function gwu_display_wrap_on_thankyou( $order ) {

    $wrap_id = absint( $order->get_meta( '_gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return;
    }

    $post = get_post( $wrap_id );

    if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'gift_wrap_option' ) {
        return;
    }

    $title     =  $post->post_title;
    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );
    $image     = get_the_post_thumbnail_url( $wrap_id, 'thumbnail' );
    ?>
    <section class="gwu-thankyou" style="margin-top:2em;">
        <h2 style="font-size:1.2em; margin-bottom:.75em;">
            <?php esc_html_e( 'Your Gift Wrap', 'gift-wrap-upsell-plugin' ); ?>
        </h2>
        <table class="woocommerce-table shop_table" style="width:100%;">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e( 'Wrap', 'gift-wrap-upsell-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Surcharge', 'gift-wrap-upsell-plugin' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="width:80px;">
                        <?php if ( $image ) : ?>
                            <img src="<?php echo esc_url( $image ); ?>"
                                 alt="<?php echo esc_attr( $title ); ?>"
                                 style="max-width:80px; border-radius:4px;">
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $title ); ?></td>
                    <td><?php echo wp_kses_post( wc_price( $surcharge ) ); ?></td>
                </tr>
            </tbody>
        </table>
    </section>
    <?php
}


/**
 * Step 6: Show selected wrap in a metabox on the admin order screen.
 * Uses add_meta_boxes — works for both HPOS and legacy post-based orders.
 */
add_action( 'add_meta_boxes', 'gwu_register_order_metabox' );

function gwu_register_order_metabox() {

    // HPOS uses 'woocommerce_page_wc-orders' as the screen,
    // legacy post-based orders use 'shop_order'.
    // We register for both so it works regardless of storage mode.
    foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
        add_meta_box(
            'gwu_wrap_metabox',
            __( 'Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'gwu_render_order_metabox',
            $screen,
            'side',   // position — side column
            'default'
        );
    }
}

function gwu_render_order_metabox( $post_or_order ) {

    // Under HPOS $post_or_order is a WC_Order object.
    // Under legacy it is a WP_Post — we need to handle both.
    if ( $post_or_order instanceof WP_Post ) {
        $order = wc_get_order( $post_or_order->ID );
    } else {
        $order = $post_or_order;
    }

    if ( ! $order ) {
        return;
    }

    $wrap_id = absint( $order->get_meta( '_gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        echo '<p>' . esc_html__( 'No gift wrap selected.', 'gift-wrap-upsell-plugin' ) . '</p>';
        return;
    }

    $post = get_post( $wrap_id );

    if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'gift_wrap_option' ) {
        echo '<p>' . esc_html__( 'Wrap not found.', 'gift-wrap-upsell-plugin' ) . '</p>';
        return;
    }

    $title     = get_the_title( $post );
    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );
    $image     = get_the_post_thumbnail_url( $wrap_id, 'thumbnail' );
    ?>
    <div class="gwu-metabox">
        <?php if ( $image ) : ?>
            <img src="<?php echo esc_url( $image ); ?>"
                 alt="<?php echo esc_attr( $title ); ?>"
                 style="max-width:100%; border-radius:4px; margin-bottom:.5em;">
        <?php endif; ?>
        <p>
            <strong><?php esc_html_e( 'Wrap:', 'gift-wrap-upsell-plugin' ); ?></strong>
            <?php echo esc_html( $title ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Surcharge:', 'gift-wrap-upsell-plugin' ); ?></strong>
            <?php echo wp_kses_post( wc_price( $surcharge ) ); ?>
        </p>
    </div>
    <?php
}

/**
 * Step 7: Show gift wrap in order emails via template override.
 * Hook: woocommerce_email_order_details
 * wc_get_template() resolves theme overrides automatically —
 * never hardcode a theme path or use include/require directly.
 */
add_action( 'woocommerce_email_order_details', 'gwu_add_wrap_to_email', 15, 4 );

function gwu_add_wrap_to_email( $order, $sent_to_admin, $plain_text, $email ) {

    // Plain text emails can't render HTML — skip
    if ( $plain_text ) {
        return;
    }

    $wrap_id = absint( $order->get_meta( '_gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return;
    }

    $post = get_post( $wrap_id );

    if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'gift_wrap_option' ) {
        return;
    }

    // Load template — wc_get_template() checks theme first, then plugin path
    wc_get_template(
        'emails/gift-wrap-notice.php',
        [
            'wrap_title'     => get_the_title( $post ),
            'wrap_surcharge' => (float) get_post_meta( $wrap_id, 'surcharge', true ),
            'wrap_image'     => get_the_post_thumbnail_url( $wrap_id, 'thumbnail' ),
        ],
        '',                                          // theme path (empty = use default)
        GWU_PATH . 'templates/'                      // plugin fallback path
    );
}