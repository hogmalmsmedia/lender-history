/**
 * Lender Rate History - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize tooltips
        initTooltips();
        
        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            initCharts();
        }
        
// Import form handler
$('#lrh-import-form').on('submit', function(e) {
    e.preventDefault();
    
    var $form = $(this);
    var $submitBtn = $form.find('button[type="submit"]');
    var originalText = $submitBtn.text();
    
    // Validera fil
    var fileInput = $form.find('input[name="import_file"]')[0];
    if (!fileInput.files.length) {
        alert('Välj en fil att importera');
        return false;
    }
    
    var file = fileInput.files[0];
    var fileName = file.name.toLowerCase();
    
    // Kontrollera filtyp
    if (!fileName.endsWith('.csv') && !fileName.endsWith('.json')) {
        alert('Endast CSV och JSON-filer stöds');
        return false;
    }
    
    var formData = new FormData(this);
    formData.append('action', 'lrh_import_data');
    formData.append('nonce', lrh_ajax.nonce);
    
    $submitBtn.prop('disabled', true).text('Importerar...');
    
    $.ajax({
        url: lrh_ajax.url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('Fel: ' + (response.data || 'Okänt fel'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Import error:', status, error);
            alert('Import misslyckades: ' + error);
        },
        complete: function() {
            $submitBtn.prop('disabled', false).text(originalText);
        }
    });
    
    return false;
});
        
        // Export form handler - updated
        $('#lrh-export-form').on('submit', function(e) {
            e.preventDefault();
            
            var format = $('#export_format').val();
            var category = $('#export_category').val();
            var days = $('#export_days').val();
            
            // Get the nonce from the form
            var nonce = $(this).find('input[name="_wpnonce"]').val();
            
            // Create form and submit
            var form = $('<form>', {
                'method': 'POST',
                'action': lrh_ajax.url
            });
            
            form.append($('<input>', {'type': 'hidden', 'name': 'action', 'value': 'lrh_export_data'}));
            form.append($('<input>', {'type': 'hidden', 'name': 'format', 'value': format}));
            form.append($('<input>', {'type': 'hidden', 'name': 'category', 'value': category}));
            form.append($('<input>', {'type': 'hidden', 'name': 'days', 'value': days}));
            form.append($('<input>', {'type': 'hidden', 'name': 'nonce', 'value': nonce}));
            
            $('body').append(form);
            form.submit();
        });
        
        // Handle initialize history
        $('#lrh-initialize-form').on('submit', function(e) {
            e.preventDefault();
            
            console.log('Initialize form submitted');
            
            if (!confirm('Detta kommer att importera alla nuvarande ACF-värden som initial historik. Fortsätt?')) {
                return;
            }
            
            var $submitBtn = $(this).find('button[type="submit"]');
            var $progress = $('#lrh-initialize-progress');
            var $progressBar = $progress.find('.progress-bar-fill');
            var $progressText = $progress.find('.progress-text');
            var $result = $('#lrh-initialize-result');
            var $resultMessage = $result.find('.result-message');
            
            console.log('Starting initialization...');
            
            $submitBtn.prop('disabled', true).text('Initierar...');
            $progress.show();
            $progressText.text('Startar initiering...');
            
            // Start initialization
            $.ajax({
                url: lrh_ajax.url,
                type: 'POST',
                data: {
                    action: 'lrh_initialize_history',
                    nonce: lrh_ajax.nonce
                },
                success: function(response) {
                    console.log('Response:', response);
                    
                    if (response.success) {
                        $progressBar.css('width', '100%');
                        $progressText.text('Klar!');
                        $resultMessage.text(response.data);
                        $result.show();
                        
                        setTimeout(function() {
                            alert('Initiering klar! ' + response.data);
                            location.reload();
                        }, 2000);
                    } else {
                        console.error('Error:', response.data);
                        alert(response.data || lrh_ajax.strings.error);
                        $submitBtn.prop('disabled', false).text('Initiera Historik');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    alert('AJAX Error: ' + error);
                    $submitBtn.prop('disabled', false).text('Initiera Historik');
                    $progress.hide();
                }
            });
        });
        
        // Clear cache button
        $('#lrh-clear-cache').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(lrh_ajax.strings.loading);
            
            $.post(lrh_ajax.url, {
                action: 'lrh_clear_cache',
                nonce: lrh_ajax.nonce
            }, function(response) {
                if (response.success) {
                    alert('Cache rensad!');
                }
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        });
        
        // Live chart updates
        if ($('.lrh-chart-container').length > 0) {
            setInterval(updateCharts, 60000); // Update every minute
        }
        
    });
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('.lrh-help-tip').each(function() {
            $(this).tipTip({
                'attribute': 'data-tip',
                'fadeIn': 50,
                'fadeOut': 50,
                'delay': 200
            });
        });
    }
    
    /**
     * Initialize charts
     */
    function initCharts() {
        // Rate trends chart
        var $trendChart = $('#lrh-rate-trends-chart');
        if ($trendChart.length > 0) {
            var ctx = $trendChart[0].getContext('2d');
            
            // Get data via AJAX
            $.post(lrh_ajax.url, {
                action: 'lrh_get_chart_data',
                type: 'trends',
                nonce: lrh_ajax.nonce
            }, function(response) {
                if (response.success && response.data) {
                    new Chart(ctx, {
                        type: 'line',
                        data: response.data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Räntetrender'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }
        
        // Activity chart
        var $activityChart = $('#lrh-activity-chart');
        if ($activityChart.length > 0) {
            var ctx = $activityChart[0].getContext('2d');
            
            $.post(lrh_ajax.url, {
                action: 'lrh_get_chart_data',
                type: 'activity',
                nonce: lrh_ajax.nonce
            }, function(response) {
                if (response.success && response.data) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: response.data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: 'Ändringar per dag'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }
    }
    
    /**
     * Update charts with fresh data
     */
    function updateCharts() {
        // Reload chart data
        if (window.lrhCharts) {
            $.each(window.lrhCharts, function(index, chart) {
                // Fetch new data and update chart
                $.post(lrh_ajax.url, {
                    action: 'lrh_get_chart_data',
                    type: chart.type,
                    nonce: lrh_ajax.nonce
                }, function(response) {
                    if (response.success && response.data) {
                        chart.data = response.data;
                        chart.update();
                    }
                });
            });
        }
    }
    
    /**
     * Handle settings form
     */
    $('#lrh-settings-form').on('submit', function() {
        // Parse textarea values to arrays
        $(this).find('textarea').each(function() {
            var $textarea = $(this);
            var lines = $textarea.val().split('\n').filter(function(line) {
                return line.trim() !== '';
            });
            $textarea.val(lines.join('\n'));
        });
    });
    
    /**
     * Field configuration helper
     */
    $('.lrh-add-field').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $container = $button.closest('.lrh-field-category');
        var $textarea = $container.find('textarea');
        var fieldName = prompt('Ange fältnamn:');
        
        if (fieldName) {
            var currentValue = $textarea.val();
            if (currentValue) {
                $textarea.val(currentValue + '\n' + fieldName);
            } else {
                $textarea.val(fieldName);
            }
        }
    });
    
    /**
     * Bulk operations helper
     */
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).siblings('select').val();
        
        if (action === 'bulk-delete' || action === 'bulk-validate') {
            var checked = $('input[name="bulk-delete[]"]:checked').length;
            
            if (checked === 0) {
                alert('Välj minst en post att ' + (action === 'bulk-delete' ? 'ta bort' : 'validera'));
                e.preventDefault();
                return false;
            }
            
            if (action === 'bulk-delete') {
                if (!confirm('Är du säker på att du vill ta bort ' + checked + ' poster?')) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });

})(jQuery);