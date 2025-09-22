<?php
/**
 * Dashboard view
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = new LRH_Database();
$stats = $database->get_statistics();
?>

<div class="wrap">
    <h1><?php _e('Rate History Dashboard', 'lender-rate-history'); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="lrh-stats-grid">
        <div class="lrh-stat-card">
            <h3><?php _e('Totalt antal ändringar', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number"><?php echo number_format($stats['total_changes']); ?></div>
        </div>
        
        <div class="lrh-stat-card">
            <h3><?php _e('Ändringar idag', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number"><?php echo number_format($stats['changes_today']); ?></div>
        </div>
        
        <div class="lrh-stat-card">
            <h3><?php _e('Ändringar denna vecka', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number"><?php echo number_format($stats['changes_week']); ?></div>
        </div>
        
        <div class="lrh-stat-card <?php echo $stats['unvalidated'] > 0 ? 'lrh-stat-warning' : ''; ?>">
            <h3><?php _e('Behöver validering', 'lender-rate-history'); ?></h3>
            <div class="lrh-stat-number">
                <?php echo number_format($stats['unvalidated']); ?>
                <?php if ($stats['unvalidated'] > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=lrh-history&filter=unvalidated'); ?>" class="button button-small">
                    <?php _e('Granska', 'lender-rate-history'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="lrh-dashboard-grid">
        <!-- Recent Changes -->
        <div class="lrh-dashboard-section">
            <h2><?php _e('Senaste ändringar', 'lender-rate-history'); ?></h2>
            <?php
            $recent_changes = $database->get_recent_changes(['limit' => 10]);
            if (!empty($recent_changes)):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                        <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <th><?php _e('Datum', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_changes as $change): 
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
                        <td>
                            <?php if ($change->old_value !== null): ?>
                            <span class="lrh-old-value"><?php echo number_format($change->old_value, 2); ?>%</span>
                            →
                            <span class="lrh-new-value"><?php echo number_format($change->new_value, 2); ?>%</span>
                            <?php if ($change->change_percentage): ?>
                            <span class="lrh-change-badge <?php echo $change->change_percentage > 0 ? 'lrh-increase' : 'lrh-decrease'; ?>">
                                <?php echo ($change->change_percentage > 0 ? '+' : '') . number_format($change->change_percentage, 2); ?>%
                            </span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="lrh-new-value"><?php echo number_format($change->new_value, 2); ?>%</span>
                            <span class="lrh-change-badge"><?php _e('Initial', 'lender-rate-history'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo human_time_diff(strtotime($change->change_date), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo admin_url('admin.php?page=lrh-history'); ?>" class="button">
                    <?php _e('Visa all historik', 'lender-rate-history'); ?>
                </a>
            </p>
            <?php else: ?>
            <p><?php _e('Inga ändringar registrerade ännu.', 'lender-rate-history'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Top Changed Fields -->
        <div class="lrh-dashboard-section">
            <h2><?php _e('Mest ändrade fält', 'lender-rate-history'); ?></h2>
            <?php if (!empty($stats['top_fields'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Fält', 'lender-rate-history'); ?></th>
                        <th><?php _e('Antal ändringar', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_fields'] as $field): ?>
                    <tr>
                        <td><?php echo esc_html($field->field_name); ?></td>
                        <td>
                            <div class="lrh-bar-chart">
                                <div class="lrh-bar" style="width: <?php echo ($field->count / $stats['top_fields'][0]->count) * 100; ?>%;">
                                    <?php echo number_format($field->count); ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('Ingen data tillgänglig.', 'lender-rate-history'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Most Active Lenders -->
        <div class="lrh-dashboard-section">
            <h2><?php _e('Mest aktiva långivare', 'lender-rate-history'); ?></h2>
            <?php if (!empty($stats['top_lenders'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Långivare', 'lender-rate-history'); ?></th>
                        <th><?php _e('Antal ändringar', 'lender-rate-history'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_lenders'] as $lender): 
                        $post = get_post($lender->post_id);
                    ?>
                    <tr>
                        <td>
                            <?php if ($post): ?>
                            <a href="<?php echo admin_url('admin.php?page=lrh-history&lender_id=' . $lender->post_id); ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                            <?php else: ?>
                            <?php _e('Okänd', 'lender-rate-history'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="lrh-bar-chart">
                                <div class="lrh-bar" style="width: <?php echo ($lender->count / $stats['top_lenders'][0]->count) * 100; ?>%;">
                                    <?php echo number_format($lender->count); ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('Ingen data tillgänglig.', 'lender-rate-history'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="lrh-dashboard-section">
            <h2><?php _e('Snabbåtgärder', 'lender-rate-history'); ?></h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=lrh-import-export'); ?>" class="button button-primary">
                    <?php _e('Importera/Exportera', 'lender-rate-history'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=lrh-settings'); ?>" class="button">
                    <?php _e('Inställningar', 'lender-rate-history'); ?>
                </a>
                <button id="lrh-clear-cache" class="button">
                    <?php _e('Rensa cache', 'lender-rate-history'); ?>
                </button>
            </p>
            
            <h3><?php _e('Systeminfo', 'lender-rate-history'); ?></h3>
            <ul>
                <li><?php _e('Plugin version:', 'lender-rate-history'); ?> <?php echo LRH_VERSION; ?></li>
                <li><?php _e('Databas version:', 'lender-rate-history'); ?> <?php echo get_option('lrh_db_version', 'Unknown'); ?></li>
                <li><?php _e('Senaste import:', 'lender-rate-history'); ?> 
                    <?php 
                    $last_import = isset($stats['last_import']) ? $stats['last_import'] : null;
                    echo $last_import ? human_time_diff(strtotime($last_import), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history') : __('Aldrig', 'lender-rate-history');
                    ?>
                </li>
                <li><?php _e('Senaste rensning:', 'lender-rate-history'); ?> 
                    <?php 
                    $last_cleanup = isset($stats['last_cleanup']) ? $stats['last_cleanup'] : null;
                    echo $last_cleanup ? human_time_diff(strtotime($last_cleanup), current_time('timestamp')) . ' ' . __('sedan', 'lender-rate-history') : __('Aldrig', 'lender-rate-history');
                    ?>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
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

.lrh-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.lrh-dashboard-section {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.lrh-dashboard-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.lrh-bar-chart {
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
}

.lrh-bar {
    background: #0073aa;
    color: white;
    padding: 2px 8px;
    min-width: 40px;
    text-align: right;
}

.lrh-change-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 5px;
}

.lrh-increase {
    background: #d63638;
    color: white;
}

.lrh-decrease {
    background: #00a32a;
    color: white;
}

.lrh-old-value {
    color: #757575;
    text-decoration: line-through;
}

.lrh-new-value {
    font-weight: 600;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#lrh-clear-cache').on('click', function() {
        if (confirm('<?php _e('Är du säker på att du vill rensa cachen?', 'lender-rate-history'); ?>')) {
            // Add AJAX call to clear cache
            alert('<?php _e('Cache rensad!', 'lender-rate-history'); ?>');
        }
    });
});
</script>