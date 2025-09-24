<?php
/**
 * Unvalidated Changes View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'lender_rate_history';

// Get all unvalidated changes
$unvalidated_changes = $wpdb->get_results(
    "SELECT * FROM {$table_name}
     WHERE is_validated = 0
     ORDER BY change_date DESC"
);

// Count total unvalidated
$total_unvalidated = count($unvalidated_changes);
?>

<div class="wrap">
    <h1>
        <?php _e('Ej validerade ändringar', 'lender-rate-history'); ?>
        <a href="<?php echo admin_url('admin.php?page=lrh-history'); ?>" class="page-title-action">
            <?php _e('← Tillbaka till översikt', 'lender-rate-history'); ?>
        </a>
    </h1>

    <?php if ($total_unvalidated > 0): ?>
    <div class="lrh-validation-actions" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <h3 style="margin-top: 0;">
            <?php printf(__('%d ändringar behöver validering', 'lender-rate-history'), $total_unvalidated); ?>
        </h3>

        <button id="lrh-validate-all" class="button button-primary button-large">
            <span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
            <?php _e('Validera alla ändringar', 'lender-rate-history'); ?>
        </button>

        <span id="lrh-validate-progress" style="display: none; margin-left: 15px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            <span class="progress-text"><?php _e('Validerar...', 'lender-rate-history'); ?></span>
        </span>
    </div>

    <div class="lrh-unvalidated-list">
        <?php
        // Group changes by lender
        $changes_by_lender = [];
        foreach ($unvalidated_changes as $change) {
            if (!isset($changes_by_lender[$change->post_id])) {
                $changes_by_lender[$change->post_id] = [];
            }
            $changes_by_lender[$change->post_id][] = $change;
        }
        ?>

        <?php foreach ($changes_by_lender as $post_id => $changes):
            $post = get_post($post_id);
            if (!$post) continue;
        ?>
        <div class="lrh-lender-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
            <h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #0073aa;">
                <?php echo esc_html($post->post_title); ?>
                <span style="font-size: 14px; font-weight: normal; color: #666; margin-left: 10px;">
                    <?php printf(__('%d ändringar', 'lender-rate-history'), count($changes)); ?>
                </span>
            </h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="150"><?php _e('Datum', 'lender-rate-history'); ?></th>
                        <th width="200"><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Tidigare', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Nytt värde', 'lender-rate-history'); ?></th>
                        <th width="120"><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <th width="100"><?php _e('Källa', 'lender-rate-history'); ?></th>
                        <th width="150"><?php _e('Åtgärder', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($changes as $change):
                        $field_info = LRH_Helpers::parse_field_name($change->field_name);
                        $change_amount = $change->old_value !== null ? ($change->new_value - $change->old_value) : 0;
                        $class = $change_amount > 0 ? 'lrh-increase' : ($change_amount < 0 ? 'lrh-decrease' : 'lrh-no-change');
                        $arrow = $change_amount > 0 ? '↑' : ($change_amount < 0 ? '↓' : '→');
                    ?>
                    <tr data-change-id="<?php echo $change->id; ?>">
                        <td><?php echo date_i18n('j F, Y', strtotime($change->change_date)); ?></td>
                        <td>
                            <strong><?php echo esc_html($field_info['type_label']); ?></strong><br>
                            <small><?php echo esc_html($field_info['period_label']); ?></small>
                        </td>
                        <td>
                            <?php if ($change->old_value !== null): ?>
                                <span class="old-value"><?php echo number_format($change->old_value, 2, ',', ' '); ?>%</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="new-value"><?php echo number_format($change->new_value, 2, ',', ' '); ?>%</span>
                        </td>
                        <td>
                            <?php if ($change->old_value !== null): ?>
                                <span class="<?php echo $class; ?>">
                                    <?php echo $arrow; ?>
                                    <?php echo ($change_amount > 0 ? '+' : '') . number_format($change_amount, 2, ',', ' '); ?>%
                                </span>
                            <?php else: ?>
                                <span class="lrh-initial"><?php _e('Initial', 'lender-rate-history'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $sources = [
                                'manual' => __('Manuell', 'lender-rate-history'),
                                'wp_all_import' => __('Import', 'lender-rate-history'),
                                'initialization' => __('Initiering', 'lender-rate-history')
                            ];
                            echo isset($sources[$change->import_source]) ? $sources[$change->import_source] : $change->import_source;
                            ?>
                        </td>
                        <td>
                            <button class="button button-small lrh-validate-single" data-id="<?php echo $change->id; ?>">
                                <?php _e('Validera', 'lender-rate-history'); ?>
                            </button>
                            <button class="button button-small lrh-delete-single" data-id="<?php echo $change->id; ?>">
                                <?php _e('Ta bort', 'lender-rate-history'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="notice notice-success" style="margin-top: 20px;">
        <p>
            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
            <?php _e('Alla ändringar är validerade!', 'lender-rate-history'); ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<style>
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

.lrh-validation-actions {
    display: flex;
    align-items: center;
}

.lrh-validation-actions h3 {
    flex: 1;
}

tr.validating {
    opacity: 0.5;
    background: #f0f0f1 !important;
}

tr.validated {
    background: #d4edda !important;
    transition: all 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Validate all button
    $('#lrh-validate-all').on('click', function() {
        if (!confirm('<?php _e("Är du säker på att du vill validera alla ändringar?", "lender-rate-history"); ?>')) {
            return;
        }

        var $button = $(this);
        var $progress = $('#lrh-validate-progress');
        var $progressText = $progress.find('.progress-text');

        $button.prop('disabled', true);
        $progress.show();

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'lrh_validate_all_changes',
                nonce: '<?php echo wp_create_nonce("lrh_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $progressText.text('<?php _e("Validering klar!", "lender-rate-history"); ?>');

                    // Mark all rows as validated
                    $('tr[data-change-id]').addClass('validated');

                    setTimeout(function() {
                        alert(response.data || '<?php _e("Alla ändringar har validerats!", "lender-rate-history"); ?>');
                        location.reload();
                    }, 1000);
                } else {
                    alert('<?php _e("Fel:", "lender-rate-history"); ?> ' + (response.data || '<?php _e("Okänt fel", "lender-rate-history"); ?>'));
                    $button.prop('disabled', false);
                    $progress.hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Validation error:', error);
                alert('<?php _e("Ett fel uppstod vid validering", "lender-rate-history"); ?>');
                $button.prop('disabled', false);
                $progress.hide();
            }
        });
    });

    // Validate single change
    $('.lrh-validate-single').on('click', function() {
        var $button = $(this);
        var changeId = $button.data('id');
        var $row = $button.closest('tr');

        $button.prop('disabled', true);
        $row.addClass('validating');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'lrh_validate_change',
                change_id: changeId,
                nonce: '<?php echo wp_create_nonce("lrh_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $row.removeClass('validating').addClass('validated');
                    setTimeout(function() {
                        $row.fadeOut();
                    }, 1000);
                } else {
                    alert('<?php _e("Kunde inte validera ändringen", "lender-rate-history"); ?>');
                    $button.prop('disabled', false);
                    $row.removeClass('validating');
                }
            },
            error: function() {
                alert('<?php _e("Ett fel uppstod", "lender-rate-history"); ?>');
                $button.prop('disabled', false);
                $row.removeClass('validating');
            }
        });
    });

    // Delete single change
    $('.lrh-delete-single').on('click', function() {
        if (!confirm('<?php _e("Är du säker på att du vill ta bort denna ändring?", "lender-rate-history"); ?>')) {
            return;
        }

        var $button = $(this);
        var changeId = $button.data('id');
        var $row = $button.closest('tr');

        $button.prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'lrh_delete_change',
                change_id: changeId,
                nonce: '<?php echo wp_create_nonce("lrh_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut();
                } else {
                    alert('<?php _e("Kunde inte ta bort ändringen", "lender-rate-history"); ?>');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e("Ett fel uppstod", "lender-rate-history"); ?>');
                $button.prop('disabled', false);
            }
        });
    });
});
</script>