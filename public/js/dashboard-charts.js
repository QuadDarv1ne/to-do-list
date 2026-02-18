/**
 * Dashboard Charts & Widgets
 * Interactive charts and animated statistics
 */

class DashboardCharts {
    constructor() {
        this.charts = {};
        this.init();
    }

    init() {
        this.initCounterAnimations();
        this.initSparklines();
        this.initCharts();
        this.initRealTimeUpdates();
    }

    /**
     * Animate counters on dashboard
     */
    initCounterAnimations() {
        const counters = document.querySelectorAll('[data-counter]');
        
        const observerOptions = {
            threshold: 0.5
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.animated) {
                    const target = parseInt(entry.target.dataset.counter);
                    const duration = parseInt(entry.target.dataset.duration) || 1000;
                    this.animateCounter(entry.target, target, duration);
                    entry.target.dataset.animated = 'true';
                }
            });
        }, observerOptions);

        counters.forEach(counter => observer.observe(counter));
    }

    /**
     * Animate counter from 0 to target
     */
    animateCounter(element, target, duration = 1000) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        const prefix = element.dataset.prefix || '';
        const suffix = element.dataset.suffix || '';

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = prefix + target.toLocaleString('ru-RU') + suffix;
                clearInterval(timer);
            } else {
                element.textContent = prefix + Math.round(current).toLocaleString('ru-RU') + suffix;
            }
        }, 16);
    }

    /**
     * Initialize sparkline mini charts
     */
    initSparklines() {
        document.querySelectorAll('[data-sparkline]').forEach(el => {
            const data = JSON.parse(el.dataset.sparkline);
            this.createSparkline(el, data);
        });
    }

    /**
     * Create sparkline SVG
     */
    createSparkline(container, data) {
        const width = container.offsetWidth || 100;
        const height = container.offsetHeight || 30;
        const padding = 2;

        const max = Math.max(...data);
        const min = Math.min(...data);
        const range = max - min || 1;

        const points = data.map((value, index) => {
            const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
            const y = height - padding - ((value - min) / range) * (height - padding * 2);
            return `${x},${y}`;
        }).join(' ');

        const svg = `
            <svg width="${width}" height="${height}" class="sparkline">
                <polyline
                    points="${points}"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        `;

        container.innerHTML = svg;
    }

    /**
     * Initialize main charts
     */
    initCharts() {
        // Task completion trend chart
        const trendCanvas = document.getElementById('taskTrendChart');
        if (trendCanvas) {
            this.createTrendChart(trendCanvas);
        }

        // Priority distribution chart
        const priorityCanvas = document.getElementById('priorityChart');
        if (priorityCanvas) {
            this.createPriorityChart(priorityCanvas);
        }

        // Status distribution chart
        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            this.createStatusChart(statusCanvas);
        }
    }

    /**
     * Create task trend line chart
     */
    createTrendChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        // Sample data - replace with real data from API
        const labels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        const data = [12, 19, 15, 25, 22, 30, 28];

        this.drawLineChart(ctx, labels, data, {
            color: '#667eea',
            fillColor: 'rgba(102, 126, 234, 0.1)'
        });
    }

    /**
     * Create priority pie chart
     */
    createPriorityChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        const data = [
            { label: 'Высокий', value: 15, color: '#dc3545' },
            { label: 'Средний', value: 35, color: '#ffc107' },
            { label: 'Низкий', value: 50, color: '#28a745' }
        ];

        this.drawPieChart(ctx, data);
    }

    /**
     * Create status bar chart
     */
    createStatusChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        const data = [
            { label: 'Новые', value: 25, color: '#17a2b8' },
            { label: 'В работе', value: 45, color: '#667eea' },
            { label: 'Завершено', value: 30, color: '#28a745' }
        ];

        this.drawBarChart(ctx, data);
    }

    /**
     * Draw simple line chart
     */
    drawLineChart(ctx, labels, data, options = {}) {
        const canvas = ctx.canvas;
        const width = canvas.width;
        const height = canvas.height;
        const padding = 40;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Calculate points
        const max = Math.max(...data);
        const step = (width - padding * 2) / (data.length - 1);
        const points = data.map((value, index) => ({
            x: padding + index * step,
            y: height - padding - (value / max) * (height - padding * 2)
        }));

        // Draw grid
        ctx.strokeStyle = '#e0e0e0';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = padding + (height - padding * 2) * (i / 4);
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }

        // Draw area fill
        if (options.fillColor) {
            ctx.fillStyle = options.fillColor;
            ctx.beginPath();
            ctx.moveTo(points[0].x, height - padding);
            points.forEach(point => ctx.lineTo(point.x, point.y));
            ctx.lineTo(points[points.length - 1].x, height - padding);
            ctx.closePath();
            ctx.fill();
        }

        // Draw line
        ctx.strokeStyle = options.color || '#667eea';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        points.forEach(point => ctx.lineTo(point.x, point.y));
        ctx.stroke();

        // Draw points
        points.forEach(point => {
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = options.color || '#667eea';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        });

        // Draw labels
        ctx.fillStyle = '#666';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        labels.forEach((label, index) => {
            ctx.fillText(label, padding + index * step, height - 10);
        });
    }

    /**
     * Draw simple pie chart
     */
    drawPieChart(ctx, data) {
        const canvas = ctx.canvas;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = Math.min(centerX, centerY) - 20;

        const total = data.reduce((sum, item) => sum + item.value, 0);
        let currentAngle = -Math.PI / 2;

        data.forEach(item => {
            const sliceAngle = (item.value / total) * Math.PI * 2;

            // Draw slice
            ctx.fillStyle = item.color;
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.closePath();
            ctx.fill();

            // Draw border
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            currentAngle += sliceAngle;
        });

        // Draw legend
        let legendY = 20;
        data.forEach(item => {
            ctx.fillStyle = item.color;
            ctx.fillRect(10, legendY, 15, 15);
            
            ctx.fillStyle = '#333';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(`${item.label}: ${item.value}`, 30, legendY + 12);
            
            legendY += 25;
        });
    }

    /**
     * Draw simple bar chart
     */
    drawBarChart(ctx, data) {
        const canvas = ctx.canvas;
        const width = canvas.width;
        const height = canvas.height;
        const padding = 40;
        const barWidth = (width - padding * 2) / data.length - 10;

        const max = Math.max(...data.map(d => d.value));

        data.forEach((item, index) => {
            const barHeight = (item.value / max) * (height - padding * 2);
            const x = padding + index * (barWidth + 10);
            const y = height - padding - barHeight;

            // Draw bar
            ctx.fillStyle = item.color;
            ctx.fillRect(x, y, barWidth, barHeight);

            // Draw label
            ctx.fillStyle = '#333';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(item.label, x + barWidth / 2, height - 10);

            // Draw value
            ctx.fillText(item.value, x + barWidth / 2, y - 5);
        });
    }

    /**
     * Real-time updates simulation
     */
    initRealTimeUpdates() {
        // Update counters every 30 seconds
        setInterval(() => {
            this.updateCounters();
        }, 30000);
    }

    /**
     * Update dashboard counters
     */
    async updateCounters() {
        try {
            // Fetch fresh data from API
            const response = await fetch('/api/dashboard/stats');
            if (!response.ok) return;

            const data = await response.json();

            // Update each counter
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[data-counter-id="${key}"]`);
                if (element) {
                    const newValue = data[key];
                    this.animateCounter(element, newValue, 500);
                }
            });
        } catch (error) {
            console.error('Failed to update counters:', error);
        }
    }

    /**
     * Destroy all charts
     */
    destroy() {
        Object.values(this.charts).forEach(chart => {
            if (chart && chart.destroy) {
                chart.destroy();
            }
        });
        this.charts = {};
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelector('[data-counter], [data-sparkline], canvas[id*="Chart"]')) {
            window.dashboardCharts = new DashboardCharts();
        }
    });
} else {
    if (document.querySelector('[data-counter], [data-sparkline], canvas[id*="Chart"]')) {
        window.dashboardCharts = new DashboardCharts();
    }
}

// Export for use in other scripts
window.DashboardCharts = DashboardCharts;
