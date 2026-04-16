<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

function gwu_sanitize_float( $value ) {
    return  max( 0, (float) $value );  // restricting not to send the negative value
}

function gwu_can_edit() {
    return current_user_can( 'edit_posts' );
}