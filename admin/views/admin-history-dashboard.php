<?php
/**
 * History Dashboard - Better UX
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'lender_rate_history';
$database = new LRH_Database();

// Check for filter parameter
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';

// Handle unvalidated filter
if ($filter === 'unvalidated') {
    // Get unvalidated changes
    $unvalidated_changes = $database->get_unvalidated_changes(100);
    ?>
    <div class="wrap">
        <h1>
            <?php _e('Ovaliderade ändringar', 'lender-rate-history'); ?>
            <a href="<?php echo admin_url('admin.php?page=lrh-history'); ?>" class="page-title-action">
                <?php _e('← Tillbaka till översikt', 'lender-rate-history'); ?>
            </a>
        </h1>

        <?php if (!empty($unvalidated_changes)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                    <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                    <th><?php _e('Tidigare värde', 'lender-rate-history'); ?></th>
                    <th><?php _e('Nytt värde', 'lender-rate-history'); ?></th>
                    <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                    <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                    <th><?php _e('Åtgärder', 'lender-rate-history'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unvalidated_changes as $change):
                    $post = get_post($change->post_id);
                ?>
                <tr>
                    <td>
                        <?php if ($post): ?>
                        <a href="<?php echo admin_url('admin.php?page=lrh-history&lender_id=' . $change->post_id); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                        <?php else: ?>
                        <?php _e('Okänd', 'lender-rate-history'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($change->field_name); ?></td>
                    <td><?php echo $change->old_value !== null ? number_format($change->old_value, 2, ',', ' ') . '%' : '-'; ?></td>
                    <td><?php echo number_format($change->new_value, 2, ',', ' ') . '%'; ?></td>
                    <td>
                        <?php
                        if ($change->change_percentage !== null) {
                            $arrow = $change->change_percentage > 0 ? '↑' : ($change->change_percentage < 0 ? '↓' : '→');
                            $class = $change->change_percentage > 0 ? 'increase' : ($change->change_percentage < 0 ? 'decrease' : 'no-change');
                            echo '<span class="change-indicator ' . $class . '">' . $arrow . ' ' .
                                 number_format(abs($change->change_percentage), 2) . '%</span>';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo date_i18n('j F, Y', strtotime($change->change_date)); ?></td>
                    <td>
                        <button class="button button-small validate-change" data-id="<?php echo $change->id; ?>">
                            <?php _e('Validera', 'lender-rate-history'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('.validate-change').on('click', function() {
                var changeId = $(this).data('id');
                var $button = $(this);

                $.post(ajaxurl, {
                    action: 'lrh_validate_change',
                    change_id: changeId,
                    nonce: '<?php echo wp_create_nonce('lrh_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut();
                    }
                });
            });
        });
        </script>

        <?php else: ?>
        <p><?php _e('Inga ovaliderade ändringar att granska.', 'lender-rate-history'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return;
}

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