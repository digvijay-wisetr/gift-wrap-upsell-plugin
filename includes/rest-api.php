<?php 
 /**
 * REST API routes for Gift Wrap Upsell.
 *                                                                                                                                     
 * @package GiftWrapUpsell
 */ 

if ( ! defined( 'ABSPATH' ) ) exit;
/**
* Register plugin REST routes.
*/ 

function gwu_register_routes() {

    register_rest_route( 'gift-wrap-upsell-plugin/v1', '/options', [
        'methods'  => 'GET',
        'callback' => 'gwu_get_active_wraps',
        'permission_callback' => 'gwu_rest_permission',
        'args' => [
            'season' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'gwu_validate_season',
            ],
            'per_page' => [                                                                                                            
                  'default'           => 20,
                  'sanitize_callback' => 'absint',
                  'validate_callback' => static fn( $v ) => $v >= 1 && $v <= 100,                                                        
              ],
              'page' => [                                                                                                                
                  'default'           => 1,
                  'sanitize_callback' => 'absint',                                                                                       
              ],
        ],
    ] );

    register_rest_route( 'gift-wrap-upsell-plugin/v1', '/webhook/shipped', [
        'methods'             => 'POST',
        'callback'            => 'gwu_handle_shipped_webhook',
        'permission_callback' => 'gwu_webhook_permission',
        'args'                => [
            'event_id'  => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'order_id'  => [ 'required' => true,  'sanitize_callback' => 'absint' ],
            'wrap_id'   => [ 'required' => true,  'sanitize_callback' => 'absint' ],
            'tracking'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );
}

add_action( 'rest_api_init', 'gwu_register_routes' );



/**
 * Gate the options endpoint.
 *                                                                                                                                     
 * Public read access, gated only by a site-level toggle. Kept deliberately
 * non-trivial so this file documents its own access decision.                                                                         
 *                                                                                                                                     
 * @return bool                                                                                                                        
 */

function gwu_rest_permission( WP_REST_Request $request ) {
    //return true; // public endpoint, but NOT '__return_true'
     return (bool) get_option( 'gwu_api_enabled', true );
}


/**             
* Validate the ?season= slug against registered taxonomy terms.
*                                                                                                                                     
* @param mixed $value Raw request value.
* @return bool|WP_Error                                                                                                               
*/
// function gwu_validate_season( $value, WP_REST_Request $request, $param ) {
//     return is_string( $value );
// }
// Bette approach above one have issue like it is accepting accepts "", "' OR 1=1--"

function gwu_validate_season( $value, WP_REST_Request $request, $param ) {                                                                             
      if ( ! is_string( $value ) || $value === '' ) {                                                                                    
          return new WP_Error(
              'rest_invalid_season',                                                                                                     
              __( 'Season must be a non-empty string.', 'gift-wrap-upsell-plugin' ),
              [ 'status' => 400 ]                                                                                                        
          );      
      }                                                                                                                                  
      return (bool) term_exists( $value, 'gift_wrap_season' );
}

/**
 * Return active gift wrap options.
 *                                                                                                                                     
 * @param WP_REST_Request $request Current request.
 * @return WP_REST_Response                                                                                                            
 */   

function gwu_get_active_wraps( WP_REST_Request $request ) {

    $season = $request->get_param( 'season' );
    $per_page = (int) $request->get_param( 'per_page' );
    $page     = (int) $request->get_param( 'page' ); 

    $args = [
        'post_type'      => 'gift_wrap_option',
        'post_status'    => 'publish',
        'posts_per_page'         => $per_page,
        'paged'                  => $page,                                                                                        
        'update_post_term_cache' => false,
        'meta_query'             => [                                                                                                  
            [    
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                // Row count will remain low — full table scan cost is acceptable here.                                                                                                               
                // update_post_meta stores booleans as string '1' — match the storage format.
                'key'     => 'is_active',                                                                                            
                'value'   => '1', 
                'compare' => '=',                                                                                                 
            ],
        ],
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
            __( 'No active gift wraps found.', 'gift-wrap-upsell-plugin' ),
            [ 'status' => 404 ]
        );
    }

    // $data = [];

    // foreach ( $query->posts as $post ) {

    //     $data[] = [
    //         'id'        => $post->ID,
    //         'name'      => get_the_title( $post ),
    //         'image' => get_the_post_thumbnail_url( $post, 'full' ) ?: null,
    //         'surcharge' => (float) get_post_meta( $post->ID, 'surcharge', true ),
    //     ];
    // }

    // return new WP_REST_Response( $data, 200 );

    // Better way of doing the same code 
    $data = array_map( static function ( WP_Post $post ) {                                                                             
          return [
              'id'        => $post->ID,                                                                                                  
              'name'      => get_the_title( $post ),                                                                                     
              'image'     => get_the_post_thumbnail_url( $post, 'full' ) ?: null,
              'surcharge' => (float) get_post_meta( $post->ID, 'surcharge', true ),                                                      
          ];                                                                                                                             
      }, $query->posts );
                                                                                                                                         
    // Empty filter results are a valid 200, not a 404.                                                                                
    return new WP_REST_Response(
        [                                                                                                                              
            'data'  => $data,
            'total' => (int) $query->found_posts,                                                                                      
            'page'  => $page,
        ],
        200
    );
}

function gwu_webhook_permission( WP_REST_Request $request ) {
    // In a real integration: verify a shared secret header
    // For the mock: allow any authenticated request
    return current_user_can( 'manage_woocommerce' );
}

function gwu_handle_shipped_webhook( WP_REST_Request $request ) {
    $logger   = wc_get_logger();
    $context  = [ 'source' => 'gift-wrap' ];
    $event_id = $request->get_param( 'event_id' );
    $order_id = $request->get_param( 'order_id' );

    // Deduplication — store seen event IDs with TTL (24 hours)
    $dedup_key = 'gwu_webhook_event_' . md5( $event_id );
    if ( get_transient( $dedup_key ) ) {
        $logger->info( 'Duplicate webhook event: ' . $event_id, $context );
        return new WP_REST_Response( [ 'status' => 'duplicate' ], 409 );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return new WP_REST_Response( [ 'status' => 'order_not_found' ], 404 );
    }

    // Mark shipped — HPOS safe, idempotent
    $order->update_meta_data( '_gwu_wrap_shipped', '1' );
    $order->update_meta_data( '_gwu_wrap_tracking', $request->get_param( 'tracking' ) ?? '' );
    $order->save();

    // Record event ID so replays are no-ops
    set_transient( $dedup_key, '1', DAY_IN_SECONDS );

    $logger->info( 'Wrap shipped for order ' . $order_id . ', event ' . $event_id, $context );
    return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
}