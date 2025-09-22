/**
 * Unified Chart.js Configuration - Clean Version
 * En gemensam graf-stil för alla LRH-grafer
 */

window.LRHChartTheme = {
    // Färgpalett - moderna, tydliga färger
    colors: {
        primary: '#3b82f6',      // Ljusblå (för externa källor)
        secondary: '#8b5cf6',    // Lila
        success: '#10b981',      // Grön
        danger: '#ef4444',       // Röd
        warning: '#f59e0b',      // Orange
        info: '#06b6d4',         // Cyan
        gray: '#6b7280',         // Grå (för genomsnitt)
        gridColor: 'rgba(0, 0, 0, 0.06)',  // Subtil grid
        textColor: '#374151',    // Mörkgrå text
        borderColor: 'rgba(0, 0, 0, 0.1)'  // Kantlinje
    },
    
    // Bankfärger för jämförelser - tydliga, distinkta färger
    bankColors: [
        '#0073aa', // Mörkblå (SEB-stil)
        '#dc2626', // Röd (Danske Bank-stil)
        '#059669', // Grön (Swedbank-stil) 
        '#8b5cf6', // Lila (Nordea-stil)
        '#f59e0b', // Orange (Handelsbanken-stil)
        '#ec4899', // Rosa (Bluestep-stil)
        '#06b6d4', // Cyan
        '#84cc16'  // Lime
    ],
    
    // Formatera datum med år
    formatDateLabel: function(dateStr, showYear = true) {
        const date = new Date(dateStr);
        const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                      'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        
        if (showYear) {
            // Visa år för första, sista och årsskiften
            return date.getDate() + ' ' + months[date.getMonth()] + 
                   (date.getMonth() === 0 || date.getDate() === 1 ? ' ' + date.getFullYear() : '');
        } else {
            return date.getDate() + ' ' + months[date.getMonth()];
        }
    },
    
    // Gemensamma Chart.js options
    getDefaultOptions: function(customOptions = {}) {
        const self = this;
        
        return {
            responsive: true,
            maintainAspectRatio: false,
            
            // Mjuka animationer
            animation: {
                duration: 600,
                easing: 'easeInOutQuart'
            },
            
            interaction: {
                mode: 'index',
                intersect: false,
                axis: 'x'
            },
            
            plugins: {
                legend: {
                    display: false // Vi använder custom legends
                },
                
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#111827',
                    titleFont: {
                        family: 'inherit',
                        size: 13,
                        weight: '600'
                    },
                    bodyColor: '#4b5563',
                    bodyFont: {
                        family: 'inherit',
                        size: 12,
                        weight: '400'
                    },
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 14,
                    displayColors: true,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    boxHeight: 8,
                    cornerRadius: 8,
                    caretSize: 6,
                    caretPadding: 10,
                    
                    callbacks: {
                        // Visa datum med år i tooltip title
                        title: function(tooltipItems) {
                            if (tooltipItems.length > 0) {
                                const dateStr = tooltipItems[0].label;
                                // Lägg till år om det inte redan finns
                                if (!dateStr.includes('202') && !dateStr.includes('201')) {
                                    const date = new Date(tooltipItems[0].raw?.date || dateStr);
                                    if (!isNaN(date)) {
                                        const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                                                      'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
                                        return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
                                    }
                                }
                                return dateStr;
                            }
                            return '';
                        },
                        
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                label += context.parsed.y.toFixed(2).replace('.', ',') + '%';
                            }
                            return label;
                        },
                        
                        // Visa förändring om tillgänglig
                        afterLabel: function(context) {
                            if (context.dataIndex > 0) {
                                const currentValue = context.parsed.y;
                                const previousValue = context.dataset.data[context.dataIndex - 1];
                                
                                if (typeof previousValue === 'object' && previousValue !== null) {
                                    // Om data är objekt, hämta y-värdet
                                    const prevY = previousValue.y;
                                    if (prevY !== null && prevY !== undefined) {
                                        const change = currentValue - prevY;
                                        const arrow = change > 0 ? '↑' : (change < 0 ? '↓' : '→');
                                        return arrow + ' ' + Math.abs(change).toFixed(2).replace('.', ',') + ' p.e.';
                                    }
                                } else if (previousValue !== null && previousValue !== undefined) {
                                    // Om data är enkelt värde
                                    const change = currentValue - previousValue;
                                    const arrow = change > 0 ? '↑' : (change < 0 ? '↓' : '→');
                                    return arrow + ' ' + Math.abs(change).toFixed(2).replace('.', ',') + ' p.e.';
                                }
                            }
                            return null;
                        }
                    }
                },
                
                title: customOptions.title || {
                    display: false
                }
            },
            
            scales: {
                y: {
                    beginAtZero: false,
                    
                    grid: {
                        color: this.colors.gridColor,
                        drawBorder: false,
                        lineWidth: 1
                    },
                    
                    border: {
                        display: false
                    },
                    
                    ticks: {
                        padding: 10,
                        font: {
                            family: 'inherit',
                            size: 11,
                            weight: '400'
                        },
                        color: this.colors.textColor,
                        callback: function(value) {
                            return value.toFixed(1).replace('.', ',') + '%';
                        }
                    }
                },
                
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    
                    border: {
                        display: false
                    },
                    
                    ticks: {
                        padding: 8,
                        font: {
                            family: 'inherit',
                            size: 11,
                            weight: '400'
                        },
                        color: this.colors.textColor,
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: customOptions.maxTicksLimit || 10,
                        
                        // Visa år för vissa datum
                        callback: function(value, index, ticks) {
                            const label = this.getLabelForValue(value);
                            
                            // För första, sista och årsskiften - visa år
                            if (index === 0 || 
                                index === ticks.length - 1 || 
                                label.includes('1 jan') ||
                                (index % Math.floor(ticks.length / 5) === 0)) {
                                
                                // Om label inte redan har år, lägg till det
                                if (!label.includes('202') && !label.includes('201')) {
                                    // Detta är en förenklad label, försök hitta året
                                    return label; // Behåll som den är för nu
                                }
                            }
                            
                            return label;
                        }
                    }
                }
            },
            
            // Merge with custom options
            ...customOptions
        };
    },
    
    // Skapa dataset med ren styling (ingen fyllning)
    createDataset: function(label, data, colorIndex = 0, options = {}) {
        const color = options.color || this.bankColors[colorIndex % this.bankColors.length];
        
        const baseConfig = {
            label: label,
            data: data,
            borderColor: color,
            backgroundColor: 'transparent', // Ingen fyllning
            borderWidth: options.borderWidth || 2,
            tension: options.tension || 0.2, // Lite mjukare kurvor
            pointRadius: options.pointRadius !== undefined ? options.pointRadius : 
                (data.length > 50 ? 0 : 2), // Mindre punkter
            pointHoverRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 1.5,
            pointHoverBackgroundColor: '#ffffff',
            pointHoverBorderColor: color,
            pointHoverBorderWidth: 2,
            fill: false, // Ingen fyllning under linjen
            ...options
        };
        
        // Special styling för genomsnittslinje
        if (label === 'Genomsnitt' || options.isAverage) {
            baseConfig.borderColor = this.colors.gray;
            baseConfig.borderDash = [8, 4];
            baseConfig.borderWidth = 2;
            baseConfig.pointRadius = 0;
            baseConfig.pointHoverRadius = 0;
        }
        
        return baseConfig;
    },
    
    // Helper för att formatera labels med år där det behövs
    createLabelsWithYears: function(dates) {
        return dates.map((dateStr, index) => {
            const date = new Date(dateStr);
            const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 
                          'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
            
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            
            // Visa år för:
            // - Första datapunkten
            // - Sista datapunkten (kommer hanteras separat)
            // - Årsskiften (januari)
            // - Var 6:e månad om det är många datapunkter
            if (index === 0 || 
                date.getMonth() === 0 || 
                (dates.length > 50 && index % 6 === 0)) {
                return `${day} ${month} ${year}`;
            }
            
            // För sista datapunkten
            if (index === dates.length - 1) {
                return `${day} ${month} ${year}`;
            }
            
            return `${day} ${month}`;
        });
    }
};

// Applicera global Chart.js konfiguration
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined') {
        // Sätt globala defaults
        Chart.defaults.font.family = 'inherit'; // Inter Variable
        Chart.defaults.color = LRHChartTheme.colors.textColor;
    }
});