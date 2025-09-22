<?php
/**
 * External Sources Management - Med förbättrat formatstöd
 * FIXAD VERSION - Löser undefined array key warnings
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_External_Sources {
    
    private $database;
    
    public function __construct() {
        $this->database = new LRH_Database();
    }
    
    /**
     * Register external source med formatstöd
     */
    public function register_source($args) {
        $defaults = [
            'source_id' => '',
            'source_name' => '',
            'source_type' => 'option',
            'option_page' => 'options',
            'option_field' => '',
            'display_name' => '',
            'category' => 'external',
            'value_format' => 'percentage',  // percentage, currency, number
            'value_suffix' => '%',            // %, kr, st, etc
            'decimals' => 2,                  // antal decimaler
            'enabled' => true
        ];
        
        $source = wp_parse_args($args, $defaults);
        
        // Validera
        if (empty($source['source_id']) || empty($source['display_name'])) {
            return false;
        }
        
        // Sätt korrekt suffix baserat på format om inte angivet
        if ($source['value_format'] === 'currency' && $source['value_suffix'] === '%') {
            $source['value_suffix'] = 'kr';
        } elseif ($source['value_format'] === 'number' && $source['value_suffix'] === '%') {
            $source['value_suffix'] = '';
        }
        
        // Store in options
        $sources = get_option('lrh_external_sources', []);
        $sources[$source['source_id']] = $source;
        update_option('lrh_external_sources', $sources);
        
        return true;
    }
    
    /**
     * Get all external sources
     */
    public function get_sources() {
        return get_option('lrh_external_sources', []);
    }
    
    /**
     * Get source by ID
     */
    public function get_source($source_id) {
        $sources = $this->get_sources();
        return isset($sources[$source_id]) ? $sources[$source_id] : null;
    }
    
    /**
     * Delete source
     */
    public function delete_source($source_id) {
        $sources = $this->get_sources();
        if (isset($sources[$source_id])) {
            unset($sources[$source_id]);
            update_option('lrh_external_sources', $sources);
            return true;
        }
        return false;
    }
    
    /**
     * Track option field change
     */
    public function track_option_change($option_name, $option_page = 'options') {
        $sources = $this->get_sources();
        
        foreach ($sources as $source) {
            if (!$source['enabled']) continue;
            
            if ($source['source_type'] === 'option' && $source['option_field'] === $option_name) {
                $new_value = $this->get_option_value($option_name, $source['option_page']);
                
                if ($new_value !== null && $new_value !== false) {
                    $this->record_option_change_direct($source, $new_value);
                }
            }
        }
    }
    
    /**
     * Get option value
     */
    private function get_option_value($option_name, $context = 'options') {
        $value = null;
        
        if (function_exists('get_field')) {
            $value = get_field($option_name, $context);
            
            if ($value === null || $value === false) {
                $value = get_field($option_name, 'option');
            }
        }
        
        if ($value === null || $value === false) {
            $value = get_option('options_' . $option_name);
            
            if ($value === false) {
                $value = get_option($option_name);
            }
        }
        
        return $value;
    }
    
    /**
     * Get available option pages för dropdown
     */
    public function get_available_option_pages() {
        $pages = [];
        
        // Standard WordPress option
        $pages['option'] = 'Standard Options';
        
        // ACF Option Pages
        if (function_exists('acf_get_options_pages')) {
            $acf_pages = acf_get_options_pages();
            if ($acf_pages) {
                foreach ($acf_pages as $page) {
                    $pages[$page['menu_slug']] = $page['page_title'];
                }
            }
        }
        
        return $pages;
    }
    
    /**
     * Record option change directly
     */
    public function record_option_change_direct($source, $new_value) {
        // Normalisera värdet baserat på format
        $new_value = $this->normalize_value($new_value, $source['value_format'] ?? 'percentage');
        
        // Hämta gamla värdet från databasen
        $old_value = $this->database->get_latest_value_for_source(
            $source['source_type'],
            $source['source_id'],
            $source['option_field']
        );
        
        // Spara om värdet har ändrats eller om det är första gången
        if ($old_value === null || $this->has_value_changed($old_value, $new_value)) {
            // Beräkna förändring baserat på format
            $change_data = $this->calculate_change($old_value, $new_value, $source['value_format'] ?? 'percentage');
            
            $result = $this->database->insert_change([
                'post_id' => null,
                'source_type' => $source['source_type'],
                'source_id' => $source['source_id'],
                'source_name' => $source['display_name'],
                'field_name' => $source['option_field'],
                'field_category' => $source['category'] ?? 'external',
                'old_value' => $old_value,
                'new_value' => $new_value,
                'change_amount' => $change_data['change_amount'],
                'change_percentage' => $change_data['change_percentage'],
                'change_type' => $old_value === null ? 'initial' : 'update',
                'import_source' => 'option_update',
                'value_format' => $source['value_format'] ?? 'percentage',
                'value_suffix' => $source['value_suffix'] ?? '%',
                'decimals' => $source['decimals'] ?? 2
            ]);
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Calculate change metrics
     */
    private function calculate_change($old_value, $new_value, $format = 'percentage') {
        if ($old_value === null) {
            return [
                'change_amount' => null,
                'change_percentage' => null
            ];
        }
        
        $change_amount = $new_value - $old_value;
        
        // Beräkna procentuell förändring olika beroende på format
        if ($format === 'percentage') {
            // För procentsatser är change_amount i procentenheter
            // och change_percentage är den relativa förändringen
            $change_percentage = $old_value != 0 ? (($new_value - $old_value) / abs($old_value)) * 100 : 0;
        } else {
            // För currency och numbers
            $change_percentage = $old_value != 0 ? (($new_value - $old_value) / abs($old_value)) * 100 : 0;
        }
        
        return [
            'change_amount' => round($change_amount, 4),  // Håll 4 decimaler i databasen
            'change_percentage' => round($change_percentage, 2)  // 2 decimaler för procent
        ];
    }
    
    /**
     * Manually add history point med format
     * FIXAD: Korrekt hantering av $source array keys
     */
    public function add_manual_entry($data) {
        // Hämta källans format om den finns
        $source = null;
        if (!empty($data['source_id']) && $data['source_id'] !== 'manual_custom') {
            $source = $this->get_source($data['source_id']);
        }
        
        // Bestäm format-värden baserat på om källan finns eller inte
        if ($source && is_array($source)) {
            // Om källan finns och är en array, använd dess värden
            $value_format = $source['value_format'] ?? 'percentage';
            $value_suffix = $source['value_suffix'] ?? '%';
            $decimals = $source['decimals'] ?? 2;
        } else {
            // Om källan inte finns, använd data från input eller standardvärden
            $value_format = $data['value_format'] ?? 'percentage';
            $value_suffix = $data['value_suffix'] ?? '%';
            $decimals = $data['decimals'] ?? 2;
        }
        
        $defaults = [
            'source_type' => 'manual',
            'source_id' => '',
            'source_name' => '',
            'field_name' => 'value',
            'field_category' => 'external',
            'new_value' => 0,
            'old_value' => null,
            'change_date' => current_time('mysql'),
            'import_source' => 'manual_entry',
            'validation_notes' => '',
            'post_id' => null,
            'value_format' => $value_format,
            'value_suffix' => $value_suffix,
            'decimals' => $decimals
        ];
        
        $entry = wp_parse_args($data, $defaults);
        
        // Normalisera värden
        $entry['new_value'] = $this->normalize_value($entry['new_value'], $entry['value_format']);
        if ($entry['old_value'] !== null && $entry['old_value'] !== '') {
            $entry['old_value'] = $this->normalize_value($entry['old_value'], $entry['value_format']);
        }
        
        // Beräkna förändring
        $change_data = $this->calculate_change($entry['old_value'], $entry['new_value'], $entry['value_format']);
        $entry['change_amount'] = $change_data['change_amount'];
        $entry['change_percentage'] = $change_data['change_percentage'];
        
        return $this->database->insert_change($entry);
    }
    
    /**
     * Get source history
     */
    public function get_source_history($source_id, $limit = 100) {
        global $wpdb;
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE source_id = %s 
             ORDER BY change_date DESC 
             LIMIT %d",
            $source_id,
            $limit
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Normalize value baserat på format
     */
    private function normalize_value($value, $format = 'percentage') {
        if ($value === '' || $value === null || $value === false || $value === '-') {
            return null;
        }
        
        // Ta bort eventuella suffix (kr, %, etc)
        $value = preg_replace('/[^\d.,\-]/', '', $value);
        
        // Ersätt komma med punkt
        $value = str_replace(',', '.', $value);
        $value = str_replace(' ', '', $value);
        
        $normalized = floatval($value);
        
        // Avrunda baserat på format
        if ($format === 'currency') {
            return round($normalized, 0);  // Inga decimaler för kronor
        } elseif ($format === 'percentage') {
            return round($normalized, 2);  // 2 decimaler för procent
        } else {
            return round($normalized, 2);  // Standard 2 decimaler
        }
    }
    
    /**
     * Check if value has changed
     */
    private function has_value_changed($old_value, $new_value) {
        $epsilon = 0.0001;
        return abs(floatval($old_value) - floatval($new_value)) > $epsilon;
    }
    
    /**
     * Format value for display
     */
    public function format_value($value, $format = 'percentage', $suffix = '%', $decimals = 2) {
        if ($value === null || $value === '') {
            return '-';
        }
        
        if ($format === 'currency') {
            return number_format($value, 0, ',', ' ') . ' ' . $suffix;
        } elseif ($format === 'percentage') {
            return number_format($value, $decimals, ',', ' ') . $suffix;
        } else {
            $formatted = number_format($value, $decimals, ',', ' ');
            return $suffix ? $formatted . ' ' . $suffix : $formatted;
        }
    }
}