<?php
/**
 * Import handler for WP All Import and manual imports
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Import {
    
    private $database;
    private $tracker;
    
    public function __construct() {
        $this->database = new LRH_Database();
        $this->tracker = new LRH_Tracker();
    }
    
    /**
     * Initialize all current values as historical data
     */
    public function initialize_all_history() {
        $lenders = $this->get_all_lenders();
        $initialized = 0;
        
        error_log('LRH: Found ' . count($lenders) . ' lenders to initialize');
        
        foreach ($lenders as $lender) {
            error_log('LRH: Processing lender: ' . $lender->post_title . ' (ID: ' . $lender->ID . ')');
            
            if ($this->tracker->initialize_post_history($lender->ID)) {
                $initialized++;
                error_log('LRH: Successfully initialized lender ID ' . $lender->ID);
            } else {
                error_log('LRH: Failed to initialize lender ID ' . $lender->ID);
            }
        }
        
        // Mark as initialized
        if ($initialized > 0) {
            update_option('lrh_needs_initial_import', false);
        }
        
        error_log('LRH: Total initialized: ' . $initialized);
        
        return $initialized;
    }
    
	/**
	 * Import data from CSV file with batch processing
	 */
	public function import_csv($file_path, $mode = 'add') {
		error_log('LRH Import - Starting CSV import from: ' . $file_path);

		if (!file_exists($file_path)) {
			error_log('LRH Import - File not found: ' . $file_path);
			return new WP_Error('file_not_found', __('Filen hittades inte', 'lender-rate-history'));
		}

		$handle = fopen($file_path, 'r');
		if (!$handle) {
			error_log('LRH Import - Cannot open file: ' . $file_path);
			return new WP_Error('file_read_error', __('Kunde inte läsa filen', 'lender-rate-history'));
		}

		// Get headers
		$headers = fgetcsv($handle);
		if (!$headers) {
			fclose($handle);
			error_log('LRH Import - No headers found in CSV');
			return new WP_Error('invalid_csv', __('Ogiltig CSV-fil - inga headers hittades', 'lender-rate-history'));
		}

		error_log('LRH Import - Original CSV headers: ' . print_r($headers, true));

		// Normalize headers - ta bort BOM, citattecken och whitespace
		$headers = array_map(function($header) {
			// Ta bort BOM om det finns
			$header = str_replace("\xEF\xBB\xBF", '', $header);
			// Ta bort citattecken
			$header = str_replace(['"', "'"], '', $header);
			// Trimma whitespace
			$header = trim($header);
			// Konvertera till lowercase och ersätt mellanslag med underscore
			$header = strtolower(str_replace([' ', '-'], '_', $header));
			return $header;
		}, $headers);

		error_log('LRH Import - Normalized headers: ' . print_r($headers, true));

		// Clear existing data if replace mode - ENDAST EN GÅNG
		if ($mode === 'replace') {
			$this->clear_all_history();
			error_log('LRH Import - Cleared all existing history (replace mode)');
		}

		$imported = 0;
		$errors = [];
		$row_number = 1; // Start från rad 1 (headers är rad 0)
		$batch = []; // Array för batch-insert
		$batch_size = 500; // Antal rader per batch

		// Process rows
		while (($row = fgetcsv($handle)) !== false) {
			$row_number++;

			// Hoppa över tomma rader
			if (empty(array_filter($row))) {
				continue;
			}

			if (count($row) !== count($headers)) {
				$errors[] = sprintf(__('Rad %d har fel antal kolumner (förväntade %d, fick %d)', 'lender-rate-history'),
					$row_number, count($headers), count($row));
				if (count($errors) <= 10) { // Begränsa loggning
					error_log('LRH Import - Row ' . $row_number . ' has wrong number of columns');
				}
				continue;
			}

			// Kombinera headers med värden
			$data = array_combine($headers, $row);

			if ($data === false) {
				$errors[] = sprintf(__('Rad %d kunde inte bearbetas', 'lender-rate-history'), $row_number);
				if (count($errors) <= 10) {
					error_log('LRH Import - Row ' . $row_number . ' could not be combined with headers');
				}
				continue;
			}

			// Map CSV fields to database fields
			$record = $this->map_csv_to_record($data);

			if ($record) {
				// Lägg till i batch istället för att insert direkt
				$batch[] = $record;

				// När batch är full, gör batch insert
				if (count($batch) >= $batch_size) {
					$batch_result = $this->database->batch_insert($batch);
					if ($batch_result) {
						$imported += count($batch);
						error_log('LRH Import - Batch inserted ' . count($batch) . ' records (total: ' . $imported . ')');
					} else {
						error_log('LRH Import - Failed to batch insert ' . count($batch) . ' records');
					}
					$batch = []; // Rensa batch
				}
			} else {
				$errors[] = sprintf(__('Rad %d saknar obligatoriska fält', 'lender-rate-history'), $row_number);
				if (count($errors) <= 10) {
					error_log('LRH Import - Row ' . $row_number . ' missing required fields');
				}
			}
		}

		// Importera eventuella kvarvarande poster i batch
		if (!empty($batch)) {
			$batch_result = $this->database->batch_insert($batch);
			if ($batch_result) {
				$imported += count($batch);
				error_log('LRH Import - Final batch inserted ' . count($batch) . ' records (total: ' . $imported . ')');
			}
		}

		fclose($handle);

		error_log('LRH Import - Import completed. Imported: ' . $imported . ', Errors: ' . count($errors));

		if (!empty($errors) && $imported === 0) {
			// Om inga rader importerades, returnera fel med bara de första 5 felen
			$error_message = implode(', ', array_slice($errors, 0, 5));
			if (count($errors) > 5) {
				$error_message .= sprintf(' ... och %d till fel', count($errors) - 5);
			}
			return new WP_Error('import_errors', $error_message, ['imported' => 0]);
		}

		// Returnera antal importerade även om det fanns några fel
		return $imported;
	}
    
    /**
     * Import data from JSON file
     */
    public function import_json($file_path, $mode = 'add') {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Filen hittades inte', 'lender-rate-history'));
        }
        
        $content = file_get_contents($file_path);
        if (!$content) {
            return new WP_Error('file_read_error', __('Kunde inte läsa filen', 'lender-rate-history'));
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Ogiltig JSON-fil', 'lender-rate-history'));
        }
        
        // Clear existing data if replace mode
        if ($mode === 'replace') {
            $this->clear_all_history();
        }
        
        $imported = 0;
        
        foreach ($data as $record) {
            // Map JSON fields to database fields
            $mapped = $this->map_json_to_record($record);
            
            if ($mapped && $this->database->insert_change($mapped)) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    /**
     * Export data to CSV
     */
    public function export_csv($args = []) {
        $defaults = [
            'category' => null,
            'days' => null,
            'limit' => 10000
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $data = $this->database->get_recent_changes([
            'limit' => $args['limit'],
            'field_category' => $args['category'],
            'days' => $args['days']
        ]);
        
        if (empty($data)) {
            return '';
        }
        
        // Prepare data for CSV
		$csv_data = [];
		foreach ($data as $row) {
			$post = get_post($row->post_id);
			$csv_data[] = [
				'post_id' => $row->post_id,  // Viktigt: detta måste finnas!
				'bank' => $post ? $post->post_title : 'Unknown',
				'field_name' => $row->field_name,  // Viktigt: detta måste finnas!
				'field_category' => $row->field_category,
				'old_value' => $row->old_value,
				'new_value' => $row->new_value,  // Viktigt: detta måste finnas!
				'change_percentage' => $row->change_percentage,
				'change_date' => $row->change_date,
				'import_source' => $row->import_source,
				'is_validated' => $row->is_validated ? 'Yes' : 'No'
			];
		}

		return LRH_Helpers::array_to_csv($csv_data);
	}
    
    /**
     * Export data to JSON
     */
    public function export_json($args = []) {
        $defaults = [
            'category' => null,
            'days' => null,
            'limit' => 10000
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $data = $this->database->get_recent_changes([
            'limit' => $args['limit'],
            'field_category' => $args['category'],
            'days' => $args['days']
        ]);
        
        if (empty($data)) {
            return '[]';
        }
        
        // Prepare data for JSON
        $json_data = [];
        foreach ($data as $row) {
            $post = get_post($row->post_id);
            $json_data[] = [
                'post_id' => $row->post_id,
                'bank' => $post ? $post->post_title : 'Unknown',
                'field_name' => $row->field_name,
                'field_category' => $row->field_category,
                'old_value' => floatval($row->old_value),
                'new_value' => floatval($row->new_value),
                'change_percentage' => floatval($row->change_percentage),
                'change_date' => $row->change_date,
                'import_source' => $row->import_source,
                'is_validated' => $row->is_validated ? true : false
            ];
        }
        
        return json_encode($json_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get all lender posts
     */
    private function get_all_lenders() {
        $lender_post_types = apply_filters('lrh_lender_post_types', ['langivare', 'lender']);
        
        // Get only lenders with the 'bolan' term
        return get_posts([
            'post_type' => $lender_post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'mall',
                    'field' => 'slug',
                    'terms' => ['bolan', 'privatlan', 'billan'], // Include all relevant terms
                    'operator' => 'IN'
                ]
            ]
        ]);
    }
    
	/**
	 * Map CSV data to database record
	 */
	private function map_csv_to_record($data) {
		$record = [];

		// Debug - logga vad vi får in
		error_log('LRH Import - CSV row data keys: ' . print_r(array_keys($data), true));

		// Hantera post_id - olika möjliga kolumnnamn
		$post_id_keys = ['post_id', 'post id', 'postid', 'id'];
		$post_id_found = false;
		foreach ($post_id_keys as $key) {
			if (isset($data[$key])) {
				$record['post_id'] = intval($data[$key]);
				$post_id_found = true;
				break;
			}
		}

		if (!$post_id_found) {
			error_log('LRH Import - Missing post_id. Available keys: ' . print_r(array_keys($data), true));
			return false;
		}

		// Hantera field_name - olika möjliga kolumnnamn
		$field_name_keys = ['field_name', 'field', 'fieldname', 'fält'];
		$field_name_found = false;
		foreach ($field_name_keys as $key) {
			if (isset($data[$key])) {
				$record['field_name'] = sanitize_text_field($data[$key]);
				$field_name_found = true;
				break;
			}
		}

		if (!$field_name_found) {
			error_log('LRH Import - Missing field_name. Available keys: ' . print_r(array_keys($data), true));
			return false;
		}

		// Hantera new_value - olika möjliga kolumnnamn
		$new_value_keys = ['new_value', 'new value', 'newvalue', 'nytt värde', 'nytt_värde'];
		$new_value_found = false;
		foreach ($new_value_keys as $key) {
			if (isset($data[$key])) {
				// Hantera både komma och punkt som decimaltecken
				$value = str_replace(',', '.', $data[$key]);
				$record['new_value'] = floatval($value);
				$new_value_found = true;
				break;
			}
		}

		if (!$new_value_found) {
			error_log('LRH Import - Missing new_value. Available keys: ' . print_r(array_keys($data), true));
			return false;
		}

		// Hantera field_category - olika möjliga kolumnnamn
		$category_keys = ['field_category', 'category', 'kategori'];
		foreach ($category_keys as $key) {
			if (isset($data[$key])) {
				$record['field_category'] = sanitize_text_field($data[$key]);
				break;
			}
		}

		// Hantera old_value - olika möjliga kolumnnamn
		$old_value_keys = ['old_value', 'old value', 'oldvalue', 'gammalt värde', 'gammalt_värde'];
		foreach ($old_value_keys as $key) {
			if (isset($data[$key])) {
				$value = str_replace(',', '.', $data[$key]);
				$record['old_value'] = floatval($value);
				break;
			}
		}

		// Om old_value saknas, sätt den till samma som new_value för initial import
		if (!isset($record['old_value'])) {
			$record['old_value'] = $record['new_value'];
		}

		// Hantera change_date - olika möjliga kolumnnamn
		$date_keys = ['change_date', 'date', 'datum', 'changed_at', 'change date'];
		foreach ($date_keys as $key) {
			if (isset($data[$key])) {
				$record['change_date'] = sanitize_text_field($data[$key]);
				break;
			}
		}

		// Använd nuvarande datum om inget datum anges
		if (!isset($record['change_date'])) {
			$record['change_date'] = current_time('mysql');
		}

		// Hantera import_source - olika möjliga kolumnnamn
		$source_keys = ['import_source', 'source', 'källa', 'import source'];
		foreach ($source_keys as $key) {
			if (isset($data[$key])) {
				$record['import_source'] = sanitize_text_field($data[$key]);
				break;
			}
		}

		if (!isset($record['import_source'])) {
			$record['import_source'] = 'csv_import';
		}

		// Hantera is_validated - olika möjliga kolumnnamn
		$validated_keys = ['is_validated', 'validated', 'validerad', 'is validated'];
		foreach ($validated_keys as $key) {
			if (isset($data[$key])) {
				$is_validated_value = strtolower(trim($data[$key]));
				$record['is_validated'] = in_array($is_validated_value, ['yes', '1', 'true', 'ja']) ? 1 : 0;
				break;
			}
		}

		if (!isset($record['is_validated'])) {
			$record['is_validated'] = 0;
		}

		// Hantera change_percentage - olika möjliga kolumnnamn
		$change_keys = ['change_percentage', 'change %', 'change_percent', 'change', 'förändring'];
		foreach ($change_keys as $key) {
			if (isset($data[$key])) {
				$value = str_replace(['%', ','], ['',' .'], $data[$key]);
				$record['change_percentage'] = floatval($value);
				break;
			}
		}

		// Beräkna change_percentage om den inte finns men både old och new value finns
		if (!isset($record['change_percentage']) && 
			isset($record['old_value']) && 
			isset($record['new_value']) && 
			$record['old_value'] != 0) {
			$record['change_percentage'] = (($record['new_value'] - $record['old_value']) / $record['old_value']) * 100;
		} elseif (!isset($record['change_percentage'])) {
			$record['change_percentage'] = 0;
		}

		error_log('LRH Import - Successfully mapped record: post_id=' . $record['post_id'] . ', field=' . $record['field_name']);

		return $record;
	}
    
    /**
     * Map JSON data to database record
     */
    private function map_json_to_record($data) {
        // Similar to CSV mapping but expects proper types
        if (!isset($data['post_id']) || !isset($data['field_name']) || !isset($data['new_value'])) {
            return false;
        }
        
        $record = [
            'post_id' => intval($data['post_id']),
            'field_name' => sanitize_text_field($data['field_name']),
            'new_value' => floatval($data['new_value'])
        ];
        
        // Optional fields with type conversion
        $field_mapping = [
            'field_category' => 'sanitize_text_field',
            'old_value' => 'floatval',
            'change_date' => 'sanitize_text_field',
            'import_source' => 'sanitize_text_field',
            'is_validated' => 'intval'
        ];
        
        foreach ($field_mapping as $field => $sanitizer) {
            if (isset($data[$field])) {
                $record[$field] = call_user_func($sanitizer, $data[$field]);
            }
        }
        
        if (!isset($record['import_source'])) {
            $record['import_source'] = 'json_import';
        }
        
        return $record;
    }
    
    /**
     * Clear all history data
     */
    private function clear_all_history() {
        global $wpdb;
        $table_name = $wpdb->prefix . LRH_TABLE_NAME;
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
	
	
	
	
}