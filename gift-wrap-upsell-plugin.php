<?php

/**
 * Plugin Name: Gift Wrap Upsell Plugin
 * Description: Adds gift wrap upsell functionality.
 * Version: 1.0.0
 * Author: Digvijay Singh
 * Text Domain: gift-wrap
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define('GWU_PATH', plugin_dir_path(__FILE__));
define('GWU_URL', plugin_dir_url(__FILE__));
define('GWU_VERSION', '1.0.0');

register_activation_hook(__FILE__,'gwu_activate_plugin');
function gwu_activate_plugin(){
    gwu_register_cpt_taxonomy();
    flush_rewrite_rules();

}

register_deactivation_hook(__FILE__,'gwu_deactivate_plugin');
function gwu_deactivate_plugin(){
    flush_rewrite_rules();
}

// registering our cpt
add_action( 'init', 'gwu_register_cpt_taxonomy' );

function gwu_register_cpt_taxonomy() {

    register_post_type( 'gift_wrap_option', [
        'labels' => [
            'name'               => __( 'Gift Wraps', 'gift-wrap' ),
            'singular_name'      => __( 'Gift Wrap', 'gift-wrap' ),
            'add_new'            => __( 'Add New', 'gift-wrap' ),
            'add_new_item'       => __( 'Add New Gift Wrap', 'gift-wrap' ),
            'edit_item'          => __( 'Edit Gift Wrap', 'gift-wrap' ),
            'new_item'           => __( 'New Gift Wrap', 'gift-wrap' ),
            'view_item'          => __( 'View Gift Wrap', 'gift-wrap' ),
            'search_items'       => __( 'Search Gift Wraps', 'gift-wrap' ),
        ],
        'public'          => true,
        'has_archive'     => true,
        'rewrite'         => [ 'slug' => 'gift-wrap' ],
        'show_in_rest'    => true,
        'supports'        => [ 'title', 'thumbnail','custom-fields' ],
        'menu_icon'       => 'dashicons-gift',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ] );

    register_taxonomy( 'gift_wrap_season', 'gift_wrap_option', [
        'labels' => [
            'name' => __( 'Seasons', 'gift-wrap' ),
        ],
        'public'              => true,
        'hierarchical'        => true,
        'show_in_rest'        => true,
        'show_admin_column'   => true,
        'rewrite'             => [ 'slug' => 'gift-wrap-season' ],
    ] );
}

// adding the meta fields
add_action( 'rest_api_init', 'gwu_register_meta' );

function gwu_register_meta() {

    register_post_meta( 'gift_wrap_option', 'surcharge', [
        'type'              => 'number',
        'single'            => true,
        'default'           => 0,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'number',
            ],
        ],
        'sanitize_callback' => 'gwu_sanitize_float',
        'auth_callback'     => 'gwu_can_edit',
    ] );

    register_post_meta( 'gift_wrap_option', 'is_active', [
        'type'              => 'boolean',
        'single'            => true,
        'default'           => false,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'boolean',
            ],
        ],
        'sanitize_callback' => 'rest_sanitize_boolean',
        'auth_callback'     => 'gwu_can_edit',
    ] );

    register_post_meta( 'gift_wrap_option', 'expiry_date', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'   => 'string',
                'format' => 'date',
            ],
        ],
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => 'gwu_can_edit',
    ] );
}

function gwu_sanitize_float( $value ) {
    return (float) $value;
}

function gwu_can_edit() {
    return current_user_can( 'edit_posts' );
}