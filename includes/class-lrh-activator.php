<?php
/**
 * Handles plugin activation and deactivation
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_database_table();
        self::create_default_options();
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('lrh_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'lrh_daily_cleanup');
        }
        
        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('lrh_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database table - UPPDATERAD MED NULL-STÖD
     */
    private static function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        // VIKTIGT: post_id är DEFAULT NULL från början
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED DEFAULT NULL,
            source_type varchar(50) DEFAULT 'post',
            source_id varchar(100) DEFAULT NULL,
            source_name varchar(255) DEFAULT NULL,
            field_name varchar(100) NOT NULL,
            field_category varchar(50) DEFAULT 'mortgage',
            old_value decimal(10,4) DEFAULT NULL,
            new_value decimal(10,4) NOT NULL,
            change_amount decimal(10,4) DEFAULT NULL,
            change_percentage decimal(10,4) DEFAULT NULL,
            change_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            change_type varchar(50) DEFAULT 'update',
            import_source varchar(50) DEFAULT 'manual',
            is_validated tinyint(1) DEFAULT 1,
            validation_notes text DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_post_field (post_id, field_name, change_date DESC),
            INDEX idx_source (source_type, source_id, field_name, change_date DESC),
            INDEX idx_category_date (field_category, change_date DESC),
            INDEX idx_post_date (post_id, change_date DESC),
            INDEX idx_field_date (field_name, change_date DESC),
            INDEX idx_validation (is_validated, change_date DESC)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store current version for future upgrades
        update_option('lrh_db_version', LRH_VERSION);
    }
    
    /**
     * Create default plugin options
     */
    private static function create_default_options() {
        // General settings
        add_option('lrh_settings', [
            'enabled' => true,
            'track_manual_changes' => true,
            'track_import_changes' => true,
            'track_option_changes' => true, // NY
            // retention_days borttagen - obegränsad lagring
            'large_change_threshold' => 25,
            'enable_validation' => true,
            'enable_notifications' => false,
            'notification_email' => get_option('admin_email'),
        ]);
        
        // Field configuration for different loan types
        add_option('lrh_tracked_fields', [
            'mortgage' => [
                'rates' => [
                    'snitt_3_man', 'snitt_1_ar', 'snitt_2_ar', 'snitt_3_ar', 
                    'snitt_5_ar', 'snitt_7_ar', 'snitt_10_ar',
                    'list_3_man', 'list_1_ar', 'list_2_ar', 'list_3_ar',
                    'list_5_ar', 'list_7_ar', 'list_10_ar'
                ],
                'fees' => [
                    'upplaggningsavgift', 'aviavgift'
                ],
                'requirements' => [
                    'ltv_max', 'min_loan', 'max_loan'
                ]
            ],
            'personal_loan' => [
                'rates' => [
                    'nominell_ranta', 'effektiv_ranta', 'min_ranta', 'max_ranta'
                ],
                'fees' => [
                    'upplaggningsavgift', 'aviavgift', 'manadsavgift'
                ],
                'limits' => [
                    'min_belopp', 'max_belopp', 'min_loptid', 'max_loptid'
                ]
            ],
            'car_loan' => [
                'rates' => [
                    'ranta_ny_bil', 'ranta_begagnad_bil', 'kampanjranta'
                ],
                'fees' => [
                    'upplaggningsavgift', 'aviavgift'
                ],
                'requirements' => [
                    'kontantinsats_procent', 'restvarde_procent'
                ]
            ]
        ]);
        
        // Taxonomy mapping
        add_option('lrh_taxonomy_mapping', [
            'bolan' => 'mortgage',
            'privatlan' => 'personal_loan',
            'billan' => 'car_loan'
        ]);
        
        // External sources (tom från början)
        add_option('lrh_external_sources', []);
        
        // Initialize stats
        add_option('lrh_stats', [
            'total_changes' => 0,
            'last_import' => null,
            'last_cleanup' => null
        ]);
    }
}