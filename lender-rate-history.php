<?php
/**
 * Plugin Name: Lender Rate History 
 * Plugin URI: https://yourdomain.com/lender-rate-history
 * Description: Tracks and displays historical rate changes for lenders with ACF integration
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourdomain.com
 * License: GPL v2 or later
 * Text Domain: lender-rate-history
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LRH_VERSION', '1.0.0');
define('LRH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LRH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LRH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('LRH_TABLE_NAME', 'lender_rate_history');


// DIREKT efter plugin constants, INNAN allt annat
add_action('plugins_loaded', function() {
    // Ladda dependencies
    if (file_exists(LRH_PLUGIN_DIR . 'includes/class-lrh-database.php')) {
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-database.php';
    }
    if (file_exists(LRH_PLUGIN_DIR . 'admin/class-lrh-external-sources.php')) {
        require_once LRH_PLUGIN_DIR . 'admin/class-lrh-external-sources.php';
    }
    // Ladda och starta option tracker
    if (file_exists(LRH_PLUGIN_DIR . 'includes/class-lrh-option-tracker.php')) {
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-option-tracker.php';
    }
}, 5);


/**
 * Activation hook
 */
function lrh_activate() {
    require_once LRH_PLUGIN_DIR . 'includes/class-lrh-activator.php';
    LRH_Activator::activate();
}
register_activation_hook(__FILE__, 'lrh_activate');

/**
 * Deactivation hook
 */
function lrh_deactivate() {
    require_once LRH_PLUGIN_DIR . 'includes/class-lrh-activator.php';
    LRH_Activator::deactivate();
}
register_deactivation_hook(__FILE__, 'lrh_deactivate');

/**
 * Load the plugin
 */
function lrh_init() {
    // Load text domain
    load_plugin_textdomain('lender-rate-history', false, dirname(LRH_PLUGIN_BASENAME) . '/languages');
    
    // Require the core class
    require_once LRH_PLUGIN_DIR . 'includes/class-lrh-core.php';
    
    // Initialize the plugin
    $plugin = new LRH_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'lrh_init');

/**
 * Global function to get rate history API instance
 */
function lrh_get_api() {
    return LRH_Core::get_instance()->get_api();
}

/**
 * Template tag: Get rate change
 */
function lrh_get_rate_change($post_id = null, $field_name = '', $format = 'percentage') {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $api = lrh_get_api();
    return $api->get_field_change($post_id, $field_name, $format);
}

/**
 * Template tag: Display rate change
 */
function lrh_rate_change($post_id = null, $field_name = '', $format = 'percentage') {
    echo lrh_get_rate_change($post_id, $field_name, $format);
}