<?php
/**
 * External Sources Admin View - FÖRBÄTTRAD VERSION
 * Strukturerad som admin-history med översikt och detaljvy
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$external_sources = new LRH_External_Sources();
$sources = $external_sources->get_sources();
$database = new LRH_Database();

// Handle single source view
$selected_source = isset($_GET['source_id']) ? sanitize_text_field($_GET['source_id']) : '';
$view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';

// Handle actions (delete, validate, etc)
if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
    switch ($_GET['action']) {
        case 'delete_source':
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_source')) {
                if ($external_sources->delete_source($_GET['source_id'])) {
                    echo '<div class="notice notice-success"><p>Källa borttagen!</p></div>';
                    $sources = $external_sources->get_sources();
                }
            }
            break;
            
        case 'validate_entry':
            if (wp_verify_nonce($_GET['_wpnonce'], 'validate_entry')) {
                $database->update_change(intval($_GET['entry_id']), [
                    'is_validated' => 1,
                    'validation_notes' => sprintf('Validerad av %s', wp_get_current_user()->display_name)
                ]);
                echo '<div class="notice notice-success"><p>Post validerad!</p></div>';
            }
            break;
    }
}

// Handle inline edit via AJAX
if (isset($_POST['ajax_edit']) && wp_verify_nonce($_POST['nonce'], 'lrh_admin_nonce')) {
    global $wpdb;
    $table_name = $wpdb->prefix . LRH_TABLE_NAME;
    
    $id = intval($_POST['id']);
    $field = sanitize_text_field($_POST['field']);
    $value = sanitize_text_field($_POST['value']);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $id
    ));
    
    if (!$existing) {
        wp_die(json_encode(['success' => false]));
    }
    
    $update_data = [];
    
    switch($field) {
        case 'old_value':
            $old_value = empty($value) ? null : floatval(str_replace(',', '.', $value));
            $new_value = floatval($existing->new_value);
            
            $update_data = [
                'old_value' => $old_value,
                'change_amount' => $old_value !== null ? ($new_value - $old_value) : null,
                'change_percentage' => $old_value !== null ? ($new_value - $old_value) : null,
                'change_type' => $old_value === null ? 'initial' : 'update'
            ];
            break;
            
        case 'new_value':
            $new_value = floatval(str_replace(',', '.', $value));
            $old_value = $existing->old_value !== null ? floatval($existing->old_value) : null;
            
            $update_data = [
                'new_value' => $new_value,
                'change_amount' => $old_value !== null ? ($new_value - $old_value) : null,
                'change_percentage' => $old_value !== null ? ($new_value - $old_value) : null
            ];
            
            // Check if needs validation
            $settings = get_option('lrh_settings', []);
            $threshold = isset($settings['large_change_threshold']) ? $settings['large_change_threshold'] : 25;
            if ($old_value !== null && abs($new_value - $old_value) > ($old_value * $threshold / 100)) {
                $update_data['is_validated'] = 0;
            }
            break;
            
        case 'change_date':
            $update_data = ['change_date' => $value . ' 00:00:00'];
            break;
            
        case 'import_source':
            $update_data = ['import_source' => $value];
            break;
    }
    
    $result = $wpdb->update($table_name, $update_data, ['id' => $id]);
    wp_die(json_encode(['success' => $result !== false]));
}

// Handle form submissions
if (isset($_POST['add_source']) && wp_verify_nonce($_POST['_wpnonce'], 'lrh_add_source')) {
    $result = $external_sources->register_source([
        'source_id' => sanitize_text_field($_POST['source_id']),
        'source_name' => sanitize_text_field($_POST['source_name'] ?? ''),
        'display_name' => sanitize_text_field($_POST['display_name']),
        'source_type' => sanitize_text_field($_POST['source_type']),
        'option_page' => sanitize_text_field($_POST['option_page'] ?? 'option'),
        'option_field' => sanitize_text_field($_POST['option_field']),
        'category' => sanitize_text_field($_POST['category']),
        'value_format' => sanitize_text_field($_POST['value_format'] ?? 'percentage'),
        'value_suffix' => sanitize_text_field($_POST['value_suffix'] ?? '%'),
        'decimals' => intval($_POST['decimals'] ?? 2),
        'enabled' => true
    ]);
    
    if ($result) {
        echo '<div class="notice notice-success"><p>Källa tillagd!</p></div>';
        $sources = $external_sources->get_sources();
    }
}

if (isset($_POST['add_manual_entry']) && wp_verify_nonce($_POST['_wpnonce'], 'lrh_manual_entry')) {
    $source_id = sanitize_text_field($_POST['source_id']);
    $source = $external_sources->get_source($source_id);
    
    $result = $external_sources->add_manual_entry([
        'source_id' => $source_id,
        'source_name' => $source ? $source['display_name'] : $source_id,
        'field_name' => sanitize_text_field($_POST['field_name']),
        'new_value' => sanitize_text_field($_POST['new_value']),
        'old_value' => !empty($_POST['old_value']) ? sanitize_text_field($_POST['old_value']) : null,
        'change_date' => sanitize_text_field($_POST['change_date']) . ' 00:00:00',
    ]);
    
    if ($result) {
        echo '<div class="notice notice-success"><p>Historikpunkt tillagd!</p></div>';
    }
}

// Get statistics for dashboard
$stats = [
    'total_sources' => count($sources),
    'total_changes' => 0,
    'unvalidated' => 0,
    'recent_changes' => []
];

foreach ($sources as $source) {
    $history = $external_sources->get_source_history($source['source_id'], 1000);
    $stats['total_changes'] += count($history);
    
    foreach ($history as $record) {
        if (!$record->is_validated) {
            $stats['unvalidated']++;
        }
    }
}

// Get recent changes for all external sources
$recent_changes = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}lender_rate_history 
     WHERE source_type != 'post' 
     ORDER BY change_date DESC 
     LIMIT 10"
);
$stats['recent_changes'] = $recent_changes;

// Show detailed view if source is selected
if ($selected_source && $view_mode === 'details') {
    include 'admin-external-sources-single.php';
    return;
}
?>

<div class="wrap">
    <h1>
        <?php _e('Externa Källor', 'lender-rate-history'); ?>
        <a href="#add-source" class="page-title-action"><?php _e('Lägg till ny', 'lender-rate-history'); ?></a>
    </h1>
    
    <!-- Statistics Overview -->
    <div class="lrh-stats-grid">
        <div class="lrh-stat-card">
            <h3><?php _e('Antal källor', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number"><?php echo $stats['total_sources']; ?></div>
        </div>
        
        <div class="lrh-stat-card">
            <h3><?php _e('Totalt antal ändringar', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number"><?php echo $stats['total_changes']; ?></div>
        </div>
        
        <div class="lrh-stat-card <?php echo $stats['unvalidated'] > 0 ? 'lrh-stat-warning' : ''; ?>">
            <h3><?php _e('Behöver validering', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number">
                <?php echo $stats['unvalidated']; ?>
                <?php if ($stats['unvalidated'] > 0): ?>
                <a href="?page=lrh-external-sources&view=unvalidated" class="button button-small">
                    <?php _e('Granska', 'lender-rate-history'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="?page=lrh-external-sources" class="nav-tab <?php echo $view_mode === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Översikt', 'lender-rate-history'); ?>
        </a>
        <a href="?page=lrh-external-sources&view=unvalidated" class="nav-tab <?php echo $view_mode === 'unvalidated' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Granska', 'lender-rate-history'); ?>
            <?php if ($stats['unvalidated'] > 0): ?>
            <span class="lrh-badge"><?php echo $stats['unvalidated']; ?></span>
            <?php endif; ?>
        </a>
    </nav>
    
    <div class="lrh-external-sources-content">
        
        <?php if ($view_mode === 'unvalidated'): ?>
        <!-- Unvalidated Changes View -->
        <div class="lrh-admin-box">
            <h2><?php _e('Källor som behöver validering', 'lender-rate-history'); ?></h2>
            
            <?php
            $unvalidated = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}lender_rate_history 
                 WHERE source_type != 'post' AND is_validated = 0 
                 ORDER BY change_date DESC"
            );
            
            if (!empty($unvalidated)):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Källa', 'lender-rate-history'); ?></th>
                        <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th><?php _e('Tidigare', 'lender-rate-history'); ?></th>
                        <th><?php _e('Nytt', 'lender-rate-history'); ?></th>
                        <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                        <th><?php _e('Åtgärder', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unvalidated as $record): 
                        $source = $external_sources->get_source($record->source_id);
                        $format = $source['value_format'] ?? 'percentage';
                        $suffix = $source['value_suffix'] ?? '%';
                        $decimals = $source['decimals'] ?? 2;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($record->source_name); ?></strong>
                        </td>
                        <td><?php echo esc_html($record->field_name); ?></td>
                        <td>
                            <?php 
                            if ($record->old_value !== null) {
                                echo number_format($record->old_value, $decimals, ',', ' ') . $suffix;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($record->new_value, $decimals, ',', ' ') . $suffix; ?></strong>
                        </td>
                        <td>
                            <?php 
                            if ($record->change_amount !== null):
                                $class = $record->change_amount > 0 ? 'lrh-increase' : 'lrh-decrease';
                                $arrow = $record->change_amount > 0 ? '↑' : '↓';
                            ?>
                                <span class="<?php echo $class; ?>">
                                    <?php echo $arrow . ' ' . number_format(abs($record->change_amount), $decimals, ',', ' '); ?>
                                    <?php if ($format === 'percentage'): ?>p.e.<?php else: echo $suffix; ?><?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="lrh-initial">INITIAL</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n('j F, Y', strtotime($record->change_date)); ?></td>
                        <td>
                            <a href="?page=lrh-external-sources&action=validate_entry&entry_id=<?php echo $record->id; ?>&_wpnonce=<?php echo wp_create_nonce('validate_entry'); ?>" 
                               class="button button-small button-primary">
                                <?php _e('Validera', 'lender-rate-history'); ?>
                            </a>
                            <a href="?page=lrh-external-sources&source_id=<?php echo esc_attr($record->source_id); ?>&view=details" 
                               class="button button-small">
                                <?php _e('Visa alla', 'lender-rate-history'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('Inga poster behöver validering.', 'lender-rate-history'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Main Overview -->
        <div class="lrh-sources-grid">
            <?php foreach ($sources as $source): 
                $history = $external_sources->get_source_history($source['source_id'], 100);
                $latest_value = $database->get_latest_value_for_source(
                    $source['source_type'],
                    $source['source_id'],
                    $source['option_field'] ?? 'value'
                );
                
                // Count unvalidated for this source
                $unvalidated_count = 0;
                foreach ($history as $record) {
                    if (!$record->is_validated) {
                        $unvalidated_count++;
                    }
                }
                
                $format = $source['value_format'] ?? 'percentage';
                $suffix = $source['value_suffix'] ?? '%';
                $decimals = $source['decimals'] ?? 2;
            ?>
            <div class="lrh-source-card">
                <h3><?php echo esc_html($source['display_name']); ?></h3>
                
                <?php if ($unvalidated_count > 0): ?>
                <div class="lrh-validation-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php printf(__('%d poster behöver validering', 'lender-rate-history'), $unvalidated_count); ?>
                </div>
                <?php endif; ?>
                
                <div class="lrh-source-stats">
                    <div class="stat">
                        <span class="label"><?php _e('Typ:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo esc_html($source['source_type']); ?></span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Format:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo esc_html(ucfirst($format) . ' (' . $suffix . ')'); ?></span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Nuvarande värde:', 'lender-rate-history'); ?></span>
                        <span class="value">
                            <?php if ($latest_value !== null): ?>
                                <strong><?php echo number_format($latest_value, $decimals, ',', ' ') . $suffix; ?></strong>
                            <?php else: ?>
                                <span style="color: #d63638;">Ingen data</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Antal ändringar:', 'lender-rate-history'); ?></span>
                        <span class="value"><?php echo count($history); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($history) && count($history) > 0): 
                    $recent = array_slice($history, 0, 3);
                ?>
                <div class="lrh-recent-changes-preview">
                    <h4><?php _e('Senaste ändringar:', 'lender-rate-history'); ?></h4>
                    <ul>
                        <?php foreach ($recent as $change): ?>
                        <li>
                            <span class="date"><?php echo date_i18n('j/n', strtotime($change->change_date)); ?>:</span>
                            <?php if ($change->old_value !== null): ?>
                                <span class="old-value"><?php echo number_format($change->old_value, $decimals, ',', ' '); ?></span>
                                →
                            <?php endif; ?>
                            <span class="new-value"><?php echo number_format($change->new_value, $decimals, ',', ' '); ?></span>
                            <?php echo $suffix; ?>
                            <?php if (!$change->is_validated): ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638; font-size: 14px;"></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="lrh-card-actions">
                    <a href="?page=lrh-external-sources&source_id=<?php echo esc_attr($source['source_id']); ?>&view=details" 
                       class="button button-primary">
                        <?php _e('Visa detaljerad historik', 'lender-rate-history'); ?>
                    </a>
                    <a href="?page=lrh-external-sources&action=delete_source&source_id=<?php echo esc_attr($source['source_id']); ?>&_wpnonce=<?php echo wp_create_nonce('delete_source'); ?>" 
                       class="button"
                       onclick="return confirm('Är du säker?');">
                        <?php _e('Ta bort', 'lender-rate-history'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            
<!-- Ersätt "Add New Source Card" med detta -->
<div class="lrh-source-card lrh-add-new-card" id="add-source">
    <h3><?php _e('Lägg till ny källa', 'lender-rate-history'); ?></h3>
    
    <form method="post" class="lrh-compact-form">
        <?php wp_nonce_field('lrh_add_source'); ?>
        
        <!-- Käll-ID genereras automatiskt men kan redigeras -->
        <div class="form-field">
            <label><?php _e('Käll-ID', 'lender-rate-history'); ?></label>
            <input type="text" name="source_id" id="source_id" required pattern="[a-z0-9_-]+" 
                   placeholder="Genereras automatiskt">
            <small class="description">Genereras automatiskt baserat på fältval</small>
        </div>
        
        <div class="form-field">
            <label><?php _e('Visningsnamn', 'lender-rate-history'); ?></label>
            <input type="text" name="display_name" id="display_name" required 
                   placeholder="T.ex. Riksbankens styrränta">
        </div>
        
        <div class="form-field">
            <label><?php _e('Typ', 'lender-rate-history'); ?></label>
            <select name="source_type" id="source_type">
                <option value="option">ACF Options Page</option>
                <option value="manual">Endast manuell</option>
            </select>
        </div>
        
        <!-- Option Page dropdown -->
        <div class="form-field option-page-row" style="display:none;">
            <label><?php _e('Options Page', 'lender-rate-history'); ?></label>
            <select name="option_page" id="option_page">
                <option value="">-- Välj options page --</option>
                <option value="option">Standard Options (option)</option>
                <option value="options">Theme Options (options)</option>
                
                <?php 
                if (function_exists('acf_get_options_pages')) {
                    $acf_pages = acf_get_options_pages();
                    if ($acf_pages) {
                        echo '<optgroup label="ACF Option Pages">';
                        foreach ($acf_pages as $page) {
                            // Använd menu_slug som value för bättre läsbarhet
                            $page_id = isset($page['menu_slug']) ? $page['menu_slug'] : $page['post_id'];
                            echo '<option value="' . esc_attr($page_id) . '" data-post-id="' . esc_attr($page['post_id']) . '">';
                            echo esc_html($page['page_title']);
                            echo '</option>';
                        }
                        echo '</optgroup>';
                    }
                }
                ?>
            </select>
        </div>
        
        <!-- Fält-väljare istället för input -->
        <div class="form-field option-field-row" style="display:none;">
            <label><?php _e('Välj fält', 'lender-rate-history'); ?></label>
            <select name="option_field" id="option_field">
                <option value="">-- Välj först en option page --</option>
            </select>
            <!-- Alternativ: manuell input om fältet inte finns i listan -->
            <div style="margin-top: 5px;">
                <label>
                    <input type="checkbox" id="manual_field_input"> 
                    <small>Ange fältnamn manuellt</small>
                </label>
                <input type="text" name="option_field_manual" id="option_field_manual" 
                       style="display:none; margin-top: 5px;" 
                       placeholder="Fältnamn">
            </div>
        </div>
        
        <div class="form-field">
            <label><?php _e('Format', 'lender-rate-history'); ?></label>
            <select name="value_format" id="value_format">
                <option value="percentage">Procent (%)</option>
                <option value="currency">Valuta (kr)</option>
                <option value="number">Nummer</option>
            </select>
        </div>
        
        <div class="form-field decimals-row">
            <label><?php _e('Decimaler', 'lender-rate-history'); ?></label>
            <input type="number" name="decimals" id="decimals" min="0" max="4" value="2">
        </div>
        
        <input type="hidden" name="value_suffix" id="value_suffix" value="%">
        
        <div class="form-field">
            <button type="submit" name="add_source" class="button button-primary">
                <?php _e('Lägg till källa', 'lender-rate-history'); ?>
            </button>
        </div>
    </form>
</div>


<script>
jQuery(document).ready(function($) {
    var availableFields = {};
    var selectedOptionPage = '';
    
    // Visa/dölj fält baserat på typ
    $('#source_type').on('change', function() {
        if ($(this).val() === 'option') {
            $('.option-page-row, .option-field-row').show();
        } else {
            $('.option-page-row, .option-field-row').hide();
            // För manuell typ, generera enklare käll-ID
            updateSourceId('manual', $('#display_name').val());
        }
    }).trigger('change');
    
    // Hämta fält när option page väljs
    $('#option_page').on('change', function() {
        var optionPage = $(this).val();
        selectedOptionPage = optionPage;
        var $fieldSelect = $('#option_field');
        
        if (!optionPage) {
            $fieldSelect.html('<option value="">-- Välj först en option page --</option>');
            return;
        }
        
        $fieldSelect.html('<option value="">Laddar fält...</option>');
        
        // AJAX för att hämta fält
        $.post(ajaxurl, {
            action: 'lrh_get_option_fields',
            option_page: optionPage,
            nonce: '<?php echo wp_create_nonce('lrh_admin_nonce'); ?>'
        }, function(response) {
            if (response.success && response.data && response.data.length > 0) {
                availableFields[optionPage] = response.data;
                
                var options = '<option value="">-- Välj fält --</option>';
                response.data.forEach(function(field) {
                    options += '<option value="' + field + '">' + field + '</option>';
                });
                
                $fieldSelect.html(options);
            } else {
                $fieldSelect.html('<option value="">Inga fält hittades</option>');
                // Visa manuell input automatiskt om inga fält hittas
                $('#manual_field_input').prop('checked', true).trigger('change');
            }
        });
    });
    
    // Generera käll-ID automatiskt
    function updateSourceId(optionPage, fieldName) {
        if (!$('#source_id').data('manual-edit')) {
            var sourceId = '';
            
            if (fieldName) {
                // Rensa option page namn för ID
                var cleanPage = optionPage.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                
                // Specialfall för standard options
                if (cleanPage === 'option' || cleanPage === 'options') {
                    sourceId = fieldName.toLowerCase();
                } else {
                    // Kombinera page och field för unikt ID
                    sourceId = cleanPage + '_' + fieldName.toLowerCase();
                }
                
                // Rensa och formatera
                sourceId = sourceId
                    .replace(/[^a-z0-9_-]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
            }
            
            $('#source_id').val(sourceId);
        }
    }
    
    // När fält väljs, uppdatera käll-ID och visningsnamn
    $('#option_field').on('change', function() {
        var fieldName = $(this).val();
        if (fieldName) {
            updateSourceId(selectedOptionPage, fieldName);
            
            // Auto-generera visningsnamn om det är tomt
            if (!$('#display_name').val()) {
                // Gör fältnamn mer läsbart
                var displayName = fieldName
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, l => l.toUpperCase());
                
                // Lägg till option page namn för kontext
                var pageLabel = $('#option_page option:selected').text();
                if (pageLabel && pageLabel !== 'Standard Options (option)') {
                    displayName = pageLabel + ' - ' + displayName;
                }
                
                $('#display_name').val(displayName);
            }
        }
    });
    
    // Markera om användaren manuellt redigerar käll-ID
    $('#source_id').on('input', function() {
        $(this).data('manual-edit', true);
    });
    
    // Toggle manuell fält-input
    $('#manual_field_input').on('change', function() {
        if ($(this).is(':checked')) {
            $('#option_field_manual').show();
            $('#option_field').prop('disabled', true);
        } else {
            $('#option_field_manual').hide();
            $('#option_field').prop('disabled', false);
        }
    });
    
    // Uppdatera käll-ID från manuellt fält
    $('#option_field_manual').on('input', function() {
        if ($('#manual_field_input').is(':checked')) {
            updateSourceId(selectedOptionPage, $(this).val());
        }
    });
    
    // Format-hantering
    $('#value_format').on('change', function() {
        var format = $(this).val();
        var $suffix = $('#value_suffix');
        var $decimals = $('#decimals');
        
        if (format === 'percentage') {
            $suffix.val('%');
            $decimals.val(2);
        } else if (format === 'currency') {
            $suffix.val('kr');
            $decimals.val(0);
        } else {
            $suffix.val('');
            $decimals.val(2);
        }
    }).trigger('change');
    
    // Validering innan submit
    $('form.lrh-compact-form').on('submit', function(e) {
        // Om manuell input är vald, kopiera värdet till option_field
        if ($('#manual_field_input').is(':checked')) {
            var manualValue = $('#option_field_manual').val();
            if (manualValue) {
                $('#option_field').prop('disabled', false).val(manualValue);
            }
        }
    });
});
</script>
			
<style>
.lrh-compact-form .form-field select {
    width: 100%;
}

.lrh-compact-form .description {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

#option_field_manual {
    width: 100%;
}
</style>

<!-- Lägg till jQuery UI autocomplete om det behövs -->
<?php if (!wp_script_is('jquery-ui-autocomplete', 'enqueued')): ?>
<script>
jQuery(document).ready(function($) {
    if (typeof $.ui === 'undefined' || typeof $.ui.autocomplete === 'undefined') {
        // Ladda jQuery UI autocomplete om det inte finns
        var script = document.createElement('script');
        script.src = 'https://code.jquery.com/ui/1.12.1/jquery-ui.min.js';
        document.head.appendChild(script);
        
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css';
        document.head.appendChild(link);
    }
});
</script>
<?php endif; ?>
        </div>
        
        <!-- Recent Changes Section -->
        <?php if (!empty($stats['recent_changes'])): ?>
        <div class="lrh-admin-box" style="margin-top: 30px;">
            <h2><?php _e('Senaste ändringar - Externa källor', 'lender-rate-history'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Källa', 'lender-rate-history'); ?></th>
                        <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                        <th><?php _e('Status', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_changes'] as $change): 
                        $source = $external_sources->get_source($change->source_id);
                    ?>
                    <tr>
                        <td>
                            <a href="?page=lrh-external-sources&source_id=<?php echo esc_attr($change->source_id); ?>&view=details">
                                <?php echo esc_html($change->source_name ?? $change->source_id); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($change->field_name); ?></td>
                        <td>
                            <?php if ($change->old_value !== null): ?>
                                <span class="lrh-old-value"><?php echo number_format($change->old_value, 2, ',', ' '); ?></span>
                                →
                                <span class="lrh-new-value"><?php echo number_format($change->new_value, 2, ',', ' '); ?></span>
                            <?php else: ?>
                                <span class="lrh-new-value"><?php echo number_format($change->new_value, 2, ',', ' '); ?></span>
                                <span class="lrh-initial">INITIAL</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo human_time_diff(strtotime($change->change_date), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history'); ?></td>
                        <td>
                            <?php if ($change->is_validated): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle option fields
    $('#source_type').on('change', function() {
        if ($(this).val() === 'option') {
            $('.option-field-row').show();
        } else {
            $('.option-field-row').hide();
        }
    }).trigger('change');
    
    // Auto-update suffix based on format
    $('#value_format').on('change', function() {
        var format = $(this).val();
        var $suffix = $('input[name="value_suffix"]');
        
        if (!$suffix.length) {
            // Add hidden suffix field if not exists
            $(this).after('<input type="hidden" name="value_suffix" value="">');
            $suffix = $('input[name="value_suffix"]');
        }
        
        if (format === 'percentage') {
            $suffix.val('%');
        } else if (format === 'currency') {
            $suffix.val('kr');
        } else {
            $suffix.val('');
        }
    });
    
    // Smooth scroll to add form
    $('a[href="#add-source"]').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $('#add-source').offset().top - 100
        }, 500);
    });
});
</script>

<style>
/* Stats Grid */
.lrh-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.lrh-stat-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.lrh-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
    color: #23282d;
}

