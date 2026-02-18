/**
 * Data Visualization
 * Интерактивная визуализация данных
 */

class DataVisualization {
    constructor() {
        this.visualizations = new Map();
        this.colors = {
            primary: 'rgb(102, 126, 234)',
            success: 'rgb(40, 167, 69)',
            warning: 'rgb(255, 193, 7)',
            danger: 'rgb(220, 53, 69)',
            info: 'rgb(23, 162, 184)'
        };
        this.init();
    }

    init() {
        this.createProgressRings();
        this.createSparklines();
        this.createMiniCharts();
        this.setupInteractiveElements();
    }

    /**
     * Создать кольца прогресса
     */
    createProgressRings() {
        document.querySelectorAll('[data-progress-ring]').forEach(element => {
            const value = parseFloat(element.dataset.progressRing);
            const size = parseInt(element.dataset.size) || 120;
            const strokeWidth = parseInt(element.dataset.strokeWidth) || 10;
            const color = element.dataset.color || this.colors.primary;

            this.renderProgressRing(element, value, size, strokeWidth, color);
        });
    }

    /**
     * Отрисовать кольцо прогресса
     */
    renderProgressRing(element, value, size, strokeWidth, color) {
        const radius = (size - strokeWidth) / 2;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (value / 100) * circumference;

        const svg = `
            <svg width="${size}" height="${size}" class="progress-ring">
                <circle
                    class="progress-ring-circle-bg"
                    stroke="var(--border)"
                    stroke-width="${strokeWidth}"
                    fill="transparent"
                    r="${radius}"
                    cx="${size / 2}"
                    cy="${size / 2}"
                />
                <circle
                    class="progress-ring-circle"
                    stroke="${color}"
                    stroke-width="${strokeWidth}"
                    stroke-dasharray="${circumference} ${circumference}"
                    stroke-dashoffset="${offset}"
                    stroke-linecap="round"
                    fill="transparent"
                    r="${radius}"
                    cx="${size / 2}"
                    cy="${size / 2}"
                    transform="rotate(-90 ${size / 2} ${size / 2})"
                />
                <text
                    x="50%"
                    y="50%"
                    text-anchor="middle"
                    dy=".3em"
                    class="progress-ring-text"
                    fill="var(--text-primary)"
                    font-size="${size / 4}"
                    font-weight="600"
                >
                    ${Math.round(value)}%
                </text>
            </svg>
        `;

        element.innerHTML = svg;
        this.addProgressRingStyles();
    }

