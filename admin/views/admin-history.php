<?php
/**
 * History page view - Now uses dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check which view file exists and use it
$dashboard_file = plugin_dir_path(__FILE__) . 'admin-history-dashboard.php';
$fallback_file = plugin_dir_path(__FILE__) . 'admin-history-fallback.php';

if (file_exists($dashboard_file)) {
    include $dashboard_file;
} else {
    // Fallback to simple view if dashboard doesn't exist
    ?>
    <div class="wrap">
        <h1><?php _e('Räntehistorik', 'lender-rate-history'); ?></h1>
        <p><?php _e('Historikvy laddas...', 'lender-rate-history'); ?></p>
        
        <?php
        // Simple table view as fallback
        global $wpdb;
        $table_name = $wpdb->prefix . 'lender_rate_history';
        $recent_changes = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY change_date DESC LIMIT 50");
        
        if (!empty($recent_changes)) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                        <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th><?php _e('Tidigare', 'lender-rate-history'); ?></th>
                        <th><?php _e('Nytt', 'lender-rate-history'); ?></th>
                        <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_changes as $change): 
                        $post = get_post($change->post_id);
                    ?>
                    <tr>
                        <td><?php echo $post ? esc_html($post->post_title) : 'Unknown'; ?></td>
                        <td><?php echo esc_html($change->field_name); ?></td>
                        <td><?php echo $change->old_value !== null ? number_format($change->old_value, 2, ',', ' ') . '%' : '-'; ?></td>
                        <td><?php echo number_format($change->new_value, 2, ',', ' ') . '%'; ?></td>
                        <td><?php echo date_i18n('j F, Y', strtotime($change->change_date)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </div>
    <?php
}
?>