<?php
/**
 * Core plugin class that coordinates all components
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Core {
    
    private static $instance = null;
    private $loader;
    private $tracker;
    private $api;
    private $admin;
    private $public;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
		
		if (is_admin()) {
		require_once LRH_PLUGIN_DIR . 'admin/class-lrh-admin-ajax.php';
		new LRH_Admin_Ajax();
	}
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-loader.php';
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-database.php';
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-tracker.php';
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-api.php';
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-helpers.php';
        require_once LRH_PLUGIN_DIR . 'includes/class-lrh-import.php';
		require_once LRH_PLUGIN_DIR . 'includes/class-lrh-value-helper.php';
        
        // Admin classes
        if (is_admin()) {
            require_once LRH_PLUGIN_DIR . 'admin/class-lrh-admin.php';
            require_once LRH_PLUGIN_DIR . 'admin/class-lrh-admin-menu.php';
            require_once LRH_PLUGIN_DIR . 'admin/class-lrh-admin-settings.php';
            require_once LRH_PLUGIN_DIR . 'admin/class-lrh-admin-history-table.php';
			require_once LRH_PLUGIN_DIR . 'admin/class-lrh-external-sources.php';
        }
        
        // Public classes
        require_once LRH_PLUGIN_DIR . 'public/class-lrh-public.php';
        require_once LRH_PLUGIN_DIR . 'public/class-lrh-shortcodes.php';
        require_once LRH_PLUGIN_DIR . 'public/class-lrh-template-tags.php';
        
        // API classes
        require_once LRH_PLUGIN_DIR . 'api/class-lrh-rest-api.php';
        
        // Initialize loader
        $this->loader = new LRH_Loader();
        
        // Initialize components
        $this->tracker = new LRH_Tracker();
        $this->api = new LRH_API();
        
        if (is_admin()) {
            $this->admin = new LRH_Admin();
        }
        
        $this->public = new LRH_Public();
    }
    
    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        // Admin menu and pages
        $this->loader->add_action('admin_menu', $this->admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $this->admin, 'init_settings');
        
        // Admin assets
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
        
        // Admin notices
        $this->loader->add_action('admin_notices', $this->admin, 'display_admin_notices');
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_lrh_validate_change', $this->admin, 'ajax_validate_change');
        $this->loader->add_action('wp_ajax_lrh_delete_change', $this->admin, 'ajax_delete_change');
        $this->loader->add_action('wp_ajax_lrh_get_chart_data', $this->admin, 'ajax_get_chart_data');
        $this->loader->add_action('wp_ajax_lrh_export_data', $this->admin, 'ajax_export_data');
        $this->loader->add_action('wp_ajax_lrh_import_data', $this->admin, 'ajax_import_data');
        $this->loader->add_action('wp_ajax_lrh_initialize_history', $this->admin, 'ajax_initialize_history');
        $this->loader->add_action('wp_ajax_lrh_clear_cache', $this->admin, 'ajax_clear_cache');
        $this->loader->add_action('wp_ajax_lrh_validate_all_changes', $this->admin, 'ajax_validate_all_changes');
    }
    
    /**
     * Define public hooks
     */
    private function define_public_hooks() {
        // Tracker hooks
        $this->loader->add_action('init', $this->tracker, 'init');
        
        // Public assets
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');
        
        // Shortcodes
        $shortcodes = new LRH_Shortcodes();
        $this->loader->add_action('init', $shortcodes, 'register_shortcodes');
        
        // REST API
        $rest_api = new LRH_REST_API();
        $this->loader->add_action('rest_api_init', $rest_api, 'register_routes');
        
        // Template tags are loaded globally via functions in main file
    }
    
    /**
     * Define cron hooks
     */
    private function define_cron_hooks() {
        // Daily cleanup
        $this->loader->add_action('lrh_daily_cleanup', $this, 'run_daily_cleanup');
        
        // Hourly cache clear
        if (!wp_next_scheduled('lrh_hourly_cache_clear')) {
            wp_schedule_event(time(), 'hourly', 'lrh_hourly_cache_clear');
        }
        $this->loader->add_action('lrh_hourly_cache_clear', $this->api, 'clear_all_cache');
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * Get API instance
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * Get tracker instance
     */
    public function get_tracker() {
        return $this->tracker;
    }
    
    /**
     * Run daily cleanup tasks
     * DISABLED - data lagras obegränsat
     */
    public function run_daily_cleanup() {
        // Funktionen är inaktiverad - data lagras obegränsat
        // Clear cache kan fortfarande köras
        $this->api->clear_all_cache();
    }
    
    /**
     * Send cleanup notification
     */
    private function send_cleanup_notification($deleted_count) {
        $settings = get_option('lrh_settings', []);
        $email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        
        $subject = __('Lender Rate History - Cleanup Report', 'lender-rate-history');
        $message = sprintf(
            __('The daily cleanup has been completed. %d old records were removed from the database.', 'lender-rate-history'),
            $deleted_count
        );
        
        wp_mail($email, $subject, $message);
    }
}