<?php
/**
 * History Dashboard - Better UX
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'lender_rate_history';

// Get all lenders with history
$lenders_sql = "SELECT DISTINCT p.post_id, 
                COUNT(DISTINCT p.field_name) as field_count,
                COUNT(*) as total_changes,
                MAX(p.change_date) as last_change
                FROM {$table_name} p
                GROUP BY p.post_id
                ORDER BY last_change DESC";

$lenders_with_history = $wpdb->get_results($lenders_sql);

// Handle single lender view
$selected_lender = isset($_GET['lender_id']) ? intval($_GET['lender_id']) : 0;

if ($selected_lender) {
    // Show detailed view for single lender
    include 'admin-history-single.php';
    return;
}
?>

<div class="wrap">
    <h1><?php _e('Räntehistorik - Översikt', 'lender-rate-history'); ?></h1>
    
    <div class="lrh-history-dashboard">
        <p><?php _e('Välj en långivare för att se detaljerad historik:', 'lender-rate-history'); ?></p>
        
        <div class="lrh-lender-grid">
            <?php foreach ($lenders_with_history as $lender_data): 
                $post = get_post($lender_data->post_id);
                if (!$post) continue;
                
                // Get latest changes for this lender
                $latest_changes = $wpdb->get_results($wpdb->prepare(
                    "SELECT field_name, old_value, new_value, change_date 
                     FROM {$table_name} 
                     WHERE post_id = %d 
                     ORDER BY change_date DESC 
                     LIMIT 3",
                    $lender_data->post_id
                ));
            ?>
            <div class="lrh-lender-card">
                <h3><?php echo esc_html($post->post_title); ?></h3>
                
                <div class="lrh-lender-stats">
                    <div class="stat">
                        <span class="label"><?php _e('Antal ändringar:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo $lender_data->total_changes; ?></span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Spårade fält:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo $lender_data->field_count; ?></span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Senast uppdaterad:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo date_i18n('j F, Y', strtotime($lender_data->last_change)); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($latest_changes)): ?>
                <div class="lrh-latest-changes-preview">
                    <h4><?php _e('Senaste ändringar:', 'lender-rate-history'); ?></h4>
                    <ul>
                        <?php foreach ($latest_changes as $change): 
                            $field_info = LRH_Helpers::parse_field_name($change->field_name);
                        ?>
                        <li>
                            <strong><?php echo esc_html($field_info['type_label'] . ' ' . $field_info['period_label']); ?>:</strong>
                            <?php if ($change->old_value !== null): ?>
                                <span class="old-value"><?php echo number_format($change->old_value, 2, ',', ' '); ?>%</span>
                                →
                                <span class="new-value"><?php echo number_format($change->new_value, 2, ',', ' '); ?>%</span>
                            <?php else: ?>
                                <span class="new-value"><?php echo number_format($change->new_value, 2, ',', ' '); ?>%</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="lrh-card-actions">
                    <a href="<?php echo admin_url('admin.php?page=lrh-history&lender_id=' . $lender_data->post_id); ?>" 
                       class="button button-primary">
                        <?php _e('Visa detaljerad historik', 'lender-rate-history'); ?>
                    </a>
                    <a href="<?php echo get_edit_post_link($lender_data->post_id); ?>" 
                       class="button">
                        <?php _e('Redigera långivare', 'lender-rate-history'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($lenders_with_history)): ?>
        <div class="notice notice-info">
            <p><?php _e('Ingen historik har registrerats ännu. Börja med att initiera historik från Import/Export-sidan.', 'lender-rate-history'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.lrh-lender-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.lrh-lender-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.lrh-lender-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.lrh-lender-card h3 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.lrh-lender-stats {
    margin: 15px 0;
}

.lrh-lender-stats .stat {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f1;
}

.lrh-lender-stats .label {
    color: #666;
}

.lrh-lender-stats .value {
    font-weight: 600;
    color: #23282d;
}

.lrh-latest-changes-preview {
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.lrh-latest-changes-preview h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    text-transform: uppercase;
    color: #666;
}

.lrh-latest-changes-preview ul {
    margin: 0;
    padding-left: 20px;
}

.lrh-latest-changes-preview li {
    margin: 5px 0;
    font-size: 13px;
}

.old-value {
    color: #999;
    text-decoration: line-through;
}

.new-value {
    color: #0073aa;
    font-weight: 600;
}

.lrh-card-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f1;
    display: flex;
    gap: 10px;
}

.lrh-card-actions .button {
    flex: 1;
    text-align: center;
}
</style>