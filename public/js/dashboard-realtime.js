/**
 * Dashboard Real-Time Updates
 * Автоматическое обновление данных дашборда
 */

class DashboardRealTime {
    constructor() {
        this.updateInterval = 60000; // 1 минута
        this.statsUpdateInterval = 300000; // 5 минут
        this.lastUpdate = Date.now();
        this.isVisible = true;
        this.init();
    }

    init() {
        this.setupVisibilityTracking();
        this.startAutoUpdate();
        this.setupManualRefresh();
        this.loadRecentActivity();
    }

    /**
     * Отслеживание видимости страницы
     */
    setupVisibilityTracking() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            
            if (this.isVisible) {
                // Обновить данные при возврате на страницу
                const timeSinceUpdate = Date.now() - this.lastUpdate;
                if (timeSinceUpdate > this.updateInterval) {
                    this.updateDashboard();
                }
            }
        });
    }

    /**
     * Автоматическое обновление
     */
    startAutoUpdate() {
        // Обновление задач
        setInterval(() => {
            if (this.isVisible) {
                this.updateRecentTasks();
            }
        }, this.updateInterval);

        // Обновление статистики
        setInterval(() => {
            if (this.isVisible) {
                this.updateStatistics();
            }
        }, this.statsUpdateInterval);
    }

    /**
     * Ручное обновление
     */
    setupManualRefresh() {
        const refreshBtn = document.getElementById('dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.updateDashboard();
                this.showRefreshAnimation(refreshBtn);
            });
        }

        // Ctrl+R для обновления
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.updateDashboard();
            }
        });
    }

    /**
     * Обновить весь дашборд
     */
    async updateDashboard() {
        try {
            await Promise.all([
                this.updateRecentTasks(),
                this.updateStatistics(),
                this.updateActivity()
            ]);
            
            this.lastUpdate = Date.now();
            this.showToast('Дашборд обновлен', 'success');
        } catch (error) {
            console.error('Dashboard update error:', error);
            this.showToast('Ошибка обновления дашборда', 'error');
        }
    }

    /**
     * Обновить последние задачи
     */
    async updateRecentTasks() {
        try {
            const response = await fetch('/api/dashboard/recent-tasks');
            if (!response.ok) throw new Error('Failed to fetch tasks');
            
            const tasks = await response.json();
            this.renderRecentTasks(tasks);
        } catch (error) {
            console.error('Error updating recent tasks:', error);
        }
    }

    /**
     * Обновить статистику
     */
    async updateStatistics() {
        try {
            const response = await fetch('/api/dashboard/statistics');
            if (!response.ok) throw new Error('Failed to fetch statistics');
            
            const stats = await response.json();
            this.renderStatistics(stats);
        } catch (error) {
            console.error('Error updating statistics:', error);
        }
    }

    /**
     * Обновить активность
     */
    async updateActivity() {
        try {
            const response = await fetch('/api/dashboard/activity');
            if (!response.ok) throw new Error('Failed to fetch activity');
            
            const activity = await response.json();
            this.renderActivity(activity);
        } catch (error) {
            console.error('Error updating activity:', error);
        }
    }

    /**
     * Загрузить недавнюю активность
     */
    async loadRecentActivity() {
        try {
            const response = await fetch('/api/dashboard/recent-activity');
            if (!response.ok) return;
            
            const activities = await response.json();
            this.renderRecentActivity(activities);
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }

    /**
     * Отрисовать последние задачи
     */
    renderRecentTasks(tasks) {
        const container = document.getElementById('recent-tasks-content');
        if (!container) return;

        if (tasks.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h5>Нет задач</h5>
                    <p class="mb-0">Создайте свою первую задачу</p>
                </div>
            `;
            return;
        }

        const html = tasks.map(task => this.createTaskHTML(task)).join('');
        container.innerHTML = `<div class="list-group list-group-flush">${html}</div>`;
        
        // Добавить анимацию
        this.animateElements(container.querySelectorAll('.list-group-item'));
    }

    /**
     * Создать HTML для задачи
     */
    createTaskHTML(task) {
        const priorityBadge = this.getPriorityBadge(task.priority);
        const statusBadge = this.getStatusBadge(task.status);
        
        return `
            <div class="list-group-item task-item border-0 px-4 py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <h6 class="task-title mb-0 me-2">
                                <a href="/tasks/${task.id}" class="text-decoration-none">
                                    ${this.escapeHtml(task.title)}
                                </a>
                            </h6>
                            ${statusBadge}
                            ${priorityBadge}
                        </div>
                        <div class="task-meta mb-2">
                            <i class="fas fa-folder me-1"></i>
                            ${task.category || 'Без категории'}
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar me-1"></i>
                            ${this.formatDate(task.createdAt)}
                        </div>
                        ${task.description ? `
                            <p class="task-description mb-0 text-muted">
                                ${this.escapeHtml(task.description.substring(0, 100))}${task.description.length > 100 ? '...' : ''}
                            </p>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Отрисовать статистику
     */
    renderStatistics(stats) {
        this.updateStatCard('total', stats.total);
        this.updateStatCard('pending', stats.pending);
        this.updateStatCard('in_progress', stats.in_progress);
        this.updateStatCard('completed', stats.completed);
    }

    /**
     * Обновить карточку статистики
     */
    updateStatCard(type, value) {
        const card = document.querySelector(`[data-stat="${type}"]`);
        if (!card) return;

        const valueEl = card.querySelector('h2');
        if (!valueEl) return;

        const oldValue = parseInt(valueEl.textContent) || 0;
        
        if (oldValue !== value) {
            this.animateValue(valueEl, oldValue, value, 500);
            card.classList.add('stat-updated');
            setTimeout(() => card.classList.remove('stat-updated'), 1000);
        }
    }

    /**
     * Анимация изменения значения
     */
    animateValue(element, start, end, duration) {
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
     * Отрисовать активность
     */
    renderActivity(activity) {
        const container = document.getElementById('activity-feed');
        if (!container) return;

        const html = activity.map(item => `
            <div class="activity-item">
                <div class="activity-icon ${item.type}">
                    <i class="fas fa-${this.getActivityIcon(item.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">${item.text}</div>
                    <div class="activity-time">${this.formatRelativeTime(item.timestamp)}</div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    /**
     * Отрисовать недавнюю активность
     */
    renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity-list');
        if (!container) return;

        const html = activities.map(activity => `
            <div class="activity-item-mini">
                <div class="activity-icon-mini ${activity.type}">
                    <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-text-mini">
                    ${activity.description}
                    <small class="text-muted d-block">${this.formatRelativeTime(activity.createdAt)}</small>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    /**
     * Получить иконку активности
     */
    getActivityIcon(type) {
        const icons = {
            'task_created': 'plus',
            'task_completed': 'check',
            'task_updated': 'edit',
            'comment_added': 'comment',
            'user_assigned': 'user-plus',
            'deadline_changed': 'calendar'
        };
        return icons[type] || 'circle';
    }

    /**
     * Получить бейдж приоритета
     */
    getPriorityBadge(priority) {
        const badges = {
            'high': '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Высокий</span>',
            'medium': '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation me-1"></i>Средний</span>',
            'low': '<span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Низкий</span>'
        };
        return badges[priority] || '';
    }

    /**
     * Получить бейдж статуса
     */
    getStatusBadge(status) {
        const badges = {
            'pending': '<span class="badge bg-secondary">В ожидании</span>',
            'in_progress': '<span class="badge bg-info">В процессе</span>',
            'completed': '<span class="badge bg-success">Завершено</span>',
            'cancelled': '<span class="badge bg-danger">Отменено</span>'
        };
        return badges[status] || '';
    }

    /**
     * Форматировать дату
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Форматировать относительное время
     */
    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'только что';
        if (minutes < 60) return `${minutes} мин назад`;
        if (hours < 24) return `${hours} ч назад`;
        if (days < 7) return `${days} дн назад`;
        
        return this.formatDate(dateString);
    }

    /**
     * Анимация обновления
     */
    showRefreshAnimation(button) {
        const icon = button.querySelector('i');
        if (!icon) return;

        icon.classList.add('fa-spin');
        setTimeout(() => {
            icon.classList.remove('fa-spin');
        }, 1000);
    }

    /**
     * Анимация элементов
     */
    animateElements(elements) {
        elements.forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                el.style.transition = 'all 0.3s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }

    /**
     * Экранирование HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Добавить стили
if (!document.getElementById('dashboardRealtimeStyles')) {
    const style = document.createElement('style');
    style.id = 'dashboardRealtimeStyles';
    style.textContent = `
        .stat-updated {
            animation: statPulse 0.5s ease;
        }

        @keyframes statPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .activity-item-mini {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .activity-item-mini:hover {
            background: var(--bg-body);
        }

        .activity-item-mini:last-child {
            border-bottom: none;
        }

        .activity-icon-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.875rem;
        }

        .activity-icon-mini.task_created {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .activity-icon-mini.task_completed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .activity-icon-mini.task_updated {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .activity-icon-mini.comment_added {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        .activity-text-mini {
            flex: 1;
            font-size: 0.875rem;
        }

        .task-item {
            transition: all 0.2s ease;
        }

        .task-item:hover {
            background: var(--bg-body);
            transform: translateX(4px);
        }
    `;
    document.head.appendChild(style);
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dashboardRealTime = new DashboardRealTime();
    });
} else {
    window.dashboardRealTime = new DashboardRealTime();
}

// Экспорт
window.DashboardRealTime = DashboardRealTime;
