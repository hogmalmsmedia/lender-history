<?php
/**
 * Admin menu handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Admin_Menu {
    
    /**
     * Register admin menu
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __('Rate History', 'lender-rate-history'),
            __('Rate History', 'lender-rate-history'),
            'manage_options',
            'lrh-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-line',
            30
        );
        
        // Dashboard (rename first submenu)
        add_submenu_page(
            'lrh-dashboard',
            __('Dashboard', 'lender-rate-history'),
            __('Dashboard', 'lender-rate-history'),
            'manage_options',
            'lrh-dashboard',
            [$this, 'render_dashboard_page']
        );
        
        // History
        add_submenu_page(
            'lrh-dashboard',
            __('Historik', 'lender-rate-history'),
            __('Historik', 'lender-rate-history'),
            'manage_options',
            'lrh-history',
            [$this, 'render_history_page']
        );
        
        // Settings
        add_submenu_page(
            'lrh-dashboard',
            __('Inställningar', 'lender-rate-history'),
            __('Inställningar', 'lender-rate-history'),
            'manage_options',
            'lrh-settings',
            [$this, 'render_settings_page']
        );
        
        // Import/Export
        add_submenu_page(
            'lrh-dashboard',
            __('Import/Export', 'lender-rate-history'),
            __('Import/Export', 'lender-rate-history'),
            'manage_options',
            'lrh-import-export',
            [$this, 'render_import_export_page']
        );
		
		 // External Sources
		add_submenu_page(
			'lrh-dashboard',
			__('Externa Källor', 'lender-rate-history'),
			__('Externa Källor', 'lender-rate-history'),
			'manage_options',
			'lrh-external-sources',
			[$this, 'render_external_sources_page']
		);
    }
	
	
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        require_once LRH_PLUGIN_DIR . 'admin/views/admin-dashboard.php';
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        require_once LRH_PLUGIN_DIR . 'admin/views/admin-history.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        require_once LRH_PLUGIN_DIR . 'admin/views/admin-settings.php';
    }
	
	
	/* 
	 * Render External history page 
	 */
	public function render_external_sources_page() {
    require_once LRH_PLUGIN_DIR . 'admin/views/admin-external-sources.php';
	}
    
    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        // Handle manual initialization if submitted
        if (isset($_POST['manual_initialize']) && wp_verify_nonce($_POST['_wpnonce'], 'lrh_manual_initialize')) {
            $import = new LRH_Import();
            $initialized = $import->initialize_all_history();
            
            if ($initialized > 0) {
                echo '<div class="notice notice-success"><p>' . 
                     sprintf(__('%d långivare initierade med historik.', 'lender-rate-history'), $initialized) . 
                     '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . 
                     __('Ingen historik kunde initieras. Kontrollera att du har långivare med ACF-värden.', 'lender-rate-history') . 
                     '</p></div>';
            }
        }
        
        // Handle clear all history
        if (isset($_POST['clear_all_history']) && 
            wp_verify_nonce($_POST['_wpnonce'], 'lrh_clear_all_history') &&
            isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'DELETE') {
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'lender_rate_history';
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>' . 
                     __('All historik har rensats från databasen.', 'lender-rate-history') . 
                     '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . 
                     __('Ett fel uppstod vid rensning av historik.', 'lender-rate-history') . 
                     '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Import/Export', 'lender-rate-history'); ?></h1>
            
            <div class="lrh-admin-container">
                <!-- Import Section -->
                <div class="lrh-admin-box">
                    <h2><?php _e('Importera Data', 'lender-rate-history'); ?></h2>
                    
                    <form method="post" enctype="multipart/form-data" id="lrh-import-form">
                        <?php wp_nonce_field('lrh_import_nonce', '_wpnonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php _e('Välj fil', 'lender-rate-history'); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="import_file" id="import_file" accept=".csv,.json" required>
                                    <p class="description"><?php _e('Accepterade format: CSV eller JSON', 'lender-rate-history'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="import_mode"><?php _e('Import-läge', 'lender-rate-history'); ?></label>
                                </th>
                                <td>
                                    <select name="import_mode" id="import_mode">
                                        <option value="add"><?php _e('Lägg till (behåll befintlig data)', 'lender-rate-history'); ?></option>
                                        <option value="replace"><?php _e('Ersätt all data', 'lender-rate-history'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Importera', 'lender-rate-history'); ?></button>
                        </p>
                    </form>
                </div>
                
                <!-- Export Section -->
                <div class="lrh-admin-box">
                    <h2><?php _e('Exportera Data', 'lender-rate-history'); ?></h2>
                    
                    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="lrh-export-form">
                        <?php wp_nonce_field('lrh_export_nonce', '_wpnonce'); ?>
                        <input type="hidden" name="action" value="lrh_export_data">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="export_format"><?php _e('Format', 'lender-rate-history'); ?></label>
                                </th>
                                <td>
                                    <select name="format" id="export_format">
                                        <option value="csv">CSV</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="export_category"><?php _e('Kategori', 'lender-rate-history'); ?></label>
                                </th>
                                <td>
                                    <select name="category" id="export_category">
                                        <option value=""><?php _e('Alla kategorier', 'lender-rate-history'); ?></option>
                                        <option value="mortgage"><?php _e('Bolån', 'lender-rate-history'); ?></option>
                                        <option value="personal_loan"><?php _e('Privatlån', 'lender-rate-history'); ?></option>
                                        <option value="car_loan"><?php _e('Billån', 'lender-rate-history'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="export_days"><?php _e('Period', 'lender-rate-history'); ?></label>
                                </th>
                                <td>
                                    <select name="days" id="export_days">
                                        <option value=""><?php _e('All historik', 'lender-rate-history'); ?></option>
                                        <option value="7"><?php _e('Senaste 7 dagarna', 'lender-rate-history'); ?></option>
                                        <option value="30"><?php _e('Senaste 30 dagarna', 'lender-rate-history'); ?></option>
                                        <option value="90"><?php _e('Senaste 90 dagarna', 'lender-rate-history'); ?></option>
                                        <option value="365"><?php _e('Senaste året', 'lender-rate-history'); ?></option>
                                        <option value="730"><?php _e('Senaste 2 åren', 'lender-rate-history'); ?></option>
                                        <option value="1095"><?php _e('Senaste 3 åren', 'lender-rate-history'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Exportera', 'lender-rate-history'); ?></button>
                        </p>
                    </form>
                </div>
                
                <!-- Initialize History -->
                <div class="lrh-admin-box">
                    <h2><?php _e('Initiera Historik', 'lender-rate-history'); ?></h2>
                    <p><?php _e('Importera alla nuvarande ACF-värden som initial historik för alla långivare.', 'lender-rate-history'); ?></p>
                    
                    <!-- AJAX Version -->
                    <form method="post" id="lrh-initialize-form">
                        <?php wp_nonce_field('lrh_initialize_nonce', '_wpnonce'); ?>
                        
                        <p class="submit">
                            <button type="submit" class="button button-secondary" id="lrh-initialize-btn">
                                <?php _e('Initiera Historik (AJAX)', 'lender-rate-history'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <!-- Manual Fallback Version -->
                    <form method="post" action="">
                        <?php wp_nonce_field('lrh_manual_initialize'); ?>
                        <input type="hidden" name="manual_initialize" value="1">
                        
                        <p class="submit">
                            <button type="submit" class="button button-secondary">
                                <?php _e('Initiera Historik (Manuell)', 'lender-rate-history'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <div id="lrh-initialize-progress" style="display:none;">
                        <div class="progress-bar" style="width: 100%; background: #f0f0f0; height: 20px; border-radius: 10px; margin: 20px 0;">
                            <div class="progress-bar-fill" style="width: 0%; background: #0073aa; height: 100%; border-radius: 10px; transition: width 0.3s;"></div>
                        </div>
                        <p class="progress-text"></p>
                    </div>
                    
                    <div id="lrh-initialize-result" style="display:none; margin-top: 20px;">
                        <div class="notice notice-success">
                            <p class="result-message"></p>
                        </div>
                    </div>
                    
                    <!-- Debug Info -->
                    <div style="margin-top: 20px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">
                        <h4><?php _e('Debug Information', 'lender-rate-history'); ?></h4>
                        <?php
                        $lender_count = wp_count_posts('langivare')->publish;
                        $settings = get_option('lrh_settings', []);
                        $tracked_fields = get_option('lrh_tracked_fields', []);
                        
                        echo '<p>Antal långivare: ' . $lender_count . '</p>';
                        echo '<p>Spårning aktiverad: ' . (isset($settings['enabled']) && $settings['enabled'] ? 'Ja' : 'Nej') . '</p>';
                        echo '<p>Spårade fält för bolån: ' . (isset($tracked_fields['mortgage']['rates']) ? count($tracked_fields['mortgage']['rates']) : 0) . '</p>';
                        
                        // Test getting one lender
                        $test_lenders = get_posts([
                            'post_type' => 'langivare',
                            'posts_per_page' => 1,
                            'tax_query' => [
                                [
                                    'taxonomy' => 'mall',
                                    'field' => 'slug',
                                    'terms' => 'bolan'
                                ]
                            ]
                        ]);
                        
                        if (!empty($test_lenders)) {
                            $test_lender = $test_lenders[0];
                            echo '<p>Test långivare: ' . $test_lender->post_title . ' (ID: ' . $test_lender->ID . ')</p>';
                            
                            // Test getting a field value
                            $test_value = get_field('snitt_3_man', $test_lender->ID);
                            echo '<p>Test värde (snitt_3_man): ' . ($test_value !== false ? $test_value : 'Inget värde') . '</p>';
                        } else {
                            echo '<p style="color: red;">Inga långivare med term "bolan" hittades!</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
		 <script>
        jQuery(document).ready(function($) {
            console.log('Import/Export page loaded');
            
            // Skapa lrh_ajax om det inte finns
            if (typeof lrh_ajax === 'undefined') {
                window.lrh_ajax = {
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    nonce: '<?php echo wp_create_nonce("lrh_admin_nonce"); ?>',
                    strings: {
                        loading: 'Laddar...',
                        error: 'Ett fel uppstod'
                    }
                };
                console.log('Created lrh_ajax object');
            }
            
            // Import handler
            $('#lrh-import-form').on('submit', function(e) {
                e.preventDefault();
                console.log('Import form submitted');
                
                var fileInput = this.querySelector('input[name="import_file"]');
                if (!fileInput.files.length) {
                    alert('Välj en fil att importera');
                    return false;
                }
                
                var formData = new FormData(this);
                formData.append('action', 'lrh_import_data');
                formData.append('nonce', lrh_ajax.nonce);
                
                var $submitBtn = $(this).find('button[type="submit"]');
                var originalText = $submitBtn.text();
                $submitBtn.prop('disabled', true).text('Importerar...');
                
				$.ajax({
					url: lrh_ajax.url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						console.log('Import response:', response);
						console.log('Response type:', typeof response);
						console.log('Response data:', response.data);

						if (response.success) {
							if (response.data && response.data.imported !== undefined) {
								alert('Import klar! ' + response.data.imported + ' poster importerade.');
							} else {
								alert(response.data || 'Import klar!');
							}
							location.reload();
						} else {
							alert('Fel: ' + (response.data || 'Okänt fel'));
						}
					},
					error: function(xhr, status, error) {
						console.error('Import AJAX error:', error);
						console.error('Response text:', xhr.responseText);
						console.error('Status:', status);
						alert('Import misslyckades: ' + error);
					},
					complete: function() {
						$submitBtn.prop('disabled', false).text(originalText);
					}
				});
                
                return false;
            });
            
            // Export handler (om den också behöver fixas)
            $('#lrh-export-form').on('submit', function(e) {
                // Export fungerar redan, men vi kan lägga till debug
                console.log('Export form submitted');
            });
        });
        </script>
        <?php
    }
	
	
}