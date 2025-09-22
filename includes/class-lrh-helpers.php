<?php
/**
 * Helper functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Helpers {
    
    /**
     * Format rate value for display
     */
    public static function format_rate($value, $decimals = 2) {
        if ($value === null || $value === '') {
            return '-';
        }
        
        return number_format(floatval($value), $decimals, ',', ' ') . '%';
    }
    
    /**
     * Format change percentage
     */
	public static function format_change($old_value, $new_value) {
		// Handle non-numeric or empty values
		if (!is_numeric($old_value) || !is_numeric($new_value)) {
			return ['percentage' => 0, 'arrow' => '→', 'class' => 'lrh-no-change', 'formatted' => ''];
		}

		// ÄNDRING HÄR: Beräkna förändring i procentenheter (inte procentuell förändring)
		$change = $new_value - $old_value;

		// Skip if no change
		if (abs($change) < 0.001) {
			return ['percentage' => 0, 'arrow' => '→', 'class' => 'lrh-no-change', 'formatted' => ''];
		}

		return [
			'percentage' => abs($change),
			'arrow' => $change > 0 ? '↑' : '↓',
			'class' => $change > 0 ? 'lrh-increase' : 'lrh-decrease',
			'formatted' => ($change > 0 ? '+' : '') . number_format($change, 2, ',', ' ') . ' %'
		];
	}
    
    /**
     * Get period label
     */
    public static function get_period_label($period) {
        $labels = [
            '3_man' => __('3 månader', 'lender-rate-history'),
            '6_man' => __('6 månader', 'lender-rate-history'),
            '1_ar' => __('1 år', 'lender-rate-history'),
            '2_ar' => __('2 år', 'lender-rate-history'),
            '3_ar' => __('3 år', 'lender-rate-history'),
            '4_ar' => __('4 år', 'lender-rate-history'),
            '5_ar' => __('5 år', 'lender-rate-history'),
            '7_ar' => __('7 år', 'lender-rate-history'),
            '10_ar' => __('10 år', 'lender-rate-history'),
        ];
        
        return isset($labels[$period]) ? $labels[$period] : $period;
    }
    
    /**
     * Get field type label
     */
    public static function get_field_type_label($field_name) {
        if (strpos($field_name, 'snitt_') === 0) {
            return __('Snittränta', 'lender-rate-history');
        } elseif (strpos($field_name, 'list_') === 0) {
            return __('Listränta', 'lender-rate-history');
        } elseif (strpos($field_name, 'rabatt_') === 0) {
            return __('Rabatt', 'lender-rate-history');
        } else {
            return $field_name;
        }
    }
    
    /**
     * Parse field name to components
     */
    public static function parse_field_name($field_name) {
        $parts = explode('_', $field_name);
        
        if (count($parts) >= 2) {
            $type = $parts[0]; // snitt, list, etc.
            array_shift($parts);
            $period = implode('_', $parts); // 3_man, 1_ar, etc.
            
            return [
                'type' => $type,
                'period' => $period,
                'type_label' => self::get_field_type_label($field_name),
                'period_label' => self::get_period_label($period)
            ];
        }
        
        return [
            'type' => $field_name,
            'period' => '',
            'type_label' => $field_name,
            'period_label' => ''
        ];
    }
    
    /**
     * Get lender by ID with caching
     */
    public static function get_lender($post_id) {
        $cache_key = 'lrh_lender_' . $post_id;
        $lender = wp_cache_get($cache_key);
        
        if ($lender === false) {
            $lender = get_post($post_id);
            if ($lender) {
                wp_cache_set($cache_key, $lender, '', 3600);
            }
        }
        
        return $lender;
    }
    
    /**
     * Check if post is a lender
     */
    public static function is_lender($post_id) {
        $post_type = get_post_type($post_id);
        $lender_post_types = apply_filters('lrh_lender_post_types', ['langivare', 'lender']);
        return in_array($post_type, $lender_post_types);
    }
    
    /**
     * Get loan type for lender
     */
    public static function get_loan_type($post_id) {
        $taxonomy_mapping = get_option('lrh_taxonomy_mapping', [
            'bolan' => 'mortgage',
            'privatlan' => 'personal_loan',
            'billan' => 'car_loan'
        ]);
        
        foreach ($taxonomy_mapping as $term_slug => $loan_type) {
            if (has_term($term_slug, 'mall', $post_id)) {
                return $loan_type;
            }
        }
        
        return 'mortgage'; // Default
    }
    
    /**
     * Generate CSV from array
     */
    public static function array_to_csv($data, $headers = null) {
        if (empty($data)) {
            return '';
        }
        
        ob_start();
        $output = fopen('php://output', 'w');
        
        // Add headers if provided
        if ($headers) {
            fputcsv($output, $headers);
        } elseif (is_array($data[0])) {
            // Use array keys as headers
            fputcsv($output, array_keys($data[0]));
        }
        
        // Add data
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            fputcsv($output, $row);
        }
        
        fclose($output);
        return ob_get_clean();
    }
    
    /**
     * Sanitize field name
     */
    public static function sanitize_field_name($field_name) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $field_name);
    }
    
    /**
     * Get color for change percentage
     */
    public static function get_change_color($percentage) {
        if ($percentage > 0) {
            return '#d63638'; // Red for increase
        } elseif ($percentage < 0) {
            return '#00a32a'; // Green for decrease
        } else {
            return '#757575'; // Gray for no change
        }
    }
    
    /**
     * Format date for display
     */
    public static function format_date($date, $format = null) {
        if (!$format) {
            $format = get_option('date_format');
        }
        
        return date_i18n($format, strtotime($date));
    }
    
    /**
     * Get time ago string
     */
    public static function time_ago($date) {
        return human_time_diff(strtotime($date), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history');
    }
    
    /**
     * Debug logger
     */
    public static function log($message, $level = 'info') {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            $prefix = '[LRH ' . strtoupper($level) . '] ';
            if (is_array($message) || is_object($message)) {
                error_log($prefix . print_r($message, true));
            } else {
                error_log($prefix . $message);
            }
        }
    }
}