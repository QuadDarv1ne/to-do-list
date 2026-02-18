/**
 * Notifications Enhanced
 * Real-time notifications with theme support
 */

class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.pollingInterval = null;
        this.init();
    }

    init() {
        this.createNotificationCenter();
        this.setupEventListeners();
        this.startPolling();
        this.loadNotifications();
        this.initThemeSupport();
    }

    /**
     * Create notification center UI
     */
    createNotificationCenter() {
        // Check if already exists
        if (document.getElementById('notification-center')) return;

        const center = document.createElement('div');
        center.id = 'notification-center';
        center.className = 'notification-center';
        center.innerHTML = `
            <div class="notification-center-header">
                <h6 class="mb-0">Уведомления</h6>
                <div class="notification-actions">
                    <button class="btn btn-sm btn-link" id="mark-all-read" title="Отметить все как прочитанные">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn btn-sm btn-link" id="notification-settings" title="Настройки">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="btn btn-sm btn-link" id="close-notification-center" title="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="notification-tabs">
                <button class="notification-tab active" data-tab="all">Все</button>
                <button class="notification-tab" data-tab="unread">Непрочитанные</button>
                <button class="notification-tab" data-tab="mentions">Упоминания</button>
            </div>
            <div class="notification-list" id="notification-list">
                <div class="notification-loading">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span class="ms-2">Загрузка...</span>
                </div>
            </div>
            <div class="notification-footer">
                <a href="/notifications" class="btn btn-sm btn-link">Все уведомления</a>
            </div>
        `;

        document.body.appendChild(center);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Toggle notification center
        const notificationBtn = document.querySelector('.notification-badge-enhanced');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleNotificationCenter();
            });
        }

        // Close button
        document.addEventListener('click', (e) => {
            if (e.target.closest('#close-notification-center')) {
                this.closeNotificationCenter();
            }
        });

        // Mark all as read
        document.addEventListener('click', (e) => {
            if (e.target.closest('#mark-all-read')) {
                this.markAllAsRead();
            }
        });

        // Notification tabs
        document.addEventListener('click', (e) => {
            const tab = e.target.closest('.notification-tab');
            if (tab) {
                this.switchTab(tab.dataset.tab);
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            const center = document.getElementById('notification-center');
            const btn = e.target.closest('.notification-badge-enhanced');
            
            if (center && !center.contains(e.target) && !btn) {
                this.closeNotificationCenter();
            }
        });

        // Notification item click
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.notification-item');
            if (item && !e.target.closest('.notification-item-actions')) {
                this.handleNotificationClick(item);
            }
        });
    }

    /**
     * Toggle notification center
     */
    toggleNotificationCenter() {
        const center = document.getElementById('notification-center');
        if (center) {
            center.classList.toggle('show');
            
            if (center.classList.contains('show')) {
                this.loadNotifications();
            }
        }
    }

    /**
     * Close notification center
     */
    closeNotificationCenter() {
        const center = document.getElementById('notification-center');
        if (center) {
            center.classList.remove('show');
        }
    }

    /**
     * Load notifications
     */
    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.notifications = data.notifications || [];
                this.unreadCount = data.unreadCount || 0;
                this.updateUI();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    /**
     * Update UI
     */
    updateUI() {
        this.updateBadge();
        this.renderNotifications();
    }

    /**
     * Update badge
     */
    updateBadge() {
        const badge = document.querySelector('.notification-badge-enhanced');
        if (badge) {
            const countSpan = badge.querySelector('.notification-badge-count');
            if (countSpan) {
                countSpan.textContent = this.unreadCount;
                countSpan.style.display = this.unreadCount > 0 ? 'flex' : 'none';
            }
        }
    }

    /**
     * Render notifications
     */
    renderNotifications() {
        const list = document.getElementById('notification-list');
        if (!list) return;

        const activeTab = document.querySelector('.notification-tab.active')?.dataset.tab || 'all';
        let filteredNotifications = this.notifications;

        if (activeTab === 'unread') {
            filteredNotifications = this.notifications.filter(n => !n.read);
        } else if (activeTab === 'mentions') {
            filteredNotifications = this.notifications.filter(n => n.type === 'mention');
        }

        if (filteredNotifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash fa-3x mb-3"></i>
                    <p class="mb-0">Нет уведомлений</p>
                </div>
            `;
            return;
        }

        list.innerHTML = filteredNotifications.map(notification => this.renderNotificationItem(notification)).join('');
    }

    /**
     * Render notification item
     */
    renderNotificationItem(notification) {
        const timeAgo = this.getTimeAgo(notification.createdAt);
        const iconClass = this.getNotificationIcon(notification.type);
        const iconColor = this.getNotificationColor(notification.type);

        return `
            <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon" style="background: ${iconColor};">
                    <i class="${iconClass}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
                <div class="notification-item-actions">
                    <button class="btn btn-sm btn-link" onclick="notificationSystem.markAsRead(${notification.id})" title="Отметить как прочитанное">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-danger" onclick="notificationSystem.deleteNotification(${notification.id})" title="Удалить">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        const icons = {
            task: 'fas fa-tasks',
            comment: 'fas fa-comment',
            mention: 'fas fa-at',
            deadline: 'fas fa-clock',
            assignment: 'fas fa-user-plus',
            completion: 'fas fa-check-circle',
            default: 'fas fa-bell'
        };
        return icons[type] || icons.default;
    }

    /**
     * Get notification color
     */
    getNotificationColor(type) {
        const colors = {
            task: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            comment: 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
            mention: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            deadline: 'linear-gradient(135deg, #ffa726 0%, #fb8c00 100%)',
            assignment: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            completion: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            default: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
        };
        return colors[type] || colors.default;
    }

    /**
     * Get time ago
     */
    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'только что';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} мин назад`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} ч назад`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)} дн назад`;
        
        return date.toLocaleDateString('ru-RU');
    }

    /**
     * Switch tab
     */
    switchTab(tab) {
        document.querySelectorAll('.notification-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
        this.renderNotifications();
    }

    /**
     * Handle notification click
     */
    handleNotificationClick(item) {
        const id = item.dataset.id;
        const notification = this.notifications.find(n => n.id == id);
        
        if (notification && notification.url) {
            this.markAsRead(id);
            window.location.href = notification.url;
        }
    }

    /**
     * Mark as read
     */
    async markAsRead(id) {
        try {
            const response = await fetch(`/api/notifications/${id}/read`, {
                method: 'PATCH',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                const notification = this.notifications.find(n => n.id == id);
                if (notification) {
                    notification.read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateUI();
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    /**
     * Mark all as read
     */
    async markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.notifications.forEach(n => n.read = true);
                this.unreadCount = 0;
                this.updateUI();
                this.showToast('Все уведомления отмечены как прочитанные', 'success');
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    /**
     * Delete notification
     */
    async deleteNotification(id) {
        if (!confirm('Удалить уведомление?')) return;

        try {
            const response = await fetch(`/api/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                const index = this.notifications.findIndex(n => n.id == id);
                if (index > -1) {
                    const notification = this.notifications[index];
                    if (!notification.read) {
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                    }
                    this.notifications.splice(index, 1);
                    this.updateUI();
                }
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }

    /**
     * Start polling
     */
    startPolling() {
        // Poll every 30 seconds
        this.pollingInterval = setInterval(() => {
            this.loadNotifications();
        }, 30000);
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    /**
     * Show new notification toast
     */
    showNewNotification(notification) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="notification-toast-icon" style="background: ${this.getNotificationColor(notification.type)};">
                <i class="${this.getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-toast-content">
                <div class="notification-toast-title">${notification.title}</div>
                <div class="notification-toast-message">${notification.message}</div>
            </div>
            <button class="notification-toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(toast);

        // Show animation
        setTimeout(() => toast.classList.add('show'), 100);

        // Close button
        toast.querySelector('.notification-toast-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });

        // Auto close after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);

        // Click to open
        toast.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-toast-close')) {
                if (notification.url) {
                    window.location.href = notification.url;
                }
            }
        });
    }

    /**
     * Theme support
     */
    initThemeSupport() {
        window.addEventListener('themechange', (e) => {
            console.log('Notifications: Theme changed to', e.detail.theme);
        });
    }

    /**
     * Show toast
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Initialize notification system
let notificationSystem;

document.addEventListener('DOMContentLoaded', function() {
    notificationSystem = new NotificationSystem();
    window.notificationSystem = notificationSystem;
});

// Export
window.NotificationSystem = NotificationSystem;
