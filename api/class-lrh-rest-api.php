<?php
/**
 * REST API endpoints - FIXAD VERSION
 * Tar bort datumfilter för externa källor så all historik visas
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_REST_API {
    
    private $namespace = 'lrh/v1';
    private $api;
    private $database;
    
    public function __construct() {
        $this->api = new LRH_API();
        $this->database = new LRH_Database();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get rate change
        register_rest_route($this->namespace, '/rate-change/(?P<post_id>\d+)/(?P<field>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_rate_change'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'field' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9_]+$/', $param);
                    }
                ],
                'format' => [
                    'default' => 'full',
                    'validate_callback' => function($param) {
                        return in_array($param, ['percentage', 'amount', 'arrow', 'class', 'full', 'raw']);
                    }
                ]
            ]
        ]);
        
        // Get field history
        register_rest_route($this->namespace, '/history/(?P<post_id>\d+)/(?P<field>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_field_history'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'field' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9_]+$/', $param);
                    }
                ],
                'days' => [
                    'default' => 30,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
        
        // Get recent changes
        register_rest_route($this->namespace, '/recent-changes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_recent_changes'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                ],
                'category' => [
                    'default' => null,
                    'validate_callback' => function($param) {
                        return in_array($param, ['mortgage', 'personal_loan', 'car_loan', null]);
                    }
                ],
                'days' => [
                    'default' => null,
                    'validate_callback' => function($param) {
                        return is_numeric($param) || is_null($param);
                    }
                ]
            ]
        ]);
        
        // Get lender statistics
        register_rest_route($this->namespace, '/stats/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lender_stats'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // Get comparison data
        register_rest_route($this->namespace, '/compare', [
            'methods' => 'POST',
            'callback' => [$this, 'get_comparison'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_ids' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ],
                'field' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9_]+$/', $param);
                    }
                ],
                'days' => [
                    'default' => 30,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
        
        // Admin endpoints (require authentication)
        register_rest_route($this->namespace, '/admin/validate/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_change'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/admin/delete/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_change'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    
		// Get all external sources
		register_rest_route($this->namespace, '/external-sources', [
			'methods' => 'GET',
			'callback' => [$this, 'get_external_sources'],
			'permission_callback' => '__return_true'
		]);

		// Get specific external source with history - UPPDATERAD
		register_rest_route($this->namespace, '/external-source/(?P<source_id>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [$this, 'get_external_source'],
			'permission_callback' => '__return_true',
			'args' => [
				'source_id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return preg_match('/^[a-zA-Z0-9_-]+$/', $param);
					}
				],
				'days' => [
					'default' => null,  // Ändrat från 365 till null för att visa all data
					'validate_callback' => function($param) {
						return is_numeric($param) || is_null($param);
					}
				],
				'limit' => [
					'default' => 1000,  // Lägg till limit parameter
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					}
				]
			]
		]);

		// Get aggregated data (både banker och externa källor)
		register_rest_route($this->namespace, '/dashboard', [
			'methods' => 'GET',
			'callback' => [$this, 'get_dashboard_data'],
			'permission_callback' => '__return_true'
		]);
	}
    
    /**
     * Get rate change endpoint
     */
    public function get_rate_change($request) {
        $post_id = $request['post_id'];
        $field = $request['field'];
        $format = $request->get_param('format');
        
        $change = $this->api->get_field_change($post_id, $field, $format);
        
        if ($change === null) {
            return new WP_Error('no_data', __('Ingen data hittades', 'lender-rate-history'), ['status' => 404]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $change
        ]);
    }
    
    /**
     * Get field history endpoint
     */
    public function get_field_history($request) {
        $post_id = $request['post_id'];
        $field = $request['field'];
        $days = $request->get_param('days');
        
        $history = $this->api->get_field_history($post_id, $field, $days);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $history
        ]);
    }
    
    /**
     * Get recent changes endpoint
     */
    public function get_recent_changes($request) {
        $changes = $this->api->get_recent_changes_table([
            'limit' => $request->get_param('limit'),
            'field_category' => $request->get_param('category'),
            'format' => 'array'
        ]);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $changes
        ]);
    }
    
    /**
     * Get lender statistics endpoint
     */
    public function get_lender_stats($request) {
        $post_id = $request['post_id'];
        
        $stats = $this->api->get_lender_stats($post_id);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    /**
     * Get comparison data endpoint
     */
    public function get_comparison($request) {
        $post_ids = $request['post_ids'];
        $field = $request['field'];
        $days = $request->get_param('days');
        
        $comparison = $this->api->get_comparison_data($post_ids, $field, $days);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $comparison
        ]);
    }
    
    /**
     * Validate change endpoint
     */
    public function validate_change($request) {
        $id = $request['id'];
        
        $result = $this->database->update_change($id, [
            'is_validated' => 1,
            'validation_notes' => sprintf(__('Validerad via API av %s', 'lender-rate-history'), wp_get_current_user()->display_name)
        ]);
        
        if (!$result) {
            return new WP_Error('validation_failed', __('Kunde inte validera ändringen', 'lender-rate-history'), ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Ändringen har validerats', 'lender-rate-history')
        ]);
    }
    
    /**
     * Delete change endpoint
     */
    public function delete_change($request) {
        $id = $request['id'];
        
        $result = $this->database->delete_change($id);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Kunde inte ta bort ändringen', 'lender-rate-history'), ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Ändringen har tagits bort', 'lender-rate-history')
        ]);
    }
	
	
	/**
	 * Get all external sources
	 */
	public function get_external_sources($request) {
		$external_sources = new LRH_External_Sources();
		$sources = $external_sources->get_sources();

		$response_data = [];

		foreach ($sources as $source) {
			// Hämta senaste värde
			$latest_value = $this->database->get_latest_value_for_source(
				$source['source_type'],
				$source['source_id'],
				$source['option_field'] ?? 'value'
			);

			$response_data[] = [
				'source_id' => $source['source_id'],
				'display_name' => $source['display_name'],
				'current_value' => $latest_value,
				'format' => $source['value_format'] ?? 'percentage',
				'suffix' => $source['value_suffix'] ?? '%',
				'decimals' => $source['decimals'] ?? 2,
				'category' => $source['category'] ?? 'external'
			];
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $response_data
		]);
	}

	/**
	 * Get specific external source with history
	 * FIXAD: Tar bort datumfilter om days är null för att visa all historik
	 */
	public function get_external_source($request) {
		$source_id = $request['source_id'];
		$days = $request->get_param('days');
		$limit = $request->get_param('limit');

		$external_sources = new LRH_External_Sources();
		$source = $external_sources->get_source($source_id);

		if (!$source) {
			return new WP_Error('not_found', __('Källa hittades inte', 'lender-rate-history'), ['status' => 404]);
		}

		// Hämta historik
		global $wpdb;
		$table_name = $wpdb->prefix . LRH_TABLE_NAME;

		// Bygg SQL-query baserat på om days är satt eller inte
		if ($days !== null && $days > 0) {
			// Om days är satt, filtrera på datum
			$sql = $wpdb->prepare(
				"SELECT 
					DATE(change_date) as date,
					new_value as value,
					old_value,
					change_amount,
					change_percentage,
					import_source,
					change_date,
					is_validated
				 FROM {$table_name}
				 WHERE source_id = %s
				 AND change_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 ORDER BY change_date DESC
				 LIMIT %d",
				$source_id,
				$days,
				$limit
			);
		} else {
			// Om days är null, hämta ALL historik (med limit)
			$sql = $wpdb->prepare(
				"SELECT 
					DATE(change_date) as date,
					new_value as value,
					old_value,
					change_amount,
					change_percentage,
					import_source,
					change_date,
					is_validated
				 FROM {$table_name}
				 WHERE source_id = %s
				 ORDER BY change_date DESC
				 LIMIT %d",
				$source_id,
				$limit
			);
		}

		$history = $wpdb->get_results($sql);

		// Formatera historik
		$formatted_history = [];
		foreach ($history as $record) {
			$formatted_history[] = [
				'date' => $record->date,
				'value' => floatval($record->value),
				'previous_value' => $record->old_value ? floatval($record->old_value) : null,
				'change' => $record->change_amount ? floatval($record->change_amount) : null,
				'change_percentage' => $record->change_percentage ? floatval($record->change_percentage) : null,
				'source' => $record->import_source,
				'is_validated' => (bool)$record->is_validated,
				'full_datetime' => $record->change_date  // Inkludera full datetime för debugging
			];
		}

		// Sortera historiken kronologiskt för grafen (äldst först)
		$formatted_history_for_chart = array_reverse($formatted_history);

		// Hämta statistik
		$values = array_column($formatted_history, 'value');
		$latest_value = !empty($values) ? $values[0] : null;
		$min_value = !empty($values) ? min($values) : null;
		$max_value = !empty($values) ? max($values) : null;

		// Räkna ovaliderade poster
		$unvalidated_count = 0;
		foreach ($history as $record) {
			if (!$record->is_validated) {
				$unvalidated_count++;
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'source' => [
					'id' => $source['source_id'],
					'name' => $source['display_name'],
					'type' => $source['source_type'],
					'format' => $source['value_format'] ?? 'percentage',
					'suffix' => $source['value_suffix'] ?? '%',
					'decimals' => $source['decimals'] ?? 2
				],
				'current_value' => $latest_value,
				'statistics' => [
					'min' => $min_value,
					'max' => $max_value,
					'average' => !empty($values) ? array_sum($values) / count($values) : null,
					'total_changes' => count($formatted_history),
					'unvalidated' => $unvalidated_count,
					'date_range' => [
						'start' => !empty($formatted_history_for_chart) ? $formatted_history_for_chart[0]['date'] : null,
						'end' => !empty($formatted_history_for_chart) ? end($formatted_history_for_chart)['date'] : null
					]
				],
				'history' => $formatted_history_for_chart,  // För grafer (kronologisk ordning)
				'recent_changes' => array_slice($formatted_history, 0, 10)  // Senaste ändringar (omvänd ordning)
			]
		]);
	}

	/**
	 * Get dashboard data - aggregerat
	 */
	public function get_dashboard_data($request) {
		// Hämta senaste ändringar för banker
		$bank_changes = $this->database->get_recent_changes([
			'limit' => 5,
			'include_external' => false
		]);

		// Hämta senaste ändringar för externa källor
		$external_changes = $this->database->get_recent_changes([
			'limit' => 5,
			'include_external' => true
		]);

		// Hämta statistik
		$stats = $this->database->get_statistics();

		// Formatera för response
		$formatted_bank_changes = [];
		foreach ($bank_changes as $change) {
			$post = get_post($change->post_id);
			$formatted_bank_changes[] = [
				'bank' => $post ? $post->post_title : 'Unknown',
				'field' => $change->field_name,
				'old_value' => floatval($change->old_value),
				'new_value' => floatval($change->new_value),
				'change' => floatval($change->change_amount),
				'date' => $change->change_date
			];
		}

		$formatted_external_changes = [];
		foreach ($external_changes as $change) {
			if ($change->source_type !== 'post') {
				$formatted_external_changes[] = [
					'source' => $change->source_name ?? $change->source_id,
					'field' => $change->field_name,
					'old_value' => floatval($change->old_value),
					'new_value' => floatval($change->new_value),
					'change' => floatval($change->change_amount),
					'date' => $change->change_date
				];
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'statistics' => [
					'total_changes' => $stats['total_changes'],
					'changes_today' => $stats['changes_today'],
					'changes_week' => $stats['changes_week'],
					'unvalidated' => $stats['unvalidated']
				],
				'recent_bank_changes' => $formatted_bank_changes,
				'recent_external_changes' => $formatted_external_changes,
				'timestamp' => current_time('c')
			]
		]);
	}
    
    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
}