    /**
     * Добавить стили колец прогресса
     */
    addProgressRingStyles() {
        if (document.getElementById('progressRingStyles')) return;

        const style = document.createElement('style');
        style.id = 'progressRingStyles';
        style.textContent = `
            .progress-ring {
                transform: rotate(0deg);
            }

            .progress-ring-circle {
                transition: stroke-dashoffset 1s ease;
            }

            .progress-ring-text {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Создать спарклайны
     */
    createSparklines() {
        document.querySelectorAll('[data-sparkline]').forEach(element => {
            const data = JSON.parse(element.dataset.sparkline);
            const color = element.dataset.color || this.colors.primary;
            const height = parseInt(element.dataset.height) || 40;

            this.renderSparkline(element, data, color, height);
        });
    }

    /**
     * Отрисовать спарклайн
     */
    renderSparkline(element, data, color, height) {
        const width = element.offsetWidth || 200;
        const padding = 2;
        const max = Math.max(...data);
        const min = Math.min(...data);
        const range = max - min || 1;

        const points = data.map((value, index) => {
            const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
            const y = height - ((value - min) / range) * (height - padding * 2) - padding;
            return `${x},${y}`;
        }).join(' ');

        const svg = `
            <svg width="${width}" height="${height}" class="sparkline">
                <polyline
                    points="${points}"
                    fill="none"
                    stroke="${color}"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <circle
                    cx="${width - padding}"
                    cy="${height - ((data[data.length - 1] - min) / range) * (height - padding * 2) - padding}"
                    r="3"
                    fill="${color}"
                />
            </svg>
        `;

        element.innerHTML = svg;
    }

    /**
     * Создать мини-графики
     */
    createMiniCharts() {
        document.querySelectorAll('[data-mini-chart]').forEach(element => {
            const type = element.dataset.miniChart;
            const data = JSON.parse(element.dataset.data || '[]');

            switch (type) {
                case 'bar':
                    this.renderMiniBarChart(element, data);
                    break;
                case 'line':
                    this.renderMiniLineChart(element, data);
                    break;
                case 'area':
                    this.renderMiniAreaChart(element, data);
                    break;
            }
        });
    }

    /**
     * Отрисовать мини-столбчатый график
     */
    renderMiniBarChart(element, data) {
        const width = element.offsetWidth || 200;
        const height = parseInt(element.dataset.height) || 60;
        const max = Math.max(...data);
        const barWidth = width / data.length - 2;

        const bars = data.map((value, index) => {
            const barHeight = (value / max) * height;
            const x = index * (barWidth + 2);
            const y = height - barHeight;
            return `<rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="${this.colors.primary}" rx="2"/>`;
        }).join('');

        element.innerHTML = `
            <svg width="${width}" height="${height}" class="mini-chart">
                ${bars}
            </svg>
        `;
    }

    /**
     * Отрисовать мини-линейный график
     */
    renderMiniLineChart(element, data) {
        const width = element.offsetWidth || 200;
        const height = parseInt(element.dataset.height) || 60;
        const max = Math.max(...data);
        const min = Math.min(...data);
        const range = max - min || 1;

        const points = data.map((value, index) => {
            const x = (index / (data.length - 1)) * width;
            const y = height - ((value - min) / range) * height;
            return `${x},${y}`;
        }).join(' ');

        element.innerHTML = `
            <svg width="${width}" height="${height}" class="mini-chart">
                <polyline
                    points="${points}"
                    fill="none"
                    stroke="${this.colors.primary}"
                    stroke-width="2"
                />
            </svg>
        `;
    }

    /**
     * Отрисовать мини-график с областью
     */
    renderMiniAreaChart(element, data) {
        const width = element.offsetWidth || 200;
        const height = parseInt(element.dataset.height) || 60;
        const max = Math.max(...data);
        const min = Math.min(...data);
        const range = max - min || 1;

        const points = data.map((value, index) => {
            const x = (index / (data.length - 1)) * width;
            const y = height - ((value - min) / range) * height;
            return `${x},${y}`;
        }).join(' ');

        const areaPoints = `0,${height} ${points} ${width},${height}`;

        element.innerHTML = `
            <svg width="${width}" height="${height}" class="mini-chart">
                <polygon
                    points="${areaPoints}"
                    fill="${this.colors.primary}"
                    fill-opacity="0.2"
                />
                <polyline
                    points="${points}"
                    fill="none"
                    stroke="${this.colors.primary}"
                    stroke-width="2"
                />
            </svg>
        `;
    }

    /**
     * Настроить интерактивные элементы
     */
    setupInteractiveElements() {
        // Анимация при наведении
        document.querySelectorAll('.stat-card, .metric-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.animateCard(card);
            });
        });

        // Обновление при изменении размера окна
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    }

    /**
     * Анимировать карточку
     */
    animateCard(card) {
        const sparkline = card.querySelector('[data-sparkline]');
        if (sparkline) {
            sparkline.style.transform = 'scale(1.05)';
            setTimeout(() => {
                sparkline.style.transform = 'scale(1)';
            }, 200);
        }
    }

    /**
     * Обработать изменение размера
     */
    handleResize() {
        // Пересоздать спарклайны
        this.createSparklines();
        this.createMiniCharts();
    }

    /**
     * Создать интерактивную легенду
     */
    createInteractiveLegend(chartId, data) {
        const container = document.getElementById(`${chartId}-legend`);
        if (!container) return;

        const html = data.map((item, index) => `
            <div class="legend-item" data-index="${index}">
                <div class="legend-color" style="background: ${item.color}"></div>
                <div class="legend-label">${item.label}</div>
                <div class="legend-value">${item.value}</div>
            </div>
        `).join('');

        container.innerHTML = html;

        // Добавить интерактивность
        container.querySelectorAll('.legend-item').forEach(item => {
            item.addEventListener('click', () => {
                this.toggleDataset(chartId, parseInt(item.dataset.index));
            });
        });

        this.addLegendStyles();
    }

    /**
     * Переключить датасет
     */
    toggleDataset(chartId, index) {
        const chart = this.visualizations.get(chartId);
        if (!chart) return;

        const meta = chart.getDatasetMeta(index);
        meta.hidden = !meta.hidden;
        chart.update();
    }

    /**
     * Добавить стили легенды
     */
    addLegendStyles() {
        if (document.getElementById('legendStyles')) return;

        const style = document.createElement('style');
        style.id = 'legendStyles';
        style.textContent = `
            .legend-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem;
                border-radius: 4px;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .legend-item:hover {
                background: var(--bg-body);
            }

            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 2px;
            }

