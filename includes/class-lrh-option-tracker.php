<?php
/**
 * Option Tracker - Permanent version av fungerande debug-kod
 */

if (!defined('ABSPATH')) exit;

class LRH_Option_Tracker {
    
    public function __construct() {
        // Registrera hooks direkt i konstruktorn - EXAKT som debug
        add_action('update_option', [$this, 'handle_option_update'], 1, 3);
        add_filter('acf/update_value', [$this, 'handle_acf_update'], 1, 4);
    }
    
    /**
     * Handle option updates - KOPIERAT från debug
     */
    public function handle_option_update($option, $old_value, $value) {
        if (strpos($option, 'options_') === 0) {
            $field_name = str_replace('options_', '', $option);
            $this->track_option($field_name, $value);
        }
    }
    
    /**
     * Handle ACF value updates - KOPIERAT från debug
     */
    public function handle_acf_update($value, $post_id, $field, $original) {
        if (strpos($post_id, 'option') !== false) {
            $this->track_option($field['name'], $value);
        }
        return $value;
    }
    
    /**
     * Track option - KOPIERAT från debug
     */
    private function track_option($field_name, $value) {
        $external_sources = new LRH_External_Sources();
        $sources = $external_sources->get_sources();
        
        foreach ($sources as $source) {
            if ($source['source_type'] === 'option' && $source['option_field'] === $field_name) {
                $external_sources->record_option_change_direct($source, $value);
                break;
            }
        }
    }
}

// VIKTIGT: Starta direkt - EXAKT som debug
new LRH_Option_Tracker();