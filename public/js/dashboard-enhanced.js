/**
 * Dashboard Enhanced
 * Modern dashboard with theme support and animations
 */

document.addEventListener('DOMContentLoaded', function() {
    initDashboard();
});

function initDashboard() {
    // Initialize all dashboard components
    initQuickActions();
    initActivityChart();
    initStatCounters();
    initTaskAnimations();
    initLoadingStates();
    initThemeAwareComponents();
}

/**
 * Quick Actions Menu
 */
function initQuickActions() {
    const quickActionsBtn = document.getElementById('quickActionsBtn');
    const quickActionsMenu = document.getElementById('quickActionsMenu');
    
    if (!quickActionsBtn || !quickActionsMenu) return;
    
    quickActionsBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        quickActionsMenu.classList.toggle('show');
        quickActionsBtn.classList.toggle('active');
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quick-actions')) {
            quickActionsMenu.classList.remove('show');
            quickActionsBtn.classList.remove('active');
        }
    });
    
    // Add ripple effect to quick action items
    const quickActionItems = document.querySelectorAll('.quick-action-item');
    quickActionItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

/**
 * Activity Chart
 */
function initActivityChart() {
    const canvas = document.getElementById('activityChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    const ctx = canvas.getContext('2d');
    
    // Get theme colors
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    const colors = getThemeColors(theme);
    
    const activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            datasets: [{
                label: 'Завершено задач',
                data: [12, 19, 15, 25, 22, 18, 20],
                borderColor: colors.primary,
                backgroundColor: colors.primaryAlpha,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: colors.primary,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }, {
                label: 'Создано задач',
                data: [8, 12, 10, 15, 14, 11, 13],
                borderColor: colors.secondary,
                backgroundColor: colors.secondaryAlpha,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: colors.secondary,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: colors.text,
                        usePointStyle: true,
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: colors.card,
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' задач';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.border,
                        drawBorder: false
                    },
                    ticks: {
                        color: colors.textSecondary,
                        padding: 8
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: colors.textSecondary,
                        padding: 8
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    // Update chart on theme change
    window.addEventListener('themechange', function(e) {
        const newColors = getThemeColors(e.detail.theme);
        
        activityChart.data.datasets[0].borderColor = newColors.primary;
        activityChart.data.datasets[0].backgroundColor = newColors.primaryAlpha;
        activityChart.data.datasets[0].pointBackgroundColor = newColors.primary;
        
        activityChart.data.datasets[1].borderColor = newColors.secondary;
        activityChart.data.datasets[1].backgroundColor = newColors.secondaryAlpha;
        activityChart.data.datasets[1].pointBackgroundColor = newColors.secondary;
        
        activityChart.options.plugins.legend.labels.color = newColors.text;
        activityChart.options.plugins.tooltip.backgroundColor = newColors.card;
        activityChart.options.plugins.tooltip.titleColor = newColors.text;
        activityChart.options.plugins.tooltip.bodyColor = newColors.text;
        activityChart.options.plugins.tooltip.borderColor = newColors.border;
        
        activityChart.options.scales.y.grid.color = newColors.border;
        activityChart.options.scales.y.ticks.color = newColors.textSecondary;
        activityChart.options.scales.x.ticks.color = newColors.textSecondary;
        
        activityChart.update();
    });
    
    // Animate stats
    animateValue('week-tasks', 0, 87, 1500);
    animateValue('week-completed', 0, 64, 1500);
    animateValue('week-streak', 0, 5, 1500);
    animateValue('week-productivity', 0, 73, 1500, '%');
}

/**
 * Get theme colors
 */
function getThemeColors(theme) {
    const themes = {
        light: {
            primary: '#667eea',
            primaryAlpha: 'rgba(102, 126, 234, 0.1)',
            secondary: '#764ba2',
            secondaryAlpha: 'rgba(118, 75, 162, 0.1)',
            text: '#212529',
            textSecondary: '#6c757d',
            border: '#e0e0e0',
            card: '#ffffff'
        },
        dark: {
            primary: '#3b82f6',
            primaryAlpha: 'rgba(59, 130, 246, 0.15)',
            secondary: '#8b5cf6',
            secondaryAlpha: 'rgba(139, 92, 246, 0.15)',
            text: '#f9fafb',
            textSecondary: '#9ca3af',
            border: '#374151',
            card: '#1f2937'
        },
        orange: {
            primary: '#f97316',
            primaryAlpha: 'rgba(249, 115, 22, 0.1)',
            secondary: '#ea580c',
            secondaryAlpha: 'rgba(234, 88, 12, 0.1)',
            text: '#1c1917',
            textSecondary: '#78716c',
            border: '#e7e5e4',
            card: '#ffffff'
        },
        purple: {
            primary: '#a855f7',
            primaryAlpha: 'rgba(168, 85, 247, 0.1)',
            secondary: '#9333ea',
            secondaryAlpha: 'rgba(147, 51, 234, 0.1)',
            text: '#1e1b4b',
            textSecondary: '#6b7280',
            border: '#e9d5ff',
            card: '#ffffff'
        }
    };
    
    return themes[theme] || themes.light;
}

/**
 * Animate stat counters
 */
function initStatCounters() {
    const statValues = document.querySelectorAll('#stats-row .card h2');
    
    statValues.forEach(stat => {
        const target = parseInt(stat.textContent);
        if (!isNaN(target)) {
            stat.textContent = '0';
            animateValue(stat, 0, target, 1000);
        }
    });
}

/**
 * Animate value
 */
function animateValue(element, start, end, duration, suffix = '') {
    const el = typeof element === 'string' ? document.getElementById(element) : element;
    if (!el) return;
    
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        el.textContent = Math.floor(current) + suffix;
    }, 16);
}

/**
 * Task animations
 */
function initTaskAnimations() {
    const taskItems = document.querySelectorAll('.task-item-enhanced');
    
    taskItems.forEach((item, index) => {
        // Stagger animation
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
        
        // Hover effect
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
}

/**
 * Loading states
 */
function initLoadingStates() {
    const recentTasksLoading = document.getElementById('recent-tasks-loading');
    const recentTasksContent = document.getElementById('recent-tasks-content');
    
    if (recentTasksLoading && recentTasksContent) {
        setTimeout(() => {
            recentTasksLoading.style.display = 'none';
            recentTasksContent.style.display = 'block';
            recentTasksContent.style.animation = 'fadeIn 0.5s ease';
        }, 1000);
    }
}

/**
 * Theme-aware components
 */
function initThemeAwareComponents() {
    // Update components when theme changes
    window.addEventListener('themechange', function(e) {
        console.log('Dashboard: Theme changed to', e.detail.theme);
        
        // Update any theme-dependent components
        updateStatCircles(e.detail.theme);
        updateBadges(e.detail.theme);
    });
}

/**
 * Update stat circles
 */
function updateStatCircles(theme) {
    const statCircles = document.querySelectorAll('.stat-circle');
    // Stat circles already have inline gradients, no need to update
}

/**
 * Update badges
 */
function updateBadges(theme) {
    const badges = document.querySelectorAll('.badge-animated');
    // Badges use CSS variables, automatically updated
}

// Export for use in other scripts
window.DashboardEnhanced = {
    initDashboard,
    animateValue,
    getThemeColors
};