            .legend-label {
                flex: 1;
                font-size: 0.875rem;
                color: var(--text-primary);
            }

            .legend-value {
                font-weight: 600;
                color: var(--text-primary);
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Создать градиентный индикатор
     */
    createGradientIndicator(element, value, min, max) {
        const percentage = ((value - min) / (max - min)) * 100;
        
        element.innerHTML = `
            <div class="gradient-indicator">
                <div class="gradient-bar">
                    <div class="gradient-fill" style="width: ${percentage}%"></div>
                </div>
                <div class="gradient-value">${value}</div>
            </div>
        `;

        this.addGradientStyles();
    }

    /**
     * Добавить стили градиента
     */
    addGradientStyles() {
        if (document.getElementById('gradientStyles')) return;

        const style = document.createElement('style');
        style.id = 'gradientStyles';
        style.textContent = `
            .gradient-indicator {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .gradient-bar {
                flex: 1;
                height: 8px;
                background: var(--bg-body);
                border-radius: 4px;
                overflow: hidden;
            }

            .gradient-fill {
                height: 100%;
                background: linear-gradient(90deg, 
                    ${this.colors.danger} 0%, 
                    ${this.colors.warning} 50%, 
                    ${this.colors.success} 100%);
                transition: width 0.5s ease;
            }

            .gradient-value {
                font-weight: 600;
                color: var(--text-primary);
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Анимировать число
     */
    animateNumber(element, start, end, duration = 1000) {
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
     * Создать пульсирующий индикатор
     */
    createPulsingIndicator(element, active = true) {
        element.innerHTML = `
            <div class="pulsing-indicator ${active ? 'active' : ''}">
                <div class="pulse-ring"></div>
                <div class="pulse-dot"></div>
            </div>
        `;

        this.addPulsingStyles();
    }

    /**
     * Добавить стили пульсации
     */
    addPulsingStyles() {
        if (document.getElementById('pulsingStyles')) return;

        const style = document.createElement('style');
        style.id = 'pulsingStyles';
        style.textContent = `
            .pulsing-indicator {
                position: relative;
                width: 12px;
                height: 12px;
            }

            .pulse-dot {
                position: absolute;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: var(--text-muted);
            }

            .pulsing-indicator.active .pulse-dot {
                background: ${this.colors.success};
            }

            .pulse-ring {
                position: absolute;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                border: 2px solid ${this.colors.success};
                opacity: 0;
            }

            .pulsing-indicator.active .pulse-ring {
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dataVisualization = new DataVisualization();
    });
} else {
    window.dataVisualization = new DataVisualization();
}

// Экспорт
window.DataVisualization = DataVisualization;
