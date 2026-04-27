<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
        'sanitize_callback' => 'gwu_sanitize_float', //sanitization callback
        'auth_callback'     => 'gwu_can_edit',  // auth call back check 
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

    //rest_sanitize_boolean() is a WordPress function designed to convert string, 
    //integer, or boolean inputs (such as 'true', '1', 0, or false) into a strict PHP boolean (true or false)

    register_post_meta( 'gift_wrap_option', 'expiry_date', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'   => 'string',
                'format' => 'date',
            ],
        ],
        'sanitize_callback' => 'sanitize_text_field',  // sanitized the text field 
        'auth_callback'     => 'gwu_can_edit',
    ] );
}