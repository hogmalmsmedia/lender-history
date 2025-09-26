<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Admin {
    
    private $menu;
    private $settings;
    private $database;
    
    public function __construct() {
        $this->database = new LRH_Database();
        
        // Load admin components
        $this->menu = new LRH_Admin_Menu();
        $this->settings = new LRH_Admin_Settings();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $this->menu->register_menu();
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        $this->settings->init();
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (!$this->is_plugin_page($hook)) {
            return;
        }
        
        wp_enqueue_style(
            'lrh-admin',
            LRH_PLUGIN_URL . 'admin/css/lrh-admin.css',
            [],
            LRH_VERSION
        );
    }
    
    /**
     * Enqueue admin scripts
     */
public function enqueue_scripts($hook) {
    if (!$this->is_plugin_page($hook)) {
        return;
    }
    
    wp_enqueue_script(
        'lrh-admin',
        LRH_PLUGIN_URL . 'admin/js/lrh-admin.js',
        ['jquery', 'chart-js'],
        LRH_VERSION,
        true
    );
    
    // Chart.js
    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
        [],
        '3.9.1'
    );
    
    // VIKTIGT: Localize måste komma EFTER wp_enqueue_script
    wp_localize_script('lrh-admin', 'lrh_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lrh_admin_nonce'),
        'strings' => [
            'confirm_delete' => __('Är du säker på att du vill ta bort denna post?', 'lender-rate-history'),
            'confirm_validate' => __('Markera denna ändring som validerad?', 'lender-rate-history'),
            'loading' => __('Laddar...', 'lender-rate-history'),
            'error' => __('Ett fel uppstod. Försök igen.', 'lender-rate-history'),
        ]
    ]);
}
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check for unvalidated changes
        $unvalidated = $this->database->get_unvalidated_changes(1);
        
        if (!empty($unvalidated)) {
            $count = count($this->database->get_unvalidated_changes(100));
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php 
                    printf(
                        __('Det finns %d ränteändringar som behöver valideras. <a href="%s">Granska nu</a>', 'lender-rate-history'),
                        $count,
                        admin_url('admin.php?page=lrh-history&filter=unvalidated')
                    );
                    ?>
                </p>
            </div>
            <?php
        }
        
        // Removed the initial import notice completely
    }
    
    /**
     * AJAX: Validate change
     */
    public function ajax_validate_change() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }
        
        $change_id = isset($_POST['change_id']) ? intval($_POST['change_id']) : 0;
        
        if (!$change_id) {
            wp_send_json_error(__('Ogiltig ändrings-ID.', 'lender-rate-history'));
        }
        
        $result = $this->database->update_change($change_id, [
            'is_validated' => 1,
            'validation_notes' => sprintf(__('Validerad av %s', 'lender-rate-history'), wp_get_current_user()->display_name)
        ]);
        
        if ($result) {
            wp_send_json_success(__('Ändringen har validerats.', 'lender-rate-history'));
        } else {
            wp_send_json_error(__('Kunde inte validera ändringen.', 'lender-rate-history'));
        }
    }
    
    /**
     * AJAX: Delete change
     */
    public function ajax_delete_change() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }
        
        $change_id = isset($_POST['change_id']) ? intval($_POST['change_id']) : 0;
        
        if (!$change_id) {
            wp_send_json_error(__('Ogiltig ändrings-ID.', 'lender-rate-history'));
        }
        
        $result = $this->database->delete_change($change_id);
        
        if ($result) {
            wp_send_json_success(__('Ändringen har tagits bort.', 'lender-rate-history'));
        } else {
            wp_send_json_error(__('Kunde inte ta bort ändringen.', 'lender-rate-history'));
        }
    }
    
    /**
     * AJAX: Get chart data
     */
    public function ajax_get_chart_data() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        if (!$post_id || !$field_name) {
            wp_send_json_error(__('Ogiltiga parametrar.', 'lender-rate-history'));
        }
        
        $api = new LRH_API();
        $data = $api->get_field_history($post_id, $field_name, $days);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        // Check nonce from POST
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
		if (!$nonce || !wp_verify_nonce($nonce, 'lrh_export_nonce')) {
            wp_die(__('Säkerhetsfel', 'lender-rate-history'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }
        
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : null;
        $days = isset($_POST['days']) ? intval($_POST['days']) : null;
        
        $changes = $this->database->get_recent_changes([
            'limit' => 100000,
            'field_category' => $category,
            'days' => $days
        ]);
        
        if ($format === 'csv') {
            $this->export_csv($changes);
        } else {
            $this->export_json($changes);
        }
    }
    
    /**
     * AJAX: Import data
     */
	public function ajax_import_data() {
		// Fixa nonce-verifiering
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lrh_admin_nonce')) {
			wp_send_json_error(__('Säkerhetsfel.', 'lender-rate-history'));
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
		}

		// Hantera fil-upload
		if (!isset($_FILES['import_file'])) {
			wp_send_json_error(__('Ingen fil uppladdad.', 'lender-rate-history'));
		}

		$file = $_FILES['import_file'];

		// Validera filtyp
		$allowed_types = ['text/csv', 'application/json', 'text/plain'];
		if (!in_array($file['type'], $allowed_types)) {
			wp_send_json_error(__('Ogiltig filtyp. Endast CSV och JSON tillåts.', 'lender-rate-history'));
		}

		// Läs filinnehåll
		$content = file_get_contents($file['tmp_name']);
		if (!$content) {
			wp_send_json_error(__('Kunde inte läsa filen.', 'lender-rate-history'));
		}

		// Import mode
		$mode = isset($_POST['import_mode']) ? sanitize_text_field($_POST['import_mode']) : 'add';

		// Hantera import baserat på filtyp
		$import = new LRH_Import();

		if (strpos($file['name'], '.json') !== false || $file['type'] === 'application/json') {
			// JSON import
			$data = json_decode($content, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				wp_send_json_error(__('Ogiltig JSON-fil.', 'lender-rate-history'));
			}

			// Clear if replace mode
			if ($mode === 'replace') {
				global $wpdb;
				$table_name = $wpdb->prefix . LRH_TABLE_NAME;
				$wpdb->query("TRUNCATE TABLE $table_name");
			}

			$imported = 0;
			foreach ($data as $record) {
				if ($this->database->insert_change($record)) {
					$imported++;
				}
			}

			wp_send_json_success(sprintf(__('%d poster importerade.', 'lender-rate-history'), $imported));

		} else {
			// CSV import
			$imported = $import->import_csv($file['tmp_name'], $mode);

			if (is_wp_error($imported)) {
				wp_send_json_error($imported->get_error_message());
			}

			wp_send_json_success(sprintf(__('%d poster importerade.', 'lender-rate-history'), $imported));
		}
	}
    
    /**
     * AJAX: Initialize history
     */
    public function ajax_initialize_history() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }
        
        $import = new LRH_Import();
        $initialized = $import->initialize_all_history();
        
        if ($initialized > 0) {
            wp_send_json_success(sprintf(__('%d långivare initierade med historik.', 'lender-rate-history'), $initialized));
        } else {
            wp_send_json_error(__('Ingen historik kunde initieras. Kontrollera att du har långivare med ACF-värden.', 'lender-rate-history'));
        }
    }
    
    /**
     * AJAX: Validate all changes
     */
    public function ajax_validate_all_changes() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'lender_rate_history';

        // Update all unvalidated changes
        $result = $wpdb->update(
            $table_name,
            [
                'is_validated' => 1,
                'validation_notes' => sprintf(__('Bulk-validerad av %s', 'lender-rate-history'), wp_get_current_user()->display_name)
            ],
            ['is_validated' => 0],
            ['%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $message = sprintf(__('%d ändringar har validerats.', 'lender-rate-history'), $result);
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Kunde inte validera ändringarna.', 'lender-rate-history'));
        }
    }

    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('lrh_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'lender-rate-history'));
        }

        wp_cache_flush();

        wp_send_json_success(__('Cache rensad!', 'lender-rate-history'));
    }
    
    /**
     * Check if current page is plugin page
     */
    private function is_plugin_page($hook) {
        $plugin_pages = [
            'toplevel_page_lrh-dashboard',
            'lender-rate-history_page_lrh-history',
            'lender-rate-history_page_lrh-settings',
            'lender-rate-history_page_lrh-import-export'
        ];
        
        return in_array($hook, $plugin_pages);
    }
    
    /**
     * Export data as CSV
     */
    private function export_csv($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="lrh-export-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Post ID',
            'Bank',
            'Field',
            'Category',
            'Old Value',
            'New Value',
            'Change %',
            'Date',
            'Source',
            'Validated'
        ]);
        
        // Data
        foreach ($data as $row) {
            $post = get_post($row->post_id);
            fputcsv($output, [
                $row->post_id,
                $post ? $post->post_title : 'Unknown',
                $row->field_name,
                $row->field_category,
                $row->old_value,
                $row->new_value,
                $row->change_percentage,
                $row->change_date,
                $row->import_source,
                $row->is_validated ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data as JSON
     */
    private function export_json($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="lrh-export-' . date('Y-m-d') . '.json"');
        
        $export = [];
        
        foreach ($data as $row) {
            $post = get_post($row->post_id);
            $export[] = [
                'post_id' => $row->post_id,
                'bank' => $post ? $post->post_title : 'Unknown',
                'field_name' => $row->field_name,
                'field_category' => $row->field_category,
                'old_value' => $row->old_value,
                'new_value' => $row->new_value,
                'change_percentage' => $row->change_percentage,
                'change_date' => $row->change_date,
                'import_source' => $row->import_source,
                'is_validated' => $row->is_validated
            ];
        }
        
        echo json_encode($export, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Parse CSV content
     */
    private function parse_csv($content) {
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));
        $data = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $row = str_getcsv($line);
            $record = [];
            
            foreach ($headers as $i => $header) {
                $record[strtolower(str_replace(' ', '_', $header))] = $row[$i];
            }
            
            $data[] = $record;
        }
        
        return $data;
    }
}