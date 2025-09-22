<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Lender_Rate_History
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user permissions
if (!current_user_can('activate_plugins')) {
    return;
}

// Check that it's the correct plugin
if ($_REQUEST['plugin'] !== 'lender-rate-history/lender-rate-history.php') {
    return;
}

/**
 * Remove plugin data
 */
function lrh_uninstall() {
    global $wpdb;
    
    // Remove database table
    $table_name = $wpdb->prefix . 'lender_rate_history';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Remove options
    $options_to_delete = [
        'lrh_settings',
        'lrh_tracked_fields',
        'lrh_taxonomy_mapping',
        'lrh_stats',
        'lrh_db_version',
        'lrh_needs_initial_import'
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Remove scheduled cron events
    wp_clear_scheduled_hook('lrh_daily_cleanup');
    wp_clear_scheduled_hook('lrh_hourly_cache_clear');
    
    // Clear any cached data
    wp_cache_flush();
    
    // Remove any transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lrh_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_lrh_%'");
}

// Run uninstall
lrh_uninstall();