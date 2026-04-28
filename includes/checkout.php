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

    if ( empty( $query->posts ) ) {
        return $fields;
    }

    $options = [ '' => __( 'No gift wrap', 'gift-wrap-upsell-plugin' ) ];

    // Build wrap data for JS card rendering
    $wrap_data = [];

    foreach ( $query->posts as $post ) {
        $surcharge = (float) get_post_meta( $post->ID, 'surcharge', true );
        $label     = get_the_title( $post );

        if ( $surcharge > 0 ) {
            $label .= ' (+' . wp_strip_all_tags( wc_price( $surcharge ) ) . ')';
        }

        $options[ (string) $post->ID ] = $label;

        // Pass rich data to JS so it can build visual cards
        $wrap_data[] = [
            'id'        => $post->ID,
            'title'     => get_the_title( $post ),
            'surcharge' => $surcharge,
            'price'     => wp_strip_all_tags( wc_price( $surcharge ) ),
            'image'     => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
        ];
    }

    $fields['order']['gwu_wrap_id'] = [
        'type'     => 'select',
        'label'    => __( 'Gift wrap', 'gift-wrap-upsell-plugin' ),
        'options'  => $options,
        'required' => false,
        'priority' => 5,
        'default'  => absint( WC()->session->get( 'gwu_wrap_id' ) ) ?: '',
        // Pass wrap data as a data attribute for JS to read
        'custom_attributes' => [
            'data-gwu-wraps' => esc_attr( wp_json_encode( $wrap_data ) ),
        ],
    ];

    return $fields;
}

/**
 * Step 2: Save wrap selection to WC session.
 */
add_action( 'woocommerce_checkout_update_order_review', 'gwu_save_wrap_to_session' );

function gwu_save_wrap_to_session( $posted_data ) {

    parse_str( $posted_data, $output );

    $wrap_id = isset( $output['gwu_wrap_id'] ) ? absint( $output['gwu_wrap_id'] ) : 0;

    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    WC()->session->set( 'gwu_wrap_id', $wrap_id );
}

/**
 * Step 3: Read from session and add surcharge as a cart fee.
 */
add_action( 'woocommerce_cart_calculate_fees', 'gwu_apply_wrap_fee' );

function gwu_apply_wrap_fee() {

    $wrap_id = absint( WC()->session->get( 'gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return;
    }

    $post = get_post( $wrap_id );

    if (
        ! ( $post instanceof WP_Post )            ||
        $post->post_type   !== 'gift_wrap_option' ||
        $post->post_status !== 'publish'          ||
        ! get_post_meta( $wrap_id, 'is_active', true )
    ) {
        WC()->session->set( 'gwu_wrap_id', 0 );
        return;
    }

    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );

    if ( $surcharge <= 0 ) {
        return;
    }

    WC()->cart->add_fee(
        get_the_title( $post ),
        $surcharge,
        true
    );
}

/**
 * Step 4: Save wrap ID to order meta on order creation.
 * HPOS-safe: $order->update_meta_data() + $order->save()
 */
add_action( 'woocommerce_checkout_order_created', 'gwu_save_wrap_to_order' );

function gwu_save_wrap_to_order( $order ) {
    
    // Idempotent — don't overwrite if already saved
    if ( $order->get_meta( '_gwu_wrap_id' ) ) {
        WC()->session->set( 'gwu_wrap_id', 0 );
        return;
    }

    $wrap_id = absint( WC()->session->get( 'gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return;
    }

    $post = get_post( $wrap_id );

    if (
        ! ( $post instanceof WP_Post )            ||
        $post->post_type   !== 'gift_wrap_option' ||
        $post->post_status !== 'publish'          ||
        ! get_post_meta( $wrap_id, 'is_active', true )
    ) {
        return;
    }

    $order->update_meta_data( '_gwu_wrap_id', $wrap_id );
    $order->save();

    WC()->session->set( 'gwu_wrap_id', 0 );
}

/**
 * Step 5: Display selected wrap on the thank-you page.
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

    $title     = get_the_title( $post );
    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );
    $image     = get_the_post_thumbnail_url( $wrap_id, 'thumbnail' );
    ?>
    <section class="gwu-thankyou">
        <h2><?php esc_html_e( 'Your Gift Wrap', 'gift-wrap-upsell-plugin' ); ?></h2>
        <table class="woocommerce-table shop_table">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e( 'Wrap', 'gift-wrap-upsell-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Surcharge', 'gift-wrap-upsell-plugin' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="gwu-thankyou__image">
                        <?php if ( $image ) : ?>
                            <img src="<?php echo esc_url( $image ); ?>"
                                 alt="<?php echo esc_attr( $title ); ?>">
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
 * Step 6: Admin order metabox — HPOS + legacy compatible.
 */
add_action( 'add_meta_boxes', 'gwu_register_order_metabox' );

function gwu_register_order_metabox() {
    foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
        add_meta_box(
            'gwu_wrap_metabox',
            __( 'Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'gwu_render_order_metabox',
            $screen,
            'side',
            'default'
        );
    }
}

function gwu_render_order_metabox( $post_or_order ) {

    $order = $post_or_order instanceof WP_Post
        ? wc_get_order( $post_or_order->ID )
        : $post_or_order;

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
                 alt="<?php echo esc_attr( $title ); ?>">
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
 * Step 7: Email template via wc_get_template().
 */
add_action( 'woocommerce_email_order_details', 'gwu_add_wrap_to_email', 15, 4 );

function gwu_add_wrap_to_email( $order, $sent_to_admin, $plain_text, $email ) {

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

    wc_get_template(
        'emails/gift-wrap-notice.php',
        [
            'wrap_title'     => get_the_title( $post ),
            'wrap_surcharge' => (float) get_post_meta( $wrap_id, 'surcharge', true ),
            'wrap_image'     => get_the_post_thumbnail_url( $wrap_id, 'thumbnail' ),
        ],
        '',
        GWU_PATH . 'templates/'
    );
}


/**
 * Step 8: Handle order status transitions — idempotent.
 * Hook: woocommerce_order_status_changed
 * Safe to fire twice for the same transition — checked via _gwu_wrap_processing_noted.
 */
add_action( 'woocommerce_order_status_changed', 'gwu_handle_order_status_changed', 10, 4 );

function gwu_handle_order_status_changed( $order_id, $old_status, $new_status, $order ) {

    // Only act when order moves to processing
    if ( $new_status !== 'processing' ) {
        return;
    }

    $wrap_id = absint( $order->get_meta( '_gwu_wrap_id' ) );

    if ( ! $wrap_id ) {
        return; // No wrap on this order — nothing to do
    }

    // Idempotency guard — if we already handled this transition, stop.
    // This makes it safe even if WC fires the hook twice for the same order.
    if ( $order->get_meta( '_gwu_wrap_processing_noted' ) ) {
        return;
    }

    // Mark as handled — HPOS safe
    $order->update_meta_data( '_gwu_wrap_processing_noted', '1' );
    $order->save();
}