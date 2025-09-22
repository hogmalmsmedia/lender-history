<?php
/**
 * Shortcode handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Shortcodes {
    
    private $api;
    
    public function __construct() {
        $this->api = new LRH_API();
    }
    
    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('lrh_rate_change', [$this, 'rate_change_shortcode']);
        add_shortcode('lrh_rate_table', [$this, 'rate_table_shortcode']);
        add_shortcode('lrh_rate_comparison', [$this, 'rate_comparison_shortcode']);
        add_shortcode('lrh_rate_sparkline', [$this, 'rate_sparkline_shortcode']);
        add_shortcode('lrh_latest_changes', [$this, 'latest_changes_shortcode']);
		add_shortcode('lrh_interactive_chart', [$this, 'interactive_chart_shortcode']);
		add_shortcode('lrh_external_chart', [$this, 'external_chart_shortcode']);

    }
    
    /**
     * Rate change shortcode
     * Usage: [lrh_rate_change field="snitt_3_man" format="percentage"]
     */
    public function rate_change_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
            'field' => 'snitt_3_man',
            'format' => 'percentage',
            'wrapper' => 'span',
            'class' => ''
        ], $atts, 'lrh_rate_change');
        
        // Validate post_id
        if (!$atts['post_id']) {
            return '<span class="lrh-error">' . __('Ingen post vald', 'lender-rate-history') . '</span>';
        }
        
        $change = $this->api->get_field_change($atts['post_id'], $atts['field'], $atts['format']);
        
        if ($atts['format'] === 'raw') {
            return $change;
        }
        
        // Get the full change data
        $full_change = $this->api->get_field_change($atts['post_id'], $atts['field'], 'full');
        
        if (!$full_change || !is_array($full_change)) {
            return sprintf(
                '<%s class="lrh-rate-change lrh-no-change %s">→ 0 %</%s>',
                esc_attr($atts['wrapper']),
                esc_attr($atts['class']),
                esc_attr($atts['wrapper'])
            );
        }
        
        $change_class = isset($full_change['class']) ? $full_change['class'] : 'lrh-no-change';
        $arrow = isset($full_change['arrow']) ? $full_change['arrow'] : '→';
        $formatted = isset($full_change['formatted']) ? $full_change['formatted'] : '0 %';
        
        $classes = 'lrh-rate-change ' . $change_class;
        if ($atts['class']) {
            $classes .= ' ' . $atts['class'];
        }
        
        return sprintf(
            '<%s class="%s"><span class="lrh-arrow">%s</span> <span class="lrh-value">%s</span></%s>',
            esc_attr($atts['wrapper']),
            esc_attr($classes),
            $arrow,
            $formatted,
            esc_attr($atts['wrapper'])
        );
    }
    
    /**
     * Rate table shortcode
     * Usage: [lrh_rate_table periods="3_man,1_ar,2_ar,3_ar,5_ar" type="snitt"]
     */
    public function rate_table_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
            'periods' => '3_man,1_ar,2_ar,3_ar,5_ar',
            'type' => 'snitt', // snitt or list
            'show_change' => 'yes',
            'class' => ''
        ], $atts, 'lrh_rate_table');
        
        $periods = explode(',', $atts['periods']);
        $post = get_post($atts['post_id']);
        
        if (!$post) {
            return '';
        }
        
        ob_start();
        ?>
        <table class="lrh-rate-table <?php echo esc_attr($atts['class']); ?>">
				<thead>
					<tr>
						<th><?php _e('Bindningstid', 'lender-rate-history'); ?></th>
						<th><?php _e('Ränta', 'lender-rate-history'); ?></th>
						<?php if ($atts['show_change'] === 'yes'): ?>
						<th><?php _e('Förändring', 'lender-rate-history'); ?></th>
						<th><?php _e('Ändrat', 'lender-rate-history'); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
            <tbody>
                <?php foreach ($periods as $period): 
                    $period = trim($period);
                    $field_name = $atts['type'] . '_' . $period;
                    $current_value = get_field($field_name, $atts['post_id']);
                    
                    // Clean and validate the value
                    if ($current_value === '' || $current_value === null || $current_value === false || $current_value === '-') {
                        $display_value = '-';
                    } else {
                        // Handle comma decimal separator and clean the value
                        $clean_value = str_replace(',', '.', $current_value);
                        $clean_value = str_replace(' ', '', $clean_value);
                        $clean_value = str_replace('%', '', $clean_value);
                        
                        if (is_numeric($clean_value)) {
                            $display_value = number_format(floatval($clean_value), 2, ',', ' ') . '%';
                        } else {
                            $display_value = '-';
                        }
                    }
                    
                    $change = $this->api->get_field_change($atts['post_id'], $field_name, 'full');
                ?>
				<?php
				// Hämta senaste ändringsdatum för detta fält
				$database = new LRH_Database();
				$latest_change = $database->get_latest_change($atts['post_id'], $field_name);
				$change_date = $latest_change ? date_i18n('j M. Y', strtotime($latest_change->change_date)) : '-';
				?>
				<tr>
					<td><?php echo $this->format_period_label($period); ?></td>
					<td><?php echo $display_value; ?></td>
					<?php if ($atts['show_change'] === 'yes'): ?>
					<td class="<?php echo esc_attr($change['class']); ?>">
						<span class="lrh-arrow"><?php echo $change['arrow']; ?></span>
						<?php echo $change['percentage']; ?>
					</td>
					<td><?php echo $change_date; ?></td>
					<?php endif; ?>
				</tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Rate comparison shortcode
     * Usage: [lrh_rate_comparison banks="danske-bank,nordea,seb" field="snitt_3_man"]
     */
	public function rate_comparison_shortcode($atts) {
		$atts = shortcode_atts([
			'banks' => '',
			'field' => 'snitt_3_man',
			'show_sparkline' => 'no',
			'days' => 90,  // Lägg till days parameter med default 90
			'class' => ''
		], $atts, 'lrh_rate_comparison');

		if (empty($atts['banks'])) {
			return '';
		}

		// Get bank posts by slug
		$bank_slugs = explode(',', $atts['banks']);
		$banks = [];

		foreach ($bank_slugs as $slug) {
			$post = get_page_by_path(trim($slug), OBJECT, 'langivare');
			if ($post) {
				$banks[] = $post;
			}
		}

		if (empty($banks)) {
			return '';
		}

		ob_start();
		?>
		<div class="lrh-rate-comparison <?php echo esc_attr($atts['class']); ?>">
	<?php foreach ($banks as $bank): 
		$current_value = get_field($atts['field'], $bank->ID);
		$change = $this->api->get_field_change($bank->ID, $atts['field'], 'full');

		// Rensa och validera värdet
		$clean_value = '';
		if ($current_value && $current_value !== '-' && $current_value !== '') {
			$clean_value = str_replace([',', ' ', '%'], ['.', '', ''], $current_value);
			if (!is_numeric($clean_value)) {
				$clean_value = '';
			}
		}
	?>
	<div class="lrh-comparison-item">
		<h4><?php echo esc_html($bank->post_title); ?></h4>
		<div class="lrh-rate-value">
			<?php 
			if ($clean_value && is_numeric($clean_value)) {
				echo number_format(floatval($clean_value), 2, ',', ' ') . '%';
			} else {
				echo '-';
			}
			?>
		</div>
		<div class="lrh-rate-change text-small <?php echo esc_attr($change['class']); ?>">
			<?php echo $change['arrow'] . ' ' . $change['percentage'] . " från föreg. period"; ?>
		</div>
	<?php if ($atts['show_sparkline'] === 'yes'): 
		// Get sparkline data with dates - ANVÄND days PARAMETERN HÄR
		$database = new LRH_Database();
		$history = $database->get_field_history($bank->ID, $atts['field'], $atts['days']); // Ändrat från 90 till $atts['days']

		$values = [];
		$dates = [];

		if (!empty($history)) {
			foreach (array_reverse($history) as $record) {
				if (is_numeric($record->new_value) && $record->new_value > 0) {
					$values[] = floatval($record->new_value);
					$dates[] = date_i18n('j M. Y', strtotime($record->change_date));
				}
			}
		}

		if (!empty($values)):
	?>
	<div class="lrh-sparkline lrh-comparison-sparkline" 
		 data-values="<?php echo implode(',', $values); ?>"
		 data-dates="<?php echo implode('|', $dates); ?>"
		 data-height="40"
		 data-color="#0073aa"
		 style="min-height: 40px; display: block; position: relative;">
		<!-- Placeholder medan SVG laddas -->
		<div class="lrh-sparkline-placeholder" style="height: 40px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px;"></div>
	</div>
	<?php endif; ?>
	<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php
			return ob_get_clean();
		}
    
    /**
     * Sparkline shortcode
     * Usage: [lrh_rate_sparkline field="snitt_3_man" days="30"]
     */
    public function rate_sparkline_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
            'field' => 'snitt_3_man',
            'days' => 720,
            'width' => '100%',
            'height' => 300,
            'color' => '#333',
            'class' => '',
            'show_label' => 'yes'
        ], $atts, 'lrh_rate_sparkline');
        
        // Get full history with dates
        $database = new LRH_Database();
        $history = $database->get_field_history($atts['post_id'], $atts['field'], $atts['days']);
        
        if (empty($history)) {
            return '';
        }
        
        // Prepare data arrays
        $values = [];
        $dates = [];
        $previous_value = null;
        
        foreach (array_reverse($history) as $record) {
            if (is_numeric($record->new_value) && $record->new_value > 0) {
                $values[] = floatval($record->new_value);
                $dates[] = date_i18n('j M. Y', strtotime($record->change_date));
                $previous_value = floatval($record->new_value);
            }
        }
        
        if (empty($values)) {
            return '';
        }
        
        // Get field info for label
        $field_info = LRH_Helpers::parse_field_name($atts['field']);
        $label = $field_info['type_label'] . ' - ' . $field_info['period_label'];
        
        // Get min/max for display
        $min_value = min($values);
        $max_value = max($values);
        $latest_value = end($values);
        
        // Generate unique ID for this sparkline
        $unique_id = 'sparkline-' . uniqid();
        
        $html = '<div class="lrh-sparkline-container">';
        
		if ($atts['show_label'] === 'yes') {
            // Get first and last dates
            $first_date = !empty($dates) ? $dates[0] : '';
            $last_date = !empty($dates) ? end($dates) : '';
            
			$html .= sprintf(
				'<div class="lrh-sparkline-info">
					<span class="lrh-sparkline-label">%s (%s - %s)</span>
					<span class="lrh-sparkline-values">
						Min: <strong>%s%%</strong> | Max: <strong>%s%%</strong> | Senaste: <strong>%s%%</strong>
					</span>
				</div>',
				esc_html($label),
				esc_html($first_date),
				esc_html($last_date),
				number_format($min_value, 2, ',', ' '),
				number_format($max_value, 2, ',', ' '),
				number_format($latest_value, 2, ',', ' ')
			);
        }
        
        $html .= sprintf(
            '<span id="%s" class="lrh-sparkline %s" 
                  data-values="%s" 
                  data-dates="%s"
                  data-width="%s" 
                  data-height="%s" 
                  data-color="%s"
                  data-field="%s"
                  data-days="%s"
                  style="display: inline-block; width: %s;"></span>',
            esc_attr($unique_id),
            esc_attr($atts['class']),
            implode(',', $values),
            implode('|', $dates),
            esc_attr($atts['width']),
            esc_attr($atts['height']),
            esc_attr($atts['color']),
            esc_attr($label),
            esc_attr($atts['days']),
            esc_attr($atts['width'])
        );
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Latest changes shortcode
     * Usage: [lrh_latest_changes limit="10" category="mortgage"]
     */
    public function latest_changes_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 10,
            'category' => 'mortgage',
            'show_time' => 'yes',
            'class' => ''
        ], $atts, 'lrh_latest_changes');
        
        $changes = $this->api->get_recent_changes_table([
            'limit' => $atts['limit'],
            'field_category' => $atts['category'],
            'format' => 'array'
        ]);
        
        if (empty($changes)) {
            return '<p>' . __('Inga ändringar att visa.', 'lender-rate-history') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="lrh-latest-changes <?php echo esc_attr($atts['class']); ?>">
            <table class="lrh-changes-table">
                <thead>
                    <tr>
                        <th><?php _e('Bank', 'lender-rate-history'); ?></th>
                        <th><?php _e('Räntetyp', 'lender-rate-history'); ?></th>
                        <th><?php _e('Nuvarande', 'lender-rate-history'); ?></th>
                        <th><?php _e('Förändring', 'lender-rate-history'); ?></th>
                        <?php if ($atts['show_time'] === 'yes'): ?>
                        <th><?php _e('När', 'lender-rate-history'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($changes as $change): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($change['bank_url']); ?>">
                                <?php echo esc_html($change['bank']); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($change['field']); ?></td>
                        <td><?php echo esc_html($change['current_rate']); ?></td>
                        <td class="<?php echo esc_attr($change['change_class']); ?>">
                            <?php echo esc_html($change['change']); ?>
                        </td>
                        <?php if ($atts['show_time'] === 'yes'): ?>
                        <td><?php echo esc_html($change['time_ago']); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
	
	
	
    /**
     * Interaktiv graf med filter
     * Usage: [lrh_interactive_chart]
     */	
	
public function interactive_chart_shortcode($atts) {
    // Ladda scripts
    wp_enqueue_script('chartjs');
	wp_enqueue_script('lrh-chart-theme'); // Lägg till denna rad
    wp_enqueue_script('lrh-interactive-chart');
    
    $atts = shortcode_atts([
        'banks' => '',
        'periods' => '3_man,1_ar,2_ar,3_ar,5_ar,10_ar',
        'types' => 'snitt,list',
        'default_type' => 'snitt',
        'days' => 365,
        'height' => 400,
        'show_average' => 'yes',
        'default_period' => '3_man',
        'class' => ''
    ], $atts, 'lrh_interactive_chart');
    
    // VIKTIGT: Hämta banker här
    if ($atts['banks'] === 'all' || empty($atts['banks'])) {
        $banks = get_posts([
            'post_type' => 'langivare',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
    } else {
        $bank_slugs = explode(',', $atts['banks']);
        $banks = [];
        foreach ($bank_slugs as $slug) {
            $post = get_page_by_path(trim($slug), OBJECT, 'langivare');
            if ($post) {
                $banks[] = $post;
            }
        }
    }
    
    if (empty($banks)) {
        return '<p>Inga banker hittades.</p>';
    }
    
    $unique_id = 'chart-' . wp_rand(1000, 9999);
    $periods = explode(',', $atts['periods']);
    $types = explode(',', $atts['types']);
    
    // Förbered data
    $chart_data = [];
    $database = new LRH_Database();
    
    foreach ($banks as $bank) {
        $bank_data = [
            'id' => $bank->ID,
            'name' => $bank->post_title,
            'slug' => $bank->post_name,
            'types' => []
        ];
        
        foreach ($types as $type) {
            $type = trim($type);
            $bank_data['types'][$type] = [];
            
            foreach ($periods as $period) {
                $period = trim($period);
                $field = $type . '_' . $period;
                
                $history = $database->get_field_history($bank->ID, $field, $atts['days']);
                
                $values = [];
                $dates = [];
                
                if (!empty($history)) {
                    foreach (array_reverse($history) as $record) {
                        if (is_numeric($record->new_value) && $record->new_value > 0) {
                            $values[] = floatval($record->new_value);
                            $dates[] = date('Y-m-d', strtotime($record->change_date));
                        }
                    }
                }
                
                $current_value = get_field($field, $bank->ID);
                if (is_numeric($current_value) && $current_value > 0) {
                    if (empty($values) || $values[count($values) - 1] != floatval($current_value)) {
                        $values[] = floatval($current_value);
                        $dates[] = date('Y-m-d');
                    }
                }
                
                $bank_data['types'][$type][$period] = [
                    'values' => $values,
                    'dates' => $dates
                ];
            }
        }
        
        $chart_data[] = $bank_data;
    }
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr($unique_id); ?>" class="lrh-interactive-chart <?php echo esc_attr($atts['class']); ?>">
        
        <!-- Huvudkontroller -->
		<div class="lrh-main-controls">

			<!-- Bindningstid -->
			<div class="lrh-period-section">
				<h4>Bindningstid</h4>
				<div class="lrh-period-grid">
					<?php foreach ($periods as $period): 
						$period = trim($period);
					?>
					<button type="button" 
							class="lrh-period-btn <?php echo $period === $atts['default_period'] ? 'active' : ''; ?>" 
							data-period="<?php echo esc_attr($period); ?>">
						<?php echo $this->format_period_label($period); ?>
					</button>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Tidsperiod -->
			<div class="lrh-time-section">
				<h4>Tidsperiod: <span class="time-display">Senaste <?php
					$days = intval($atts['days']);
					if ($days > 365) {
						$years = round($days / 365, 1);
						echo $years == intval($years) ? intval($years) . ' åren' : str_replace('.', ',', $years) . ' åren';
					} else {
						echo round($days / 30) . ' månaderna';
					}
				?></span></h4>
				<div class="lrh-slider-container">
					<input type="range"
						   class="lrh-time-slider"
						   min="90"
						   max="<?php echo max(730, intval($atts['days'])); ?>"
						   value="<?php echo esc_attr($atts['days']); ?>"
						   step="30">
					<div class="slider-labels">
						<span>3 mån</span>
						<span><?php
							$max_days = max(730, intval($atts['days']));
							if ($max_days <= 730) {
								echo '2 år';
							} else {
								$years = round($max_days / 365);
								echo $years . ' år';
							}
						?></span>
					</div>
				</div>
			</div>
		</div>
        
        <!-- Typ-växlare (Snitt/List) -->
        <?php if (count($types) > 1): ?>
        <div class="lrh-type-switcher">
            <?php foreach ($types as $type): 
                $type = trim($type);
            ?>
            <button type="button" 
                    class="lrh-type-btn <?php echo $type === $atts['default_type'] ? 'active' : ''; ?>"
                    data-type="<?php echo esc_attr($type); ?>">
                <?php echo $type === 'snitt' ? 'Snitträntor' : 'Listräntor'; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Graf -->
        <div class="lrh-chart-wrapper">
            <canvas id="canvas-<?php echo esc_attr($unique_id); ?>"></canvas>
        </div>
        
        <!-- Custom legend -->
        <div class="lrh-custom-legend"></div>
        
        <!-- Aktuella värden -->
        <div class="lrh-current-values">
            <h4>Aktuella värden</h4>
            <div class="lrh-values-cards"></div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initLRHInteractiveChart === 'function') {
            initLRHInteractiveChart('<?php echo esc_js($unique_id); ?>', <?php echo json_encode($chart_data); ?>, {
                defaultPeriod: '<?php echo esc_js($atts['default_period']); ?>',
                defaultType: '<?php echo esc_js($atts['default_type']); ?>',
                showAverage: <?php echo $atts['show_average'] === 'yes' ? 'true' : 'false'; ?>,
                height: <?php echo intval($atts['height']); ?>
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
	
    
    /**
     * Format period label
     */
    private function format_period_label($period) {
        $labels = [
            '3_man' => __('3 månader', 'lender-rate-history'),
            '1_ar' => __('1 år', 'lender-rate-history'),
            '2_ar' => __('2 år', 'lender-rate-history'),
            '3_ar' => __('3 år', 'lender-rate-history'),
            '5_ar' => __('5 år', 'lender-rate-history'),
            '7_ar' => __('7 år', 'lender-rate-history'),
            '10_ar' => __('10 år', 'lender-rate-history'),
        ];
        
        return isset($labels[$period]) ? $labels[$period] : $period;
    }
	
	/**
 * External source chart shortcode - REN VERSION
 * Samma stil som interactive chart
 */
public function external_chart_shortcode($atts) {
    // Ladda Chart.js och tema
    wp_enqueue_script('chartjs');
    wp_enqueue_script('lrh-chart-theme');

    $atts = shortcode_atts([
        'source' => '',
        'days' => 'all',
        'title' => '',
        'height' => 400,
        'show_values' => 'yes',
        'class' => ''
    ], $atts, 'lrh_external_chart');

    if (empty($atts['source'])) {
        return '<p class="text-small">Ingen källa angiven.</p>';
    }

    // Hämta källinformation
    $external_sources = new LRH_External_Sources();
    $source = $external_sources->get_source($atts['source']);

    if (!$source) {
        return '<p class="text-small">Källan hittades inte.</p>';
    }

    // Format och suffix
    $format = $source['value_format'] ?? 'percentage';
    $suffix = $source['value_suffix'] ?? '%';
    $decimals = $source['decimals'] ?? 2;
    
    if ($format === 'number') {
        $suffix = $source['value_suffix'] ?? '';
    } elseif ($format === 'currency') {
        $suffix = $source['value_suffix'] ?? 'kr';
    } elseif ($format === 'percentage') {
        $suffix = '%';
    }

    // Titel
    if (empty($atts['title'])) {
        $atts['title'] = $source['display_name'] ?? $source['source_name'] ?? $atts['source'];
    }

    // Hämta historik
    global $wpdb;
    $table_name = $wpdb->prefix . LRH_TABLE_NAME;

    if ($atts['days'] === 'all' || $atts['days'] == 0) {
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(change_date) as date,
                new_value as value,
                old_value,
                change_date
             FROM {$table_name}
             WHERE source_id = %s
             ORDER BY change_date ASC",
            $atts['source']
        );
    } else {
        $days = intval($atts['days']);
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(change_date) as date,
                new_value as value,
                old_value,
                change_date
             FROM {$table_name}
             WHERE source_id = %s
             AND change_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY change_date ASC",
            $atts['source'],
            $days
        );
    }

    $results = $wpdb->get_results($sql);

    if (empty($results)) {
        return '<p class="text-small">Ingen data att visa för denna källa.</p>';
    }

    // Förbered data
    $values = [];
    $dates = [];
    $labels = [];

    foreach ($results as $row) {
        $values[] = floatval($row->value);
        $dates[] = $row->date;
        
        // Skapa labels med år för första, sista och årsskiften
        $date = new DateTime($row->date);
        $day = $date->format('j');
        $month = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'][$date->format('n') - 1];
        $year = $date->format('Y');
        
        $labels[] = $day . ' ' . $month;
    }

    // Statistik
    $min_value = min($values);
    $max_value = max($values);
    $latest_value = end($values);
    $first_value = reset($values);
    $values_count = count($values);
    $previous_value = $values_count > 1 ? $values[$values_count - 2] : $first_value;
    $total_change = $latest_value - $first_value;
    $latest_change = $latest_value - $previous_value;
    $change_class = $latest_change > 0 ? 'lrh-increase' : ($latest_change < 0 ? 'lrh-decrease' : 'lrh-no-change');
    $arrow = $latest_change > 0 ? '↑' : ($latest_change < 0 ? '↓' : '→');

    $unique_id = 'external-chart-' . uniqid();

    // Formatera värden
    $format_value = function($val) use ($format, $suffix, $decimals) {
        if ($format === 'currency') {
            return number_format($val, 0, ',', ' ') . ' ' . $suffix;
        } elseif ($format === 'number') {
            return number_format($val, $decimals, ',', ' ') . ($suffix ? ' ' . $suffix : '');
        } else {
            return number_format($val, $decimals, ',', ' ') . $suffix;
        }
    };

    // Tidsperiod
    $first_date = reset($dates);
    $last_date = end($dates);
    $days_span = (strtotime($last_date) - strtotime($first_date)) / 86400;
    $period_text = $days_span > 365 ? round($days_span / 365, 1) . ' år' : round($days_span / 30) . ' månader';

    ob_start();
    ?>

    <div class="lrh-external-chart-container <?php echo esc_attr($atts['class']); ?>">
        
        <!-- Graf med ren styling -->
        <div class="lrh-chart-wrapper" style="position: relative; height: <?php echo intval($atts['height']); ?>px; background: white; border-radius: 8px; padding: 15px; border: 1px solid #e5e7eb;">
            <canvas id="<?php echo esc_attr($unique_id); ?>"></canvas>
        </div>

        <?php if ($atts['show_values'] === 'yes'): ?>
        <div class="lrh-chart-stats grid-4 gap-s" style="margin-top: 20px;">
            <div class="lrh-stat-card" style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e5e7eb;">
                <span class="text-xs" style="display: block; color: #6b7280; margin-bottom: 8px;">Nuvarande</span>
                <span class="headline-h5" style="display: block; color: #111827;"><?php echo $format_value($latest_value); ?></span>
            </div>

            <div class="lrh-stat-card" style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e5e7eb;">
                <span class="text-xs" style="display: block; color: #6b7280; margin-bottom: 8px;">Senaste förändring</span>
                <span class="headline-h5 <?php echo $change_class; ?>" style="display: block;">
                    <?php echo $arrow; ?> <?php echo $format_value(abs($latest_change)); ?>
                </span>
                <?php if ($values_count > 1): ?>
                <span class="text-xs" style="display: block; color: #9ca3af; margin-top: 4px;">
                    Från <?php echo $format_value($previous_value); ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="lrh-stat-card" style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e5e7eb;">
                <span class="text-xs" style="display: block; color: #6b7280; margin-bottom: 8px;">Total (<?php echo $period_text; ?>)</span>
                <span class="headline-h5 <?php echo $total_change > 0 ? 'lrh-increase' : ($total_change < 0 ? 'lrh-decrease' : 'lrh-no-change'); ?>" style="display: block;">
                    <?php 
                    $total_arrow = $total_change > 0 ? '↑' : ($total_change < 0 ? '↓' : '→');
                    echo $total_arrow; ?> <?php echo $format_value(abs($total_change)); ?>
                </span>
                <span class="text-xs" style="display: block; color: #9ca3af; margin-top: 4px;">
                    Från <?php echo $format_value($first_value); ?>
                </span>
            </div>

            <div class="lrh-stat-card" style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e5e7eb;">
                <span class="text-xs" style="display: block; color: #6b7280; margin-bottom: 8px;">Min / Max</span>
                <span class="body-text-s" style="display: block; color: #111827;">
                    <?php echo $format_value($min_value); ?> / <?php echo $format_value($max_value); ?>
                </span>
                <span class="text-xs" style="display: block; color: #9ca3af; margin-top: 4px;">
                    <?php echo count($values); ?> datapunkter
                </span>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <style>
    .lrh-stat-value.lrh-increase { color: #ef4444; }
    .lrh-stat-value.lrh-decrease { color: #10b981; }
    .lrh-stat-value.lrh-no-change { color: #6b7280; }
    .headline-h5.lrh-increase { color: #ef4444; }
    .headline-h5.lrh-decrease { color: #10b981; }
    .headline-h5.lrh-no-change { color: #6b7280; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined' || typeof LRHChartTheme === 'undefined') return;

        const ctx = document.getElementById('<?php echo esc_js($unique_id); ?>').getContext('2d');
        
        // Original dates för tooltip
        const originalDates = <?php echo json_encode($dates); ?>;
        
        // Skapa labels med år där det behövs
        const labels = LRHChartTheme.createLabelsWithYears(originalDates);
        
        // Format funktion
        const formatValue = function(value) {
            <?php if ($format === 'currency'): ?>
                return Math.round(value) + ' <?php echo esc_js($suffix); ?>';
            <?php elseif ($format === 'number'): ?>
                return value.toFixed(<?php echo $decimals; ?>).replace('.', ',') + '<?php echo $suffix ? ' ' . esc_js($suffix) : ''; ?>';
            <?php else: ?>
                return value.toFixed(<?php echo $decimals; ?>).replace('.', ',') + '<?php echo esc_js($suffix); ?>';
            <?php endif; ?>
        };
        
        // Hämta tema-options
        const chartOptions = LRHChartTheme.getDefaultOptions({
            maxTicksLimit: <?php echo count($values) > 20 ? 10 : count($values); ?>,
            plugins: {
                title: {
                    display: true,
                    text: '<?php echo esc_js($atts['title']); ?>',
                    font: {
                        family: 'inherit',
                        size: 16,
                        weight: '600'
                    },
                    color: '#111827',
                    padding: {
                        top: 0,
                        bottom: 20
                    }
                },
                tooltip: {
                    ...LRHChartTheme.getDefaultOptions().plugins.tooltip,
                    callbacks: {
                        title: function(tooltipItems) {
                            if (tooltipItems.length > 0) {
                                const index = tooltipItems[0].dataIndex;
                                const date = new Date(originalDates[index]);
                                const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                                              'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                                return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
                            }
                            return '';
                        },
                        label: function(context) {
                            return '<?php echo esc_js($atts['title']); ?>: ' + formatValue(context.parsed.y);
                        },
                        afterLabel: function(context) {
                            if (context.dataIndex > 0) {
                                const currentValue = context.parsed.y;
                                const previousValue = context.dataset.data[context.dataIndex - 1];
                                if (previousValue !== null && previousValue !== undefined) {
                                    const change = currentValue - previousValue;
                                    const arrow = change > 0 ? '↑' : (change < 0 ? '↓' : '→');
                                    return arrow + ' ' + Math.abs(change).toFixed(2).replace('.', ',') + ' p.e.';
                                }
                            }
                            return null;
                        }
                    }
                }
            },
            scales: {
                ...LRHChartTheme.getDefaultOptions().scales,
                y: {
                    ...LRHChartTheme.getDefaultOptions().scales.y,
                    ticks: {
                        ...LRHChartTheme.getDefaultOptions().scales.y.ticks,
                        callback: function(value) {
                            return formatValue(value);
                        }
                    }
                }
            }
        });
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    LRHChartTheme.createDataset(
                        '<?php echo esc_js($atts['title']); ?>',
                        <?php echo json_encode($values); ?>,
                        0,
                        {
                            color: LRHChartTheme.colors.primary,
                            fill: false, // Ingen fyllning, ren linje
                            tension: 0.2,
                            pointRadius: <?php echo count($values) > 50 ? 0 : 2; ?>
                        }
                    )
                ]
            },
            options: chartOptions
        });
    });
    </script>
    <?php

    return ob_get_clean();
}

}