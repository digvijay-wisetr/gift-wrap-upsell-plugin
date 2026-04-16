<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

function gwu_sanitize_float( $value ) {
    return (float) $value;
}

function gwu_can_edit() {
    return current_user_can( 'edit_posts' );
}