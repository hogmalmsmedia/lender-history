<?php
/**
 * Single External Source Detail View
 * Shows detailed history for a specific external source
 */

if (!defined('ABSPATH')) {
    exit;
}

$source = $external_sources->get_source($selected_source);

if (!$source) {
    echo '<div class="notice notice-error"><p>' . __('Källa hittades inte.', 'lender-rate-history') . '</p></div>';
    return;
}

global $wpdb;
$table_name = $wpdb->prefix . LRH_TABLE_NAME;

// Get all history for this source
$history = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} 
     WHERE source_id = %s 
     ORDER BY change_date DESC",
    $selected_source
));

// Handle edit/update if submitted
if (isset($_POST['update_entry']) && wp_verify_nonce($_POST['_wpnonce'], 'update_entry_' . $_POST['record_id'])) {
    $record_id = intval($_POST['record_id']);
    $new_value = str_replace(',', '.', $_POST['new_value']);
    $new_value = floatval($new_value);
    
    $old_value = isset($_POST['old_value']) && $_POST['old_value'] !== '' 
        ? floatval(str_replace(',', '.', $_POST['old_value'])) 
        : null;
    
    $change_date = sanitize_text_field($_POST['change_date']) . ' 00:00:00';
    $import_source = sanitize_text_field($_POST['import_source']);
    
    // Räkna om förändringen
    $change_amount = null;
    $change_percentage = null;
    $change_type = 'update';
    
    if ($old_value !== null) {
        $change_amount = $new_value - $old_value;
        $change_percentage = $change_amount; // Procentenheter
        $change_type = 'update';
    } else {
        $change_type = 'initial';
    }
    
    // Check if needs validation
    $is_validated = 1;
    $settings = get_option('lrh_settings', []);
    $threshold = isset($settings['large_change_threshold']) ? $settings['large_change_threshold'] : 25;
    
    if ($old_value !== null && abs($change_amount) > ($old_value * $threshold / 100)) {
        $is_validated = 0;
    }
    
    $wpdb->update(
        $table_name,
        [
            'new_value' => $new_value,
            'old_value' => $old_value,
            'change_amount' => $change_amount,
            'change_percentage' => $change_percentage,
            'change_type' => $change_type,
            'change_date' => $change_date,
            'import_source' => $import_source,
            'is_validated' => $is_validated
        ],
        ['id' => $record_id]
    );
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Värde uppdaterat!', 'lender-rate-history') . '</p></div>';
    
    // Refresh history
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE source_id = %s 
         ORDER BY change_date DESC",
        $selected_source
    ));
}

// Delete record if requested
if (isset($_GET['delete_record']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_record_' . $_GET['delete_record'])) {
    $wpdb->delete($table_name, ['id' => intval($_GET['delete_record'])], ['%d']);
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Post borttagen!', 'lender-rate-history') . '</p></div>';
    
    // Refresh history
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE source_id = %s 
         ORDER BY change_date DESC",
        $selected_source
    ));
}

// Get format settings  
$format = $source['value_format'] ?? 'percentage';
$suffix = $source['value_suffix'] ?? '%';
$decimals = $source['decimals'] ?? 2;

// Override suffix based on format if needed
if ($format === 'number') {
    $suffix = $source['value_suffix'] ?? '';
} elseif ($format === 'currency') {
    $suffix = $source['value_suffix'] ?? 'kr';
}

// Get current value
$current_value = $database->get_latest_value_for_source(
    $source['source_type'],
    $source['source_id'],
    $source['option_field'] ?? 'value'
);

// Count unvalidated
$unvalidated_count = 0;
foreach ($history as $record) {
    if (!$record->is_validated) {
        $unvalidated_count++;
    }
}

// Import source options
$import_source_options = [
    'manual_entry' => 'Manuell',
    'option_update' => 'Option Update',
    'monthly_update' => 'Månadsuppdatering',
    'update' => 'Uppdatering'
];
?>

