<?php 
if ( ! defined( 'ABSPATH' ) ) exit;
// This file for resposible for handling the ajax
add_action( 'wp_ajax_gift_wrap_preview', 'gwu_ajax_preview' );
//add_action( 'wp_ajax_nopriv_gift_wrap_preview', 'gwu_ajax_preview' ); commenting this as it will make our hook exposed non logged in user


function gwu_ajax_preview() {

    //Verifying nonce before processing
    check_ajax_referer( 'gwu_preview_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'gift-wrap-upsell-plugin' ), 403 );
    }

    $wrap_id = isset( $_POST['wrap_id'] ) ? absint( $_POST['wrap_id'] ) : 0;

    if ( ! $wrap_id ) {
        wp_send_json_error( __( 'Invalid wrap ID.', 'gift-wrap-upsell-plugin' ) );
    }

    $post = get_post( $wrap_id );

    if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'gift_wrap_option' ) {
        wp_send_json_error( __( 'Wrap not found.', 'gift-wrap-upsell-plugin' ) );
    }

    // Fetch data
    $title     = get_the_title( $post );
    $image     = get_the_post_thumbnail_url( $wrap_id, 'medium' );
    $surcharge = (float) get_post_meta( $wrap_id, 'surcharge', true );

    // Build HTML safely
    ob_start();
    ?>
    <div class="gwu-preview">
        <h3><?php echo esc_html( $title ); ?></h3>

        <?php if ( $image ): ?>
            <img src="<?php echo esc_url( $image ); ?>" style="max-width:200px;">
        <?php endif; ?>

        <p>
            <?php echo esc_html__( 'Price:', 'gift-wrap-upsell-plugin' ); ?>
            <?php echo esc_html( number_format_i18n( $surcharge, 2 ) ); ?>
        </p>
    </div>
    <?php

    wp_send_json_success( [
        'html' => ob_get_clean()
    ] );
}