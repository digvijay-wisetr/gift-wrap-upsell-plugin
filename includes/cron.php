<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'gwu_daily_expiry_check', 'gwu_handle_expiry' );

function gwu_handle_expiry() {

    $today = current_time( 'Y-m-d' );

    $query = new WP_Query([
        'post_type'      => 'gift_wrap_option',
        'posts_per_page' => -1,
        'fields'         => 'ids',  // only need IDs, this will saves memory
        'meta_query'     => [
            [
                'key'     => 'expiry_date',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE'
            ],
            [
                'key'     => 'is_active',
                'value'   => '1',
            ]
        ],
    ]);

    foreach ( $query->posts as $post_id ) {                                                                                                                                                               
      update_post_meta( $post_id, 'is_active', 0 );
    } 

}