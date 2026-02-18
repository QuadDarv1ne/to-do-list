/**
 * Enhanced Dashboard Functionality
 * Provides interactive widgets, real-time updates, and smooth animations
 */

class DashboardManager {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.initQuickActions();
        this.initActivityChart();
        this.initAutoRefresh();
        this.initKeyboardShortcuts();
        this.initTooltips();
        this.initAnimations();
    }

    /**
     * Quick Actions Menu
     */
    initQuickActions() {
        const quickActionsBtn = document.getElementById('quickActionsBtn');
        const quickActionsMenu = document.getElementById('quickActionsMenu');

        if (quickActionsBtn && quickActionsMenu) {
            quickActionsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                quickActionsMenu.classList.toggle('active');
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!quickActionsBtn.contains(e.target) && !quickActionsMenu.contains(e.target)) {
                    quickActionsMenu.classList.remove('active');
                }
            });

            // Add ripple effect to quick action items
            const quickActionItems = quickActionsMenu.querySelectorAll('.quick-action-item');
            quickActionItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple');
                    this.appendChild(ripple);

                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';

                    setTimeout(() => ripple.remove(), 600);
                });
            });
        }
    }

    /**
     * Activity Chart with Chart.js
     */
    initActivityChart() {
        const canvas = document.getElementById('activityChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const ctx = canvas.getContext('2d');
        
        // Sample data - replace with actual data from backend
        const data = {
            labels: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            datasets: [
                {
                    label: 'Задачи',
                    data: [12, 19, 15, 25, 22, 18, 20],
                    borderColor: 'rgba(102, 126, 234, 1)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Завершено',
                    data: [8, 15, 12, 20, 18, 14, 16],
                    borderColor: 'rgba(17, 153, 142, 1)',
                    backgroundColor: 'rgba(17, 153, 142, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        };

        this.charts.activity = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        borderRadius: 8,
                        titleFont: {
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Period switcher
        const periodButtons = document.querySelectorAll('[data-period]');
        periodButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                periodButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.updateActivityChart(btn.dataset.period);
            });
        });
    }

    /**
     * Update activity chart based on period
     */
    updateActivityChart(period) {
        if (!this.charts.activity) return;

        // Simulate data update - replace with actual API call
        const newData = period === 'week' 
            ? {
                labels: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                tasks: [12, 19, 15, 25, 22, 18, 20],
                completed: [8, 15, 12, 20, 18, 14, 16]
              }
            : {
                labels: ['Нед 1', 'Нед 2', 'Нед 3', 'Нед 4'],
                tasks: [85, 92, 78, 95],
                completed: [70, 80, 65, 85]
              };

        this.charts.activity.data.labels = newData.labels;
        this.charts.activity.data.datasets[0].data = newData.tasks;
        this.charts.activity.data.datasets[1].data = newData.completed;
        this.charts.activity.update('active');

        // Update stats
        const weekTasks = newData.tasks.reduce((a, b) => a + b, 0);
        const weekCompleted = newData.completed.reduce((a, b) => a + b, 0);
        const productivity = Math.round((weekCompleted / weekTasks) * 100);

        this.animateValue('week-tasks', 0, weekTasks, 1000);
        this.animateValue('week-completed', 0, weekCompleted, 1000);
        this.animateValue('week-productivity', 0, productivity, 1000, '%');
    }

    /**
     * Animate number counting
     */
    animateValue(id, start, end, duration, suffix = '') {
        const element = document.getElementById(id);
        if (!element) return;

        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            element.textContent = Math.round(current) + suffix;
        }, 16);
    }

    /**
     * Auto-refresh dashboard data
     */
    initAutoRefresh() {
        // Refresh every 5 minutes
        this.refreshInterval = setInterval(() => {
            this.refreshDashboardData();
        }, 300000);
    }

    /**
     * Refresh dashboard data via AJAX
     */
    async refreshDashboardData() {
        try {
            // Placeholder for actual API call
            console.log('Refreshing dashboard data...');
            
            // Example: fetch('/api/dashboard/stats')
            //     .then(response => response.json())
            //     .then(data => this.updateStats(data));
        } catch (error) {
            console.error('Failed to refresh dashboard:', error);
        }
    }

    /**
     * Keyboard shortcuts
     */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K: Quick search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('#searchInput, [type="search"]');
                if (searchInput) searchInput.focus();
            }

            // Ctrl/Cmd + N: New task
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                const newTaskBtn = document.querySelector('[href*="task_new"]');
                if (newTaskBtn) newTaskBtn.click();
            }

            // Escape: Close modals/menus
            if (e.key === 'Escape') {
                const quickActionsMenu = document.getElementById('quickActionsMenu');
                if (quickActionsMenu) quickActionsMenu.classList.remove('active');
            }
        });
    }

    /**
     * Initialize tooltips
     */
    initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.dataset.tooltip;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

                setTimeout(() => tooltip.classList.add('show'), 10);

                this.addEventListener('mouseleave', function() {
                    tooltip.classList.remove('show');
                    setTimeout(() => tooltip.remove(), 300);
                }, { once: true });
            });
        });
    }

    /**
     * Initialize scroll animations
     */
    initAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        const animatedElements = document.querySelectorAll('.card, .stat-card-client, .stat-card-deal');
        animatedElements.forEach(el => observer.observe(el));
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboardManager = new DashboardManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.dashboardManager) {
        window.dashboardManager.destroy();
    }
});

// Add custom styles for tooltips and ripple effect
const style = document.createElement('style');
style.textContent = `
    .custom-tooltip {
        position: fixed;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.875rem;
        pointer-events: none;
        z-index: 10000;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 0.3s, transform 0.3s;
        white-space: nowrap;
    }

    .custom-tooltip.show {
        opacity: 1;
        transform: translateY(0);
    }

    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }

    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }

    .animate-in {
        animation: fadeInUp 0.6s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);
