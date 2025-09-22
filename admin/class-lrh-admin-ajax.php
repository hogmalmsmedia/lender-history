<?php
/**
 * Admin AJAX handlers - FÖRBÄTTRAD VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Admin_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_lrh_inline_edit', [$this, 'handle_inline_edit']);
        add_action('wp_ajax_lrh_delete_change', [$this, 'handle_delete_change']);
        add_action('wp_ajax_lrh_get_option_pages', [$this, 'handle_get_option_pages']);
		add_action('wp_ajax_lrh_get_option_fields', [$this, 'handle_get_option_fields']);
    }
    
    /**
     * Handle inline edit - FÖRBÄTTRAD VERSION
     */
    public function handle_inline_edit() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;
        
        $id = intval($_POST['id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        
        // Hämta befintlig post
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $id
        ));
        
        if (!$existing) {
            wp_send_json_error('Post hittades inte');
        }
        
        $update_data = [];
        
        // Normalisera värde-funktion
        $normalize_value = function($val) {
            if ($val === '' || $val === null || $val === '-') {
                return null;
            }
            // Hantera komma som decimal
            $val = str_replace(',', '.', $val);
            $val = str_replace(' ', '', $val);
            $val = str_replace('%', '', $val);
            return floatval($val);
        };
        
        switch($field) {
            case 'old_value':
                $old_value = $normalize_value($value);
                $new_value = floatval($existing->new_value);
                
                // VIKTIGT: Räkna alltid om förändring
                if ($old_value !== null) {
                    $change_amount = $new_value - $old_value;
                    $change_percentage = $change_amount; // Procentenheter
                    $change_type = 'update';
                } else {
                    $change_amount = null;
                    $change_percentage = null;
                    $change_type = 'initial';
                }
                
                $update_data = [
                    'old_value' => $old_value,
                    'change_amount' => $change_amount,
                    'change_percentage' => $change_percentage,
                    'change_type' => $change_type
                ];
                break;
                
            case 'new_value':
                $new_value = $normalize_value($value);
                $old_value = $existing->old_value !== null ? floatval($existing->old_value) : null;
                
                // VIKTIGT: Räkna alltid om förändring
                if ($old_value !== null) {
                    $change_amount = $new_value - $old_value;
                    $change_percentage = $change_amount; // Procentenheter
                    $change_type = 'update';
                } else {
                    $change_amount = null;
                    $change_percentage = null;
                    $change_type = 'initial';
                }
                
                $update_data = [
                    'new_value' => $new_value,
                    'change_amount' => $change_amount,
                    'change_percentage' => $change_percentage,
                    'change_type' => $change_type
                ];
                break;
                
            case 'change_date':
                // Säkerställ korrekt datumformat
                $date = sanitize_text_field($value);
                if (strpos($date, ':') === false) {
                    $date .= ' 00:00:00';
                }
                $update_data = ['change_date' => $date];
                break;
                
            case 'import_source':
                $update_data = ['import_source' => sanitize_text_field($value)];
                break;
                
            default:
                wp_send_json_error('Ogiltigt fält');
                return;
        }
        
        // Utför uppdatering
        $result = $wpdb->update(
            $table_name, 
            $update_data, 
            ['id' => $id],
            $this->get_field_formats($update_data),
            ['%d']
        );
        
        if ($result !== false) {
            // Hämta uppdaterad rad för att returnera
            $updated = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ));
            
            wp_send_json_success([
                'message' => 'Uppdaterad',
                'data' => $updated
            ]);
        } else {
            wp_send_json_error('Kunde inte uppdatera: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Handle delete change
     */
    public function handle_delete_change() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $database = new LRH_Database();
        $result = $database->delete_change(intval($_POST['change_id']));
        
        if ($result) {
            wp_send_json_success('Post borttagen');
        } else {
            wp_send_json_error('Kunde inte ta bort post');
        }
    }
    
    /**
     * Get ACF option pages for dropdown - NY FUNKTION
     */
    public function handle_get_option_pages() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $option_pages = [];
        
        // Standard WordPress option
        $option_pages[] = [
            'value' => 'option',
            'label' => 'Standard Options (option)'
        ];
        
        // Hämta ACF option pages om ACF är aktivt
        if (function_exists('acf_get_options_pages')) {
            $acf_pages = acf_get_options_pages();
            
            if ($acf_pages) {
                foreach ($acf_pages as $page) {
                    $option_pages[] = [
                        'value' => $page['post_id'],
                        'label' => $page['page_title'] . ' (' . $page['post_id'] . ')'
                    ];
                }
            }
        }
        
        // Lägg till theme options om de finns
        $option_pages[] = [
            'value' => 'options',
            'label' => 'Theme Options (options)'
        ];
        
        wp_send_json_success($option_pages);
    }
    
    /**
     * Get field formats for database
     */
    private function get_field_formats($data) {
        $formats = [];
        
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'old_value':
                case 'new_value':
                case 'change_amount':
                case 'change_percentage':
                    $formats[] = $value === null ? '%s' : '%f';
                    break;
                    
                case 'id':
                case 'post_id':
                case 'is_validated':
                case 'user_id':
                    $formats[] = '%d';
                    break;
                    
                default:
                    $formats[] = '%s';
                    break;
            }
        }
        
        return $formats;
    }
	
	/**
	 * Get ACF fields for a specific option page
	 */
	public function handle_get_option_fields() {
		check_ajax_referer('lrh_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$option_page = sanitize_text_field($_POST['option_page']);
		$fields = [];

		// Hämta ACF field groups
		if (function_exists('acf_get_field_groups')) {
			$field_groups = acf_get_field_groups();

			foreach ($field_groups as $group) {
				// Kontrollera om gruppen är kopplad till denna option page
				$show_on_page = false;

				if (!empty($group['location'])) {
					foreach ($group['location'] as $location_group) {
						foreach ($location_group as $rule) {
							// Kontrollera olika location rules
							if ($rule['param'] === 'options_page' && $rule['value'] === $option_page) {
								$show_on_page = true;
								break 2;
							}
							// Kontrollera också för standard option pages
							if ($option_page === 'option' || $option_page === 'options') {
								if ($rule['param'] === 'options_page' && $rule['operator'] === '==') {
									$show_on_page = true;
									break 2;
								}
							}
						}
					}
				}

				if ($show_on_page) {
					// Hämta fält från denna grupp
					if (function_exists('acf_get_fields')) {
						$group_fields = acf_get_fields($group['key']);

						if ($group_fields) {
							foreach ($group_fields as $field) {
								// Lägg till fältnamn (field name, inte label)
								if (!empty($field['name'])) {
									$fields[] = $field['name'];

									// Om det är en grupp eller repeater, hämta sub-fields
									if ($field['type'] === 'group' && !empty($field['sub_fields'])) {
										foreach ($field['sub_fields'] as $sub_field) {
											if (!empty($sub_field['name'])) {
												$fields[] = $field['name'] . '_' . $sub_field['name'];
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		// Om inga ACF-fält hittades, försök hämta från options-tabellen
		if (empty($fields) && ($option_page === 'option' || $option_page === 'options')) {
			global $wpdb;

			// Hämta alla options som börjar med options_ eller option_
			$prefix = $option_page === 'options' ? 'options_' : 'option_';

			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} 
					 WHERE option_name LIKE %s 
					 AND option_name NOT LIKE %s
					 LIMIT 50",
					$prefix . '%',
					'%_transient%'
				)
			);

			foreach ($results as $option_name) {
				// Ta bort prefix för att få fältnamnet
				$field_name = str_replace($prefix, '', $option_name);
				// Filtrera bort systemfält
				if (!in_array($field_name, ['_', '__', '___']) && 
					!strpos($field_name, '_field_') &&
					!strpos($field_name, '_acf_')) {
					$fields[] = $field_name;
				}
			}
		}

		// Ta bort dubbletter och sortera
		$fields = array_unique($fields);
		sort($fields);

		wp_send_json_success($fields);
	}

}