<?php
add_action( 'admin_enqueue_scripts', 'gwu_admin_scripts' );

function gwu_admin_scripts( $hook ) {

    // Only load on your page
    if ( $hook !== 'gift_wrap_option_page_gwu-wraps' ) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
        'gwu-admin-js',
        GWU_URL . 'assets/js/admin.js',
        [ 'jquery' ],
        GWU_VERSION,
        true
    );
}