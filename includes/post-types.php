<?php
// File responsible for registering the CPT and Taxonomy
if ( ! defined( 'ABSPATH' ) ) exit;

function gwu_register_cpt_taxonomy() {

    register_post_type( 'gift_wrap_option', [
        'labels' => [
            'name'               => __( 'Gift Wraps', 'gift-wrap-upsell-plugin' ),
            'singular_name'      => __( 'Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'add_new'            => __( 'Add New', 'gift-wrap-upsell-plugin' ),
            'add_new_item'       => __( 'Add New Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'edit_item'          => __( 'Edit Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'new_item'           => __( 'New Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'view_item'          => __( 'View Gift Wrap', 'gift-wrap-upsell-plugin' ),
            'search_items'       => __( 'Search Gift Wraps', 'gift-wrap-upsell-plugin' ),
        ],
        'public'          => true,
        'has_archive'     => true,
        'rewrite'         => [ 'slug' => 'gift-wrap-upsell-plugin' ],
        'show_in_rest'    => true,
        'supports'        => [ 'title', 'thumbnail','custom-fields' ],
        'menu_icon'       => 'dashicons-tickets-alt',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ] );

    register_taxonomy( 'gift_wrap_season', 'gift_wrap_option', [
        'labels' => [
            'name' => __( 'Seasons', 'gift-wrap-upsell-plugin' ),
        ],
        'public'              => true,
        'hierarchical'        => true,
        'show_in_rest'        => true,
        'show_admin_column'   => true,
        'rewrite'             => [ 'slug' => 'gift-wrap-upsell-plugin-season' ],
    ] );
}