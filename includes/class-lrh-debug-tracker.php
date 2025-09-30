<?php
/**
 * Debug Tracker - För att felsöka option tracking
 */

if (!defined('ABSPATH')) exit;

class LRH_Debug_Tracker {

    private static $log_file = null;

    public function __construct() {
        // Sätt loggfil i plugin-mappen
        self::$log_file = LRH_PLUGIN_DIR . 'lrh-debug.log';

        // Registrera alla möjliga hooks för ACF och WordPress options
        $this->register_all_hooks();

        // Lägg till admin notice om debug mode är på
        add_action('admin_notices', [$this, 'show_debug_notice']);
    }

    /**
     * Registrera alla hooks för debugging
     */
    private function register_all_hooks() {
        // WordPress option hooks
        add_action('added_option', [$this, 'debug_added_option'], 1, 2);
        add_action('updated_option', [$this, 'debug_updated_option'], 1, 3);
        add_action('update_option', [$this, 'debug_update_option'], 1, 3);

        // ACF hooks
        add_filter('acf/update_value', [$this, 'debug_acf_update_value'], 1, 4);
        add_action('acf/save_post', [$this, 'debug_acf_save_post'], 1);

        // Pre option hooks
        add_filter('pre_update_option', [$this, 'debug_pre_update_option'], 1, 3);

        self::log("=== LRH DEBUG TRACKER STARTED ===");
    }

    /**
     * Debug added_option
     */
    public function debug_added_option($option, $value) {
        self::log("ADDED_OPTION: {$option}", [
            'value' => $value,
            'is_options' => strpos($option, 'options_') === 0
        ]);
    }

    /**
     * Debug updated_option
     */
    public function debug_updated_option($option, $old_value, $value) {
        self::log("UPDATED_OPTION: {$option}", [
            'old_value' => $old_value,
            'new_value' => $value,
            'is_options' => strpos($option, 'options_') === 0,
            'changed' => $old_value !== $value
        ]);

        // Om det är en ACF option field
        if (strpos($option, 'options_') === 0) {
            $field_name = str_replace('options_', '', $option);
            self::log("DETECTED ACF OPTION FIELD: {$field_name}");

            // Kolla om vi har en external source för detta fält
            $this->check_external_source($field_name, $value);
        }
    }

    /**
     * Debug update_option (före uppdatering)
     */
    public function debug_update_option($option, $old_value, $value) {
        self::log("UPDATE_OPTION (pre): {$option}", [
            'old_value' => $old_value,
            'new_value' => $value,
            'is_options' => strpos($option, 'options_') === 0
        ]);
    }

    /**
     * Debug ACF update_value
     */
    public function debug_acf_update_value($value, $post_id, $field, $original) {
        self::log("ACF_UPDATE_VALUE", [
            'field_name' => $field['name'] ?? 'unknown',
            'field_key' => $field['key'] ?? 'unknown',
            'post_id' => $post_id,
            'value' => $value,
            'original' => $original,
            'is_option' => strpos($post_id, 'option') !== false
        ]);

        // Om det är en option page
        if (strpos($post_id, 'option') !== false) {
            $field_name = $field['name'] ?? '';
            if ($field_name) {
                self::log("DETECTED ACF OPTION UPDATE: {$field_name}");
                $this->check_external_source($field_name, $value);
            }
        }

        return $value;
    }

    /**
     * Debug ACF save_post
     */
    public function debug_acf_save_post($post_id) {
        self::log("ACF_SAVE_POST", [
            'post_id' => $post_id,
            'is_option' => strpos($post_id, 'option') !== false,
            'POST_data' => $_POST['acf'] ?? 'no ACF data'
        ]);
    }

    /**
     * Debug pre_update_option
     */
    public function debug_pre_update_option($value, $option, $old_value) {
        if (strpos($option, 'options_') === 0 || strpos($option, 'acf') === 0) {
            self::log("PRE_UPDATE_OPTION: {$option}", [
                'old_value' => $old_value,
                'new_value' => $value,
                'will_update' => $old_value !== $value
            ]);
        }
        return $value;
    }

    /**
     * Kolla om vi har en external source för fältet
     */
    private function check_external_source($field_name, $value) {
        if (!class_exists('LRH_External_Sources')) {
            self::log("ERROR: LRH_External_Sources class not found!");
            return;
        }

        $external_sources = new LRH_External_Sources();
        $sources = $external_sources->get_sources();

        self::log("Checking external sources", [
            'field_name' => $field_name,
            'total_sources' => count($sources)
        ]);

        $found = false;
        foreach ($sources as $source) {
            if ($source['source_type'] === 'option' && $source['option_field'] === $field_name) {
                $found = true;
                self::log("FOUND MATCHING SOURCE!", [
                    'source_id' => $source['source_id'],
                    'source_name' => $source['display_name'],
                    'field_name' => $field_name,
                    'new_value' => $value
                ]);

                // Försök spara direkt
                $result = $external_sources->record_option_change_direct($source, $value);
                self::log("SAVE RESULT", [
                    'success' => $result !== false,
                    'result' => $result
                ]);

                // Kolla databasen
                $this->check_database($source['source_id'], $field_name);
                break;
            }
        }

        if (!$found) {
            self::log("No external source found for field: {$field_name}");
        }
    }

    /**
     * Kolla om värdet sparades i databasen
     */
    private function check_database($source_id, $field_name) {
        global $wpdb;
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;

        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE source_id = %s AND field_name = %s
             ORDER BY change_date DESC, id DESC
             LIMIT 1",
            $source_id,
            $field_name
        ));

        self::log("DATABASE CHECK", [
            'source_id' => $source_id,
            'field_name' => $field_name,
            'latest_record' => $latest,
            'wpdb_last_error' => $wpdb->last_error
        ]);
    }

    /**
     * Visa debug notice i admin
     */
    public function show_debug_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>LRH Debug Mode Active!</strong> Logging to: ' . self::$log_file . '</p>';
            echo '<p>Disable by removing debug tracker from plugin.</p>';
            echo '</div>';
        }
    }

    /**
     * Loggfunktion
     */
    public static function log($message, $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}";

        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }

        $log_entry .= "\n" . str_repeat('-', 80) . "\n";

        error_log($log_entry, 3, self::$log_file);
    }
}

// Starta debug tracker direkt
new LRH_Debug_Tracker();