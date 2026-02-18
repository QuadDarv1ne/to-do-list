/**
 * Dashboard Widgets
 * Interactive dashboard components and charts
 */

(function() {
    'use strict';

    class DashboardWidgets {
        constructor() {
            this.init();
        }

        init() {
            this.initCounters();
            this.initProgressBars();
            this.initCharts();
            this.initRefreshButtons();
        }

        /**
         * Animated counters
         */
        initCounters() {
            const counters = document.querySelectorAll('[data-counter-value]');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            counters.forEach(counter => observer.observe(counter));
        }

        animateCounter(element) {
            const target = parseInt(element.getAttribute('data-counter-value'));
            const duration = parseInt(element.getAttribute('data-counter-duration')) || 2000;
            const step = target / (duration / 16);
            let current = 0;

            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    element.textContent = this.formatNumber(target);
                    clearInterval(timer);
                } else {
                    element.textContent = this.formatNumber(Math.floor(current));
                }
            }, 16);
        }

        formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        /**
         * Animated progress bars
         */
        initProgressBars() {
            const progressBars = document.querySelectorAll('[data-progress]');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const bar = entry.target;
                        const targetWidth = bar.getAttribute('data-progress');
                        
                        setTimeout(() => {
                            bar.style.width = targetWidth + '%';
                        }, 100);
                        
                        observer.unobserve(bar);
                    }
                });
            }, { threshold: 0.5 });

            progressBars.forEach(bar => {
                bar.style.width = '0%';
                bar.style.transition = 'width 1s ease-out';
                observer.observe(bar);
            });
        }

        /**
         * Initialize charts
         */
        initCharts() {
            // Simple bar chart
            document.querySelectorAll('[data-chart="bar"]').forEach(chart => {
                this.createBarChart(chart);
            });

            // Simple line chart
            document.querySelectorAll('[data-chart="line"]').forEach(chart => {
                this.createLineChart(chart);
            });

            // Donut chart
            document.querySelectorAll('[data-chart="donut"]').forEach(chart => {
                this.createDonutChart(chart);
            });
        }

        createBarChart(container) {
            const data = JSON.parse(container.getAttribute('data-chart-data'));
            const maxValue = Math.max(...data.map(d => d.value));
            
            const html = data.map(item => {
                const height = (item.value / maxValue) * 100;
                return `
                    <div class="chart-bar-item">
                        <div class="chart-bar" style="height: ${height}%" data-value="${item.value}">
                            <span class="chart-bar-value">${item.value}</span>
                        </div>
                        <div class="chart-bar-label">${item.label}</div>
                    </div>
                `;
            }).join('');

            container.innerHTML = `<div class="chart-bar-container">${html}</div>`;
        }

        createLineChart(container) {
            const data = JSON.parse(container.getAttribute('data-chart-data'));
            const width = container.offsetWidth;
            const height = 200;
            const padding = 20;
            
            const maxValue = Math.max(...data.map(d => d.value));
            const minValue = Math.min(...data.map(d => d.value));
            const range = maxValue - minValue;
            
            const points = data.map((item, index) => {
                const x = padding + (index / (data.length - 1)) * (width - padding * 2);
                const y = height - padding - ((item.value - minValue) / range) * (height - padding * 2);
                return `${x},${y}`;
            }).join(' ');

            container.innerHTML = `
                <svg width="${width}" height="${height}" class="line-chart">
                    <polyline points="${points}" fill="none" stroke="#0d6efd" stroke-width="2"/>
                    ${data.map((item, index) => {
                        const x = padding + (index / (data.length - 1)) * (width - padding * 2);
                        const y = height - padding - ((item.value - minValue) / range) * (height - padding * 2);
                        return `<circle cx="${x}" cy="${y}" r="4" fill="#0d6efd"/>`;
                    }).join('')}
                </svg>
            `;
        }

        createDonutChart(container) {
            const data = JSON.parse(container.getAttribute('data-chart-data'));
            const total = data.reduce((sum, item) => sum + item.value, 0);
            
            let currentAngle = -90;
            const radius = 80;
            const centerX = 100;
            const centerY = 100;
            
            const paths = data.map((item, index) => {
                const percentage = (item.value / total) * 100;
                const angle = (percentage / 100) * 360;
                const endAngle = currentAngle + angle;
                
                const startX = centerX + radius * Math.cos(currentAngle * Math.PI / 180);
                const startY = centerY + radius * Math.sin(currentAngle * Math.PI / 180);
                const endX = centerX + radius * Math.cos(endAngle * Math.PI / 180);
                const endY = centerY + radius * Math.sin(endAngle * Math.PI / 180);
                
                const largeArc = angle > 180 ? 1 : 0;
                
                const path = `
                    M ${centerX} ${centerY}
                    L ${startX} ${startY}
                    A ${radius} ${radius} 0 ${largeArc} 1 ${endX} ${endY}
                    Z
                `;
                
                currentAngle = endAngle;
                
                const colors = ['#0d6efd', '#6c757d', '#28a745', '#ffc107', '#dc3545'];
                return `<path d="${path}" fill="${colors[index % colors.length]}" opacity="0.8"/>`;
            }).join('');

            container.innerHTML = `
                <svg width="200" height="200" viewBox="0 0 200 200" class="donut-chart">
                    ${paths}
                    <circle cx="${centerX}" cy="${centerY}" r="50" fill="white"/>
                </svg>
            `;
        }

        /**
         * Refresh buttons
         */
        initRefreshButtons() {
            document.querySelectorAll('[data-refresh]').forEach(button => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const target = button.getAttribute('data-refresh');
                    const url = button.getAttribute('data-refresh-url');
                    
                    button.disabled = true;
                    const icon = button.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    try {
                        const response = await fetch(url);
                        const html = await response.text();
                        
                        const targetElement = document.querySelector(target);
                        if (targetElement) {
                            targetElement.innerHTML = html;
                            window.notify?.success('Данные обновлены');
                        }
                    } catch (error) {
                        console.error('Refresh error:', error);
                        window.notify?.error('Ошибка обновления данных');
                    } finally {
                        button.disabled = false;
                        icon.className = originalClass;
                    }
                });
            });
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        new DashboardWidgets();
    });

})();

// Add CSS for charts
const style = document.createElement('style');
style.textContent = `
    .chart-bar-container {
        display: flex;
        align-items: flex-end;
        justify-content: space-around;
        height: 200px;
        padding: 20px;
        gap: 10px;
    }

    .chart-bar-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .chart-bar {
        width: 100%;
        background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%);
        border-radius: 4px 4px 0 0;
        position: relative;
        transition: all 0.3s ease;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 5px;
    }

    .chart-bar:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    }

    .chart-bar-value {
        color: white;
        font-weight: bold;
        font-size: 0.875rem;
    }

    .chart-bar-label {
        font-size: 0.75rem;
        color: #6c757d;
        text-align: center;
    }

    .line-chart {
        width: 100%;
        height: auto;
    }

    .donut-chart {
        width: 100%;
        height: auto;
    }

    [data-theme='dark'] .chart-bar-label {
        color: #a0aec0;
    }
`;
document.head.appendChild(style);
