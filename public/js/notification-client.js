/**
 * Enhanced Notification Client
 * Handles real-time notifications via Server-Sent Events and WebSocket fallback
 */

class NotificationClient {
    constructor(options = {}) {
        this.options = {
            apiUrl: '/api/notifications',
            streamUrl: '/api/notifications/stream',
            reconnectInterval: 5000,
            maxReconnectAttempts: 10,
            ...options
        };
        
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.isConnected = false;
        this.listeners = {
            'notification': [],
            'connected': [],
            'disconnected': [],
            'error': [],
            'heartbeat': []
        };
        
        this.init();
    }
    
    init() {
        this.connect();
        this.setupVisibilityHandler();
    }
    
    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        try {
            this.eventSource = new EventSource(this.options.streamUrl);
            
            this.eventSource.addEventListener('connected', (event) => {
                this.handleConnected(event);
            });
            
            this.eventSource.addEventListener('notification', (event) => {
                this.handleNotification(event);
            });
            
            this.eventSource.addEventListener('heartbeat', (event) => {
                this.handleHeartbeat(event);
            });
            
            this.eventSource.addEventListener('disconnected', (event) => {
                this.handleDisconnected(event);
            });
            
            this.eventSource.onerror = (error) => {
                this.handleError(error);
            };
            
        } catch (error) {
            console.error('Failed to create EventSource:', error);
            this.handleError(error);
        }
    }
    
    handleConnected(event) {
        this.isConnected = true;
        this.reconnectAttempts = 0;
        console.log('Connected to notification stream');
        this.emit('connected', JSON.parse(event.data));
    }
    
    handleNotification(event) {
        const notification = JSON.parse(event.data);
        console.log('New notification received:', notification);
        this.emit('notification', notification);
        
        // Trigger browser notification if permission granted
        this.showBrowserNotification(notification);
    }
    
    handleHeartbeat(event) {
        console.debug('Heartbeat received');
        this.emit('heartbeat', JSON.parse(event.data));
    }
    
    handleDisconnected(event) {
        this.isConnected = false;
        console.log('Disconnected from notification stream');
        this.emit('disconnected', JSON.parse(event.data));
        this.attemptReconnect();
    }
    
    handleError(error) {
        this.isConnected = false;
        console.error('Notification stream error:', error);
        this.emit('error', error);
        this.attemptReconnect();
    }
    
    attemptReconnect() {
        if (this.reconnectAttempts < this.options.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.options.maxReconnectAttempts})`);
            
            setTimeout(() => {
                this.connect();
            }, this.options.reconnectInterval);
        } else {
            console.error('Max reconnection attempts reached');
        }
    }
    
    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is hidden, close connection to save resources
                if (this.eventSource) {
                    this.eventSource.close();
                    this.isConnected = false;
                }
            } else {
                // Page is visible, reconnect if not connected
                if (!this.isConnected) {
                    this.connect();
                }
            }
        });
    }
    
    showBrowserNotification(notification) {
        // Check if browser notifications are supported and permitted
        if (!('Notification' in window)) {
            return;
        }
        
        if (Notification.permission === 'granted') {
            const browserNotification = new Notification(notification.title, {
                body: notification.message,
                icon: '/icons/notification-icon.png',
                tag: `notification-${notification.id}`,
                requireInteraction: notification.type === 'warning' || notification.type === 'error'
            });
            
            browserNotification.onclick = () => {
                // Focus window and navigate to relevant page
                window.focus();
                if (notification.task_id) {
                    window.location.href = `/tasks/${notification.task_id}`;
                }
                browserNotification.close();
            };
            
            // Auto-close after 5 seconds for non-critical notifications
            if (notification.type !== 'warning' && notification.type !== 'error') {
                setTimeout(() => {
                    browserNotification.close();
                }, 5000);
            }
        }
    }
    
    // Event listener methods
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    off(event, callback) {
        if (this.listeners[event]) {
            const index = this.listeners[event].indexOf(callback);
            if (index > -1) {
                this.listeners[event].splice(index, 1);
            }
        }
    }
    
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Error in notification listener:', error);
                }
            });
        }
    }
    
    // API methods
    async getUnreadNotifications() {
        try {
            const response = await fetch(`${this.options.apiUrl}/unread`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch unread notifications:', error);
            throw error;
        }
    }
    
    async getNotificationStats() {
        try {
            const response = await fetch(`${this.options.apiUrl}/stats`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch notification stats:', error);
            throw error;
        }
    }
    
    async sendTestNotification(data) {
        try {
            const response = await fetch(`${this.options.apiUrl}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Failed to send test notification:', error);
            throw error;
        }
    }
    
    async getTemplates() {
        try {
            const response = await fetch(`${this.options.apiUrl}/templates`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch templates:', error);
            throw error;
        }
    }
    
    // Utility methods
    isConnected() {
        return this.isConnected;
    }
    
    close() {
        if (this.eventSource) {
            this.eventSource.close();
            this.isConnected = false;
        }
    }
    
    // Request notification permission
    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            return false;
        }
        
        if (Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }
        
        return Notification.permission === 'granted';
    }
}

// Initialize notification client when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on a page that needs notifications
    if (document.querySelector('[data-notifications]')) {
        window.notificationClient = new NotificationClient();
        
        // Request notification permission
        window.notificationClient.requestNotificationPermission().then(granted => {
            if (granted) {
                console.log('Notification permission granted');
            }
        });
        
        // Example: Update notification badge
        window.notificationClient.on('notification', (notification) => {
            const badge = document.querySelector('[data-notification-badge]');
            if (badge) {
                // Update badge count
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = currentCount + 1;
                badge.style.display = 'block';
            }
        });
        
        // Example: Show notification in UI
        window.notificationClient.on('notification', (notification) => {
            // Add to notification dropdown or show toast
            showNotificationToast(notification);
        });
    }
});

// Helper function to show toast notifications
function showNotificationToast(notification) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${notification.type}`;
    toast.innerHTML = `
        <div class="toast-header">
            <strong class="me-auto">${notification.title}</strong>
            <small>${new Date().toLocaleTimeString()}</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${notification.message}
        </div>
    `;
    
    // Add to toast container
    const container = document.querySelector('.toast-container') || createToastContainer();
    container.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationClient;
}