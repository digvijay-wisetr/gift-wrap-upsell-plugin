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
require_once GWU_PATH . 'includes/rest-api.php';
require_once GWU_PATH . 'includes/class-gwu-wraps-table.php';
require_once GWU_PATH . 'includes/admin.php';
require_once GWU_PATH . 'includes/enqueue.php';
require_once GWU_PATH .  'includes/ajax-handler.php';
require_once GWU_PATH .  'includes/gwu-cli.php';
require_once GWU_PATH .  'includes/cron.php';

add_action( 'init', function () {
      load_plugin_textdomain( 'gift-wrap', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );                               
} );


function gwu_activate_plugin(){
    gwu_register_cpt_taxonomy();
    
    if ( ! wp_next_scheduled( 'gwu_daily_expiry_check' ) ) {
        wp_schedule_event( time(), 'daily', 'gwu_daily_expiry_check' );
    }

    flush_rewrite_rules();

}
register_activation_hook(__FILE__,'gwu_activate_plugin');


function gwu_deactivate_plugin(){
    wp_clear_scheduled_hook( 'gwu_daily_expiry_check' );
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__,'gwu_deactivate_plugin');


// Init hooks
add_action('init', 'gwu_register_cpt_taxonomy');
add_action('init', 'gwu_register_meta');
/*  Previsouly i am using rest_api_init,later on i modify it to init  as if we only register it in the REST context, the meta won't be               
  sanitized/authorized when saved from the block editor's metabox, from CLI, or from update_post_meta() calls elsewhere. */

// We add this  wp_next_scheduled prevents duplicate scheduling. 
// It just ensures the cron always exists regardless of how the plugin was activated.
add_action( 'init', 'gwu_next_scheduled');
                                                                                                                                                                                                       
function gwu_next_scheduled(){
    if ( ! wp_next_scheduled( 'gwu_daily_expiry_check' ) ) {                                                                                                                                          
          wp_schedule_event( time(), 'daily', 'gwu_daily_expiry_check' );
    }
}
