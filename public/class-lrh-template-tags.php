<?php
/**
 * Template tags for use in themes
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Template_Tags {
    
    /**
     * Display rate change badge
     */
    public static function rate_badge($post_id = null, $field_name = 'snitt_3_man', $args = []) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $defaults = [
            'show_arrow' => true,
            'show_percentage' => true,
            'show_tooltip' => true,
            'wrapper' => 'span',
            'class' => 'lrh-rate-badge'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $api = new LRH_API();
        $change = $api->get_field_change($post_id, $field_name, 'full');
        
        if (!$change) {
            return '';
        }
        
        $html = sprintf('<%s class="%s %s"', 
            esc_attr($args['wrapper']), 
            esc_attr($args['class']),
            esc_attr($change['class'])
        );
        
        if ($args['show_tooltip']) {
            $html .= sprintf(' title="%s: %s → %s"',
                __('Förändring', 'lender-rate-history'),
                $change['old_value'],
                $change['new_value']
            );
        }
        
        $html .= '>';
        
        if ($args['show_arrow']) {
            $html .= sprintf('<span class="lrh-arrow">%s</span> ', $change['arrow']);
        }
        
        if ($args['show_percentage']) {
            $html .= sprintf('<span class="lrh-value">%s</span>', $change['percentage']);
        }
        
        $html .= sprintf('</%s>', esc_attr($args['wrapper']));
        
        echo $html;
    }
    
    /**
     * Display rate comparison table
     */
    public static function comparison_table($post_ids = [], $fields = [], $args = []) {
        if (empty($post_ids) || empty($fields)) {
            return;
        }
        
        $defaults = [
            'show_change' => true,
            'show_sparkline' => false,
            'table_class' => 'lrh-comparison-table',
            'responsive' => true
        ];
        
        $args = wp_parse_args($args, $defaults);
        $api = new LRH_API();
        
        ?>
        <table class="<?php echo esc_attr($args['table_class']); ?><?php echo $args['responsive'] ? ' lrh-responsive' : ''; ?>">
            <thead>
                <tr>
                    <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                    <?php foreach ($fields as $field): 
                        $field_info = LRH_Helpers::parse_field_name($field);
                    ?>
                    <th><?php echo esc_html($field_info['period_label']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($post_ids as $post_id): 
                    $post = get_post($post_id);
                    if (!$post) continue;
                ?>
                <tr>
                    <td>
                        <a href="<?php echo get_permalink($post_id); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                    </td>
                    <?php foreach ($fields as $field): 
                        $current_value = get_field($field, $post_id);
                        $change = $args['show_change'] ? $api->get_field_change($post_id, $field, 'full') : null;
                    ?>
                    <td>
                        <div class="lrh-rate-cell">
                            <span class="lrh-current-rate">
                                <?php echo $current_value ? number_format($current_value, 2) . '%' : '-'; ?>
                            </span>
                            <?php if ($change && $args['show_change']): ?>
                            <span class="lrh-rate-change <?php echo esc_attr($change['class']); ?>">
                                <?php echo $change['arrow'] . ' ' . $change['percentage']; ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($args['show_sparkline']): ?>
                            <span class="lrh-sparkline" 
                                  data-values="<?php echo implode(',', $api->get_sparkline_data($post_id, $field)); ?>">
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Display rate history timeline
     */
    public static function history_timeline($post_id = null, $field_name = 'snitt_3_man', $days = 30) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $database = new LRH_Database();
        $history = $database->get_field_history($post_id, $field_name, $days);
        
        if (empty($history)) {
            echo '<p>' . __('Ingen historik tillgänglig.', 'lender-rate-history') . '</p>';
            return;
        }
        
        ?>
        <div class="lrh-timeline">
            <?php foreach ($history as $record): ?>
            <div class="lrh-timeline-item">
                <div class="lrh-timeline-date">
                    <?php echo LRH_Helpers::format_date($record->change_date); ?>
                </div>
                <div class="lrh-timeline-content">
                    <span class="lrh-timeline-value">
                        <?php echo number_format($record->new_value, 2); ?>%
                    </span>
                    <?php if ($record->old_value !== null): 
                        $change_info = LRH_Helpers::format_change($record->old_value, $record->new_value);
                    ?>
                    <span class="lrh-timeline-change <?php echo esc_attr($change_info['class']); ?>">
                        <?php echo $change_info['arrow'] . ' ' . $change_info['formatted']; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Display lender statistics
     */
    public static function lender_stats($post_id = null, $args = []) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $defaults = [
            'show_total_changes' => true,
            'show_last_update' => true,
            'show_avg_change' => true,
            'show_most_changed' => true,
            'wrapper_class' => 'lrh-lender-stats'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $api = new LRH_API();
        $stats = $api->get_lender_stats($post_id);
        
        if (empty($stats)) {
            return;
        }
        
        ?>
        <div class="<?php echo esc_attr($args['wrapper_class']); ?>">
            <?php if ($args['show_total_changes'] && isset($stats['total_changes'])): ?>
            <div class="lrh-stat">
                <span class="lrh-stat-label"><?php _e('Totalt antal ändringar:', 'lender-rate-history'); ?></span>
                <span class="lrh-stat-value"><?php echo number_format($stats['total_changes']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($args['show_last_update'] && isset($stats['last_update'])): ?>
            <div class="lrh-stat">
                <span class="lrh-stat-label"><?php _e('Senast uppdaterad:', 'lender-rate-history'); ?></span>
                <span class="lrh-stat-value"><?php echo LRH_Helpers::time_ago($stats['last_update']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($args['show_avg_change'] && isset($stats['avg_change'])): ?>
            <div class="lrh-stat">
                <span class="lrh-stat-label"><?php _e('Genomsnittlig förändring:', 'lender-rate-history'); ?></span>
                <span class="lrh-stat-value"><?php echo number_format($stats['avg_change'], 2); ?>%</span>
            </div>
            <?php endif; ?>
            
            <?php if ($args['show_most_changed'] && isset($stats['most_changed_field'])): ?>
            <div class="lrh-stat">
                <span class="lrh-stat-label"><?php _e('Mest ändrade fält:', 'lender-rate-history'); ?></span>
                <span class="lrh-stat-value">
                    <?php echo esc_html($stats['most_changed_field']); ?> 
                    (<?php echo $stats['most_changed_count']; ?> <?php _e('ändringar', 'lender-rate-history'); ?>)
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Check if field has changed recently
     */
    public static function has_recent_change($post_id = null, $field_name = 'snitt_3_man', $days = 7) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $database = new LRH_Database();
        $history = $database->get_field_history($post_id, $field_name, 1, 0);
        
        if (empty($history)) {
            return false;
        }
        
        $last_change = $history[0];
        $days_ago = (time() - strtotime($last_change->change_date)) / (60 * 60 * 24);
        
        return $days_ago <= $days;
    }
    
    /**
     * Get rate trend indicator
     */
    public static function rate_trend($post_id = null, $field_name = 'snitt_3_man', $days = 30) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $database = new LRH_Database();
        $history = $database->get_field_history($post_id, $field_name, $days);
        
        if (count($history) < 2) {
            return 'stable';
        }
        
        // Calculate trend
        $first = end($history);
        $last = reset($history);
        
        $change = $last->new_value - $first->new_value;
        
        if ($change > 0.1) {
            return 'increasing';
        } elseif ($change < -0.1) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
}