<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sanitize a float value and ensure it is non-negative.
 *
 * @param mixed $value Raw input value.
 * @return float
 */
function gwu_sanitize_float( $value ) {
    return  max( 0.0, (float) $value );  // restricting not to send the negative value
}

/**
 * Check if the current user can edit gift wrap posts.
 *
 * @return bool
 */
function gwu_can_edit() {
    return current_user_can( 'edit_posts' );
}