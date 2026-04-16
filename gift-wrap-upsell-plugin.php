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

// Includes
require_once GWU_PATH . 'includes/helpers.php';
require_once GWU_PATH . 'includes/post-types.php';
require_once GWU_PATH . 'includes/meta.php';


register_activation_hook(__FILE__,'gwu_activate_plugin');
function gwu_activate_plugin(){
    gwu_register_cpt_taxonomy();
    flush_rewrite_rules();

}

register_deactivation_hook(__FILE__,'gwu_deactivate_plugin');
function gwu_deactivate_plugin(){
    flush_rewrite_rules();
}

// Init hooks
add_action('init', 'gwu_register_cpt_taxonomy');
add_action('rest_api_init', 'gwu_register_meta');