<div class="wrap">
    <h1>
        <?php printf(__('Externa Källor: %s', 'lender-rate-history'), esc_html($source['display_name'])); ?>
        <a href="<?php echo admin_url('admin.php?page=lrh-external-sources'); ?>" class="page-title-action">
            <?php _e('← Tillbaka till översikt', 'lender-rate-history'); ?>
        </a>
    </h1>
    
    <!-- Source Information Box -->
    <div class="lrh-source-info-box">
        <div class="lrh-info-grid">
            <div class="info-item">
                <span class="label"><?php _e('Käll-ID:', 'lender-rate-history'); ?></span>
                <span class="value"><code><?php echo esc_html($source['source_id']); ?></code></span>
            </div>
            <div class="info-item">
                <span class="label"><?php _e('Typ:', 'lender-rate-history'); ?></span>
                <span class="value"><?php echo esc_html($source['source_type']); ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?php _e('Format:', 'lender-rate-history'); ?></span>
                <span class="value"><?php echo esc_html(ucfirst($format) . ' (' . $suffix . ')'); ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?php _e('Nuvarande värde:', 'lender-rate-history'); ?></span>
                <span class="value">
                    <?php if ($current_value !== null): ?>
                        <strong style="color: #0073aa; font-size: 18px;">
                            <?php echo number_format($current_value, $decimals, ',', ' ') . $suffix; ?>
                        </strong>
                    <?php else: ?>
                        <span style="color: #d63638;">Ingen data</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($source['source_type'] === 'option'): ?>
            <div class="info-item">
                <span class="label"><?php _e('Option Field:', 'lender-rate-history'); ?></span>
                <span class="value"><code><?php echo esc_html($source['option_field'] ?? '-'); ?></code></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="label"><?php _e('Status:', 'lender-rate-history'); ?></span>
                <span class="value">
                    <?php if ($unvalidated_count > 0): ?>
                        <span style="color: #d63638;">
                            <span class="dashicons dashicons-warning"></span>
                            <?php printf(__('%d poster behöver validering', 'lender-rate-history'), $unvalidated_count); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #00a32a;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Alla poster validerade', 'lender-rate-history'); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Manual Entry Form -->
    <div class="lrh-admin-box">
        <h2><?php _e('Lägg till manuell historikpunkt', 'lender-rate-history'); ?></h2>
        
        <form method="post" action="?page=lrh-external-sources" class="lrh-inline-form">
            <?php wp_nonce_field('lrh_manual_entry'); ?>
            <input type="hidden" name="source_id" value="<?php echo esc_attr($selected_source); ?>">
            <input type="hidden" name="field_name" value="<?php echo esc_attr($source['option_field'] ?? 'value'); ?>">
            
            <table class="form-table-inline">
                <tr>
                    <td>
                        <input type="text" name="old_value" class="small-text" 
                               placeholder="Tidigare värde">
                    </td>
                    <td>→</td>
                    <td>
                        <input type="text" name="new_value" class="small-text" required
                               placeholder="Nytt värde">
                    </td>
                    <td>
                        <input type="date" name="change_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </td>
                    <td>
                        <button type="submit" name="add_manual_entry" class="button button-primary">
                            <?php _e('Lägg till', 'lender-rate-history'); ?>
                        </button>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <!-- History Table -->
    <div class="lrh-admin-box">
        <h2>
            <?php _e('Historik', 'lender-rate-history'); ?>
            <?php if (!empty($history)): ?>
            <span class="count">(<?php echo count($history); ?> <?php _e('poster', 'lender-rate-history'); ?>)</span>
            <?php endif; ?>
        </h2>
        
        <?php if (!empty($history)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="120"><?php _e('Datum', 'lender-rate-history'); ?></th>
                    <th width="100"><?php _e('Tidigare', 'lender-rate-history'); ?></th>
                    <th width="100"><?php _e('Nytt värde', 'lender-rate-history'); ?></th>
                    <th width="120"><?php _e('Förändring', 'lender-rate-history'); ?></th>
                    <?php if ($format === 'percentage'): ?>
                    <th width="100"><?php _e('Procentuell', 'lender-rate-history'); ?></th>
                    <?php endif; ?>
                    <th width="100"><?php _e('Källa', 'lender-rate-history'); ?></th>
                    <th width="60"><?php _e('Status', 'lender-rate-history'); ?></th>
                    <th width="150"><?php _e('Åtgärder', 'lender-rate-history'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $record): 
                    $editing = isset($_GET['edit']) && $_GET['edit'] == $record->id;
                ?>
                <tr id="row-<?php echo $record->id; ?>" <?php echo !$record->is_validated ? 'class="needs-validation"' : ''; ?>>
                    <td><?php echo date_i18n('j F, Y', strtotime($record->change_date)); ?></td>
                    <td>
                        <?php if ($editing): ?>
                            <input type="text" form="edit-form-<?php echo $record->id; ?>"
                                   name="old_value" 
                                   value="<?php echo $record->old_value !== null ? number_format($record->old_value, $decimals, ',', ' ') : ''; ?>" 
                                   size="8" 
                                   placeholder="-">
                        <?php else: ?>
                            <?php if ($record->old_value !== null): ?>
                                <span class="old-value"><?php echo number_format($record->old_value, $decimals, ',', ' ') . $suffix; ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($editing): ?>
                            <form method="post" style="display: inline;" id="edit-form-<?php echo $record->id; ?>">
                                <?php wp_nonce_field('update_entry_' . $record->id); ?>
                                <input type="hidden" name="record_id" value="<?php echo $record->id; ?>">
                                <input type="text" name="new_value" 
                                       value="<?php echo number_format($record->new_value, $decimals, ',', ' '); ?>" 
                                       size="8" required>
                        <?php else: ?>
                            <span class="new-value"><?php echo number_format($record->new_value, $decimals, ',', ' ') . $suffix; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($record->change_amount !== null):
                            $class = $record->change_amount > 0 ? 'lrh-increase' : 
                                    ($record->change_amount < 0 ? 'lrh-decrease' : 'lrh-no-change');
                            $arrow = $record->change_amount > 0 ? '↑' : 
                                    ($record->change_amount < 0 ? '↓' : '→');
                        ?>
                            <span class="<?php echo $class; ?>">
                                <?php 
                                echo $arrow . ' ';
                                echo ($record->change_amount > 0 ? '+' : '') . 
                                     number_format($record->change_amount, $decimals, ',', ' ');
                                if ($format === 'percentage') {
                                    echo ' p.e.';
                                } else {
                                    echo $suffix;
                                }
                                ?>
                            </span>
                        <?php else: ?>
                            <span class="lrh-initial">INITIAL</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($format === 'percentage'): ?>
                    <td>
                        <?php 
                        if ($record->change_percentage !== null && $record->old_value !== null && $record->old_value != 0) {
                            $percentage = (($record->new_value - $record->old_value) / abs($record->old_value)) * 100;
                            $class = $percentage > 0 ? 'lrh-increase' : 
                                    ($percentage < 0 ? 'lrh-decrease' : 'lrh-no-change');
                            echo '<span class="' . $class . '">';
                            echo ($percentage > 0 ? '+' : '') . 
                                 number_format($percentage, 1, ',', ' ') . '%';
                            echo '</span>';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($editing): ?>
                            <select name="import_source">
                                <?php foreach ($import_source_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($record->import_source, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php echo isset($import_source_options[$record->import_source]) ? 
                                      $import_source_options[$record->import_source] : 
                                      $record->import_source; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($record->is_validated): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;" title="Validerad"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: #d63638;" title="Behöver validering"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($editing): ?>
                            <input type="date" name="change_date" value="<?php echo date('Y-m-d', strtotime($record->change_date)); ?>" required>
                            <button type="submit" name="update_entry" class="button button-small button-primary"><?php _e('Spara', 'lender-rate-history'); ?></button>
                            <a href="<?php echo remove_query_arg('edit'); ?>" class="button button-small"><?php _e('Avbryt', 'lender-rate-history'); ?></a>
                            </form>
                        <?php else: ?>
                            <?php if (!$record->is_validated): ?>
                            <a href="?page=lrh-external-sources&action=validate_entry&entry_id=<?php echo $record->id; ?>&source_id=<?php echo $selected_source; ?>&view=details&_wpnonce=<?php echo wp_create_nonce('validate_entry'); ?>" 
                               class="button button-small button-primary">
                                <?php _e('Validera', 'lender-rate-history'); ?>
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo add_query_arg('edit', $record->id); ?>" 
                               class="button button-small">
                                <?php _e('Redigera', 'lender-rate-history'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(add_query_arg('delete_record', $record->id), 'delete_record_' . $record->id); ?>" 
                               class="button button-small"
                               onclick="return confirm('<?php _e('Är du säker?', 'lender-rate-history'); ?>');">
                                <?php _e('Ta bort', 'lender-rate-history'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Ingen historik registrerad än.', 'lender-rate-history'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Chart Section -->
    <?php if (!empty($history) && count($history) > 1): ?>
    <div class="lrh-admin-box">
        <h2><?php _e('Visualisering', 'lender-rate-history'); ?></h2>
        <canvas id="lrh-source-chart" width="400" height="150"></canvas>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Prepare chart data
        var chartData = {
            labels: [
                <?php 
                $reversed = array_reverse($history);
                foreach ($reversed as $record): 
                ?>
                '<?php echo date_i18n('j M', strtotime($record->change_date)); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: '<?php echo esc_js($source['display_name']); ?>',
                data: [
                    <?php foreach ($reversed as $record): ?>
                    <?php echo $record->new_value; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                tension: 0.4
            }]
        };
        
        // Create chart
        var ctx = document.getElementById('lrh-source-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toFixed(<?php echo $decimals; ?>) + '<?php echo $suffix; ?>';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(<?php echo $decimals; ?>) + '<?php echo $suffix; ?>';
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
</div>

<style>
.lrh-source-info-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.lrh-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-item .label {
    font-weight: 600;
    color: #666;
}

.info-item .value {
    color: #23282d;
}

.info-item code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}

.lrh-inline-form {
    margin-top: 10px;
}

.form-table-inline {
    display: inline-table;
}

.form-table-inline tr {
    display: table-row;
}

.form-table-inline td {
    padding: 5px;
    vertical-align: middle;
}

.lrh-admin-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.lrh-admin-box h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.lrh-admin-box h2 .count {
    font-size: 14px;
    font-weight: normal;
    color: #666;
}

.needs-validation {
    background-color: #fff3cd !important;
}

.old-value {
    color: #999;
    text-decoration: line-through;
}

.new-value {
    color: #0073aa;
    font-weight: 600;
}

.lrh-increase {
    color: #d63638;
    font-weight: 600;
}

.lrh-decrease {
    color: #00a32a;
    font-weight: 600;
}

.lrh-no-change {
    color: #666;
}

.lrh-initial {
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
}

.small-text {
    width: 80px;
}
</style>