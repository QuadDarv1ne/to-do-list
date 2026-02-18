/**
 * Enhanced Notifications System
 * Real-time notifications with beautiful UI
 */

class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.currentTab = 'all';
        this.pollingInterval = null;
        this.init();
    }

    init() {
        this.setupDropdown();
        this.setupTabs();
        this.loadNotifications();
        this.startPolling();
        this.setupMarkAsRead();
        this.setupActions();
    }

    /**
     * Setup notification dropdown
     */
    setupDropdown() {
        const bell = document.querySelector('.notification-bell');
        const dropdown = document.querySelector('.notification-dropdown');

        if (bell && dropdown) {
            bell.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('show');
                
                if (dropdown.classList.contains('show')) {
                    this.loadNotifications();
                }
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }
    }

    /**
     * Setup tabs
     */
    setupTabs() {
        const tabs = document.querySelectorAll('.notification-tab');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active state
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Update current tab
                this.currentTab = tab.dataset.tab;
                
                // Filter notifications
                this.filterNotifications();
            });
        });
    }

    /**
     * Load notifications from server
     */
    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications');
            const data = await response.json();
            
            this.notifications = data.notifications || [];
            this.unreadCount = data.unread || 0;
            
            this.updateBadge();
            this.renderNotifications();
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    /**
     * Start polling for new notifications
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
        }
    }

    /**
     * Update notification badge
     */
    updateBadge() {
        const badge = document.querySelector('.notification-badge');
        const bell = document.querySelector('.notification-bell');
        
        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
        }
        
        if (bell) {
            if (this.unreadCount > 0) {
                bell.classList.add('has-notifications');
            } else {
                bell.classList.remove('has-notifications');
            }
        }
        
        // Update tab badges
        this.updateTabBadges();
    }

    /**
     * Update tab badges
     */
    updateTabBadges() {
        const unreadTab = document.querySelector('[data-tab="unread"] .notification-tab-badge');
        if (unreadTab) {
            unreadTab.textContent = this.unreadCount;
            unreadTab.style.display = this.unreadCount > 0 ? 'inline-flex' : 'none';
        }
    }

    /**
     * Filter notifications by tab
     */
    filterNotifications() {
        let filtered = this.notifications;
        
        switch (this.currentTab) {
            case 'unread':
                filtered = this.notifications.filter(n => !n.read);
                break;
            case 'important':
                filtered = this.notifications.filter(n => n.important);
                break;
            case 'all':
            default:
                filtered = this.notifications;
                break;
        }
        
        this.renderNotifications(filtered);
    }

    /**
     * Render notifications
     */
    renderNotifications(notifications = null) {
        const list = document.querySelector('.notification-list');
        if (!list) return;
        
        const items = notifications || this.notifications;
        
        if (items.length === 0) {
            list.innerHTML = this.renderEmptyState();
            return;
        }
        
        list.innerHTML = items.map(notification => this.renderNotificationItem(notification)).join('');
    }

    /**
     * Render single notification item
     */
    renderNotificationItem(notification) {
        const unreadClass = notification.read ? '' : 'unread';
        const typeClass = `type-${notification.type || 'info'}`;
        const timeAgo = this.getTimeAgo(notification.created_at);
        
        return `
            <div class="notification-item ${unreadClass}" data-id="${notification.id}">
                <div class="notification-icon ${typeClass}">
                    <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">
                        ${notification.title}
                        ${notification.important ? '<i class="fas fa-star text-warning"></i>' : ''}
                    </div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-meta">
                        <span class="notification-time">
                            <i class="far fa-clock"></i>
                            ${timeAgo}
                        </span>
                        ${notification.category ? `<span class="notification-category">${notification.category}</span>` : ''}
                    </div>
                    ${notification.actions ? this.renderActions(notification.actions) : ''}
                </div>
            </div>
        `;
    }

    /**
     * Render notification actions
     */
    renderActions(actions) {
        return `
            <div class="notification-actions">
                ${actions.map(action => `
                    <button class="notification-action-btn notification-action-btn-${action.type || 'secondary'}"
                            data-action="${action.action}"
                            data-url="${action.url || ''}">
                        ${action.label}
                    </button>
                `).join('')}
            </div>
        `;
    }

    /**
     * Render empty state
     */
    renderEmptyState() {
        return `
            <div class="notification-empty">
                <div class="notification-empty-icon">
                    <i class="far fa-bell"></i>
                </div>
                <div class="notification-empty-title">Нет уведомлений</div>
                <div class="notification-empty-message">
                    Здесь будут отображаться ваши уведомления
                </div>
            </div>
        `;
    }

    /**
     * Setup mark as read
     */
    setupMarkAsRead() {
        document.addEventListener('click', async (e) => {
            const item = e.target.closest('.notification-item');
            if (!item) return;
            
            const id = item.dataset.id;
            
            if (item.classList.contains('unread')) {
                await this.markAsRead(id);
                item.classList.remove('unread');
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
            }
            
            // Navigate if has URL
            const notification = this.notifications.find(n => n.id == id);
            if (notification && notification.url) {
                window.location.href = notification.url;
            }
        });
    }

    /**
     * Mark notification as read
     */
    async markAsRead(id) {
        try {
            await fetch(`/api/notifications/${id}/read`, {
                method: 'POST'
            });
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    /**
     * Mark all as read
     */
    async markAllAsRead() {
        try {
            await fetch('/api/notifications/mark-all-read', {
                method: 'POST'
            });
            
            this.notifications.forEach(n => n.read = true);
            this.unreadCount = 0;
            this.updateBadge();
            this.renderNotifications();
            
            this.showToast('Все уведомления отмечены как прочитанные', 'success');
        } catch (error) {
            console.error('Error marking all as read:', error);
            this.showToast('Ошибка при обновлении уведомлений', 'error');
        }
    }

    /**
     * Setup action buttons
     */
    setupActions() {
        // Mark all as read
        const markAllBtn = document.getElementById('markAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => {
                this.markAllAsRead();
            });
        }
        
        // Clear all
        const clearAllBtn = document.getElementById('clearAll');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', () => {
                if (confirm('Удалить все уведомления?')) {
                    this.clearAll();
                }
            });
        }
        
        // Settings
        const settingsBtn = document.getElementById('notificationSettings');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', () => {
                window.location.href = '/settings/notifications';
            });
        }
        
        // Action buttons in notifications
        document.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('.notification-action-btn');
            if (!actionBtn) return;
            
            e.stopPropagation();
            
            const action = actionBtn.dataset.action;
            const url = actionBtn.dataset.url;
            
            if (url) {
                window.location.href = url;
            } else if (action) {
                this.handleAction(action);
            }
        });
    }

    /**
     * Handle notification action
     */
    handleAction(action) {
        console.log('Handling action:', action);
        // Implement custom action handlers here
    }

    /**
     * Clear all notifications
     */
    async clearAll() {
        try {
            await fetch('/api/notifications/clear-all', {
                method: 'POST'
            });
            
            this.notifications = [];
            this.unreadCount = 0;
            this.updateBadge();
            this.renderNotifications();
            
            this.showToast('Все уведомления удалены', 'success');
        } catch (error) {
            console.error('Error clearing notifications:', error);
            this.showToast('Ошибка при удалении уведомлений', 'error');
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', title = null) {
        const container = this.getToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `notification-toast type-${type}`;
        toast.innerHTML = `
            <div class="notification-toast-icon">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            </div>
            <div class="notification-toast-content">
                ${title ? `<div class="notification-toast-title">${title}</div>` : ''}
                <div class="notification-toast-message">${message}</div>
            </div>
            <button class="notification-toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Close button
        toast.querySelector('.notification-toast-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });
        
        // Animate in
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    /**
     * Get toast container
     */
    getToastContainer() {
        let container = document.querySelector('.notification-toast-container');
        
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-toast-container';
            document.body.appendChild(container);
        }
        
        return container;
    }

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle',
            task: 'tasks',
            comment: 'comment',
            mention: 'at',
            deadline: 'clock'
        };
        return icons[type] || 'bell';
    }

    /**
     * Get time ago string
     */
    getTimeAgo(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'только что';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} мин назад`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} ч назад`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)} дн назад`;
        
        return date.toLocaleDateString('ru-RU');
    }

    /**
     * Add new notification (for real-time updates)
     */
    addNotification(notification) {
        this.notifications.unshift(notification);
        this.unreadCount++;
        this.updateBadge();
        this.renderNotifications();
        
        // Show toast
        this.showToast(notification.message, notification.type, notification.title);
        
        // Play sound if enabled
        this.playNotificationSound();
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        const soundEnabled = localStorage.getItem('notificationSound') !== 'false';
        
        if (soundEnabled) {
            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}
