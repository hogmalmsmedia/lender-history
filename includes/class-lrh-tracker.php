<?php
/**
 * Tracks ACF field changes - UPPDATERAD MED HJÄLPKLASS
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Tracker {

    private $database;
    private $tracked_fields;
    private $taxonomy_mapping;
    private $batch_queue = [];
    private $is_import = false;
    private $posts_to_track = [];
    private $processed_fields = []; // För att förhindra duplicerade inserts
    
    public function __construct() {
        $this->database = new LRH_Database();
        $this->tracked_fields = get_option('lrh_tracked_fields', []);
        $this->taxonomy_mapping = get_option('lrh_taxonomy_mapping', []);
        
        // Registrera option tracking direkt i konstruktorn
        $settings = get_option('lrh_settings', []);
        if (!empty($settings['enabled']) && !empty($settings['track_option_changes'])) {
            add_action('update_option', [$this, 'catch_option_update'], 1, 3);
            add_filter('acf/update_value', [$this, 'catch_acf_update'], 1, 4);
        }
    }
    
    /**
     * Initialize hooks för posts och imports
     */
    public function init() {
        $settings = get_option('lrh_settings', []);

        if (!empty($settings['enabled'])) {

            // Track manual changes
            if (!empty($settings['track_manual_changes'])) {
                add_action('acf/save_post', [$this, 'track_acf_changes'], 20);
                add_action('save_post', [$this, 'track_post_changes'], 30, 3);
            }

            // Track WP All Import changes
            if (!empty($settings['track_import_changes'])) {
                // Huvudhook för när en post sparas av WP All Import
                add_action('pmxi_saved_post', [$this, 'track_import_changes'], 10, 1);

                // Hooks för batch-hantering
                add_action('pmxi_before_xml_import', [$this, 'before_import'], 10, 1);
                add_action('pmxi_after_xml_import', [$this, 'after_import'], 10, 1);

                // VIKTIG: Lägg också till en hook som alltid lyssnar på ACF-uppdateringar
                // Detta säkerställer att vi fångar ändringar även vid cron
                add_action('acf/update_value', [$this, 'track_acf_value_update'], 99, 4);
            }
        }
    }
    
    /**
     * Fånga option updates
     */
    public function catch_option_update($option, $old_value, $value) {
        if (strpos($option, 'options_') === 0) {
            $field_name = str_replace('options_', '', $option);
            $this->track_option_value($field_name, $value);
        }
    }
    
    /**
     * Fånga ACF updates
     */
    public function catch_acf_update($value, $post_id, $field, $original) {
        if (strpos($post_id, 'option') !== false && isset($field['name'])) {
            $this->track_option_value($field['name'], $value);
        }
        return $value;
    }
    
    /**
     * Spara option-värde
     */
    private function track_option_value($field_name, $value) {
        if ($value === null || $value === false || $value === '') {
            return;
        }
        
        $external_sources = new LRH_External_Sources();
        $sources = $external_sources->get_sources();
        
        foreach ($sources as $source) {
            if ($source['source_type'] === 'option' && 
                $source['option_field'] === $field_name) {
                $external_sources->record_option_change_direct($source, $value);
                break;
            }
        }
    }
    
    /**
     * Track ACF field changes för posts - UPPDATERAD MED HJÄLPKLASS
     */
    public function track_acf_changes($post_id) {
        // Skip options (hanteras av catch_option_update)
        if ($post_id === 'options' || strpos($post_id, 'option') === 0) {
            return;
        }

        // Skip autosave och revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check if this is a lender post type
        if (!$this->is_lender_post($post_id)) {
            return;
        }

        // Get the loan type for this post
        $loan_type = $this->get_loan_type($post_id);
        if (!$loan_type) {
            return;
        }
        
        // Get fields to track for this loan type
        $fields_to_track = $this->get_tracked_fields($loan_type);
        if (empty($fields_to_track)) {
            return;
        }

        $changes = [];

        // Check each tracked field
        foreach ($fields_to_track as $field_name) {
            $this->check_field_change($post_id, $field_name, $loan_type, $changes);
        }
        
        // Save changes
        if (!empty($changes)) {
            // För cron-importer, spara direkt för att undvika problem med after_import
            // För manuella importer, använd batch för bättre prestanda
            if ($this->is_import && !(defined('DOING_CRON') && DOING_CRON)) {
                // Manuell import - använd batch för prestanda
                $this->batch_queue = array_merge($this->batch_queue, $changes);
                error_log('LRH: Added ' . count($changes) . ' changes to batch queue for post ' . $post_id);
            } else {
                // Cron-import eller manuell ändring - spara direkt
                $source = (defined('DOING_CRON') && DOING_CRON) ? 'cron_import' : 'direct';
                error_log('LRH: Saving ' . count($changes) . ' changes directly for post ' . $post_id . ' (source: ' . $source . ')');
                foreach ($changes as $change) {
                    $this->database->insert_change($change);
                }
            }
        }
    }
    
    /**
     * Track changes on regular post save
     */
    public function track_post_changes($post_id, $post, $update) {
        if (!$update) {
            return;
        }
        
        if (did_action('acf/save_post')) {
            return;
        }
        
        $this->track_acf_changes($post_id);
    }
    
    /**
     * Track ACF value updates direkt (för cron-importer)
     */
    public function track_acf_value_update($value, $post_id, $field, $original) {
        // Skip om det inte är en post
        if (!is_numeric($post_id)) {
            return $value;
        }

        // Kontrollera om vi är i en import-kontext
        // Vi kontrollerar flera sätt att detektera import
        $is_importing = (defined('WP_IMPORTING') && WP_IMPORTING) ||
                       (defined('PMXI_VERSION')) || // WP All Import är laddat
                       (defined('DOING_CRON') && DOING_CRON && class_exists('PMXI_Import_Record')) || // Cron med WP All Import
                       $this->is_import;

        // Ytterligare kontroll: är vi i en WP All Import process?
        if (!$is_importing && function_exists('wp_all_import_get_import_id')) {
            $import_id = wp_all_import_get_import_id();
            if ($import_id) {
                $is_importing = true;
            }
        }

        if (!$is_importing) {
            // Om inte import, returnera värdet utan att spåra
            return $value;
        }

        // Kontrollera om detta är en lender post
        if (!$this->is_lender_post($post_id)) {
            return $value;
        }

        // Kontrollera om detta är ett fält vi spårar
        $field_name = isset($field['name']) ? $field['name'] : '';
        if (empty($field_name)) {
            return $value;
        }

        $loan_type = $this->get_loan_type($post_id);
        $tracked_fields = $this->get_tracked_fields($loan_type);

        if (!in_array($field_name, $tracked_fields)) {
            return $value;
        }

        // Spara ändringen direkt här
        $this->track_single_field_change($post_id, $field_name, $value, $loan_type);

        return $value;
    }

    /**
     * Track a single field change immediately
     */
    private function track_single_field_change($post_id, $field_name, $new_value, $loan_type) {
        // Använd hjälpklass för att parsa värdet först
        $parsed_value = LRH_Value_Helper::parse_acf_value($new_value);

        if ($parsed_value === null) {
            return;
        }

        // Skapa en unik nyckel baserat på post, fält och det PARSADE värdet
        // Detta är viktigt eftersom samma värde kan komma i olika format
        $field_key = $post_id . '_' . $field_name . '_' . $parsed_value;

        // Kontrollera om vi redan har bearbetat detta fält med samma värde under denna import
        if (isset($this->processed_fields[$field_key])) {
            return;
        }

        // Markera som bearbetat
        $this->processed_fields[$field_key] = true;

        $old_value = $this->database->get_latest_value($post_id, $field_name);

        // Kontrollera om värdet har ändrats
        if ($old_value === null || LRH_Value_Helper::has_value_changed($old_value, $parsed_value)) {
            // Dubbelkolla att vi inte redan har denna exakta ändring i databasen
            // Detta är en extra säkerhet mot duplicering
            if ($this->is_duplicate_change($post_id, $field_name, $old_value, $parsed_value)) {
                return;
            }

            $change = [
                'post_id' => $post_id,
                'field_name' => $field_name,
                'field_category' => $loan_type,
                'old_value' => $old_value,
                'new_value' => $parsed_value,
                'change_type' => $old_value === null ? 'initial' : 'update',
                'import_source' => (defined('DOING_CRON') && DOING_CRON) ? 'wp_all_import' : 'wp_all_import'
            ];

            // Spara direkt till databasen
            $this->database->insert_change($change);
        }
    }

    /**
     * Check if this exact change already exists in recent records
     */
    private function is_duplicate_change($post_id, $field_name, $old_value, $new_value) {
        global $wpdb;
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;

        // Kontrollera om en identisk ändring redan finns från de senaste 5 minuterna
        $sql = $wpdb->prepare(
            "SELECT id FROM {$table_name}
             WHERE post_id = %d
             AND field_name = %s
             AND new_value = %f
             AND change_date > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             LIMIT 1",
            $post_id,
            $field_name,
            $new_value
        );

        $existing = $wpdb->get_var($sql);
        return !empty($existing);
    }

    /**
     * Track WP All Import changes
     */
    public function track_import_changes($post_id) {
        $this->is_import = true;
        // Kör INTE track_acf_changes här eftersom vi redan spårar via acf/update_value
        // Detta förhindrar dubbel bearbetning
        // $this->track_acf_changes($post_id);
    }
    
    /**
     * Before import starts
     */
    public function before_import($import_id) {
        $this->is_import = true;
        $this->batch_queue = [];
        $this->processed_fields = []; // Rensa processed fields vid ny import

        $import_type = (defined('DOING_CRON') && DOING_CRON) ? 'cron' : 'manual';
        error_log('LRH: Import started - ID: ' . $import_id . ', Type: ' . $import_type);
    }
    
    /**
     * After import completes
     */
    public function after_import($import_id) {
        // Spara eventuella kvarvarande ändringar i batch-kön
        // Detta hanterar främst manuella importer där vi använder batch för prestanda
        if (!empty($this->batch_queue)) {
            error_log('LRH: After import - saving ' . count($this->batch_queue) . ' batched changes');
            $result = $this->database->batch_insert($this->batch_queue);
            if ($result === false) {
                error_log('LRH: Failed to save batch changes after import');
            } else {
                error_log('LRH: Successfully saved batch changes after import');
            }
            $this->batch_queue = [];
        }

        $this->is_import = false;
        $this->processed_fields = []; // Rensa processed fields efter import

        $stats = get_option('lrh_stats', []);
        $stats['last_import'] = current_time('mysql');
        $stats['import_type'] = defined('DOING_CRON') && DOING_CRON ? 'cron' : 'manual';
        update_option('lrh_stats', $stats);

        error_log('LRH: Import completed - type: ' . $stats['import_type']);
    }
    
    /**
     * Check if a field has changed and track it - UPPDATERAD MED HJÄLPKLASS
     */
    private function check_field_change($post_id, $field_name, $loan_type, &$changes) {
        $raw_value = get_field($field_name, $post_id);

        // Använd hjälpklass för att parsa ACF-värde
        $new_value = LRH_Value_Helper::parse_acf_value($raw_value);

        if ($new_value === null) {
            return;
        }

        $old_value = $this->database->get_latest_value($post_id, $field_name);

        // Använd hjälpklass för att kontrollera förändring
        if ($old_value === null || LRH_Value_Helper::has_value_changed($old_value, $new_value)) {
            $changes[] = [
                'post_id' => $post_id,
                'field_name' => $field_name,
                'field_category' => $loan_type,
                'old_value' => $old_value,
                'new_value' => $new_value,
                'change_type' => $old_value === null ? 'initial' : 'update',
                'import_source' => $this->is_import ? 'wp_all_import' : 'manual'
            ];
        }
    }
    
    /**
     * Check if this is a lender post
     */
    private function is_lender_post($post_id) {
        $post_type = get_post_type($post_id);
        $lender_post_types = apply_filters('lrh_lender_post_types', ['langivare', 'lender']);
        return in_array($post_type, $lender_post_types);
    }
    
    /**
     * Get loan type for a post
     */
    private function get_loan_type($post_id) {
        foreach ($this->taxonomy_mapping as $term_slug => $loan_type) {
            if (has_term($term_slug, 'mall', $post_id)) {
                return $loan_type;
            }
        }
        return apply_filters('lrh_default_loan_type', 'mortgage', $post_id);
    }
    
    /**
     * Get tracked fields for a loan type
     */
    private function get_tracked_fields($loan_type) {
        if (!isset($this->tracked_fields[$loan_type])) {
            return [];
        }
        
        $fields = [];
        foreach ($this->tracked_fields[$loan_type] as $category => $field_list) {
            $fields = array_merge($fields, $field_list);
        }
        
        return apply_filters('lrh_tracked_fields', $fields, $loan_type);
    }
    
    /**
     * Manually track a specific field change - UPPDATERAD MED HJÄLPKLASS
     */
    public function track_field_manually($post_id, $field_name, $new_value, $source = 'manual') {
        if (!$this->is_lender_post($post_id)) {
            return false;
        }
        
        $loan_type = $this->get_loan_type($post_id);
        $old_value = $this->database->get_latest_value($post_id, $field_name);
        
        // Använd hjälpklass för normalisering
        $new_value = LRH_Value_Helper::normalize_value($new_value);
        
        // Använd hjälpklass för att kontrollera förändring
        if (LRH_Value_Helper::has_value_changed($old_value, $new_value)) {
            return $this->database->insert_change([
                'post_id' => $post_id,
                'field_name' => $field_name,
                'field_category' => $loan_type,
                'old_value' => $old_value,
                'new_value' => $new_value,
                'change_type' => $old_value === null ? 'initial' : 'update',
                'import_source' => $source
            ]);
        }
        
        return false;
    }
    
    /**
     * Initialize post history - UPPDATERAD MED HJÄLPKLASS
     */
    public function initialize_post_history($post_id) {
        if (!$this->is_lender_post($post_id)) {
            return false;
        }
        
        $loan_type = $this->get_loan_type($post_id);
        $fields = $this->get_tracked_fields($loan_type);
        
        $changes = [];
        
        foreach ($fields as $field_name) {
            $value = get_field($field_name, $post_id);
            
            // Använd hjälpklass för att parsa ACF-värde
            $normalized_value = LRH_Value_Helper::parse_acf_value($value);
            
            if ($normalized_value !== null) {
                $changes[] = [
                    'post_id' => $post_id,
                    'field_name' => $field_name,
                    'field_category' => $loan_type,
                    'old_value' => null,
                    'new_value' => $normalized_value,
                    'change_type' => 'initial',
                    'import_source' => 'initialization'
                ];
            }
        }
        
        if (!empty($changes)) {
            return $this->database->batch_insert($changes);
        }
        
        return false;
    }
}