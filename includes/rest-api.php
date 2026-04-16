<?php 

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'gwu_register_routes' );

function gwu_register_routes() {

    register_rest_route( 'gift-wrap/v1', '/options', [
        'methods'  => 'GET',
        'callback' => 'gwu_get_active_wraps',
        'permission_callback' => 'gwu_rest_permission',
        'args' => [
            'season' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'gwu_validate_season',
            ],
        ],
    ] );
}


function gwu_rest_permission( WP_REST_Request $request ) {
    return true; // public endpoint, but NOT '__return_true'
}

function gwu_validate_season( $value, WP_REST_Request $request, $param ) {
    return is_string( $value );
}


function gwu_get_active_wraps( $request ) {

    $season = $request->get_param( 'season' );
    $args = [
        'post_type'      => 'gift_wrap_option',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'   => 'is_active',
                'value' => true,
            ]
        ]
    ];

    // Optional filter by season
    if ( ! empty( $season  ) ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'gift_wrap_season',
                'field'    => 'slug',
                'terms'    => $season,
            ]
        ];
    }

    $query = new WP_Query( $args );

    if ( empty( $query->posts ) ) {
        return new WP_Error(
            'no_wraps',
            __( 'No active gift wraps found.', 'gift-wrap' ),
            [ 'status' => 404 ]
        );
    }

    $data = [];

    foreach ( $query->posts as $post ) {

        $data[] = [
            'id'        => $post->ID,
            'name'      => get_the_title( $post ),
            'image'     => get_the_post_thumbnail_url( $post, 'full' ),
            'surcharge' => (float) get_post_meta( $post->ID, 'surcharge', true ),
        ];
    }

    return new WP_REST_Response( $data, 200 );
}