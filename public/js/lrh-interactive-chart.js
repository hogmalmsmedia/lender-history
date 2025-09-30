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
		bankColors: ['#0073aa', '#dc2626', '#059669', '#8b5cf6', '#f59e0b', '#ec4899', '#06b6d4', '#84cc16', '#7c3aed', '#b91c1c', '#15803d', '#ea580c', '#0891b2', '#c026d3', '#047857', '#0284c7', '#be123c', '#6366f1', '#ca8a04', '#92400e'],
		colors: { gray: '#6b7280' },
		getDefaultOptions: function () {
			return {};
		},
		createDataset: function (label, data, colorIndex, opts) {
			return {
				label: label,
				data: data,
				borderColor: this.bankColors[colorIndex % this.bankColors.length],
				backgroundColor: 'transparent',
				borderWidth: 2,
				stepped: true,
				pointRadius: 0,
				fill: false,
				...opts
			};
		},
		createLabelsWithYears: function (dates) {
			// Skapa seenYears INUTI funktionen så den resettas varje gång
			const seenYears = new Set();
			let lastYear = null;

			return dates.map((dateStr, index) => {
				// Parse date components manually to avoid timezone issues
				const parts = dateStr.split('-');
				if (parts.length !== 3) return dateStr;

				const year = parseInt(parts[0]);
				const monthIndex = parseInt(parts[1]) - 1;
				const day = parseInt(parts[2]);

				const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
				const month = months[monthIndex];

				// Visa år för första, sista, och när året ändras
				const shouldShowYear =
					index === 0 ||
					index === dates.length - 1 ||
					year !== lastYear;

				lastYear = year;

				if (shouldShowYear) {
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
	let currentSortedDates = []; // Global reference till aktuella datum

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

		// Create cutoff date at midnight to avoid timezone issues
		const today = new Date();
		const cutoffDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - days);

		const filtered = { dates: [], values: [] };
		dates.forEach((dateStr, i) => {
			// Parse date string manually to avoid timezone issues
			const parts = dateStr.split('-');
			if (parts.length === 3) {
				const dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
				if (dateObj >= cutoffDate) {
					filtered.dates.push(dateStr);
					filtered.values.push(values[i]);
				}
			}
		});

		return filtered;
	}

	function updateChart() {
		const datasets = [];
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

				if (
					!periodData ||
					!periodData.dates ||
					!periodData.values ||
					periodData.dates.length === 0 ||
					periodData.values.length === 0
				) {
					return;
				}

				const filtered = filterDataByTimeRange(periodData.dates, periodData.values, timeRange);

				if (filtered.dates.length > 0) {
					rawDatasets.push({
						label: bank.name,
						bankId: bank.id,
						values: filtered.values,
						dates: filtered.dates,
						colorIndex: bankIndex
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

		// Hitta gemensamma datum mellan alla aktiva banker
		let allDates = new Set();
		rawDatasets.forEach(dataset => {
			dataset.dates.forEach(date => allDates.add(date));
		});

		// Sortera datum
		const sortedDates = Array.from(allDates).sort();
		currentSortedDates = sortedDates; // Uppdatera global referens

		// Justera datasets för alla datum
		const alignedDatasets = rawDatasets.map((dataset) => {
			const alignedData = sortedDates.map((date) => {
				const index = dataset.dates.indexOf(date);
				return index !== -1 ? dataset.values[index] : null;
			});

			// Forward-fill ENDAST mellan första och sista datapunkten för denna bank
			let firstNonNullIndex = alignedData.findIndex(v => v !== null);
			let lastNonNullIndex = -1;
			for (let i = alignedData.length - 1; i >= 0; i--) {
				if (alignedData[i] !== null) {
					lastNonNullIndex = i;
					break;
				}
			}

			// Fyll endast mellan första och sista datapunkten
			if (firstNonNullIndex !== -1 && lastNonNullIndex !== -1) {
				let lastValidValue = null;
				for (let i = firstNonNullIndex; i <= lastNonNullIndex; i++) {
					if (alignedData[i] !== null) {
						lastValidValue = alignedData[i];
					} else if (lastValidValue !== null) {
						alignedData[i] = lastValidValue;
					}
				}
			}

			// Använd tema för att skapa dataset
			return theme.createDataset(dataset.label, alignedData, dataset.colorIndex, {
				fill: false,
				stepped: true, // Trappstegseffekt - horisontella linjer mellan ändringar
				pointRadius: 0, // Inga synliga punkter - bara linjer
				pointHoverRadius: 5, // Visa punkt vid hover
				spanGaps: false // Ritar inte linjer över null (utanför datapunkterna)
			});
		});

		// Lägg till genomsnittslinje om aktiverad
		if (options.showAverage && activeBanks.get('average') && alignedDatasets.length > 0) {
			const averageData = sortedDates.map((date, i) => {
				let sum = 0;
				let count = 0;
				alignedDatasets.forEach((dataset) => {
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
				stepped: true,
				pointRadius: 0,
				pointHoverRadius: 0,
				fill: false
			});
		}

		// Använd tema för att skapa labels med år
		const labels = theme.createLabelsWithYears
			? theme.createLabelsWithYears(sortedDates)
			: sortedDates.map((dateStr) => {
					// Parse date components manually to avoid timezone issues
					const parts = dateStr.split('-');
					if (parts.length === 3) {
						const day = parseInt(parts[2]);
						const monthIndex = parseInt(parts[1]) - 1;
						const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
						return day + ' ' + months[monthIndex];
					}
					return dateStr;
			  });

		// Hämta tema options
		const chartOptions = theme.getDefaultOptions
			? theme.getDefaultOptions({
					maxTicksLimit: Math.min(sortedDates.length, 15),
					plugins: {
						legend: {
							display: false // Vi använder custom legend
						},
						tooltip: {
							backgroundColor: 'rgba(255, 255, 255, 0.98)',
							titleColor: '#111827',
							titleFont: {
								family: 'inherit',
								size: 15,
								weight: '700'
							},
							titleAlign: 'center',
							bodyColor: '#4b5563',
							bodyFont: {
								family: 'inherit',
								size: 13
							},
							borderColor: '#e5e7eb',
							borderWidth: 1,
							padding: {
								top: 10,
								right: 12,
								bottom: 10,
								left: 12
							},
							displayColors: true,
							usePointStyle: true,
							pointStyle: 'circle',
							boxWidth: 6,
							boxHeight: 6,
							boxPadding: 4,
							cornerRadius: 6,
							caretSize: 6,
							caretPadding: 8,
							// Begränsa antalet items som visas samtidigt
							filter: function(tooltipItem) {
								// Visa bara datapunkter som har värde (inte null)
								return tooltipItem.parsed.y !== null;
							},
							// Anpassad sortering - högst värde först
							itemSort: function(a, b) {
								return b.parsed.y - a.parsed.y;
							},
							callbacks: {
								title: function (tooltipItems) {
									if (tooltipItems.length > 0) {
										const index = tooltipItems[0].dataIndex;
										// Använd currentSortedDates istället för sortedDates closure
										if (index >= 0 && index < currentSortedDates.length) {
											const dateStr = currentSortedDates[index];
											// Parse date components manually to avoid timezone issues
											const parts = dateStr.split('-');
											if (parts.length === 3) {
												const year = parseInt(parts[0]);
												const month = parseInt(parts[1]) - 1; // 0-indexed
												const day = parseInt(parts[2]);

												const months = [
													'januari', 'februari', 'mars', 'april', 'maj', 'juni',
													'juli', 'augusti', 'september', 'oktober', 'november', 'december'
												];
												return day + ' ' + months[month] + ' ' + year;
											}
										}
									}
									return '';
								},
								label: function (context) {
									if (context.parsed.y !== null && !isNaN(context.parsed.y)) {
										// Huvudrad med bank, värde OCH förändring på samma rad
										const value = context.parsed.y.toFixed(2).replace('.', ',') + '%';
										const label = context.dataset.label;

										// Begränsa längden på banknamn
										const maxLength = 15;
										const truncatedLabel = label.length > maxLength
											? label.substring(0, maxLength) + '...'
											: label;

										// Beräkna förändring
										let changeText = '';
										if (context.dataIndex > 0) {
											const currentValue = context.parsed.y;
											const previousValue = context.dataset.data[context.dataIndex - 1];
											if (previousValue !== null && previousValue !== undefined) {
												const change = currentValue - previousValue;
												if (Math.abs(change) > 0.001) {
													const arrow = change > 0 ? '↑' : '↓';
													changeText = ' (' + arrow + ' ' + Math.abs(change).toFixed(2).replace('.', ',') + ' p.e.)';
												}
											}
										}

										return truncatedLabel + ': ' + value + changeText;
									}
									return null;
								},
								footer: function(tooltipItems) {
									return null;
								}
							},
							// Responsive positioning
							position: 'nearest',
							// Max bredd för mobil
							bodySpacing: 4,
							titleMarginBottom: 8,
							footerMarginTop: 8,
							// Anpassningar för mobil/desktop
							enabled: true,
							external: function(context) {
								// Kontrollera skärmbredd och justera position om nödvändigt
								const tooltip = context.tooltip;
								if (tooltip && tooltip.width) {
									const windowWidth = window.innerWidth;
									// På mobil, begränsa bredden
									if (windowWidth < 768) {
										const maxWidth = windowWidth - 40; // 20px marginal på varje sida
										if (tooltip.width > maxWidth) {
											tooltip.width = maxWidth;
										}
									}
								}
							}
						}
					}
			  })
			: {
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
								label: function (context) {
									if (context.parsed.y !== null) {
										return (
											context.dataset.label +
											': ' +
											context.parsed.y.toFixed(2).replace('.', ',') +
											'%'
										);
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
								callback: function (value) {
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

		let html =
			'<div style="font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 15px;">Välj banker att visa:</div><div class="lrh-legend-items" style="display: flex; flex-wrap: wrap; gap: 8px;">';

		// Lägg till genomsnitt först om det finns
		if (options.showAverage) {
			const isActive = activeBanks.get('average');
			html += `
                <div class="lrh-legend-item ${!isActive ? 'disabled' : ''}"
                     data-bank="average"
                     style="cursor: pointer; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; background: ${
							isActive ? '#fff' : '#f9fafb'
						}; font-size: 13px;">
                    <span class="lrh-legend-color" style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: ${
						theme.colors.gray
					}; opacity: ${!isActive ? '0.3' : '1'}; flex-shrink: 0;"></span>
                    <span style="font-size: 13px; line-height: 1;">Genomsnitt</span>
                </div>
            `;
		}

		// Lägg till banker - använd bankIndex för konsekvent färgtilldelning
		banksData.forEach((bank, bankIndex) => {
			const isActive = activeBanks.get(bank.id.toString());
			const color = theme.bankColors[bankIndex % theme.bankColors.length]; // Använd bankIndex för konsekvent färg
			html += `
                <div class="lrh-legend-item ${!isActive ? 'disabled' : ''}"
                     data-bank="${bank.id}"
                     style="cursor: pointer; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; background: ${
							isActive ? '#fff' : '#f9fafb'
						}; font-size: 13px;">
                    <span class="lrh-legend-color" style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: ${color}; opacity: ${
				!isActive ? '0.3' : '1'
			}; flex-shrink: 0;"></span>
                    <span style="font-size: 13px; line-height: 1;">${bank.name}</span>
                </div>
            `;
		});

		html += '</div>';
		legendContainer.innerHTML = html;

		// Gör legend klickbar
		legendContainer.querySelectorAll('.lrh-legend-item').forEach((item) => {
			item.addEventListener('click', function () {
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
		let valueCards = [];

		banksData.forEach((bank) => {
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
					const dates = periodData.dates;
					const latestValue = values[values.length - 1];
					const previousValue = values.length > 1 ? values[values.length - 2] : null;
					const change = previousValue ? latestValue - previousValue : 0;
					const changeClass = change > 0 ? 'lrh-increase' : change < 0 ? 'lrh-decrease' : 'lrh-no-change';
					const arrow = change > 0 ? '↑' : change < 0 ? '↓' : '→';

					// Formatera datum
					let changeDate = '';
					if (dates && dates.length > 1) {
						const dateStr = dates[dates.length - 2];
						const parts = dateStr.split('-');
						if (parts.length === 3) {
							const day = parseInt(parts[2]);
							const monthIndex = parseInt(parts[1]) - 1;
							const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
							changeDate = day + ' ' + months[monthIndex];
						}
					}

					valueCards.push(`
						<div class="lrh-value-card">
							<div class="lrh-bank-name">${bank.name}</div>
							<div class="lrh-bank-rate">${latestValue.toFixed(2).replace('.', ',')}%</div>
							<div class="lrh-bank-change ${changeClass}">
								${arrow} ${Math.abs(change).toFixed(2).replace('.', ',')} p.e. fr. ${changeDate}
							</div>
						</div>
					`);
				}
			}
		});

		if (hasValues) {
			// Visa alltid med automatisk grid som anpassar sig
			valuesContainer.innerHTML = `<div class="lrh-values-grid">${valueCards.join('')}</div>`;
		} else {
			valuesContainer.innerHTML = '<p class="text-small" style="color: #6b7280;">Inga värden att visa</p>';
		}
	}

	// Event listeners för period-knappar
	container.querySelectorAll('.lrh-period-btn').forEach((btn) => {
		btn.addEventListener('click', function () {
			container.querySelectorAll('.lrh-period-btn').forEach((b) => b.classList.remove('active'));
			this.classList.add('active');
			currentPeriod = this.dataset.period;
			updateChart();
		});
	});

	// Event listener för typ-växlare (om den finns)
	container.querySelectorAll('.lrh-type-btn').forEach((btn) => {
		btn.addEventListener('click', function () {
			container.querySelectorAll('.lrh-type-btn').forEach((b) => b.classList.remove('active'));
			this.classList.add('active');
			currentType = this.dataset.type;
			updateChart();
		});
	});

	// Tidsintervall slider
	const timeSlider = container.querySelector('.lrh-time-slider');
	const timeDisplay = container.querySelector('.time-display');

	if (timeSlider) {
		timeSlider.addEventListener('input', function () {
			timeRange = parseInt(this.value);
			const days = timeRange;
			if (timeDisplay) {
				if (days > 365) {
					const years = days / 365;
					const roundedYears = Math.round(years * 10) / 10;
					if (roundedYears === Math.floor(roundedYears)) {
						timeDisplay.textContent = `Senaste ${Math.floor(roundedYears)} åren`;
					} else {
						timeDisplay.textContent = `Senaste ${roundedYears.toString().replace('.', ',')} åren`;
					}
				} else {
					const months = Math.round(days / 30);
					timeDisplay.textContent = `Senaste ${months} månaderna`;
				}
			}
			updateChart();
		});
	}

	// Lägg till CSS för legend items och värdekort
	const style = document.createElement('style');
	style.textContent = `
		/* Legend styling */
		.lrh-legend-item {
			transition: all 0.2s ease;
			white-space: nowrap;
		}
		.lrh-legend-item:hover {
			transform: translateY(-1px);
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
		}

		/* Values grid - Automatisk anpassning */
		.lrh-values-grid {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
			width: 100%;
		}

		@media (min-width: 1200px) {
			.lrh-values-grid {
				grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
			}
		}

		@media (max-width: 768px) {
			.lrh-values-grid {
				grid-template-columns: repeat(2, 1fr);
				gap: 8px;
				width: 100%;
			}
		}

		@media (max-width: 480px) {
			.lrh-values-grid {
				grid-template-columns: repeat(2, 1fr);
				gap: 8px;
				width: 100%;
			}
		}

		/* Value cards - Enhetlig storlek */
		.lrh-value-card {
			background: #ffffff;
			padding: 12px 10px;
			border-radius: 8px;
			text-align: center;
			border: 1px solid #e5e7eb;
			transition: all 0.2s ease;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
			min-height: 85px;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}
		.lrh-value-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
			border-color: #d1d5db;
		}

		.lrh-bank-name {
			font-size: 12px;
			color: #6b7280;
			margin-bottom: 4px;
			font-weight: 500;
			line-height: 1.2;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.lrh-bank-rate {
			font-size: 19px;
			font-weight: 700;
			color: #111827;
			margin-bottom: 3px;
			line-height: 1;
		}

		.lrh-bank-change {
			font-size: 11px;
			font-weight: 600;
			line-height: 1.2;
		}

		/* Färger för ändringar */
		.lrh-increase { color: #ef4444; }
		.lrh-decrease { color: #10b981; }
		.lrh-no-change { color: #6b7280; }

		/* Tooltip överskrivningar för bättre utseende */
		.chartjs-tooltip {
			max-width: 300px !important;
			font-family: inherit !important;
			font-size: inherit !important;
		}

		.chartjs-tooltip * {
			font-family: inherit !important;
			font-size: inherit !important;
		}

		@media (max-width: 768px) {
			.chartjs-tooltip {
				max-width: calc(100vw - 40px) !important;
			}

			/* Kompakt tooltip på mobil */
			canvas {
				-webkit-tap-highlight-color: transparent;
			}
		}

		/* Mobile adjustments */
		@media (max-width: 480px) {
			.lrh-value-card {
				padding: 10px 8px;
				min-height: 75px;
			}
			.lrh-bank-name {
				font-size: 11px;
			}
			.lrh-bank-rate {
				font-size: 17px;
			}
			.lrh-bank-change {
				font-size: 10px;
			}

			/* Extra kompakt tooltip på små skärmar */
			.chartjs-tooltip {
				max-width: calc(100vw - 20px) !important;
			}
		}

		/* Förbättra tooltip-synlighet */
		.lrh-interactive-chart {
			position: relative;
		}

		.lrh-chart-wrapper {
			position: relative;
			overflow: visible;
		}
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
