/**
 * Smart Notifications System
 * Умная система уведомлений с группировкой и приоритетами
 */

class SmartNotifications {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.maxVisible = 5;
        this.groupingEnabled = true;
        this.soundEnabled = true;
        this.init();
    }

    init() {
        this.createNotificationCenter();
        this.loadNotifications();
        this.setupEventListeners();
        this.startPolling();
        this.requestPermission();
    }

    /**
     * Создать центр уведомлений
     */
    createNotificationCenter() {
        if (document.getElementById('notification-center')) return;

        const center = document.createElement('div');
        center.id = 'notification-center';
        center.className = 'notification-center';
        center.innerHTML = `
            <div class="notification-center-header">
                <h6 class="mb-0">
                    <i class="fas fa-bell me-2"></i>
                    Уведомления
                    <span class="badge bg-primary ms-2" id="notification-count">0</span>
                </h6>
                <div class="notification-actions">
                    <button class="btn btn-sm btn-link" id="mark-all-read" title="Отметить все как прочитанные">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn btn-sm btn-link" id="notification-settings" title="Настройки">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="btn btn-sm btn-link" id="close-notification-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="notification-filters">
                <button class="filter-btn active" data-filter="all">Все</button>
                <button class="filter-btn" data-filter="unread">Непрочитанные</button>
                <button class="filter-btn" data-filter="important">Важные</button>
            </div>
            <div class="notification-list" id="notification-list">
                <div class="notification-loading">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(center);
        this.addStyles();
    }

    /**
     * Добавить стили
     */
    addStyles() {
        if (document.getElementById('smartNotificationStyles')) return;

        const style = document.createElement('style');
        style.id = 'smartNotificationStyles';
        style.textContent = `
            .notification-center {
                position: fixed;
                top: 60px;
                right: -400px;
                width: 400px;
                max-width: 90vw;
                height: calc(100vh - 80px);
                background: var(--bg-card);
                border-left: 1px solid var(--border);
                box-shadow: -2px 0 10px rgba(0,0,0,0.1);
                z-index: 1040;
                display: flex;
                flex-direction: column;
                transition: right 0.3s ease;
            }

            .notification-center.show {
                right: 0;
            }

            .notification-center-header {
                padding: 1rem;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: var(--bg-card);
            }

            .notification-actions {
                display: flex;
                gap: 0.25rem;
            }

            .notification-filters {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--border);
                display: flex;
                gap: 0.5rem;
            }

            .filter-btn {
                padding: 0.375rem 0.75rem;
                border: 1px solid var(--border);
                border-radius: var(--radius);
                background: transparent;
                color: var(--text-primary);
                font-size: 0.875rem;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .filter-btn:hover {
                background: var(--bg-body);
            }

            .filter-btn.active {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }

            .notification-list {
                flex: 1;
                overflow-y: auto;
                padding: 0.5rem;
            }

            .notification-item {
                padding: 1rem;
                border-radius: var(--radius);
                margin-bottom: 0.5rem;
                background: var(--bg-card);
                border: 1px solid var(--border);
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
            }

            .notification-item:hover {
                background: var(--bg-body);
                transform: translateX(-4px);
            }

            .notification-item.unread {
                border-left: 3px solid var(--primary);
                background: rgba(102, 126, 234, 0.05);
            }

            .notification-item.important {
                border-left: 3px solid var(--danger);
            }

            .notification-header {
                display: flex;
                justify-content: space-between;
                align-items: start;
                margin-bottom: 0.5rem;
            }

            .notification-icon {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 0.75rem;
                flex-shrink: 0;
            }

            .notification-icon.task {
                background: rgba(102, 126, 234, 0.1);
                color: #667eea;
            }

            .notification-icon.comment {
                background: rgba(23, 162, 184, 0.1);
                color: #17a2b8;
            }

            .notification-icon.deadline {
                background: rgba(255, 193, 7, 0.1);
                color: #ffc107;
            }

            .notification-icon.system {
                background: rgba(108, 117, 125, 0.1);
                color: #6c757d;
            }

            .notification-content {
                flex: 1;
            }

            .notification-title {
                font-weight: 600;
                margin-bottom: 0.25rem;
                color: var(--text-primary);
            }

            .notification-text {
                font-size: 0.875rem;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
            }

            .notification-time {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .notification-actions-item {
                display: flex;
                gap: 0.5rem;
                margin-top: 0.5rem;
            }

            .notification-actions-item .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }

            .notification-loading {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
            }

            .notification-empty {
                text-align: center;
                padding: 3rem 1rem;
                color: var(--text-muted);
            }

            .notification-empty i {
                font-size: 3rem;
                margin-bottom: 1rem;
                opacity: 0.3;
            }

            .notification-group {
                margin-bottom: 1rem;
            }

            .notification-group-header {
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--text-muted);
                text-transform: uppercase;
                padding: 0.5rem 1rem;
                background: var(--bg-body);
                border-radius: var(--radius);
                margin-bottom: 0.5rem;
            }

            @media (max-width: 768px) {
                .notification-center {
                    width: 100%;
                    right: -100%;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Открытие/закрытие центра уведомлений
        const bellIcon = document.querySelector('.quick-action-btn-enhanced[aria-label="Уведомления"]');
        if (bellIcon) {
            bellIcon.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleCenter();
            });
        }

        // Закрытие центра
        document.getElementById('close-notification-center')?.addEventListener('click', () => {
            this.closeCenter();
        });

        // Отметить все как прочитанные
        document.getElementById('mark-all-read')?.addEventListener('click', () => {
            this.markAllAsRead();
        });

        // Фильтры
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.filterNotifications(e.target.dataset.filter);
            });
        });

        // Закрытие при клике вне центра
        document.addEventListener('click', (e) => {
            const center = document.getElementById('notification-center');
            if (center && center.classList.contains('show') && 
                !center.contains(e.target) && 
                !e.target.closest('.quick-action-btn-enhanced[aria-label="Уведомления"]')) {
                this.closeCenter();
            }
        });
    }

    /**
     * Загрузить уведомления
     */
    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications');
            if (!response.ok) throw new Error('Failed to load notifications');

            const data = await response.json();
            this.notifications = data.notifications || [];
            this.unreadCount = data.unread || 0;

            this.renderNotifications();
            this.updateBadge();
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showError();
        }
    }

    /**
     * Отрисовать уведомления
     */
    renderNotifications() {
        const list = document.getElementById('notification-list');
        if (!list) return;

        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p class="mb-0">Нет уведомлений</p>
                </div>
            `;
            return;
        }

        const grouped = this.groupingEnabled ? this.groupNotifications() : { 'Все': this.notifications };
        
        let html = '';
        for (const [group, items] of Object.entries(grouped)) {
            if (this.groupingEnabled && Object.keys(grouped).length > 1) {
                html += `<div class="notification-group-header">${group}</div>`;
            }
            
            items.forEach(notification => {
                html += this.createNotificationHTML(notification);
            });
        }

        list.innerHTML = html;

        // Добавить обработчики для каждого уведомления
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id);
                this.handleNotificationClick(id);
            });
        });
    }

    /**
     * Создать HTML для уведомления
     */
    createNotificationHTML(notification) {
        const unreadClass = notification.isRead ? '' : 'unread';
        const importantClass = notification.priority === 'high' ? 'important' : '';
        const iconType = this.getIconType(notification.type);

        return `
            <div class="notification-item ${unreadClass} ${importantClass}" data-id="${notification.id}">
                <div class="d-flex">
                    <div class="notification-icon ${iconType}">
                        <i class="fas fa-${this.getIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                        <div class="notification-text">${this.escapeHtml(notification.message)}</div>
                        <div class="notification-time">
                            <i class="fas fa-clock me-1"></i>
                            ${this.formatRelativeTime(notification.createdAt)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Группировать уведомления
     */
    groupNotifications() {
        const groups = {
            'Сегодня': [],
            'Вчера': [],
            'Ранее': []
        };

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        this.notifications.forEach(notification => {
            const date = new Date(notification.createdAt);
            date.setHours(0, 0, 0, 0);

            if (date.getTime() === today.getTime()) {
                groups['Сегодня'].push(notification);
            } else if (date.getTime() === yesterday.getTime()) {
                groups['Вчера'].push(notification);
            } else {
                groups['Ранее'].push(notification);
            }
        });

        // Удалить пустые группы
        Object.keys(groups).forEach(key => {
            if (groups[key].length === 0) {
                delete groups[key];
            }
        });

        return groups;
    }

    /**
     * Фильтровать уведомления
     */
    filterNotifications(filter) {
        const items = document.querySelectorAll('.notification-item');
        
        items.forEach(item => {
            const isUnread = item.classList.contains('unread');
            const isImportant = item.classList.contains('important');

            let show = true;
            if (filter === 'unread') {
                show = isUnread;
            } else if (filter === 'important') {
                show = isImportant;
            }

            item.style.display = show ? 'block' : 'none';
        });
    }

    /**
     * Обработать клик по уведомлению
     */
    async handleNotificationClick(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (!notification) return;

        // Отметить как прочитанное
        if (!notification.isRead) {
            await this.markAsRead(id);
        }

        // Перейти по ссылке если есть
        if (notification.link) {
            window.location.href = notification.link;
        }
    }

    /**
     * Отметить как прочитанное
     */
    async markAsRead(id) {
        try {
            const response = await fetch(`/api/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const notification = this.notifications.find(n => n.id === id);
                if (notification) {
                    notification.isRead = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateBadge();
                    this.renderNotifications();
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    /**
     * Отметить все как прочитанные
     */
    async markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                this.notifications.forEach(n => n.isRead = true);
                this.unreadCount = 0;
                this.updateBadge();
                this.renderNotifications();
                this.showToast('Все уведомления отмечены как прочитанные', 'success');
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            this.showToast('Ошибка при отметке уведомлений', 'error');
        }
    }

    /**
     * Обновить бейдж
     */
    updateBadge() {
        const badge = document.querySelector('.notification-badge-enhanced');
        const countEl = document.getElementById('notification-count');

        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
        }

        if (countEl) {
            countEl.textContent = this.unreadCount;
        }
    }

    /**
     * Переключить центр уведомлений
     */
    toggleCenter() {
        const center = document.getElementById('notification-center');
        if (center) {
            center.classList.toggle('show');
        }
    }

    /**
     * Закрыть центр уведомлений
     */
    closeCenter() {
        const center = document.getElementById('notification-center');
        if (center) {
            center.classList.remove('show');
        }
    }

    /**
     * Начать опрос сервера
     */
    startPolling() {
        setInterval(() => {
            this.loadNotifications();
        }, 60000); // Каждую минуту
    }

    /**
     * Запросить разрешение на уведомления
     */
    async requestPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    }

    /**
     * Показать браузерное уведомление
     */
    showBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
            });
        }
    }

    /**
     * Получить иконку по типу
     */
    getIcon(type) {
        const icons = {
            'task_assigned': 'tasks',
            'task_completed': 'check-circle',
            'comment_added': 'comment',
            'deadline_approaching': 'clock',
            'mention': 'at',
            'system': 'info-circle',
        };
        return icons[type] || 'bell';
    }

    /**
     * Получить тип иконки
     */
    getIconType(type) {
        if (type.includes('task')) return 'task';
        if (type.includes('comment')) return 'comment';
        if (type.includes('deadline')) return 'deadline';
        return 'system';
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
        
        return date.toLocaleDateString('ru-RU');
    }

    /**
     * Экранировать HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Показать ошибку
     */
    showError() {
        const list = document.getElementById('notification-list');
        if (list) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p class="mb-0">Ошибка загрузки уведомлений</p>
                </div>
            `;
        }
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

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.smartNotifications = new SmartNotifications();
    });
} else {
    window.smartNotifications = new SmartNotifications();
}

// Экспорт
window.SmartNotifications = SmartNotifications;
