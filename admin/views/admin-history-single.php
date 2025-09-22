<?php
/**
 * Single Lender History View
 */

if (!defined('ABSPATH')) {
    exit;
}

$lender_id = intval($_GET['lender_id']);
$post = get_post($lender_id);

if (!$post) {
    echo '<div class="notice notice-error"><p>' . __('Långivare hittades inte.', 'lender-rate-history') . '</p></div>';
    return;
}

global $wpdb;
$table_name = $wpdb->prefix . 'lender_rate_history';

// Get all fields for this lender and sort them
$fields = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT field_name 
     FROM {$table_name} 
     WHERE post_id = %d",
    $lender_id
));

// Custom sort function to prioritize 3_man
function sort_fields_by_period($a, $b) {
    // Extract period from field name
    $a_parts = explode('_', $a->field_name);
    $b_parts = explode('_', $b->field_name);
    
    // Get type (snitt/list)
    $a_type = $a_parts[0] ?? '';
    $b_type = $b_parts[0] ?? '';
    
    // Type priority: snitt before list
    if ($a_type !== $b_type) {
        if ($a_type === 'snitt') return -1;
        if ($b_type === 'snitt') return 1;
    }
    
    // Get period
    $a_period = implode('_', array_slice($a_parts, 1));
    $b_period = implode('_', array_slice($b_parts, 1));
    
    // Period order
    $period_order = [
        '3_man' => 1,
        '6_man' => 2,
        '1_ar' => 3,
        '2_ar' => 4,
        '3_ar' => 5,
        '4_ar' => 6,
        '5_ar' => 7,
        '7_ar' => 8,
        '10_ar' => 9
    ];
    
    $a_order = $period_order[$a_period] ?? 99;
    $b_order = $period_order[$b_period] ?? 99;
    
    return $a_order - $b_order;
}

usort($fields, 'sort_fields_by_period');

// Handle edit/update if submitted
if (isset($_POST['update_rate']) && wp_verify_nonce($_POST['_wpnonce'], 'update_rate_' . $_POST['record_id'])) {
    $record_id = intval($_POST['record_id']);
    $new_value = str_replace(',', '.', $_POST['new_value']);
    $new_value = floatval($new_value);
    
    $old_value = isset($_POST['old_value']) && $_POST['old_value'] !== '' 
        ? floatval(str_replace(',', '.', $_POST['old_value'])) 
        : null;
    
    $change_date = sanitize_text_field($_POST['change_date']);
    $import_source = sanitize_text_field($_POST['import_source']);
    
    // VIKTIGT: Räkna om förändringen
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
    
    $wpdb->update(
        $table_name,
        [
            'new_value' => $new_value,
            'old_value' => $old_value,
            'change_amount' => $change_amount,
            'change_percentage' => $change_percentage,
            'change_type' => $change_type,
            'change_date' => $change_date,
            'import_source' => $import_source
        ],
        ['id' => $record_id],
        ['%f', '%f', '%f', '%f', '%s', '%s', '%s'],
        ['%d']
    );
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Värde uppdaterat!', 'lender-rate-history') . '</p></div>';
    
    // Add JavaScript to scroll back to edited row
    echo '<script>
        jQuery(document).ready(function($) {
            var rowId = "row-' . $record_id . '";
            if ($("#" + rowId).length) {
                // Calculate center position
                var windowHeight = $(window).height();
                var rowTop = $("#" + rowId).offset().top;
                var scrollTo = rowTop - (windowHeight / 3); // Position row in upper third of screen
                
                $("html, body").animate({
                    scrollTop: scrollTo
                }, 500);
                
                $("#" + rowId).addClass("highlight-row");
                setTimeout(function() {
                    $("#" + rowId).removeClass("highlight-row");
                }, 2000);
            }
        });
    </script>';
}

// Delete record if requested
if (isset($_GET['delete_record']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_record_' . $_GET['delete_record'])) {
    $wpdb->delete($table_name, ['id' => intval($_GET['delete_record'])], ['%d']);
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Post borttagen!', 'lender-rate-history') . '</p></div>';
}

// Group history by field
$history_by_field = [];
foreach ($fields as $field) {
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE post_id = %d AND field_name = %s 
         ORDER BY change_date DESC",
        $lender_id,
        $field->field_name
    ));
    
    if (!empty($history)) {
        $history_by_field[$field->field_name] = $history;
    }
}
?>

