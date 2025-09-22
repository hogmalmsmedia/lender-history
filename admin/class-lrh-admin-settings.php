<?php
/**
 * Settings handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Admin_Settings {
    
    /**
     * Initialize settings
     */
    public function init() {
        register_setting('lrh_settings_group', 'lrh_settings');
        register_setting('lrh_settings_group', 'lrh_tracked_fields');
        register_setting('lrh_settings_group', 'lrh_taxonomy_mapping');
        
        $this->add_settings_sections();
        $this->add_settings_fields();
    }
    
    /**
     * Add settings sections
     */
    private function add_settings_sections() {
        add_settings_section(
            'lrh_general_section',
            __('Allmänna inställningar', 'lender-rate-history'),
            [$this, 'render_general_section'],
            'lrh_settings'
        );
        
        add_settings_section(
            'lrh_tracking_section',
            __('Spårningsinställningar', 'lender-rate-history'),
            [$this, 'render_tracking_section'],
            'lrh_settings'
        );
        
        add_settings_section(
            'lrh_fields_section',
            __('Fält att spåra', 'lender-rate-history'),
            [$this, 'render_fields_section'],
            'lrh_settings'
        );
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings
        add_settings_field(
            'lrh_enabled',
            __('Aktivera spårning', 'lender-rate-history'),
            [$this, 'render_checkbox_field'],
            'lrh_settings',
            'lrh_general_section',
            ['name' => 'enabled', 'label' => __('Aktivera historikspårning', 'lender-rate-history')]
        );
        
        add_settings_field(
            'lrh_retention_days',
            __('Datalagring', 'lender-rate-history'),
            [$this, 'render_number_field'],
            'lrh_settings',
            'lrh_general_section',
            ['name' => 'retention_days', 'label' => __('Behåll historik (dagar)', 'lender-rate-history'), 'default' => 365]
        );
        
        // Tracking settings
        add_settings_field(
            'lrh_track_manual',
            __('Manuella ändringar', 'lender-rate-history'),
            [$this, 'render_checkbox_field'],
            'lrh_settings',
            'lrh_tracking_section',
            ['name' => 'track_manual_changes', 'label' => __('Spåra manuella ändringar', 'lender-rate-history')]
        );
        
        add_settings_field(
            'lrh_track_import',
            __('Import-ändringar', 'lender-rate-history'),
            [$this, 'render_checkbox_field'],
            'lrh_settings',
            'lrh_tracking_section',
            ['name' => 'track_import_changes', 'label' => __('Spåra WP All Import ändringar', 'lender-rate-history')]
        );
        
        add_settings_field(
            'lrh_large_change_threshold',
            __('Stora ändringar', 'lender-rate-history'),
            [$this, 'render_number_field'],
            'lrh_settings',
            'lrh_tracking_section',
            ['name' => 'large_change_threshold', 'label' => __('Flagga ändringar över (%)', 'lender-rate-history'), 'default' => 25]
        );
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . __('Konfigurera allmänna inställningar för Rate History.', 'lender-rate-history') . '</p>';
    }
    
    /**
     * Render tracking section
     */
    public function render_tracking_section() {
        echo '<p>' . __('Välj vilka typer av ändringar som ska spåras.', 'lender-rate-history') . '</p>';
    }
    
    /**
     * Render fields section
     */
    public function render_fields_section() {
        echo '<p>' . __('Konfigurera vilka ACF-fält som ska spåras för varje lånetyp.', 'lender-rate-history') . '</p>';
        $this->render_tracked_fields_editor();
    }
    
    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args) {
        $settings = get_option('lrh_settings', []);
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : false;
        ?>
        <label>
            <input type="checkbox" 
                   name="lrh_settings[<?php echo esc_attr($args['name']); ?>]" 
                   value="1" 
                   <?php checked($value, 1); ?>>
            <?php echo esc_html($args['label']); ?>
        </label>
        <?php
    }
    
    /**
     * Render number field
     */
    public function render_number_field($args) {
        $settings = get_option('lrh_settings', []);
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <input type="number" 
               name="lrh_settings[<?php echo esc_attr($args['name']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text">
        <span class="description"><?php echo esc_html($args['label']); ?></span>
        <?php
    }
    
    /**
     * Render tracked fields editor
     */
    private function render_tracked_fields_editor() {
        $tracked_fields = get_option('lrh_tracked_fields', []);
        ?>
        <div id="lrh-tracked-fields-editor">
            <?php foreach ($tracked_fields as $loan_type => $categories): ?>
            <div class="lrh-field-group">
                <h3><?php echo $this->get_loan_type_label($loan_type); ?></h3>
                
                <?php foreach ($categories as $category => $fields): ?>
                <div class="lrh-field-category">
                    <h4><?php echo $this->get_category_label($category); ?></h4>
                    <textarea 
                        name="lrh_tracked_fields[<?php echo esc_attr($loan_type); ?>][<?php echo esc_attr($category); ?>]" 
                        rows="3" 
                        cols="50"
                        placeholder="<?php _e('Ett fältnamn per rad', 'lender-rate-history'); ?>"><?php 
                        echo esc_textarea(implode("\n", $fields)); 
                    ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Parse textarea values on save
            $('#lrh-settings-form').on('submit', function() {
                $('#lrh-tracked-fields-editor textarea').each(function() {
                    var lines = $(this).val().split('\n').filter(function(line) {
                        return line.trim() !== '';
                    });
                    $(this).val(lines.join('\n'));
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get loan type label
     */
    private function get_loan_type_label($type) {
        $labels = [
            'mortgage' => __('Bolån', 'lender-rate-history'),
            'personal_loan' => __('Privatlån', 'lender-rate-history'),
            'car_loan' => __('Billån', 'lender-rate-history')
        ];
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
    
    /**
     * Get category label
     */
    private function get_category_label($category) {
        $labels = [
            'rates' => __('Räntor', 'lender-rate-history'),
            'fees' => __('Avgifter', 'lender-rate-history'),
            'requirements' => __('Krav', 'lender-rate-history'),
            'limits' => __('Gränser', 'lender-rate-history')
        ];
        
        return isset($labels[$category]) ? $labels[$category] : $category;
    }
}