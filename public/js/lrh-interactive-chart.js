/**
 * Interactive Chart med enhetlig styling
 * Använder LRHChartTheme för konsekvent utseende
 */

function initLRHInteractiveChart(chartId, banksData, options) {
    const container = document.getElementById(chartId);
    if (!container) return;
    
    const canvas = document.getElementById('canvas-' + chartId);
    if (!canvas) return;
    
    // Använd tema om det finns, annars fallback
    const theme = window.LRHChartTheme || {
        bankColors: ['#0073aa', '#dc2626', '#059669', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'],
        colors: { gray: '#6b7280' },
        getDefaultOptions: function() { return {}; },
        createDataset: function(label, data, colorIndex, opts) {
            return {
                label: label,
                data: data,
                borderColor: this.bankColors[colorIndex % this.bankColors.length],
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.2,
                pointRadius: data.length > 50 ? 0 : 2,
                fill: false,
                ...opts
            };
        },
        createLabelsWithYears: function(dates) {
            return dates.map((dateStr, index) => {
                const date = new Date(dateStr);
                const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                              'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                
                const day = date.getDate();
                const month = months[date.getMonth()];
                const year = date.getFullYear();
                
                // Visa år för första, sista och årsskiften
                if (index === 0 || 
                    index === dates.length - 1 ||
                    date.getMonth() === 0 || 
                    (dates.length > 50 && index % 6 === 0)) {
                    return `${day} ${month} ${year}`;
                }
                
                return `${day} ${month}`;
            });
        }
    };
    
    let chart = null;
    let currentPeriod = options.defaultPeriod || '3_man';
    let currentType = options.defaultType || 'snitt';
    let activeBanks = new Map();
    let timeRange = parseInt(container.querySelector('.lrh-time-slider').value);
    
    // Initial setup - aktivera första 3 bankerna
    banksData.forEach((bank, index) => {
        if (index < 3) {
            activeBanks.set(bank.id.toString(), true);
        } else {
            activeBanks.set(bank.id.toString(), false);
        }
    });
    
    if (options.showAverage) {
        activeBanks.set('average', true);
    }
    
    function filterDataByTimeRange(dates, values, days) {
        if (!dates || !values || dates.length === 0) {
            return { dates: [], values: [] };
        }
        
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - days);
        
        const filtered = { dates: [], values: [] };
        dates.forEach((date, i) => {
            if (new Date(date) >= cutoffDate) {
                filtered.dates.push(date);
                filtered.values.push(values[i]);
            }
        });
        
        return filtered;
    }
    
    function updateChart() {
        const datasets = [];
        let allDates = new Set();
        const rawDatasets = [];

        // Samla data för aktiva banker
        banksData.forEach((bank, bankIndex) => {
            if (activeBanks.get(bank.id.toString())) {
                let periodData = null;

                // Kolla om bank har types eller periods struktur
                if (bank.types && bank.types[currentType]) {
                    const typeData = bank.types[currentType];
                    if (typeData[currentPeriod]) {
                        periodData = typeData[currentPeriod];
                    }
                } else if (bank.periods && bank.periods[currentPeriod]) {
                    periodData = bank.periods[currentPeriod];
                }

                if (!periodData || !periodData.dates || !periodData.values ||
                    periodData.dates.length === 0 || periodData.values.length === 0) {
                    return;
                }

                const filtered = filterDataByTimeRange(
                    periodData.dates,
                    periodData.values,
                    timeRange
                );

                if (filtered.dates.length > 0) {
                    filtered.dates.forEach(date => allDates.add(date));

                    rawDatasets.push({
                        label: bank.name,
                        bankId: bank.id,
                        values: filtered.values,
                        dates: filtered.dates,
                        colorIndex: bankIndex  // Använd bankIndex istället för colorIndex++
                    });
                }
            }
        });
        
        // Hantera tom data
        if (rawDatasets.length === 0) {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            canvas.style.display = 'none';
            
            let messageEl = container.querySelector('.no-data-message');
            if (!messageEl) {
                messageEl = document.createElement('div');
                messageEl.className = 'no-data-message text-small';
                messageEl.style.textAlign = 'center';
                messageEl.style.padding = '40px';
                messageEl.style.color = '#6b7280';
                canvas.parentNode.appendChild(messageEl);
            }
            messageEl.textContent = 'Ingen data tillgänglig för vald period';
            messageEl.style.display = 'block';
            
            updateInteractiveLegend();
            updateCurrentValues();
            return;
        } else {
            canvas.style.display = 'block';
            const messageEl = container.querySelector('.no-data-message');
            if (messageEl) {
                messageEl.style.display = 'none';
            }
        }
        
        // Sortera datum
        const sortedDates = Array.from(allDates).sort();
        
        // Justera datasets för alla datum
        const alignedDatasets = rawDatasets.map(dataset => {
            const alignedData = sortedDates.map(date => {
                const index = dataset.dates.indexOf(date);
                return index !== -1 ? dataset.values[index] : null;
            });
            
            // Fyll i null-värden
            let lastValidValue = null;
            for (let i = 0; i < alignedData.length; i++) {
                if (alignedData[i] !== null) {
                    lastValidValue = alignedData[i];
                } else if (lastValidValue !== null) {
                    alignedData[i] = lastValidValue;
                }
            }
            
            // Använd tema för att skapa dataset
            return theme.createDataset(
                dataset.label,
                alignedData,
                dataset.colorIndex,
                {
                    fill: false, // Ingen fyllning
                    tension: 0.2,
                    pointRadius: sortedDates.length > 50 ? 0 : 2
                }
            );
        });
        
        // Lägg till genomsnittslinje om aktiverad
        if (options.showAverage && activeBanks.get('average') && alignedDatasets.length > 0) {
            const averageData = sortedDates.map((date, i) => {
                let sum = 0;
                let count = 0;
                alignedDatasets.forEach(dataset => {
                    if (dataset.data[i] !== null && !isNaN(dataset.data[i])) {
                        sum += dataset.data[i];
                        count++;
                    }
                });
                return count > 0 ? sum / count : null;
            });
            
            alignedDatasets.push({
                label: 'Genomsnitt',
                data: averageData,
                borderColor: theme.colors.gray,
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [8, 4],
                tension: 0.2,
                pointRadius: 0,
                pointHoverRadius: 0,
                fill: false
            });
        }
        
        // Använd tema för att skapa labels med år
        const labels = theme.createLabelsWithYears ? 
            theme.createLabelsWithYears(sortedDates) :
            sortedDates.map(dateStr => {
                const date = new Date(dateStr);
                const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                              'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                return date.getDate() + ' ' + months[date.getMonth()];
            });
        
        // Hämta tema options
        const chartOptions = theme.getDefaultOptions ? theme.getDefaultOptions({
            maxTicksLimit: sortedDates.length > 20 ? 10 : sortedDates.length,
            plugins: {
                legend: {
                    display: false // Vi använder custom legend
                },
                tooltip: {
                    ...((theme.getDefaultOptions() || {}).plugins || {}).tooltip,
                    callbacks: {
                        title: function(tooltipItems) {
                            if (tooltipItems.length > 0) {
                                const index = tooltipItems[0].dataIndex;
                                const date = new Date(sortedDates[index]);
                                const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                                              'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                                return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
                            }
                            return '';
                        },
                        label: function(context) {
                            if (context.parsed.y !== null) {
                                return context.dataset.label + ': ' + 
                                       context.parsed.y.toFixed(2).replace('.', ',') + '%';
                            }
                            return context.dataset.label + ': -';
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
            }
        }) : {
            // Fallback options om temat inte finns
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#111827',
                    bodyColor: '#4b5563',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    usePointStyle: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    callbacks: {
                        label: function(context) {
                            if (context.parsed.y !== null) {
                                return context.dataset.label + ': ' + 
                                       context.parsed.y.toFixed(2).replace('.', ',') + '%';
                            }
                            return context.dataset.label + ': -';
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
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        };
        
        // Skapa eller uppdatera chart
        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets = alignedDatasets;
            chart.update('none');
        } else {
            chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: alignedDatasets
                },
                options: chartOptions
            });
        }
        
        updateInteractiveLegend();
        updateCurrentValues();
    }
    
    function updateInteractiveLegend() {
        const legendContainer = container.querySelector('.lrh-custom-legend');
        if (!legendContainer) return;

        let html = '<div class="headline-h6" style="margin-bottom: 12px;">Välj banker att visa:</div><div class="lrh-legend-items grid-3 gap-s">';

        // Lägg till genomsnitt först om det finns
        if (options.showAverage) {
            const isActive = activeBanks.get('average');
            html += `
                <div class="lrh-legend-item ${!isActive ? 'disabled' : ''}"
                     data-bank="average"
                     style="cursor: pointer; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; display: flex; align-items: center; gap: 8px; background: ${isActive ? '#fff' : '#f9fafb'};">
                    <span class="lrh-legend-color" style="display: inline-block; width: 12px; height: 12px; border-radius: 2px; background: ${theme.colors.gray}; opacity: ${!isActive ? '0.3' : '1'};"></span>
                    <span class="text-small">Genomsnitt</span>
                </div>
            `;
        }

        // Lägg till banker - använd bankIndex för konsekvent färgtilldelning
        banksData.forEach((bank, bankIndex) => {
            const isActive = activeBanks.get(bank.id.toString());
            const color = theme.bankColors[bankIndex % theme.bankColors.length];  // Använd bankIndex för konsekvent färg
            html += `
                <div class="lrh-legend-item ${!isActive ? 'disabled' : ''}"
                     data-bank="${bank.id}"
                     style="cursor: pointer; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; display: flex; align-items: center; gap: 8px; background: ${isActive ? '#fff' : '#f9fafb'};">
                    <span class="lrh-legend-color" style="display: inline-block; width: 12px; height: 12px; border-radius: 2px; background: ${color}; opacity: ${!isActive ? '0.3' : '1'};"></span>
                    <span class="text-small">${bank.name}</span>
                </div>
            `;
        });
        
        html += '</div>';
        legendContainer.innerHTML = html;
        
        // Gör legend klickbar
        legendContainer.querySelectorAll('.lrh-legend-item').forEach(item => {
            item.addEventListener('click', function() {
                const bankId = this.dataset.bank;
                const isActive = activeBanks.get(bankId.toString());
                
                // Toggle status
                activeBanks.set(bankId.toString(), !isActive);
                
                // Uppdatera visual feedback
                this.classList.toggle('disabled');
                const colorSpan = this.querySelector('.lrh-legend-color');
                colorSpan.style.opacity = !isActive ? '1' : '0.3';
                this.style.background = !isActive ? '#fff' : '#f9fafb';
                
                // Uppdatera chart
                updateChart();
            });
        });
    }
    
    function updateCurrentValues() {
        const valuesContainer = container.querySelector('.lrh-values-cards');
        if (!valuesContainer) return;
        
        let hasValues = false;
        let html = '<div class="grid-3 gap-s">';
        
        banksData.forEach(bank => {
            if (activeBanks.get(bank.id.toString())) {
                let periodData = null;
                
                if (bank.types && bank.types[currentType]) {
                    const typeData = bank.types[currentType];
                    if (typeData[currentPeriod]) {
                        periodData = typeData[currentPeriod];
                    }
                } else if (bank.periods && bank.periods[currentPeriod]) {
                    periodData = bank.periods[currentPeriod];
                }
                
                if (periodData && periodData.values && periodData.values.length > 0) {
                    hasValues = true;
                    const values = periodData.values;
                    const latestValue = values[values.length - 1];
                    const previousValue = values.length > 1 ? values[values.length - 2] : null;
                    const change = previousValue ? latestValue - previousValue : 0;
                    const changeClass = change > 0 ? 'lrh-increase' : (change < 0 ? 'lrh-decrease' : 'lrh-no-change');
                    const arrow = change > 0 ? '↑' : (change < 0 ? '↓' : '→');
                    
                    html += `
                        <div class="lrh-value-card" style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e5e7eb;">
                            <div class="text-small" style="color: #6b7280; margin-bottom: 8px;">${bank.name}</div>
                            <div class="headline-h5" style="color: #111827;">${latestValue.toFixed(2).replace('.', ',')}%</div>
                            <div class="text-xs ${changeClass}" style="margin-top: 4px;">
                                ${arrow} ${Math.abs(change).toFixed(2).replace('.', ',')}%
                            </div>
                        </div>
                    `;
                }
            }
        });
        
        html += '</div>';
        valuesContainer.innerHTML = hasValues ? html : '<p class="text-small">Inga värden att visa</p>';
    }
    
    // Event listeners för period-knappar
    container.querySelectorAll('.lrh-period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            container.querySelectorAll('.lrh-period-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentPeriod = this.dataset.period;
            updateChart();
        });
    });
    
    // Event listener för typ-växlare (om den finns)
    container.querySelectorAll('.lrh-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            container.querySelectorAll('.lrh-type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentType = this.dataset.type;
            updateChart();
        });
    });
    
    // Tidsintervall slider
    const timeSlider = container.querySelector('.lrh-time-slider');
    const timeDisplay = container.querySelector('.time-display');
    
    if (timeSlider) {
        timeSlider.addEventListener('input', function() {
            timeRange = parseInt(this.value);
            const months = Math.round(timeRange / 30);
            if (timeDisplay) {
                timeDisplay.textContent = months > 12 
                    ? `Senaste ${Math.round(months / 12)} åren` 
                    : `Senaste ${months} månaderna`;
            }
            updateChart();
        });
    }
    
    // Lägg till CSS för legend items
    const style = document.createElement('style');
    style.textContent = `
        .lrh-legend-item {
            transition: all 0.2s ease;
        }
        .lrh-legend-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .lrh-value-card {
            transition: all 0.2s ease;
        }
        .lrh-value-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .lrh-increase { color: #ef4444; }
        .lrh-decrease { color: #10b981; }
        .lrh-no-change { color: #6b7280; }
    `;
    if (!document.getElementById('lrh-interactive-styles')) {
        style.id = 'lrh-interactive-styles';
        document.head.appendChild(style);
    }
    
    // Initial render
    updateChart();
}

// Gör funktionen global
window.initLRHInteractiveChart = initLRHInteractiveChart;