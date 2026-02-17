// Notification System for Modern Theme

class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.init();
    }
    
    init() {
        this.loadNotifications();
        this.setupEventListeners();
        this.startPolling();
    }
    
    loadNotifications() {
        // Load from localStorage or API
        const saved = localStorage.getItem('notifications');
        if (saved) {
            this.notifications = JSON.parse(saved);
            this.updateBadge();
        }
    }
    
    addNotification(notification) {
        this.notifications.unshift({
            id: Date.now(),
            title: notification.title,
            message: notification.message,
            type: notification.type || 'info',
            read: false,
            timestamp: new Date().toISOString()
        });
        
        this.unreadCount++;
        this.updateBadge();
        this.saveNotifications();
        this.showToast(notification);
    }
    
    markAsRead(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification && !notification.read) {
            notification.read = true;
            this.unreadCount--;
            this.updateBadge();
            this.saveNotifications();
        }
    }
    
    markAllAsRead() {
        this.notifications.forEach(n => n.read = true);
        this.unreadCount = 0;
        this.updateBadge();
        this.saveNotifications();
    }
    
    updateBadge() {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
        }
    }
    
    saveNotifications() {
        localStorage.setItem('notifications', JSON.stringify(this.notifications));
    }
    
    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px 20px;
            min-width: 300px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
        `;
        
        const icon = this.getIcon(notification.type);
        const color = this.getColor(notification.type);
        
        toast.innerHTML = `
            <div style="display: flex; align-items: start; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: ${color}20; display: flex; align-items: center; justify-content: center; color: ${color}; flex-shrink: 0;">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--text-primary);">${notification.title}</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">${notification.message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0; font-size: 18px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'bell';
    }
    
    getColor(type) {
        const colors = {
            success: '#4dd4ac',
            error: '#ff6bcb',
            warning: '#ffd93d',
            info: '#8b7ff4'
        };
        return colors[type] || '#8b7ff4';
    }
    
    setupEventListeners() {
        // Click on notification icon
        const notificationIcon = document.querySelector('.notification-icon');
        if (notificationIcon) {
            notificationIcon.addEventListener('click', () => {
                this.showNotificationPanel();
            });
        }
    }
    
    showNotificationPanel() {
        // Create notification panel
        const panel = document.createElement('div');
        panel.className = 'notification-panel';
        panel.style.cssText = `
            position: fixed;
            top: 70px;
            right: 20px;
            width: 400px;
            max-height: 600px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            overflow: hidden;
            animation: fadeIn 0.2s ease-out;
        `;
        
        const header = `
            <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;">Уведомления</h3>
                <button onclick="notificationManager.markAllAsRead(); this.closest('.notification-panel').remove();" style="background: none; border: none; color: var(--color-sales); cursor: pointer; font-size: 13px; font-weight: 600;">
                    Прочитать все
                </button>
            </div>
        `;
        
        const notifications = this.notifications.slice(0, 10).map(n => `
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); ${n.read ? 'opacity: 0.6;' : ''}" onclick="notificationManager.markAsRead(${n.id})">
                <div style="font-weight: 600; margin-bottom: 4px; color: var(--text-primary);">${n.title}</div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">${n.message}</div>
                <div style="font-size: 11px; color: var(--text-secondary);">${this.formatTime(n.timestamp)}</div>
            </div>
        `).join('');
        
        const empty = `
            <div style="padding: 60px 20px; text-align: center;">
                <i class="fas fa-bell" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 16px;"></i>
                <p style="color: var(--text-secondary); margin: 0;">Нет уведомлений</p>
            </div>
        `;
        
        panel.innerHTML = header + (this.notifications.length > 0 ? `<div style="max-height: 500px; overflow-y: auto;">${notifications}</div>` : empty);
        
        document.body.appendChild(panel);
        
        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', function closePanel(e) {
                if (!panel.contains(e.target) && !e.target.closest('.notification-icon')) {
                    panel.remove();
                    document.removeEventListener('click', closePanel);
                }
            });
        }, 100);
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Только что';
        if (minutes < 60) return `${minutes} мин назад`;
        if (hours < 24) return `${hours} ч назад`;
        if (days < 7) return `${days} дн назад`;
        
        return date.toLocaleDateString('ru-RU');
    }
    
    startPolling() {
        // Poll for new notifications every 30 seconds
        setInterval(() => {
            this.checkForNewNotifications();
        }, 30000);
    }
    
    checkForNewNotifications() {
        // In real app, fetch from API
        // For demo, we'll simulate
        if (Math.random() > 0.9) {
            this.addNotification({
                title: 'Новая задача',
                message: 'Вам назначена новая задача',
                type: 'info'
            });
        }
    }
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;
document.head.appendChild(style);

// Initialize
const notificationManager = new NotificationManager();

// Expose globally
window.notificationManager = notificationManager;
