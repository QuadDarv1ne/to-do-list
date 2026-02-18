/**
 * Advanced Notifications System
 * Расширенная система уведомлений с группировкой и приоритетами
 */

class AdvancedNotifications {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.settings = this.loadSettings();
        this.init();
    }

    init() {
        this.createNotificationCenter();
        this.startPolling();
        this.bindEvents();
        this.requestPermission();
    }

    /**
     * Загрузить настройки
     */
    loadSettings() {
        const defaults = {
            sound: true,
            desktop: true,
            email: false,
            grouping: true,
            pollInterval: 30000 // 30 секунд
        };

        const stored = localStorage.getItem('notificationSettings');
        return stored ? { ...defaults, ...JSON.parse(stored) } : defaults;
    }

    /**
     * Сохранить настройки
     */
    saveSettings() {
        localStorage.setItem('notificationSettings', JSON.stringify(this.settings));
    }

    /**
     * Создать центр уведомлений
     */
    createNotificationCenter() {
        // Проверяем, не создан ли уже центр
        if (document.getElementById('notificationCenter')) return;

        const center = document.createElement('div');
        center.id = 'notificationCenter';
        center.className = 'notification-center';
        center.innerHTML = `
            <div class="notification-center-header">
                <h5 class="mb-0">Уведомления</h5>
                <div class="notification-center-actions">
                    <button class="btn btn-sm btn-link" data-action="settings" title="Настройки">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="btn btn-sm btn-link" data-action="mark-all-read" title="Отметить все как прочитанные">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn btn-sm btn-link" data-action="close" title="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="notification-center-tabs">
                <button class="tab-btn active" data-tab="all">Все <span class="badge" data-count="all">0</span></button>
                <button class="tab-btn" data-tab="unread">Непрочитанные <span class="badge" data-count="unread">0</span></button>
                <button class="tab-btn" data-tab="mentions">Упоминания <span class="badge" data-count="mentions">0</span></button>
            </div>
            <div class="notification-center-content">
                <div class="notification-list" data-list="all"></div>
                <div class="notification-list" data-list="unread" style="display: none;"></div>
                <div class="notification-list" data-list="mentions" style="display: none;"></div>
            </div>
            <div class="notification-center-footer">
                <a href="/notifications" class="btn btn-sm btn-link">Все уведомления</a>
            </div>
        `;

        document.body.appendChild(center);

        // Добавляем стили
        this.addStyles();
    }

    /**
     * Добавить стили
     */
    addStyles() {
        if (document.getElementById('notificationCenterStyles')) return;

        const style = document.createElement('style');
        style.id = 'notificationCenterStyles';
        style.textContent = `
            .notification-center {
                position: fixed;
                top: 60px;
                right: 20px;
                width: 400px;
                max-height: 600px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: var(--radius-lg);
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                display: none;
                flex-direction: column;
                z-index: 1050;
                animation: slideIn 0.3s ease-out;
            }

            .notification-center.show {
                display: flex;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .notification-center-header {
                padding: 1rem;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .notification-center-actions {
                display: flex;
                gap: 0.5rem;
            }

            .notification-center-tabs {
                display: flex;
                border-bottom: 1px solid var(--border);
                padding: 0 1rem;
            }

            .notification-center-tabs .tab-btn {
                flex: 1;
                padding: 0.75rem 1rem;
                border: none;
                background: none;
                color: var(--text-secondary);
                cursor: pointer;
                border-bottom: 2px solid transparent;
                transition: all 0.2s ease;
            }

            .notification-center-tabs .tab-btn:hover {
                color: var(--text-primary);
            }

            .notification-center-tabs .tab-btn.active {
                color: var(--primary);
                border-bottom-color: var(--primary);
            }

            .notification-center-tabs .badge {
                background: var(--primary);
                color: white;
                padding: 0.125rem 0.5rem;
                border-radius: 10px;
                font-size: 0.75rem;
                margin-left: 0.25rem;
            }

            .notification-center-content {
                flex: 1;
                overflow-y: auto;
                padding: 0.5rem;
            }

            .notification-list {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .notification-item {
                padding: 0.75rem;
                border-radius: var(--radius);
                background: var(--bg-body);
                border-left: 3px solid var(--border);
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .notification-item:hover {
                background: var(--bg-card);
                box-shadow: var(--shadow);
            }

            .notification-item.unread {
                background: rgba(102, 126, 234, 0.05);
                border-left-color: var(--primary);
            }

            .notification-item.priority-high {
                border-left-color: var(--danger);
            }

            .notification-item-header {
                display: flex;
                justify-content: space-between;
                align-items: start;
                margin-bottom: 0.5rem;
            }

            .notification-item-title {
                font-weight: 600;
                color: var(--text-primary);
                font-size: 0.875rem;
            }

            .notification-item-time {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .notification-item-content {
                font-size: 0.8125rem;
                color: var(--text-secondary);
                line-height: 1.4;
            }

            .notification-item-actions {
                display: flex;
                gap: 0.5rem;
                margin-top: 0.5rem;
            }

            .notification-item-actions button {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            .notification-center-footer {
                padding: 0.75rem 1rem;
                border-top: 1px solid var(--border);
                text-align: center;
            }

            .notification-empty {
                padding: 2rem;
                text-align: center;
                color: var(--text-muted);
            }

            @media (max-width: 768px) {
                .notification-center {
                    right: 10px;
                    left: 10px;
                    width: auto;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Привязать события
     */
    bindEvents() {
        // Открытие/закрытие центра уведомлений
        document.addEventListener('click', (e) => {
            const bellBtn = e.target.closest('[data-notifications-toggle]');
            if (bellBtn) {
                e.preventDefault();
                this.toggleCenter();
                return;
            }

            const center = document.getElementById('notificationCenter');
            if (!center.contains(e.target) && !bellBtn) {
                this.closeCenter();
            }
        });

        // Действия в центре уведомлений
        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]');
            if (!action) return;

            const actionType = action.dataset.action;
            
            if (actionType === 'close') {
                this.closeCenter();
            } else if (actionType === 'mark-all-read') {
                this.markAllAsRead();
            } else if (actionType === 'settings') {
                this.showSettings();
            }
        });

        // Переключение вкладок
        document.addEventListener('click', (e) => {
            const tab = e.target.closest('.tab-btn');
            if (!tab) return;

            const tabName = tab.dataset.tab;
            this.switchTab(tabName);
        });

        // Клик по уведомлению
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            const notificationId = item.dataset.id;
            this.handleNotificationClick(notificationId);
        });
    }

    /**
     * Переключить центр уведомлений
     */
    toggleCenter() {
        const center = document.getElementById('notificationCenter');
        center.classList.toggle('show');

        if (center.classList.contains('show')) {
            this.loadNotifications();
        }
    }

    /**
     * Закрыть центр уведомлений
     */
    closeCenter() {
        const center = document.getElementById('notificationCenter');
        center.classList.remove('show');
    }

    /**
     * Переключить вкладку
     */
    switchTab(tabName) {
        // Обновляем активную вкладку
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });

        // Показываем соответствующий список
        document.querySelectorAll('.notification-list').forEach(list => {
            list.style.display = list.dataset.list === tabName ? '' : 'none';
        });
    }

    /**
     * Загрузить уведомления
     */
    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications');
            const data = await response.json();

            this.notifications = data.notifications;
            this.unreadCount = data.unreadCount;

            this.renderNotifications();
            this.updateBadges();
        } catch (error) {
            console.error('Load notifications error:', error);
        }
    }

    /**
     * Отрисовать уведомления
     */
    renderNotifications() {
        const allList = document.querySelector('[data-list="all"]');
        const unreadList = document.querySelector('[data-list="unread"]');
        const mentionsList = document.querySelector('[data-list="mentions"]');

        const all = this.notifications;
        const unread = all.filter(n => !n.read);
        const mentions = all.filter(n => n.type === 'mention');

        allList.innerHTML = this.renderNotificationList(all);
        unreadList.innerHTML = this.renderNotificationList(unread);
        mentionsList.innerHTML = this.renderNotificationList(mentions);
    }

    /**
     * Отрисовать список уведомлений
     */
    renderNotificationList(notifications) {
        if (notifications.length === 0) {
            return '<div class="notification-empty">Нет уведомлений</div>';
        }

        return notifications.map(n => this.renderNotificationItem(n)).join('');
    }

    /**
     * Отрисовать элемент уведомления
     */
    renderNotificationItem(notification) {
        const unreadClass = notification.read ? '' : 'unread';
        const priorityClass = notification.priority === 'high' ? 'priority-high' : '';

        return `
            <div class="notification-item ${unreadClass} ${priorityClass}" data-id="${notification.id}">
                <div class="notification-item-header">
                    <div class="notification-item-title">
                        <i class="fas fa-${this.getNotificationIcon(notification.type)} me-2"></i>
                        ${notification.title}
                    </div>
                    <div class="notification-item-time">${this.formatTime(notification.createdAt)}</div>
                </div>
                <div class="notification-item-content">${notification.message}</div>
                ${notification.actions ? this.renderActions(notification.actions) : ''}
            </div>
        `;
    }

    /**
     * Получить иконку уведомления
     */
    getNotificationIcon(type) {
        const icons = {
            'task': 'tasks',
            'comment': 'comment',
            'mention': 'at',
            'assignment': 'user-plus',
            'deadline': 'clock',
            'completion': 'check-circle',
            'system': 'info-circle'
        };
        return icons[type] || 'bell';
    }

    /**
     * Отрисовать действия
     */
    renderActions(actions) {
        return `
            <div class="notification-item-actions">
                ${actions.map(action => `
                    <button class="btn btn-sm btn-outline-primary" data-action-id="${action.id}">
                        ${action.label}
                    </button>
                `).join('')}
            </div>
        `;
    }

    /**
     * Обновить счетчики
     */
    updateBadges() {
        const unread = this.notifications.filter(n => !n.read).length;
        const mentions = this.notifications.filter(n => n.type === 'mention' && !n.read).length;

        document.querySelector('[data-count="all"]').textContent = this.notifications.length;
        document.querySelector('[data-count="unread"]').textContent = unread;
        document.querySelector('[data-count="mentions"]').textContent = mentions;

        // Обновляем значок в navbar
        const navBadge = document.querySelector('.notification-badge-enhanced');
        if (navBadge) {
            navBadge.textContent = unread;
            navBadge.style.display = unread > 0 ? '' : 'none';
        }
    }

    /**
     * Обработать клик по уведомлению
     */
    async handleNotificationClick(notificationId) {
        const notification = this.notifications.find(n => n.id === notificationId);
        if (!notification) return;

        // Отмечаем как прочитанное
        if (!notification.read) {
            await this.markAsRead(notificationId);
        }

        // Переходим по ссылке, если есть
        if (notification.link) {
            window.location.href = notification.link;
        }
    }

    /**
     * Отметить как прочитанное
     */
    async markAsRead(notificationId) {
        try {
            await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const notification = this.notifications.find(n => n.id === notificationId);
            if (notification) {
                notification.read = true;
            }

            this.renderNotifications();
            this.updateBadges();
        } catch (error) {
            console.error('Mark as read error:', error);
        }
    }

    /**
     * Отметить все как прочитанные
     */
    async markAllAsRead() {
        try {
            await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            this.notifications.forEach(n => n.read = true);
            this.renderNotifications();
            this.updateBadges();

            this.showToast('Все уведомления отмечены как прочитанные', 'success');
        } catch (error) {
            console.error('Mark all as read error:', error);
        }
    }

    /**
     * Начать опрос сервера
     */
    startPolling() {
        this.loadNotifications();
        
        setInterval(() => {
            this.loadNotifications();
        }, this.settings.pollInterval);
    }

    /**
     * Запросить разрешение на desktop уведомления
     */
    async requestPermission() {
        if (!('Notification' in window)) return;

        if (Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    }

    /**
     * Показать desktop уведомление
     */
    showDesktopNotification(notification) {
        if (!this.settings.desktop) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;

        const n = new Notification(notification.title, {
            body: notification.message,
            icon: '/icon.png',
            badge: '/badge.png',
            tag: notification.id
        });

        n.onclick = () => {
            window.focus();
            this.handleNotificationClick(notification.id);
            n.close();
        };
    }

    /**
     * Воспроизвести звук
     */
    playSound() {
        if (!this.settings.sound) return;

        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0.5;
        audio.play().catch(e => console.log('Audio play failed:', e));
    }

    /**
     * Показать настройки
     */
    showSettings() {
        // Создаем модальное окно настроек
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Настройки уведомлений</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="soundSetting" ${this.settings.sound ? 'checked' : ''}>
                            <label class="form-check-label" for="soundSetting">Звуковые уведомления</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="desktopSetting" ${this.settings.desktop ? 'checked' : ''}>
                            <label class="form-check-label" for="desktopSetting">Desktop уведомления</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="groupingSetting" ${this.settings.grouping ? 'checked' : ''}>
                            <label class="form-check-label" for="groupingSetting">Группировка уведомлений</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Интервал обновления (секунды)</label>
                            <input type="number" class="form-control" id="pollIntervalSetting" value="${this.settings.pollInterval / 1000}" min="10" max="300">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="saveSettingsBtn">Сохранить</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Сохранение настроек
        modal.querySelector('#saveSettingsBtn').addEventListener('click', () => {
            this.settings.sound = modal.querySelector('#soundSetting').checked;
            this.settings.desktop = modal.querySelector('#desktopSetting').checked;
            this.settings.grouping = modal.querySelector('#groupingSetting').checked;
            this.settings.pollInterval = parseInt(modal.querySelector('#pollIntervalSetting').value) * 1000;

            this.saveSettings();
            bsModal.hide();
            this.showToast('Настройки сохранены', 'success');
        });

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    /**
     * Форматировать время
     */
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'только что';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} мин назад`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч назад`;
        if (diff < 604800000) return `${Math.floor(diff / 86400000)} дн назад`;
        return date.toLocaleDateString();
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
        window.advancedNotifications = new AdvancedNotifications();
    });
} else {
    window.advancedNotifications = new AdvancedNotifications();
}

// Экспорт
window.AdvancedNotifications = AdvancedNotifications;