<div class="wrap">
    <h1>
        <?php printf(__('Räntehistorik: %s', 'lender-rate-history'), esc_html($post->post_title)); ?>
        <a href="<?php echo admin_url('admin.php?page=lrh-history'); ?>" class="page-title-action">
            <?php _e('← Tillbaka till översikt', 'lender-rate-history'); ?>
        </a>
    </h1>
    
    <div class="lrh-single-lender-history">
        <?php foreach ($history_by_field as $field_name => $history): 
            $field_info = LRH_Helpers::parse_field_name($field_name);
            $current_value = get_field($field_name, $lender_id);
        ?>
        
        <div class="lrh-field-section">
            <h2>
                <?php echo esc_html($field_info['type_label'] . ' - ' . $field_info['period_label']); ?>
                <span class="current-value">
                    <?php _e('Nuvarande:', 'lender-rate-history'); ?> 
                    <strong><?php echo $current_value ? number_format(floatval(str_replace(',', '.', $current_value)), 2, ',', ' ') . '%' : '-'; ?></strong>
                </span>
            </h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="150"><?php _e('Datum', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Tidigare', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Nytt värde', 'lender-rate-history'); ?></th>
                        <th width="120"><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Källa', 'lender-rate-history'); ?></th>
                        <th width="150"><?php _e('Åtgärder', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $record): 
                        $change_amount = $record->old_value !== null ? ($record->new_value - $record->old_value) : 0;
                        $editing = isset($_GET['edit']) && $_GET['edit'] == $record->id;
                    ?>
                    <tr id="row-<?php echo $record->id; ?>">
                        <td><?php echo date_i18n('j F, Y', strtotime($record->change_date)); ?></td>
                        <td>
    <?php if ($editing): ?>
        <script>
            // Flytta old_value input till rätt form
            document.addEventListener('DOMContentLoaded', function() {
                var oldValueInput = document.querySelector('#row-<?php echo $record->id; ?> input[name="old_value"]');
                var form = document.querySelector('#edit-form-<?php echo $record->id; ?>');
                if (oldValueInput && form) {
                    var hiddenOldValue = oldValueInput.cloneNode(true);
                    hiddenOldValue.type = 'hidden';
                    form.appendChild(hiddenOldValue);
                }
            });
        </script>
        <input type="text" 
               name="old_value" 
               value="<?php echo $record->old_value !== null ? number_format($record->old_value, 2, ',', ' ') : ''; ?>" 
               size="8" 
               placeholder="-"
               onchange="document.querySelector('#edit-form-<?php echo $record->id; ?> input[name=old_value]').value = this.value;">
    <?php else: ?>
        <?php if ($record->old_value !== null): ?>
            <span class="old-value"><?php echo number_format($record->old_value, 2, ',', ' '); ?>%</span>
        <?php else: ?>
            -
        <?php endif; ?>
    <?php endif; ?>
</td>
                        <td>
                            <?php if ($editing): ?>
                                <form method="post" style="display: inline;" id="edit-form-<?php echo $record->id; ?>">
                                    <?php wp_nonce_field('update_rate_' . $record->id); ?>
                                    <input type="hidden" name="record_id" value="<?php echo $record->id; ?>">
                                    <input type="text" name="new_value" value="<?php echo number_format($record->new_value, 2, ',', ' '); ?>" size="8" required>
                            <?php else: ?>
                                <span class="new-value"><?php echo number_format($record->new_value, 2, ',', ' '); ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record->old_value !== null): 
                                $class = $change_amount > 0 ? 'lrh-increase' : ($change_amount < 0 ? 'lrh-decrease' : 'lrh-no-change');
                                $arrow = $change_amount > 0 ? '↑' : ($change_amount < 0 ? '↓' : '→');
                            ?>
                                <span class="<?php echo $class; ?>">
                                    <?php echo $arrow; ?> 
                                    <?php echo ($change_amount > 0 ? '+' : '') . number_format($change_amount, 2, ',', ' '); ?>%
                                </span>
                            <?php else: ?>
                                <span class="lrh-initial"><?php _e('Initial', 'lender-rate-history'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($editing): ?>
                                <select name="import_source">
                                    <option value="manual" <?php selected($record->import_source, 'manual'); ?>><?php _e('Manuell', 'lender-rate-history'); ?></option>
                                    <option value="wp_all_import" <?php selected($record->import_source, 'wp_all_import'); ?>><?php _e('Import', 'lender-rate-history'); ?></option>
                                    <option value="initialization" <?php selected($record->import_source, 'initialization'); ?>><?php _e('Initiering', 'lender-rate-history'); ?></option>
                                </select>
                            <?php else: ?>
                                <?php
                                $sources = [
                                    'manual' => __('Manuell', 'lender-rate-history'),
                                    'wp_all_import' => __('Import', 'lender-rate-history'),
                                    'initialization' => __('Initiering', 'lender-rate-history')
                                ];
                                echo isset($sources[$record->import_source]) ? $sources[$record->import_source] : $record->import_source;
                                ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($editing): ?>
                                <input type="date" name="change_date" value="<?php echo date('Y-m-d', strtotime($record->change_date)); ?>" required>
                                <button type="submit" name="update_rate" class="button button-small button-primary"><?php _e('Spara', 'lender-rate-history'); ?></button>
                                <a href="<?php echo remove_query_arg('edit'); ?>#row-<?php echo $record->id; ?>" class="button button-small"><?php _e('Avbryt', 'lender-rate-history'); ?></a>
                                </form>
                            <?php else: ?>
                                <a href="<?php echo add_query_arg('edit', $record->id); ?>#row-<?php echo $record->id; ?>" 
                                   class="button button-small"
                                   onclick="setTimeout(function() { 
                                       var row = document.getElementById('row-<?php echo $record->id; ?>');
                                       if(row) {
                                           var rect = row.getBoundingClientRect();
                                           var scrollTo = window.pageYOffset + rect.top - (window.innerHeight / 3);
                                           window.scrollTo({top: scrollTo, behavior: 'smooth'});
                                       }
                                   }, 100); return true;">
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
        </div>
        
        <?php endforeach; ?>
        
        <?php if (empty($history_by_field)): ?>
        <div class="notice notice-info">
            <p><?php _e('Ingen historik för denna långivare.', 'lender-rate-history'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.lrh-field-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.lrh-field-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.current-value {
    font-size: 14px;
    font-weight: normal;
    color: #666;
}

.current-value strong {
    color: #0073aa;
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

.highlight-row {
    background: #fff3cd !important;
    transition: background 0.5s ease;
}

.highlight-row td {
    animation: pulse 0.5s ease-in-out;
}

@keyframes pulse {
    0% { background: #fff3cd; }
    50% { background: #ffe8a1; }
    100% { background: #fff3cd; }
}
</style>