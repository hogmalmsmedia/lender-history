/**
 * Lender Rate History - Public JavaScript
 * SVG Sparkline version - Optimized
 */

(function($) {
    'use strict';

    var resizeTimer;

    $(document).ready(function() {
        initializeComponents();
        setupEventListeners();
    });

    /**
     * Initialize all components
     */
    function initializeComponents() {
        // Initialize SVG sparklines
        initializeSparklines();
        
        // Setup auto-refresh for rate changes
        setupAutoRefresh();
        
        // Load AJAX rate data
        loadAjaxRates();
        
        // Setup tooltips
        setupTooltips();
    }

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Interactive comparison tool
    $('.lrh-comparison-selector').on('change', updateComparison);

    // Window resize handler - ENDAST vid faktisk breddförändring
    var lastWidth = $(window).width();
    
    $(window).on('resize', function() {
        var newWidth = $(window).width();
        
        // Rita bara om om bredden faktiskt ändrats (inte vid scroll)
        if (newWidth !== lastWidth) {
            lastWidth = newWidth;
            
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                $('.lrh-sparkline').each(function() {
                    var $this = $(this);
                    // Bara rita om om elementets bredd faktiskt ändrats
                    var currentWidth = $this.parent().width();
                    var lastRenderedWidth = $this.data('last-rendered-width');
                    
                    if (!lastRenderedWidth || Math.abs(currentWidth - lastRenderedWidth) > 10) {
                        $this.empty();
                        $this.data('last-rendered-width', currentWidth);
                    }
                });
                initializeSparklines();
            }, 250);
        }
    });
}

    /**
     * Initialize SVG sparklines
     */
    function initializeSparklines() {
        setTimeout(function() {
            $('.lrh-sparkline').each(function() {
                var $sparkline = $(this);
                var values = $sparkline.data('values');
                var dates = $sparkline.data('dates');
                
                if (!values) {
                    $sparkline.html('<div class="lrh-loading">Laddar data...</div>');
                    return;
                }
                
                var valueArray = values.toString().split(',').map(Number);
                var dateArray = dates ? dates.toString().split('|') : [];
                
                setTimeout(function() {
                    // Get container width
                    var containerElement = $sparkline.closest('.lrh-sparkline-container');
                    var availableWidth = containerElement.width();
                    
                    if (!containerElement.length || availableWidth < 100) {
                        containerElement = $sparkline.closest('.lrh-comparison-item');
                        availableWidth = containerElement.width();
                    }
                    
                    if (!availableWidth || availableWidth < 100) {
                        availableWidth = $sparkline.parent().width();
                    }
                    
                    var sparklineWidth;
                    if ($sparkline.closest('.lrh-comparison-item').length) {
                        sparklineWidth = Math.floor(availableWidth);
                        if (sparklineWidth < 150) sparklineWidth = 150;
                    } else {
                        sparklineWidth = Math.floor(availableWidth);
                        if (sparklineWidth < 200) sparklineWidth = 200;
                        if (sparklineWidth > 800) sparklineWidth = 800;
                    }
                    
                    var height = $sparkline.data('height') || 30;
                    var color = $sparkline.data('color') || '#333';
                    
                    createSVGSparkline($sparkline, valueArray, dateArray, {
                        width: sparklineWidth,
                        height: height,
                        color: color
                    });
                    
                }, 30);
            });
        }, 60);
    }

    /**
     * Create SVG sparkline
     */
	function createSVGSparkline($element, values, dates, options) {
		var width = options.width;
		var height = options.height;
		var color = options.color;

		var padding = 5;
		var innerWidth = width - (padding * 2);
		var innerHeight = height - (padding * 2);

		var min = Math.min(...values);
		var max = Math.max(...values);
		var range = max - min || 1;

		var points = values.map((val, i) => {
			var x = padding + (i / (values.length - 1)) * innerWidth;
			var y = padding + innerHeight - ((val - min) / range) * innerHeight;
			return { x: x, y: y, value: val, date: dates[i] || '' };
		});

		var pathData = points.map((p, i) => {
			return (i === 0 ? 'M' : 'L') + p.x + ',' + p.y;
		}).join(' ');

		var svgHtml = `
			<svg class="lrh-svg-sparkline" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
				<path d="${pathData}" 
					  fill="none" 
					  stroke="${color}" 
					  stroke-width="2" 
					  stroke-linejoin="round"
					  stroke-linecap="round"/>

				<!-- Highlight segment (initially hidden) -->
				<path class="lrh-highlight-segment"
					  fill="none"
					  stroke="#ff6b6b"
					  stroke-width="3"
					  stroke-linecap="round"
					  opacity="0"/>

				<g class="lrh-sparkline-hover-areas">`;

		// Create hover areas
		points.forEach((point, i) => {
			var areaWidth = innerWidth / (values.length - 1);
			var areaX = point.x - areaWidth / 2;

			if (i === 0) {
				areaX = 0;
				areaWidth = point.x + areaWidth / 2;
			} else if (i === values.length - 1) {
				areaX = point.x - areaWidth / 2;
				areaWidth = width - areaX;
			}

			svgHtml += `
				<rect x="${areaX}" 
					  y="0" 
					  width="${areaWidth}" 
					  height="${height}"
					  fill="transparent"
					  class="lrh-sparkline-hover-area"
					  data-index="${i}"
					  data-x="${point.x}"
					  data-y="${point.y}"
					  style="cursor: pointer;"/>`;
		});

		svgHtml += `</g>
				<g class="lrh-sparkline-points">`;

		// Add visible points
		points.forEach((point, i) => {
			var isMin = values[i] === min;
			var isMax = values[i] === max;
			var pointColor = isMin ? '#00a32a' : (isMax ? '#d63638' : '#0073aa');
			var pointRadius = isMin || isMax ? 4 : 3;

			svgHtml += `
				<circle cx="${point.x}" 
						cy="${point.y}" 
						r="${pointRadius}" 
						fill="${pointColor}"
						stroke="white"
						stroke-width="1"
						opacity="${isMin || isMax ? 1 : 0}"
						class="lrh-sparkline-point"
						data-index="${i}"
						style="pointer-events: none;">
				</circle>`;
		});

		svgHtml += `
				</g>
			</svg>`;
		$element.data('last-rendered-width', options.width);
		$element.html(svgHtml);

		// Pass points data to setupSVGTooltips
		setupSVGTooltips($element, values, dates, points);
	}

    /**
     * Setup SVG tooltips
     */
	function setupSVGTooltips($element, values, dates, points) {
		var $svg = $element.find('svg');
		var $tooltip = null;
		var currentIndex = -1;
		var touchTimeout = null;
		var lastEventType = null;

		// Show tooltip function
		function showTooltip(index, pageX, pageY) {
			currentIndex = index;

			// Hide all points except min/max
			$svg.find('.lrh-sparkline-point').each(function() {
				var pointIndex = parseInt($(this).data('index'));
				var isMin = values[pointIndex] === Math.min(...values);
				var isMax = values[pointIndex] === Math.max(...values);
				$(this).attr('opacity', isMin || isMax ? '1' : '0');
			});

			// Show current and previous point
			$svg.find('.lrh-sparkline-point[data-index="' + index + '"]').attr('opacity', '1');

			// Highlight segment between previous and current point
			var $highlightPath = $svg.find('.lrh-highlight-segment');
			if (index > 0) {
				$svg.find('.lrh-sparkline-point[data-index="' + (index - 1) + '"]').attr('opacity', '1');

				// Create path for highlight segment
				var prevPoint = points[index - 1];
				var currPoint = points[index];
				var highlightData = `M${prevPoint.x},${prevPoint.y} L${currPoint.x},${currPoint.y}`;

				// Determine color based on change
				var change = values[index] - values[index - 1];
				var highlightColor = change > 0 ? '#dc2626' : (change < 0 ? '#059669' : '#6b7280');

				$highlightPath
					.attr('d', highlightData)
					.attr('stroke', highlightColor)
					.attr('opacity', '1');
			} else {
				$highlightPath.attr('opacity', '0');
			}

			var value = values[index];
			var date = dates[index] || '';

			// Remove existing tooltip
			$('.lrh-svg-tooltip').remove();

			// Build tooltip
			var tooltipHtml = '<div class="lrh-svg-tooltip">';
			if (date) {
				tooltipHtml += '<div class="lrh-tooltip-date">' + date + '</div>';
			}
			tooltipHtml += '<div class="lrh-tooltip-value">' + value.toFixed(2).replace('.', ',') + '%</div>';

			if (index > 0) {
				var prevValue = values[index - 1];
				var prevDate = dates[index - 1] || '';
				var change = value - prevValue;
				var changeClass = change > 0 ? 'positive' : (change < 0 ? 'negative' : 'neutral');
				var arrow = change > 0 ? '↑' : (change < 0 ? '↓' : '→');

				// Format the change text with previous date
				var changeAmount = Math.abs(change).toFixed(2).replace('.', ',');
				var changeText = arrow + ' ' + changeAmount + '%';

				// Add the previous date if available
				if (prevDate) {
					changeText += ' från ' + prevDate;
				} else {
					changeText += ' från föreg. period';
				}

				tooltipHtml += '<div class="lrh-tooltip-change ' + changeClass + '">' + changeText + '</div>';
			}
			tooltipHtml += '</div>';

			$tooltip = $(tooltipHtml).appendTo('body');

			// Position tooltip
			var tooltipWidth = $tooltip.outerWidth();
			var tooltipHeight = $tooltip.outerHeight();
			var windowWidth = $(window).width();

			var left = pageX - (tooltipWidth / 2);
			var top = pageY - tooltipHeight - 20;

			if (left < 10) left = 10;
			if (left + tooltipWidth > windowWidth - 10) {
				left = windowWidth - tooltipWidth - 10;
			}
			if (top < 10) {
				top = pageY + 20;
			}

			$tooltip.css({
				left: left + 'px',
				top: top + 'px'
			});
		}

		// Hide tooltip function
		function hideTooltip() {
			$('.lrh-svg-tooltip').remove();

			// Hide highlight segment
			$svg.find('.lrh-highlight-segment').attr('opacity', '0');

			// Hide all points except min/max
			$svg.find('.lrh-sparkline-point').each(function() {
				var index = parseInt($(this).data('index'));
				var isMin = values[index] === Math.min(...values);
				var isMax = values[index] === Math.max(...values);
				$(this).attr('opacity', isMin || isMax ? '1' : '0');
			});
			currentIndex = -1;
		}

		// Desktop mouse events
		$svg.on('mouseenter', '.lrh-sparkline-hover-area', function(e) {
			if (lastEventType !== 'touch') {
				lastEventType = 'mouse';
				var $area = $(this);
				var index = parseInt($area.data('index'));

				var svgOffset = $svg.offset();
				var x = parseFloat($area.data('x')) + svgOffset.left;
				var y = parseFloat($area.data('y')) + svgOffset.top;

				showTooltip(index, x, y);
			}
		});

		$svg.on('mouseleave', function(e) {
			if (lastEventType === 'mouse') {
				hideTooltip();
				lastEventType = null;
			}
		});

		// Touch events
		$svg.on('touchstart', function(e) {
			lastEventType = 'touch';
			var touch = e.originalEvent.touches[0];
			var svgRect = this.getBoundingClientRect();
			var x = touch.clientX - svgRect.left;
			var width = svgRect.width;

			var index = Math.round((x / width) * (values.length - 1));
			index = Math.max(0, Math.min(values.length - 1, index));

			showTooltip(index, touch.clientX, touch.clientY);

			clearTimeout(touchTimeout);
			touchTimeout = setTimeout(hideTooltip, 10000);

			e.stopPropagation();
		});

		$svg.on('touchmove', function(e) {
			var touch = e.originalEvent.touches[0];
			var svgRect = this.getBoundingClientRect();
			var x = touch.clientX - svgRect.left;
			var width = svgRect.width;

			var index = Math.round((x / width) * (values.length - 1));
			index = Math.max(0, Math.min(values.length - 1, index));

			if (index !== currentIndex) {
				showTooltip(index, touch.clientX, touch.clientY);
				clearTimeout(touchTimeout);
				touchTimeout = setTimeout(hideTooltip, 10000);
			}

			e.preventDefault();
		});

		$svg.on('touchend', function() {
			clearTimeout(touchTimeout);
			touchTimeout = setTimeout(hideTooltip, 5000);
		});

		// Reset event type after mouse move
		$svg.on('mousemove', function() {
			if (lastEventType === 'touch') {
				setTimeout(function() {
					lastEventType = null;
				}, 500);
			}
		});

		// Close on scroll
		$(window).on('scroll', hideTooltip);

		// Close on click/touch outside
		$(document).on('click touchstart', function(e) {
			if (!$(e.target).closest('.lrh-svg-sparkline, .lrh-svg-tooltip').length) {
				hideTooltip();
			}
		});
	}

    /**
     * Setup auto-refresh functionality
     */
    function setupAutoRefresh() {
        if ($('.lrh-auto-refresh').length > 0) {
            setInterval(refreshRateChanges, 60000);
        }
    }

    /**
     * Load AJAX rate data
     */
    function loadAjaxRates() {
        $('.lrh-ajax-rate').each(function() {
            var $element = $(this);
            var postId = $element.data('post-id');
            var field = $element.data('field');
            var format = $element.data('format') || 'percentage';
            
            if (!lrh_public || !lrh_public.ajax_url) {
                return;
            }
            
            $.ajax({
                url: lrh_public.ajax_url,
                type: 'GET',
                data: {
                    action: 'lrh_get_rate_change',
                    post_id: postId,
                    field: field,
                    format: format,
                    nonce: lrh_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $element.html(response.data);
                    }
                },
                error: function() {
                    $element.addClass('lrh-error').text('Kunde ej ladda data');
                }
            });
        });
    }

    /**
     * Setup tooltips
     */
    function setupTooltips() {
        $('.lrh-rate-change').each(function() {
            var $this = $(this);
            var tooltipText = $this.data('tooltip');
            
            if (tooltipText) {
                $this.addClass('lrh-tooltip');
                $this.append('<span class="lrh-tooltip-content">' + tooltipText + '</span>');
            }
        });
    }

    /**
     * Refresh rate changes via AJAX
     */
    function refreshRateChanges() {
        $('.lrh-auto-refresh').each(function() {
            var $container = $(this);
            var limit = $container.data('limit') || 10;
            var category = $container.data('category') || 'mortgage';
            
            if (!lrh_public || !lrh_public.ajax_url) {
                return;
            }
            
            $.ajax({
                url: lrh_public.ajax_url,
                type: 'GET',
                data: {
                    action: 'lrh_get_recent_changes',
                    limit: limit,
                    category: category,
                    nonce: lrh_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateChangesTable($container, response.data);
                    }
                },
                error: function() {
                    $container.html('<div class="lrh-error">Kunde ej ladda ändringar</div>');
                }
            });
        });
    }

    /**
     * Update changes table with new data
     */
    function updateChangesTable($container, data) {
        if (!data || !data.length) {
            $container.html('<div class="lrh-loading">Inga ändringar att visa</div>');
            return;
        }

        var html = '<table class="lrh-changes-table">';
        html += '<thead><tr>';
        html += '<th>Bank</th>';
        html += '<th>Räntetyp</th>';
        html += '<th>Nuvarande</th>';
        html += '<th>Förändring</th>';
        html += '<th>När</th>';
        html += '</tr></thead><tbody>';
        
        $.each(data, function(index, change) {
            html += '<tr>';
            html += '<td><a href="' + (change.bank_url || '#') + '">' + (change.bank || 'N/A') + '</a></td>';
            html += '<td>' + (change.field || 'N/A') + '</td>';
            html += '<td>' + (change.current_rate || 'N/A') + '</td>';
            html += '<td class="' + (change.change_class || '') + '">' + (change.change || 'N/A') + '</td>';
            html += '<td>' + (change.time_ago || 'N/A') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        $container.html(html).hide().fadeIn(500);
    }

    /**
     * Update comparison display
     */
    function updateComparison() {
        var selectedBanks = [];
        $('.lrh-comparison-selector:checked').each(function() {
            selectedBanks.push($(this).val());
        });
        
        if (selectedBanks.length === 0) {
            return;
        }
        
        var field = $('#lrh-comparison-field').val();
        var days = $('#lrh-comparison-days').val() || 30;
        
        if (!lrh_public || !lrh_public.ajax_url) {
            return;
        }
        
        $.ajax({
            url: lrh_public.ajax_url,
            type: 'POST',
            data: {
                action: 'lrh_get_comparison',
                post_ids: selectedBanks,
                field: field,
                days: days,
                nonce: lrh_public.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderComparison(response.data);
                }
            },
            error: function() {
                $('#lrh-comparison-results').html('<div class="lrh-error">Kunde ej ladda jämförelse</div>');
            }
        });
    }

    /**
     * Render comparison results
     */
    function renderComparison(data) {
        var $container = $('#lrh-comparison-results');
        
        if (!data || !data.length) {
            $container.html('<div class="lrh-loading">Inga data att visa</div>');
            return;
        }
        
        var html = '<div class="lrh-rate-comparison">';
        
        $.each(data, function(index, bank) {
            html += '<div class="lrh-comparison-item">';
            html += '<h4>' + (bank.label || 'N/A') + '</h4>';
            html += '<div class="lrh-rate-value">' + (bank.current_rate || 'N/A') + '</div>';
            html += '<div class="lrh-rate-change ' + (bank.change_class || '') + '">';
            html += '<span class="lrh-arrow">' + (bank.arrow || '→') + '</span> ';
            html += (bank.change || 'N/A');
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        $container.html(html);
    }
	
	
	/**
	 * INTERAKTIV GRAF
	 */
	function initializeInteractiveChart(chartId, data) {
    var container = document.getElementById(chartId);
    var canvas = document.getElementById('chart-' + chartId);
    var ctx = canvas.getContext('2d');
    
    // Chart.js konfiguration
    var chartColors = [
        '#0073aa', '#dc2626', '#059669', '#f59e0b', '#8b5cf6',
        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
    ];
    
    var chart = null;
    var currentPeriod = data.currentPeriod;
    var activeBanks = new Set();
    
    // Initial setup - visa första 3 bankerna
    data.banks.slice(0, 3).forEach(bank => {
        activeBanks.add(bank.id.toString());
    });
    
    if (data.showAverage) {
        activeBanks.add('average');
    }
    
    function updateChart() {
        var datasets = [];
        var allDates = new Set();
        
        // Samla alla datum
        data.banks.forEach(bank => {
            if (activeBanks.has(bank.id.toString()) && bank.periods[currentPeriod]) {
                bank.periods[currentPeriod].dates.forEach(date => allDates.add(date));
            }
        });
        
        // Sortera datum
        var sortedDates = Array.from(allDates).sort();
        
        // Skapa dataset för varje aktiv bank
        var colorIndex = 0;
        data.banks.forEach(bank => {
            if (activeBanks.has(bank.id.toString()) && bank.periods[currentPeriod]) {
                var periodData = bank.periods[currentPeriod];
                var chartData = sortedDates.map(date => {
                    var index = periodData.dates.indexOf(date);
                    return index !== -1 ? periodData.values[index] : null;
                });
                
                // Fyll i null-värden med föregående värde
                for (var i = 1; i < chartData.length; i++) {
                    if (chartData[i] === null) {
                        chartData[i] = chartData[i - 1];
                    }
                }
                
                datasets.push({
                    label: bank.name,
                    data: chartData,
                    borderColor: chartColors[colorIndex % chartColors.length],
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 5
                });
                colorIndex++;
            }
        });
        
        // Lägg till snittlinje om aktiverad
        if (activeBanks.has('average') && sortedDates.length > 0) {
            var averageData = sortedDates.map((date, dateIndex) => {
                var sum = 0;
                var count = 0;
                datasets.forEach(dataset => {
                    if (dataset.data[dateIndex] !== null) {
                        sum += dataset.data[dateIndex];
                        count++;
                    }
                });
                return count > 0 ? sum / count : null;
            });
            
            datasets.push({
                label: 'Snitt',
                data: averageData,
                borderColor: '#6b7280',
                backgroundColor: 'transparent',
                borderWidth: 3,
                borderDash: [5, 5],
                tension: 0.1,
                pointRadius: 0
            });
        }
        
        // Uppdatera eller skapa chart
        if (chart) {
            chart.data.labels = sortedDates.map(d => formatDate(d));
            chart.data.datasets = datasets;
            chart.update();
        } else {
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: sortedDates.map(d => formatDate(d)),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + 
                                           context.parsed.y.toFixed(2).replace('.', ',') + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(1).replace('.', ',') + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        updateCurrentValues();
    }
    
    function formatDate(dateStr) {
        var date = new Date(dateStr);
        var months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                      'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
    }
    
    function updateCurrentValues() {
        var valuesDiv = container.querySelector('.current-values');
        var html = '<h4>Aktuella värden:</h4><div class="value-grid">';
        
        data.banks.forEach(bank => {
            if (activeBanks.has(bank.id.toString()) && bank.periods[currentPeriod]) {
                var values = bank.periods[currentPeriod].values;
                var latestValue = values[values.length - 1];
                if (latestValue) {
                    html += '<div class="value-item">';
                    html += '<span class="bank-name">' + bank.name + ':</span> ';
                    html += '<span class="rate-value">' + 
                            latestValue.toFixed(2).replace('.', ',') + '%</span>';
                    html += '</div>';
                }
            }
        });
        
        html += '</div>';
        valuesDiv.innerHTML = html;
    }
    
    // Event listeners
    container.querySelectorAll('.bank-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                activeBanks.add(this.dataset.bank);
            } else {
                activeBanks.delete(this.dataset.bank);
            }
            updateChart();
        });
    });
    
    container.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            container.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentPeriod = this.dataset.period;
            updateChart();
        });
    });
    
    // Initial render
    updateChart();
	}
	
	
	
	
	
})(jQuery);