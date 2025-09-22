<?php
/**
 * API for retrieving rate history data
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_API {
    
    private $database;
    private $cache_group = 'lrh_cache';
    private $cache_expiry = 3600; // 1 hour
    
    public function __construct() {
        $this->database = new LRH_Database();
    }
    
    /**
     * Get field change information
     */
    public function get_field_change($post_id, $field_name, $format = 'percentage') {
        // Try cache first
        $cache_key = "change_{$post_id}_{$field_name}_{$format}";
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get latest change
        $change = $this->database->get_latest_change($post_id, $field_name);
        
        if (!$change || $change->old_value === null) {
            return $this->format_no_change($format);
        }
        
        $result = '';
        
        switch ($format) {
            case 'percentage':
                if ($change->change_percentage !== null) {
                    $result = $this->format_percentage($change->change_percentage);
                }
                break;
                
            case 'amount':
                if ($change->change_amount !== null) {
                    $result = $this->format_amount($change->change_amount);
                }
                break;
                
            case 'arrow':
                $result = $this->get_change_arrow($change->change_amount);
                break;
                
            case 'class':
                $result = $this->get_change_class($change->change_amount);
                break;
                
            case 'full':
                $result = $this->format_full_change($change);
                break;
                
            case 'raw':
                $result = $change;
                break;
                
            default:
                $result = apply_filters('lrh_custom_format', '', $format, $change);
                break;
        }
        
        // Cache the result
        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_expiry);
        
        return $result;
    }
    
    /**
     * Get historical data for a field
     */
    public function get_field_history($post_id, $field_name, $days = 30) {
        $cache_key = "history_{$post_id}_{$field_name}_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $history = $this->database->get_field_history($post_id, $field_name, $days);
        
        // Format for charts
        $formatted = [
            'labels' => [],
            'values' => [],
            'changes' => []
        ];
        
        foreach ($history as $record) {
            $formatted['labels'][] = date('Y-m-d', strtotime($record->change_date));
            $formatted['values'][] = floatval($record->new_value);
            $formatted['changes'][] = floatval($record->change_percentage);
        }
        
        wp_cache_set($cache_key, $formatted, $this->cache_group, $this->cache_expiry);
        
        return $formatted;
    }
    
    /**
     * Get recent changes table data
     */
    public function get_recent_changes_table($args = []) {
        $defaults = [
            'limit' => 10,
            'field_category' => 'mortgage',
            'format' => 'array',
            'group_by_bank' => false // New option to control grouping
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Get recent changes
        $changes = $this->database->get_recent_changes([
            'limit' => $args['limit'],
            'field_category' => $args['field_category'],
            'group_by_post' => false // Always get all changes, not grouped
        ]);
        
        if ($args['format'] === 'html') {
            return $this->format_changes_table_html($changes);
        }
        
        // Format for display
        $formatted = [];
        
        foreach ($changes as $change) {
            $post = get_post($change->post_id);
            
            // Parse field name for better display
            $field_info = LRH_Helpers::parse_field_name($change->field_name);
            
            // Calculate change in percentage points
            $change_amount = $change->old_value !== null ? ($change->new_value - $change->old_value) : 0;
            
            $formatted[] = [
                'bank' => $post ? $post->post_title : 'Unknown',
                'bank_url' => $post ? get_permalink($post->ID) : '#',
                'field' => $field_info['type_label'] . ' - ' . $field_info['period_label'],
                'current_rate' => number_format($change->new_value, 2, ',', ' ') . '%',
                'previous_rate' => $change->old_value !== null ? number_format($change->old_value, 2, ',', ' ') . '%' : '-',
                'change' => $this->format_percentage_points($change_amount),
                'change_class' => $this->get_change_class($change_amount),
                'date' => date_i18n('j F, Y', strtotime($change->change_date)),
                'time_ago' => human_time_diff(strtotime($change->change_date), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history')
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Format percentage points (not percentage change)
     */
	private function format_percentage_points($value) {
		if ($value === null || $value == 0) {
			return '0 %';
		}

		$formatted = number_format(abs($value), 2, ',', ' ');

		if ($value > 0) {
			return '+' . $formatted . ' %';
		} else {
			return '-' . $formatted . ' %';
		}
	}
    
    /**
     * Get comparison data for multiple lenders
     */
    public function get_comparison_data($post_ids, $field_name, $days = 30) {
        $data = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            
            $history = $this->get_field_history($post_id, $field_name, $days);
            
            $data[] = [
                'label' => $post->post_title,
                'data' => $history['values'],
                'post_id' => $post_id
            ];
        }
        
        return $data;
    }
    
    /**
     * Get sparkline data
     */
    public function get_sparkline_data($post_id, $field_name, $days = 7) {
        $history = $this->database->get_field_history($post_id, $field_name, $days);
        
        $values = [];
        foreach ($history as $record) {
            $values[] = floatval($record->new_value);
        }
        
        // Reverse to show oldest first
        return array_reverse($values);
    }
    
    /**
     * Get statistics for a lender
     */
    public function get_lender_stats($post_id) {
        $cache_key = "stats_{$post_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $stats = [];
        
        // Get all changes for this lender
        $history = $this->database->get_post_history($post_id, 1000);
        
        // Calculate stats
        $stats['total_changes'] = count($history);
        $stats['last_update'] = !empty($history) ? $history[0]->change_date : null;
        
        // Average change percentage
        $total_percentage = 0;
        $count = 0;
        
        foreach ($history as $record) {
            if ($record->change_percentage !== null) {
                $total_percentage += abs($record->change_percentage);
                $count++;
            }
        }
        
        $stats['avg_change'] = $count > 0 ? $total_percentage / $count : 0;
        
        // Most changed field
        $field_counts = [];
        foreach ($history as $record) {
            $field_counts[$record->field_name] = isset($field_counts[$record->field_name]) 
                ? $field_counts[$record->field_name] + 1 
                : 1;
        }
        
        if (!empty($field_counts)) {
            arsort($field_counts);
            $stats['most_changed_field'] = key($field_counts);
            $stats['most_changed_count'] = current($field_counts);
        }
        
        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_expiry);
        
        return $stats;
    }
    
    /**
     * Format percentage
     */
	private function format_percentage($value) {
		if ($value === null || $value == 0) {
			return '0 %';
		}

		// ÄNDRING HÄR: Detta är redan förändring i procentenheter
		$formatted = number_format(abs($value), 2, ',', ' ');

		if ($value > 0) {
			return '+' . $formatted . ' %';
		} else {
			return '-' . $formatted . ' %';
		}
	}
    
    /**
     * Format amount
     */
    private function format_amount($value) {
        if ($value === null || $value == 0) {
            return '0.00';
        }
        
        $formatted = number_format(abs($value), 2);
        
        if ($value > 0) {
            return '+' . $formatted;
        } else {
            return '-' . $formatted;
        }
    }
    
    /**
     * Get change arrow
     */
    private function get_change_arrow($value) {
        if ($value === null || $value == 0) {
            return '→';
        }
        
        return $value > 0 ? '↑' : '↓';
    }
    
    /**
     * Get change CSS class
     */
    private function get_change_class($value) {
        if ($value === null || $value == 0) {
            return 'lrh-no-change';
        }
        
        return $value > 0 ? 'lrh-increase' : 'lrh-decrease';
    }
    
    /**
     * Format full change information
     */
    private function format_full_change($change) {
        return [
            'percentage' => $this->format_percentage($change->change_amount),  // Använd change_amount istället
            'amount' => $this->format_amount($change->change_amount),
            'arrow' => $this->get_change_arrow($change->change_amount),
            'class' => $this->get_change_class($change->change_amount),
            'old_value' => number_format($change->old_value, 2) . '%',
            'new_value' => number_format($change->new_value, 2) . '%',
            'date' => date('Y-m-d', strtotime($change->change_date)),
            'time_ago' => human_time_diff(strtotime($change->change_date), current_time('timestamp')) . ' ' . __('ago', 'lender-rate-history')
        ];
    }
    
    /**
     * Format no change response
     */
    private function format_no_change($format) {
        switch ($format) {
            case 'percentage':
            case 'amount':
                return '0';
            case 'arrow':
                return '→';
            case 'class':
                return 'lrh-no-change';
            case 'full':
                return [
                    'percentage' => '0%',
                    'amount' => '0',
                    'arrow' => '→',
                    'class' => 'lrh-no-change'
                ];
            default:
                return null;
        }
    }
    
    /**
     * Get field label
     */
    private function get_field_label($field_name) {
        $labels = apply_filters('lrh_field_labels', [
            'snitt_3_man' => __('3 mån snitt', 'lender-rate-history'),
            'snitt_1_ar' => __('1 år snitt', 'lender-rate-history'),
            'snitt_2_ar' => __('2 år snitt', 'lender-rate-history'),
            'snitt_3_ar' => __('3 år snitt', 'lender-rate-history'),
            'snitt_5_ar' => __('5 år snitt', 'lender-rate-history'),
            'snitt_7_ar' => __('7 år snitt', 'lender-rate-history'),
            'snitt_10_ar' => __('10 år snitt', 'lender-rate-history'),
            'list_3_man' => __('3 mån list', 'lender-rate-history'),
            'list_1_ar' => __('1 år list', 'lender-rate-history'),
            'list_2_ar' => __('2 år list', 'lender-rate-history'),
            'list_3_ar' => __('3 år list', 'lender-rate-history'),
            'list_5_ar' => __('5 år list', 'lender-rate-history'),
            'list_7_ar' => __('7 år list', 'lender-rate-history'),
            'list_10_ar' => __('10 år list', 'lender-rate-history'),
        ]);
        
        return isset($labels[$field_name]) ? $labels[$field_name] : $field_name;
    }
    
    /**
     * Format changes table as HTML
     */
    private function format_changes_table_html($changes) {
        ob_start();
        ?>
        <table class="lrh-changes-table">
            <thead>
                <tr>
                    <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                    <th><?php _e('Räntetyp', 'lender-rate-history'); ?></th>
                    <th><?php _e('Nuvarande', 'lender-rate-history'); ?></th>
                    <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                    <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($changes as $change): 
                    $post = get_post($change->post_id);
                    if (!$post) continue;
                ?>
                <tr>
                    <td>
                        <a href="<?php echo get_permalink($post->ID); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($this->get_field_label($change->field_name)); ?></td>
                    <td><?php echo number_format($change->new_value, 2); ?>%</td>
                    <td class="<?php echo $this->get_change_class($change->change_amount); ?>">
                        <span class="lrh-arrow"><?php echo $this->get_change_arrow($change->change_amount); ?></span>
                        <?php echo $this->format_percentage($change->change_percentage); ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($change->change_date)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Clear cache for a post
     */
    public function clear_post_cache($post_id) {
        // Clear all cached data for this post
        $patterns = [
            "change_{$post_id}_*",
            "history_{$post_id}_*",
            "stats_{$post_id}"
        ];
        
        foreach ($patterns as $pattern) {
            wp_cache_delete($pattern, $this->cache_group);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        wp_cache_flush();
    }
}