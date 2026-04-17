<?php
add_action( 'admin_enqueue_scripts', 'gwu_admin_scripts' );

function gwu_admin_scripts( $hook ) {

    // Only load on your page
    $allowed = [    
      'gift_wrap_option_page_gwu-wraps',
      'gift_wrap_option_page_gwu-wrap-view',
    ];  
    //we allowing it to render it not specific on the Manage Wraps page
   // but also trigger it our for  AJAX previews from the "All Wraps" page                                                                                                                                                       
    
    if ( ! in_array( $hook, $allowed, true ) ) {                                                                                                                 
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

    wp_localize_script( 'gwu-admin-js', 'gwuAjax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'gwu_preview_nonce' ),
        'loading'       => __( 'Loading...', 'gift-wrap' ),                                                                                                                                               
        'requestFailed' => __( 'Request failed.', 'gift-wrap' ),
    ] );

    wp_localize_script( 'gwu-admin-js', 'gwuAdmin', [                                                                                      
      'mediaTitle'    => __( 'Select Image', 'gift-wrap' ),                                                                              
      'mediaButton'   => __( 'Use this image', 'gift-wrap' ),                                                                            
      'invalidImage'  => __( 'Please select a valid image.', 'gift-wrap' ),                                                              
  ] );
}