.lrh-stat-number {
    font-size: 32px;
    font-weight: 300;
    color: #0073aa;
}

.lrh-stat-warning .lrh-stat-number {
    color: #d63638;
}

/* Tab Navigation */
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.lrh-badge {
    display: inline-block;
    background: #d63638;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    margin-left: 5px;
}

/* Sources Grid */
.lrh-sources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.lrh-source-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.lrh-source-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.lrh-source-card h3 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.lrh-add-new-card {
    border: 2px dashed #ccd0d4;
    background: #f9f9f9;
}

.lrh-validation-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    padding: 8px;
    margin: 10px 0;
    color: #856404;
    display: flex;
    align-items: center;
    gap: 5px;
}

.lrh-source-stats {
    margin: 15px 0;
}

.lrh-source-stats .stat {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f1;
}

.lrh-source-stats .label {
    color: #666;
}

.lrh-source-stats .value {
    font-weight: 600;
    color: #23282d;
}

.lrh-recent-changes-preview {
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.lrh-recent-changes-preview h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    text-transform: uppercase;
    color: #666;
}

.lrh-recent-changes-preview ul {
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.lrh-recent-changes-preview li {
    margin: 5px 0;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.lrh-recent-changes-preview .date {
    color: #999;
    min-width: 35px;
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

/* Compact Form */
.lrh-compact-form {
    margin-top: 15px;
}

.lrh-compact-form .form-field {
    margin-bottom: 12px;
}

.lrh-compact-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 13px;
}

.lrh-compact-form input,
.lrh-compact-form select {
    width: 100%;
}

/* Admin Box */
.lrh-admin-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.lrh-admin-box h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

/* Status indicators */
.lrh-increase { color: #dc2626; font-weight: 600; }
.lrh-decrease { color: #059669; font-weight: 600; }
.lrh-no-change { color: #6b7280; }
.lrh-initial { 
    background: #f0f0f1; 
    padding: 2px 8px; 
    border-radius: 3px; 
    font-size: 11px; 
    text-transform: uppercase; 
}
.lrh-old-value { color: #757575; text-decoration: line-through; }
.lrh-new-value { font-weight: 600; }
</style>