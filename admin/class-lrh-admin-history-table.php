<?php
/**
 * History table using WP_List_Table
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class LRH_Admin_History_Table extends WP_List_Table {
    
    private $database;
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Ränteändring', 'lender-rate-history'),
            'plural' => __('Ränteändringar', 'lender-rate-history'),
            'ajax' => false
        ]);
        
        $this->database = new LRH_Database();
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'bank' => __('Bank', 'lender-rate-history'),
            'field' => __('Fält', 'lender-rate-history'),
            'change' => __('Förändring', 'lender-rate-history'),
            'date' => __('Datum', 'lender-rate-history'),
            'source' => __('Källa', 'lender-rate-history'),
            'validated' => __('Validerad', 'lender-rate-history'),
            'actions' => __('Åtgärder', 'lender-rate-history')
        ];
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return [
            'bank' => ['post_id', false],
            'field' => ['field_name', false],
            'date' => ['change_date', true],
            'source' => ['import_source', false],
            'validated' => ['is_validated', false]
        ];
    }
    
    /**
     * Get default column value
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'bank':
                $post = get_post($item->post_id);
                if ($post) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        get_edit_post_link($item->post_id),
                        esc_html($post->post_title)
                    );
                }
                return __('Okänd', 'lender-rate-history');
                
            case 'field':
                $field_info = LRH_Helpers::parse_field_name($item->field_name);
                return sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    esc_html($field_info['type_label']),
                    esc_html($field_info['period_label'])
                );
                
            case 'change':
                if ($item->old_value !== null) {
                    $change_info = LRH_Helpers::format_change($item->old_value, $item->new_value);
                    $formatted = isset($change_info['formatted']) ? $change_info['formatted'] : 
                                 (($change_info['percentage'] > 0 ? '+' : '') . number_format($change_info['percentage'], 2) . '%');
                    
                    return sprintf(
                        '<span class="lrh-old-value">%s%%</span> → <span class="lrh-new-value">%s%%</span><br>
                        <span class="lrh-change-badge %s">%s %s</span>',
                        number_format($item->old_value, 2),
                        number_format($item->new_value, 2),
                        esc_attr($change_info['class']),
                        $change_info['arrow'],
                        $formatted
                    );
                } else {
                    return sprintf(
                        '<span class="lrh-new-value">%s%%</span><br>
                        <span class="lrh-change-badge">%s</span>',
                        number_format($item->new_value, 2),
                        __('Initial värde', 'lender-rate-history')
                    );
                }
                
            case 'date':
                return sprintf(
                    '%s<br><small>%s</small>',
                    LRH_Helpers::format_date($item->change_date),
                    LRH_Helpers::time_ago($item->change_date)
                );
                
            case 'source':
                $sources = [
                    'manual' => __('Manuell', 'lender-rate-history'),
                    'wp_all_import' => __('WP All Import', 'lender-rate-history'),
                    'initialization' => __('Initiering', 'lender-rate-history'),
                    'csv_import' => __('CSV Import', 'lender-rate-history'),
                    'json_import' => __('JSON Import', 'lender-rate-history')
                ];
                return isset($sources[$item->import_source]) ? $sources[$item->import_source] : $item->import_source;
                
            case 'validated':
                if ($item->is_validated) {
                    return '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>';
                } else {
                    return '<span class="dashicons dashicons-warning" style="color: #d63638;"></span>';
                }
                
            case 'actions':
                $actions = [];
                
                if (!$item->is_validated) {
                    $actions[] = sprintf(
                        '<button class="button button-small lrh-validate" data-id="%d">%s</button>',
                        $item->id,
                        __('Validera', 'lender-rate-history')
                    );
                }
                
                $actions[] = sprintf(
                    '<button class="button button-small lrh-delete" data-id="%d">%s</button>',
                    $item->id,
                    __('Ta bort', 'lender-rate-history')
                );
                
                return implode(' ', $actions);
                
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->id);
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return [
            'bulk-delete' => __('Ta bort', 'lender-rate-history'),
            'bulk-validate' => __('Validera', 'lender-rate-history')
        ];
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        // Security check
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce'])) {
            $nonce = $_POST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                die('Security check failed');
            }
        }
        
        $action = $this->current_action();
        
        if ($action === 'bulk-delete') {
            if (!empty($_POST['bulk-delete'])) {
                foreach ($_POST['bulk-delete'] as $id) {
                    $this->database->delete_change(intval($id));
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Valda poster har tagits bort.', 'lender-rate-history') . 
                     '</p></div>';
            }
        }
        
        if ($action === 'bulk-validate') {
            if (!empty($_POST['bulk-delete'])) {
                foreach ($_POST['bulk-delete'] as $id) {
                    $this->database->update_change(intval($id), [
                        'is_validated' => 1,
                        'validation_notes' => sprintf(__('Bulk-validerad av %s', 'lender-rate-history'), wp_get_current_user()->display_name)
                    ]);
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Valda poster har validerats.', 'lender-rate-history') . 
                     '</p></div>';
            }
        }
    }
    
    /**
     * Prepare items for display
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Get filter parameters
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null;
        $lender_id = isset($_GET['lender']) ? intval($_GET['lender']) : null;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Pagination
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Build query args
        $args = [
            'limit' => $per_page,
            'offset' => $offset,
            'field_category' => $category
        ];
        
        // Filter by specific lender if selected
        if ($lender_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'lender_rate_history';
            
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE post_id = %d ORDER BY change_date DESC LIMIT %d OFFSET %d",
                $lender_id,
                $per_page,
                $offset
            );
            
            $this->items = $wpdb->get_results($sql);
            
            // Get total for this lender
            $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d",
                $lender_id
            ));
        } else {
            // Apply filters
            if ($filter === 'unvalidated') {
                $args['validated_only'] = false;
                $this->items = $this->database->get_unvalidated_changes($per_page);
                $total_items = count($this->database->get_unvalidated_changes(1000));
            } else {
                $this->items = $this->database->get_recent_changes($args);
                
                // Get total count
                $count_args = $args;
                unset($count_args['limit'], $count_args['offset']);
                $count_args['limit'] = 10000;
                $total_items = count($this->database->get_recent_changes($count_args));
            }
        }
        
        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    /**
     * Extra table navigation
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <!-- Lender Filter -->
                <select name="lender" id="filter-by-lender">
                    <option value=""><?php _e('Alla långivare', 'lender-rate-history'); ?></option>
                    <?php
                    // Get all lenders with history
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'lender_rate_history';
                    
                    $lenders_with_history = $wpdb->get_results(
                        "SELECT DISTINCT post_id, COUNT(*) as change_count 
                         FROM {$table_name} 
                         GROUP BY post_id 
                         ORDER BY change_count DESC"
                    );
                    
                    $current_lender = isset($_GET['lender']) ? intval($_GET['lender']) : 0;
                    
                    foreach ($lenders_with_history as $lender_data) {
                        $post = get_post($lender_data->post_id);
                        if ($post) {
                            printf(
                                '<option value="%d" %s>%s (%d ändringar)</option>',
                                $lender_data->post_id,
                                selected($current_lender, $lender_data->post_id, false),
                                esc_html($post->post_title),
                                $lender_data->change_count
                            );
                        }
                    }
                    ?>
                </select>
                
                <!-- Category Filter -->
                <select name="category" id="filter-by-category">
                    <option value=""><?php _e('Alla kategorier', 'lender-rate-history'); ?></option>
                    <option value="mortgage" <?php selected(isset($_GET['category']) && $_GET['category'] === 'mortgage'); ?>>
                        <?php _e('Bolån', 'lender-rate-history'); ?>
                    </option>
                    <option value="personal_loan" <?php selected(isset($_GET['category']) && $_GET['category'] === 'personal_loan'); ?>>
                        <?php _e('Privatlån', 'lender-rate-history'); ?>
                    </option>
                    <option value="car_loan" <?php selected(isset($_GET['category']) && $_GET['category'] === 'car_loan'); ?>>
                        <?php _e('Billån', 'lender-rate-history'); ?>
                    </option>
                </select>
                
                <!-- Validation Filter -->
                <select name="filter" id="filter-by-validation">
                    <option value=""><?php _e('Alla poster', 'lender-rate-history'); ?></option>
                    <option value="unvalidated" <?php selected(isset($_GET['filter']) && $_GET['filter'] === 'unvalidated'); ?>>
                        <?php _e('Ej validerade', 'lender-rate-history'); ?>
                    </option>
                </select>
                
                <?php submit_button(__('Filtrera', 'lender-rate-history'), 'button', 'filter_action', false); ?>
                
                <?php if ($current_lender): 
                    $post = get_post($current_lender);
                ?>
                <span style="margin-left: 10px;">
                    <strong><?php _e('Visar historik för:', 'lender-rate-history'); ?></strong> 
                    <?php echo esc_html($post->post_title); ?>
                    <a href="<?php echo admin_url('admin.php?page=lrh-history'); ?>" class="button button-small">
                        <?php _e('Visa alla', 'lender-rate-history'); ?>
                    </a>
                </span>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Message for no items
     */
    public function no_items() {
        _e('Inga ränteändringar hittades.', 'lender-rate-history');
    }
}