<?php
/**
 * Settings page view
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings if form submitted
if (isset($_POST['submit']) && isset($_POST['_wpnonce'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'lrh_settings_nonce')) {
        // Process tracked fields
        if (isset($_POST['lrh_tracked_fields'])) {
            $tracked_fields = $_POST['lrh_tracked_fields'];
            
            // Convert textarea values to arrays
            foreach ($tracked_fields as $loan_type => &$categories) {
                foreach ($categories as $category => &$fields) {
                    if (is_string($fields)) {
                        $fields = array_filter(array_map('trim', explode("\n", $fields)));
                    }
                }
            }
            
            update_option('lrh_tracked_fields', $tracked_fields);
        }
        
        // Save other settings
        if (isset($_POST['lrh_settings'])) {
            $settings = $_POST['lrh_settings'];

            // Sanitize settings
            $settings['enabled'] = 1; // Alltid aktiverad
            $settings['track_manual_changes'] = isset($settings['track_manual_changes']) ? 1 : 0;
            $settings['track_import_changes'] = isset($settings['track_import_changes']) ? 1 : 0;
            $settings['enable_validation'] = isset($settings['enable_validation']) ? 1 : 0;
            $settings['enable_notifications'] = isset($settings['enable_notifications']) ? 1 : 0;
            // Ta bort retention_days - obegränsad lagring
            $settings['large_change_threshold'] = intval($settings['large_change_threshold']);
            $settings['notification_email'] = sanitize_email($settings['notification_email']);
            
            update_option('lrh_settings', $settings);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Inställningar sparade!', 'lender-rate-history') . '</p></div>';
    }
}

$settings = get_option('lrh_settings', []);
$tracked_fields = get_option('lrh_tracked_fields', []);
?>

<div class="wrap">
    <h1><?php _e('Rate History Inställningar', 'lender-rate-history'); ?></h1>
    
    <form method="post" action="" id="lrh-settings-form">
        <?php wp_nonce_field('lrh_settings_nonce'); ?>
        
        <!-- General Settings -->
        <h2><?php _e('Allmänna inställningar', 'lender-rate-history'); ?></h2>
        <p class="description"><?php _e('Historikspårning är alltid aktiverad för alla långivare.', 'lender-rate-history'); ?></p>
        
        <!-- Tracking Settings -->
        <h2><?php _e('Spårningsinställningar', 'lender-rate-history'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Spårningslägen', 'lender-rate-history'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="lrh_settings[track_manual_changes]" value="1" 
                                   <?php checked(!empty($settings['track_manual_changes'])); ?>>
                            <?php _e('Spåra manuella ändringar', 'lender-rate-history'); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="lrh_settings[track_import_changes]" value="1" 
                                   <?php checked(!empty($settings['track_import_changes'])); ?>>
                            <?php _e('Spåra WP All Import ändringar', 'lender-rate-history'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Validering', 'lender-rate-history'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="lrh_settings[enable_validation]" value="1" 
                               <?php checked(!empty($settings['enable_validation'])); ?>>
                        <?php _e('Aktivera validering av stora ändringar', 'lender-rate-history'); ?>
                    </label>
                    
                    <br><br>
                    
                    <label>
                        <?php _e('Flagga ändringar över', 'lender-rate-history'); ?>
                        <input type="number" name="lrh_settings[large_change_threshold]" 
                               value="<?php echo isset($settings['large_change_threshold']) ? esc_attr($settings['large_change_threshold']) : 25; ?>" 
                               min="5" max="100" class="small-text">
                        %
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Notifikationer', 'lender-rate-history'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="lrh_settings[enable_notifications]" value="1" 
                               <?php checked(!empty($settings['enable_notifications'])); ?>>
                        <?php _e('Skicka e-postnotifikationer', 'lender-rate-history'); ?>
                    </label>
                    
                    <br><br>
                    
                    <label>
                        <?php _e('E-postadress:', 'lender-rate-history'); ?>
                        <input type="email" name="lrh_settings[notification_email]" 
                               value="<?php echo isset($settings['notification_email']) ? esc_attr($settings['notification_email']) : get_option('admin_email'); ?>" 
                               class="regular-text">
                    </label>
                </td>
            </tr>
        </table>
        
        <!-- Field Configuration -->
        <h2><?php _e('Fält att spåra', 'lender-rate-history'); ?></h2>
        <p class="description"><?php _e('Konfigurera vilka ACF-fält som ska spåras för varje lånetyp. Ange ett fältnamn per rad.', 'lender-rate-history'); ?></p>
        
        <div id="lrh-tracked-fields-editor">
            <?php
            $loan_types = [
                'mortgage' => __('Bolån', 'lender-rate-history'),
                'personal_loan' => __('Privatlån', 'lender-rate-history'),
                'car_loan' => __('Billån', 'lender-rate-history')
            ];
            
            $categories = [
                'rates' => __('Räntor', 'lender-rate-history'),
                'fees' => __('Avgifter', 'lender-rate-history'),
                'requirements' => __('Krav', 'lender-rate-history'),
                'limits' => __('Gränser', 'lender-rate-history')
            ];
            
            foreach ($loan_types as $loan_type => $loan_label):
            ?>
            <div class="lrh-field-group">
                <h3><?php echo esc_html($loan_label); ?></h3>
                
                <?php foreach ($categories as $category => $category_label): 
                    $fields = isset($tracked_fields[$loan_type][$category]) ? $tracked_fields[$loan_type][$category] : [];
                ?>
                <div class="lrh-field-category">
                    <h4><?php echo esc_html($category_label); ?></h4>
                    <textarea 
                        name="lrh_tracked_fields[<?php echo esc_attr($loan_type); ?>][<?php echo esc_attr($category); ?>]" 
                        rows="4" 
                        cols="50"
                        placeholder="<?php _e('Ett fältnamn per rad, t.ex:', 'lender-rate-history'); ?>&#10;snitt_3_man&#10;snitt_1_ar&#10;snitt_2_ar"><?php 
                        echo esc_textarea(is_array($fields) ? implode("\n", $fields) : $fields); 
                    ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Taxonomy Mapping -->
        <h2><?php _e('Taxonomi-mappning', 'lender-rate-history'); ?></h2>
        <p class="description"><?php _e('Koppla dina taxonomy-termer till lånetyper', 'lender-rate-history'); ?></p>
        
        <?php
        $taxonomy_mapping = get_option('lrh_taxonomy_mapping', [
            'bolan' => 'mortgage',
            'privatlan' => 'personal_loan',
            'billan' => 'car_loan'
        ]);
        ?>
        
        <table class="form-table">
            <tr>
                <th><?php _e('Term (slug)', 'lender-rate-history'); ?></th>
                <th><?php _e('Lånetyp', 'lender-rate-history'); ?></th>
            </tr>
            <?php foreach ($taxonomy_mapping as $term => $type): ?>
            <tr>
                <td><code><?php echo esc_html($term); ?></code></td>
                <td><?php echo esc_html($loan_types[$type] ?? $type); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="<?php _e('Spara inställningar', 'lender-rate-history'); ?>">
        </p>
    </form>
</div>

<style>
.lrh-field-group {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.lrh-field-group h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.lrh-field-category {
    margin: 15px 0;
}

.lrh-field-category h4 {
    margin: 10px 0 5px 0;
    font-size: 13px;
    font-weight: 600;
}

.lrh-field-category textarea {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
</style>