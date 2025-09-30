<?php
/**
 * Database operations handler - UPPDATERAD MED HJÄLPKLASS
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Database {
    
    private $table_name;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . LRH_TABLE_NAME;
    }
    
    /**
     * Insert rate change record - ANVÄNDER NU HJÄLPKLASS
     */
    public function insert_change($data) {
        error_log("LRH DB: insert_change called with data: " . print_r($data, true));

        // Standardvärden
        $defaults = [
            'post_id' => null,
            'source_type' => 'post',
            'source_id' => null,
            'source_name' => null,
            'field_name' => '',
            'field_category' => 'mortgage',
            'old_value' => null,
            'new_value' => 0,
            'change_amount' => null,
            'change_percentage' => null,
            'change_date' => current_time('mysql'),
            'change_type' => 'update',
            'import_source' => 'manual',
            'is_validated' => 1,
            'validation_notes' => null,
            'user_id' => get_current_user_id()
        ];

        $data = wp_parse_args($data, $defaults);
        error_log("LRH DB: After defaults: " . print_r($data, true));

        // Använd hjälpklass för normalisering
        if (isset($data['new_value'])) {
            $data['new_value'] = LRH_Value_Helper::normalize_value($data['new_value']);
            error_log("LRH DB: Normalized new_value: " . $data['new_value']);
        }
        if (isset($data['old_value']) && $data['old_value'] !== null && $data['old_value'] !== '') {
            $data['old_value'] = LRH_Value_Helper::normalize_value($data['old_value']);
            error_log("LRH DB: Normalized old_value: " . $data['old_value']);
        }

        // Validering beroende på typ
        if ($data['source_type'] === 'post') {
            if (empty($data['post_id']) || empty($data['field_name']) || !isset($data['new_value'])) {
                error_log("LRH DB: Validation failed for post type");
                return false;
            }
        } else {
            if (empty($data['source_id']) || empty($data['field_name']) || !isset($data['new_value'])) {
                error_log("LRH DB: Validation failed - source_id: " . $data['source_id'] . ", field_name: " . $data['field_name'] . ", new_value: " . $data['new_value']);
                return false;
            }
        }

        // Använd hjälpklass för att beräkna förändring
        $change_data = LRH_Value_Helper::calculate_change($data['old_value'], $data['new_value']);
        $data['change_amount'] = $change_data['change_amount'];
        $data['change_percentage'] = $change_data['change_percentage'];
        $data['change_type'] = $change_data['change_type'];

        // Validera stora förändringar med hjälpklass
        if (LRH_Value_Helper::is_large_change($data['change_amount'])) {
            $data['is_validated'] = 0;
            $data['validation_notes'] = 'Large change detected - requires validation';
        }

        error_log("LRH DB: Final data before insert: " . print_r($data, true));

        // Insert
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            $this->get_field_formats($data)
        );

        if ($result === false) {
            error_log('LRH Database Error: ' . $this->wpdb->last_error);
            error_log('LRH Database Query: ' . $this->wpdb->last_query);
            return false;
        }

        error_log("LRH DB: Insert successful, ID: " . $this->wpdb->insert_id);

        $this->update_stats('insert');

        return $this->wpdb->insert_id;
    }
    
    /**
     * Update a change record - ANVÄNDER NU HJÄLPKLASS
     */
    public function update_change($id, $data) {
        // Om vi uppdaterar värden, räkna om förändringen
        if (isset($data['new_value']) || isset($data['old_value'])) {
            // Hämta befintlig post om vi inte har alla värden
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ));
            
            if (!$existing) {
                return false;
            }
            
            // Normalisera värden med hjälpklass
            $old_value = isset($data['old_value']) ? 
                LRH_Value_Helper::normalize_value($data['old_value']) : 
                $existing->old_value;
            
            $new_value = isset($data['new_value']) ? 
                LRH_Value_Helper::normalize_value($data['new_value']) : 
                $existing->new_value;
            
            // Beräkna förändring med hjälpklass
            $change_data = LRH_Value_Helper::calculate_change($old_value, $new_value);
            
            $data['old_value'] = $old_value;
            $data['new_value'] = $new_value;
            $data['change_amount'] = $change_data['change_amount'];
            $data['change_percentage'] = $change_data['change_percentage'];
            $data['change_type'] = $change_data['change_type'];
        }
        
        return $this->wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id],
            $this->get_field_formats($data),
            ['%d']
        );
    }
    
    /**
     * Get latest value for a field
     */
    public function get_latest_value($post_id, $field_name) {
        $sql = $this->wpdb->prepare(
            "SELECT new_value FROM {$this->table_name} 
             WHERE post_id = %d AND field_name = %s 
             ORDER BY change_date DESC 
             LIMIT 1",
            $post_id,
            $field_name
        );
        
        return $this->wpdb->get_var($sql);
    }
    
    /**
     * Get latest value for external source
     */
    public function get_latest_value_for_source($source_type, $source_id, $field_name) {
        $sql = $this->wpdb->prepare(
            "SELECT new_value FROM {$this->table_name} 
             WHERE source_type = %s 
             AND source_id = %s 
             AND field_name = %s 
             ORDER BY change_date DESC, id DESC 
             LIMIT 1",
            $source_type,
            $source_id,
            $field_name
        );

        return $this->wpdb->get_var($sql);
    }
    
    /**
     * Get latest change record for a field
     */
    public function get_latest_change($post_id, $field_name) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE post_id = %d AND field_name = %s 
             ORDER BY change_date DESC 
             LIMIT 1",
            $post_id,
            $field_name
        );
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Get history for a specific field
     */
    public function get_field_history($post_id, $field_name, $limit = 30, $offset = 0) {
        // Om limit är dagar
        $date_filter = "";
        if ($limit <= 365) {
            $date_filter = "AND change_date >= DATE_SUB(NOW(), INTERVAL {$limit} DAY)";
            $limit = 1000;
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE post_id = %d AND field_name = %s {$date_filter}
             ORDER BY change_date DESC 
             LIMIT %d OFFSET %d",
            $post_id,
            $field_name,
            $limit,
            $offset
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get all changes for a post
     */
    public function get_post_history($post_id, $limit = 100, $offset = 0) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE post_id = %d 
             ORDER BY change_date DESC 
             LIMIT %d OFFSET %d",
            $post_id,
            $limit,
            $offset
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get recent changes across all sources
     */
    public function get_recent_changes($args = []) {
        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'field_category' => null,
            'field_name' => null,
            'days' => null,
            'validated_only' => false,
            'group_by_post' => false,
            'include_external' => false
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        // Filter för att inkludera/exkludera externa källor
        if (!$args['include_external']) {
            $where[] = "source_type = 'post'";
        }
        
        // Filter by category
        if ($args['field_category']) {
            $where[] = 'field_category = %s';
            $values[] = $args['field_category'];
        }
        
        // Filter by field name
        if ($args['field_name']) {
            $where[] = 'field_name = %s';
            $values[] = $args['field_name'];
        }
        
        // Filter by days
        if ($args['days']) {
            $where[] = 'change_date >= DATE_SUB(NOW(), INTERVAL %d DAY)';
            $values[] = $args['days'];
        }
        
        // Filter by validation
        if ($args['validated_only']) {
            $where[] = 'is_validated = 1';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Build query
        if ($args['group_by_post']) {
            $sql = "SELECT t1.* FROM {$this->table_name} t1
                    INNER JOIN (
                        SELECT post_id, MAX(change_date) as max_date
                        FROM {$this->table_name}
                        WHERE {$where_clause} AND post_id IS NOT NULL
                        GROUP BY post_id
                    ) t2 ON t1.post_id = t2.post_id AND t1.change_date = t2.max_date
                    ORDER BY t1.change_date DESC
                    LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        } else {
            $sql = "SELECT * FROM {$this->table_name} 
                    WHERE {$where_clause}
                    ORDER BY change_date DESC 
                    LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }
        
        if (!empty($values)) {
            $sql = $this->wpdb->prepare($sql, $values);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get changes that need validation
     */
    public function get_unvalidated_changes($limit = 50) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE is_validated = 0 
             ORDER BY change_date DESC 
             LIMIT %d",
            $limit
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Delete a change record
     */
    public function delete_change($id) {
        return $this->wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
    }
    
    /**
     * Cleanup old records
     */
    public function cleanup_old_records($days = 365) {
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE change_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        $deleted = $this->wpdb->query($sql);
        
        $this->update_stats('cleanup', $deleted);
        
        return $deleted;
    }
    
    /**
     * Get statistics
     */
    public function get_statistics() {
        $stats = [];
        
        // Total changes
        $stats['total_changes'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );
        
        // Changes today
        $stats['changes_today'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE DATE(change_date) = CURDATE()"
        );
        
        // Changes this week
        $stats['changes_week'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE change_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Unvalidated changes
        $stats['unvalidated'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE is_validated = 0"
        );
        
        // Most changed fields
        $stats['top_fields'] = $this->wpdb->get_results(
            "SELECT field_name, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE source_type = 'post'
             GROUP BY field_name 
             ORDER BY count DESC 
             LIMIT 5"
        );
        
        // Most active lenders
        $stats['top_lenders'] = $this->wpdb->get_results(
            "SELECT post_id, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE post_id IS NOT NULL
             GROUP BY post_id 
             ORDER BY count DESC 
             LIMIT 5"
        );
        
        return $stats;
    }
    
    /**
     * Get field formats for database operations
     */
    private function get_field_formats($data = []) {
        $formats = [
            'post_id' => '%d',
            'source_type' => '%s',
            'source_id' => '%s',
            'source_name' => '%s',
            'field_name' => '%s',
            'field_category' => '%s',
            'old_value' => '%f',
            'new_value' => '%f',
            'change_amount' => '%f',
            'change_percentage' => '%f',
            'change_date' => '%s',
            'change_type' => '%s',
            'import_source' => '%s',
            'is_validated' => '%d',
            'validation_notes' => '%s',
            'user_id' => '%d'
        ];
        
        if (!empty($data)) {
            $field_formats = [];
            foreach ($data as $field => $value) {
                if (isset($formats[$field])) {
                    // Hantera null-värden korrekt
                    if ($value === null && in_array($field, ['old_value', 'change_amount', 'change_percentage'])) {
                        $field_formats[] = '%s'; // NULL som sträng
                    } else {
                        $field_formats[] = $formats[$field];
                    }
                }
            }
            return $field_formats;
        }
        
        return $formats;
    }
    
    /**
     * Update plugin statistics
     */
    private function update_stats($action, $count = 1) {
        $stats = get_option('lrh_stats', []);
        
        switch ($action) {
            case 'insert':
                $stats['total_changes'] = isset($stats['total_changes']) ? $stats['total_changes'] + $count : $count;
                break;
            case 'cleanup':
                $stats['last_cleanup'] = current_time('mysql');
                break;
            case 'import':
                $stats['last_import'] = current_time('mysql');
                break;
        }
        
        update_option('lrh_stats', $stats);
    }
    
    /**
     * Batch insert for better performance - UPPDATERAD MED HJÄLPKLASS
     */
    public function batch_insert($changes) {
        if (empty($changes)) {
            return false;
        }
        
        $values = [];
        $placeholders = [];
        
        foreach ($changes as $change) {
            // Defaults
            $change = wp_parse_args($change, [
                'post_id' => null,
                'source_type' => 'post',
                'source_id' => null,
                'source_name' => null,
                'user_id' => get_current_user_id(),
                'change_date' => current_time('mysql'),
                'field_category' => 'mortgage',
                'change_type' => 'update',
                'import_source' => 'manual',
                'validation_notes' => null
            ]);
            
            // Använd hjälpklass för normalisering och beräkning
            $new_value = LRH_Value_Helper::normalize_value($change['new_value']);
            $old_value = isset($change['old_value']) ? LRH_Value_Helper::normalize_value($change['old_value']) : null;
            
            $change_data = LRH_Value_Helper::calculate_change($old_value, $new_value);
            
            $change['new_value'] = $new_value;
            $change['old_value'] = $old_value;
            $change['change_amount'] = $change_data['change_amount'];
            $change['change_percentage'] = $change_data['change_percentage'];
            $change['change_type'] = $change_data['change_type'];
            
            $change['is_validated'] = LRH_Value_Helper::is_large_change($change_data['change_amount']) ? 0 : 1;
            
            $placeholders[] = "(%d, %s, %s, %s, %s, %s, %f, %f, %f, %f, %s, %s, %s, %d, %s, %d)";
            
            array_push($values,
                $change['post_id'],
                $change['source_type'],
                $change['source_id'],
                $change['source_name'],
                $change['field_name'],
                $change['field_category'],
                $change['old_value'],
                $change['new_value'],
                $change['change_amount'],
                $change['change_percentage'],
                $change['change_date'],
                $change['change_type'],
                $change['import_source'],
                $change['is_validated'],
                $change['validation_notes'],
                $change['user_id']
            );
        }
        
        $sql = "INSERT INTO {$this->table_name} 
                (post_id, source_type, source_id, source_name, field_name, field_category, 
                 old_value, new_value, change_amount, change_percentage, change_date, 
                 change_type, import_source, is_validated, validation_notes, user_id) 
                VALUES " . implode(', ', $placeholders);
        
        $result = $this->wpdb->query($this->wpdb->prepare($sql, $values));
        
        if ($result !== false) {
            $this->update_stats('insert', count($changes));
        }
        
        return $result;
    }
}