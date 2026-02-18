/**
 * Analytics Dashboard
 * Расширенная аналитика и визуализация данных
 */

class AnalyticsDashboard {
    constructor() {
        this.charts = new Map();
        this.data = {};
        this.filters = {
            period: 'week',
            category: 'all',
            user: 'all'
        };
        this.init();
    }

    init() {
        this.loadAnalyticsData();
        this.setupFilters();
        this.createCharts();
        this.setupExport();
        this.startAutoRefresh();
    }

    /**
     * Загрузить данные аналитики
     */
    async loadAnalyticsData() {
        try {
            const params = new URLSearchParams(this.filters);
            const response = await fetch(`/api/analytics/data?${params}`);
            
            if (!response.ok) throw new Error('Failed to load analytics');
            
            this.data = await response.json();
            this.updateCharts();
            this.updateMetrics();
        } catch (error) {
            console.error('Error loading analytics:', error);
            this.showError();
        }
    }

    /**
     * Настроить фильтры
     */
    setupFilters() {
        // Период
        document.querySelectorAll('[data-period]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.filters.period = e.target.dataset.period;
                this.updateActiveFilter('period', e.target);
                this.loadAnalyticsData();
            });
        });

        // Категория
        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.filters.category = e.target.value;
                this.loadAnalyticsData();
            });
        }

        // Пользователь
        const userFilter = document.getElementById('user-filter');
        if (userFilter) {
            userFilter.addEventListener('change', (e) => {
                this.filters.user = e.target.value;
                this.loadAnalyticsData();
            });
        }
    }

    /**
     * Обновить активный фильтр
     */
    updateActiveFilter(type, element) {
        document.querySelectorAll(`[data-${type}]`).forEach(btn => {
            btn.classList.remove('active');
        });
        element.classList.add('active');
    }

    /**
     * Создать графики
     */
    createCharts() {
        this.createTasksOverTimeChart();
        this.createCompletionRateChart();
        this.createPriorityDistributionChart();
        this.createCategoryBreakdownChart();
        this.createProductivityHeatmap();
        this.createVelocityChart();
    }

    /**
     * График задач во времени
     */
    createTasksOverTimeChart() {
        const canvas = document.getElementById('tasks-over-time-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Создано',
                        data: [],
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Завершено',
                        data: [],
                        borderColor: 'rgb(40, 167, 69)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        this.charts.set('tasksOverTime', chart);
    }

    /**
     * График процента завершения
     */
    createCompletionRateChart() {
        const canvas = document.getElementById('completion-rate-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Завершено', 'В процессе', 'В ожидании'],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgb(40, 167, 69)',
                        'rgb(23, 162, 184)',
                        'rgb(255, 193, 7)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        this.charts.set('completionRate', chart);
    }

    /**
     * График распределения по приоритетам
     */
    createPriorityDistributionChart() {
        const canvas = document.getElementById('priority-distribution-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Высокий', 'Средний', 'Низкий'],
                datasets: [{
                    label: 'Количество задач',
                    data: [],
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        this.charts.set('priorityDistribution', chart);
    }

    /**
     * График разбивки по категориям
     */
    createCategoryBreakdownChart() {
        const canvas = document.getElementById('category-breakdown-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        this.charts.set('categoryBreakdown', chart);
    }

    /**
     * Тепловая карта продуктивности
     */
    createProductivityHeatmap() {
        const container = document.getElementById('productivity-heatmap');
        if (!container) return;

        // Создать SVG тепловую карту
        const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        const hours = Array.from({length: 24}, (_, i) => i);

        let html = '<div class="heatmap-container">';
        html += '<div class="heatmap-labels-y">';
        days.forEach(day => {
            html += `<div class="heatmap-label">${day}</div>`;
        });
        html += '</div>';
        html += '<div class="heatmap-grid">';
        
        days.forEach((day, dayIndex) => {
            html += '<div class="heatmap-row">';
            hours.forEach((hour, hourIndex) => {
                html += `<div class="heatmap-cell" data-day="${dayIndex}" data-hour="${hourIndex}" title="${day} ${hour}:00"></div>`;
            });
            html += '</div>';
        });
        
        html += '</div>';
        html += '</div>';

        container.innerHTML = html;
        this.addHeatmapStyles();
    }

    /**
     * График скорости выполнения
     */
    createVelocityChart() {
        const canvas = document.getElementById('velocity-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Скорость (задач/день)',
                    data: [],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 1
                        }
                    }
                }
            }
        });

        this.charts.set('velocity', chart);
    }

    /**
     * Обновить графики
     */
    updateCharts() {
        if (!this.data) return;

        // Обновить график задач во времени
        const tasksChart = this.charts.get('tasksOverTime');
        if (tasksChart && this.data.tasksOverTime) {
            tasksChart.data.labels = this.data.tasksOverTime.labels;
            tasksChart.data.datasets[0].data = this.data.tasksOverTime.created;
            tasksChart.data.datasets[1].data = this.data.tasksOverTime.completed;
            tasksChart.update();
        }

        // Обновить график завершения
        const completionChart = this.charts.get('completionRate');
        if (completionChart && this.data.completionRate) {
            completionChart.data.datasets[0].data = [
                this.data.completionRate.completed,
                this.data.completionRate.inProgress,
                this.data.completionRate.pending
            ];
            completionChart.update();
        }

        // Обновить график приоритетов
        const priorityChart = this.charts.get('priorityDistribution');
        if (priorityChart && this.data.priorityDistribution) {
            priorityChart.data.datasets[0].data = [
                this.data.priorityDistribution.high,
                this.data.priorityDistribution.medium,
                this.data.priorityDistribution.low
            ];
            priorityChart.update();
        }

        // Обновить график категорий
        const categoryChart = this.charts.get('categoryBreakdown');
        if (categoryChart && this.data.categoryBreakdown) {
            categoryChart.data.labels = this.data.categoryBreakdown.labels;
            categoryChart.data.datasets[0].data = this.data.categoryBreakdown.values;
            categoryChart.update();
        }

        // Обновить тепловую карту
        if (this.data.productivityHeatmap) {
            this.updateHeatmap(this.data.productivityHeatmap);
        }

        // Обновить график скорости
        const velocityChart = this.charts.get('velocity');
        if (velocityChart && this.data.velocity) {
            velocityChart.data.labels = this.data.velocity.labels;
            velocityChart.data.datasets[0].data = this.data.velocity.values;
            velocityChart.update();
        }
    }

    /**
     * Обновить тепловую карту
     */
    updateHeatmap(data) {
        const cells = document.querySelectorAll('.heatmap-cell');
        
        // Найти максимальное значение для нормализации
        const maxValue = Math.max(...Object.values(data).flat());
        
        cells.forEach(cell => {
            const day = parseInt(cell.dataset.day);
            const hour = parseInt(cell.dataset.hour);
            const value = data[day]?.[hour] || 0;
            
            // Нормализовать значение (0-1)
            const normalized = maxValue > 0 ? value / maxValue : 0;
            
            // Установить цвет
            const intensity = Math.round(normalized * 255);
            cell.style.backgroundColor = `rgba(102, 126, 234, ${normalized})`;
            cell.setAttribute('data-value', value);
            
            // Обновить tooltip
            const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            cell.title = `${days[day]} ${hour}:00 - ${value} задач`;
        });
    }

    /**
     * Обновить метрики
     */
    updateMetrics() {
        if (!this.data.metrics) return;

        // Обновить карточки метрик
        this.updateMetricCard('total-tasks', this.data.metrics.totalTasks);
        this.updateMetricCard('completion-rate', `${this.data.metrics.completionRate}%`);
        this.updateMetricCard('avg-completion-time', `${this.data.metrics.avgCompletionTime} дн`);
        this.updateMetricCard('productivity-score', this.data.metrics.productivityScore);
    }

    /**
     * Обновить карточку метрики
     */
    updateMetricCard(id, value) {
        const card = document.querySelector(`[data-metric="${id}"]`);
        if (!card) return;

        const valueEl = card.querySelector('.metric-value');
        if (valueEl) {
            const oldValue = parseFloat(valueEl.textContent) || 0;
            this.animateValue(valueEl, oldValue, parseFloat(value) || value, 1000);
        }
    }

    /**
     * Анимировать значение
     */
    animateValue(element, start, end, duration) {
        if (typeof end === 'string') {
            element.textContent = end;
            return;
        }

        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            
            element.textContent = Math.round(current);
        }, 16);
    }

    /**
     * Настроить экспорт
     */
    setupExport() {
        const exportBtn = document.getElementById('export-analytics');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportData();
            });
        }
    }

    /**
     * Экспортировать данные
     */
    async exportData() {
        try {
            const params = new URLSearchParams(this.filters);
            const response = await fetch(`/api/analytics/export?${params}`);
            
            if (!response.ok) throw new Error('Export failed');
            
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `analytics-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showNotification('Данные экспортированы', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Ошибка экспорта', 'error');
        }
    }

    /**
     * Начать автообновление
     */
    startAutoRefresh() {
        setInterval(() => {
            this.loadAnalyticsData();
        }, 300000); // 5 минут
    }

    /**
     * Добавить стили тепловой карты
     */
    addHeatmapStyles() {
        if (document.getElementById('heatmapStyles')) return;

        const style = document.createElement('style');
        style.id = 'heatmapStyles';
        style.textContent = `
            .heatmap-container {
                display: flex;
                gap: 0.5rem;
            }

            .heatmap-labels-y {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .heatmap-label {
                height: 20px;
                display: flex;
                align-items: center;
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .heatmap-grid {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .heatmap-row {
                display: flex;
                gap: 2px;
            }

            .heatmap-cell {
                width: 20px;
                height: 20px;
                background: var(--bg-body);
                border-radius: 2px;
                cursor: pointer;
                transition: transform 0.2s ease;
            }

            .heatmap-cell:hover {
                transform: scale(1.2);
                z-index: 10;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Показать ошибку
     */
    showError() {
        this.showNotification('Ошибка загрузки аналитики', 'error');
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('analytics-dashboard')) {
            window.analyticsDashboard = new AnalyticsDashboard();
        }
    });
} else {
    if (document.getElementById('analytics-dashboard')) {
        window.analyticsDashboard = new AnalyticsDashboard();
    }
}

// Экспорт
window.AnalyticsDashboard = AnalyticsDashboard;
