/**
 * Real-Time Notifications with Server-Sent Events (SSE)
 * Real-time уведомления через Server-Sent Events
 */

(function() {
    'use strict';

    class RealTimeNotifications {
        constructor(options = {}) {
            this.options = {
                endpoint: '/api/notifications/stream',
                reconnectDelay: 3000,
                maxReconnectDelay: 30000,
                heartbeatTimeout: 60000,
                enableBrowserNotifications: true,
                soundEnabled: true,
                ...options
            };

            this.eventSource = null;
            this.reconnectTimer = null;
            this.heartbeatTimer = null;
            this.lastEventId = null;
            this.reconnectDelay = this.options.reconnectDelay;
            this.isConnected = false;
            this.notifications = [];
            this.unreadCount = 0;

            this.init();
        }

        /**
         * Экранирование HTML для предотвращения XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Initialize real-time notifications
         */
        init() {
            // Check if user is logged in
            if (!document.querySelector('meta[name="csrf-token"]')) {
                console.log('[RTN] User not logged in, skipping initialization');
                return;
            }

            // Request browser notification permission
            if (this.options.enableBrowserNotifications) {
                this.requestNotificationPermission();
            }

            // Connect to SSE stream
            this.connect();

            // Setup visibility change handler
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pause();
                } else {
                    this.resume();
                }
            });

            // Setup online/offline handlers
            window.addEventListener('online', () => this.reconnect());
            window.addEventListener('offline', () => this.disconnect());

            // Load initial unread count
            this.loadUnreadCount();
        }

        /**
         * Connect to SSE stream
         */
        connect() {
            if (this.eventSource) {
                this.disconnect();
            }

            console.log('[RTN] Connecting to SSE stream...');

            try {
                const url = new URL(this.options.endpoint, window.location.origin);
                
                // Add last event ID for reconnection
                if (this.lastEventId) {
                    url.searchParams.set('lastEventId', this.lastEventId);
                }

                this.eventSource = new EventSource(url.toString(), {
                    withCredentials: true
                });

                // Connection opened
                this.eventSource.addEventListener('open', () => {
                    console.log('[RTN] Connected to SSE stream');
                    this.isConnected = true;
                    this.reconnectDelay = this.options.reconnectDelay;
                    this.updateConnectionStatus('connected');
                    this.startHeartbeat();
                });

                // Handle notifications
                this.eventSource.addEventListener('notification', (event) => {
                    console.log('[RTN] Notification received', event);
                    this.lastEventId = event.lastEventId;
                    
                    try {
                        const notification = JSON.parse(event.data);
                        this.handleNotification(notification);
                    } catch (e) {
                        console.error('[RTN] Failed to parse notification:', e);
                    }
                });

                // Handle unread count updates
                this.eventSource.addEventListener('unread-count', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.unreadCount = data.count;
                        this.updateUnreadBadge();
                    } catch (e) {
                        console.error('[RTN] Failed to parse unread count:', e);
                    }
                });

                // Handle errors
                this.eventSource.addEventListener('error', (error) => {
                    console.error('[RTN] SSE Error:', error);
                    this.handleConnectionError();
                });

                // Handle connection closed by server
                this.eventSource.addEventListener('close', () => {
                    console.log('[RTN] Connection closed by server');
                    this.disconnect();
                });

            } catch (error) {
                console.error('[RTN] Failed to create EventSource:', error);
                this.handleConnectionError();
            }
        }

        /**
         * Disconnect from SSE stream
         */
        disconnect() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            
            this.isConnected = false;
            this.stopHeartbeat();
            this.clearReconnectTimer();
            this.updateConnectionStatus('disconnected');
        }

        /**
         * Handle connection error with reconnection
         */
        handleConnectionError() {
            console.log('[RTN] Connection error, will reconnect in', this.reconnectDelay, 'ms');
            
            this.isConnected = false;
            this.updateConnectionStatus('connecting');
            this.stopHeartbeat();

            this.clearReconnectTimer();
            this.reconnectTimer = setTimeout(() => {
                this.reconnectDelay = Math.min(
                    this.reconnectDelay * 1.5,
                    this.options.maxReconnectDelay
                );
                this.connect();
            }, this.reconnectDelay);
        }

        /**
         * Reconnect immediately
         */
        reconnect() {
            this.clearReconnectTimer();
            this.reconnectDelay = this.options.reconnectDelay;
            this.connect();
        }

        /**
         * Pause notifications (when tab is hidden)
         */
        pause() {
            console.log('[RTN] Pausing notifications');
            // Could implement pause logic here
        }

        /**
         * Resume notifications (when tab becomes visible)
         */
        resume() {
            console.log('[RTN] Resuming notifications');
            this.loadUnreadCount();
        }

        /**
         * Start heartbeat to detect stale connections
         */
        startHeartbeat() {
            this.stopHeartbeat();
            
            this.heartbeatTimer = setInterval(() => {
                if (this.isConnected) {
                    // Could send a ping message here if needed
                    console.log('[RTN] Heartbeat OK');
                }
            }, this.options.heartbeatTimeout);
        }

        /**
         * Stop heartbeat
         */
        stopHeartbeat() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
        }

        /**
         * Clear reconnect timer
         */
        clearReconnectTimer() {
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
        }

        /**
         * Handle incoming notification
         * @param {Object} notification - Notification data
         */
        handleNotification(notification) {
            // Add to notifications array
            this.notifications.unshift(notification);
            
            // Limit stored notifications
            if (this.notifications.length > 50) {
                this.notifications = this.notifications.slice(0, 50);
            }

            // Update unread count
            this.unreadCount++;
            this.updateUnreadBadge();

            // Show browser notification
            if (this.options.enableBrowserNotifications && document.hidden) {
                this.showBrowserNotification(notification);
            }

            // Show in-app toast
            this.showToast(notification);

            // Play sound
            if (this.options.soundEnabled) {
                this.playNotificationSound();
            }

            // Update dropdown menu
            this.addNotificationToDropdown(notification);

            // Dispatch custom event
            window.dispatchEvent(new CustomEvent('notification-received', {
                detail: { notification }
            }));
        }

        /**
         * Show browser notification
         * @param {Object} notification - Notification data
         */
        showBrowserNotification(notification) {
            if (Notification.permission === 'granted') {
                new Notification(notification.title, {
                    body: notification.message,
                    icon: notification.icon || '/favicon.ico',
                    badge: '/favicon.ico',
                    tag: notification.id,
                    requireInteraction: false,
                    silent: !this.options.soundEnabled
                });
            }
        }

        /**
         * Show in-app toast notification
         * @param {Object} notification - Notification data
         */
        showToast(notification) {
            const toastContainer = document.querySelector('.toast-notification') || this.createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast priority-${notification.priority || 'low'}`;
            toast.innerHTML = `
                <div class="toast-header">
                    <div class="toast-icon ${this.getIconClass(notification.type)}">
                        <i class="fas ${this.getIcon(notification.type)}"></i>
                    </div>
                    <strong class="me-auto">${this.escapeHtml(notification.title)}</strong>
                    <small class="text-muted">${this.formatTime(notification.createdAt)}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${this.escapeHtml(notification.message)}
                </div>
            `;

            toastContainer.appendChild(toast);

            // Initialize Bootstrap toast
            const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
            bsToast.show();

            // Auto-remove
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        /**
         * Create toast container if it doesn't exist
         */
        createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-notification';
            document.body.appendChild(container);
            return container;
        }

        /**
         * Add notification to dropdown menu
         * @param {Object} notification - Notification data
         */
        addNotificationToDropdown(notification) {
            const dropdown = document.querySelector('.notifications-dropdown');
            if (!dropdown) return;

            const item = document.createElement('div');
            item.className = 'notification-item unread';
            item.dataset.notificationId = notification.id;
            item.innerHTML = `
                <div class="d-flex">
                    <div class="notification-icon ${this.getIconClass(notification.type)}">
                        <i class="fas ${this.getIcon(notification.type)}" style="color: #fff;"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${this.formatTime(notification.createdAt)}</div>
                    </div>
                </div>
            `;

            // Insert after header
            const header = dropdown.querySelector('.dropdown-header');
            if (header) {
                header.insertAdjacentElement('afterend', item);
            } else {
                dropdown.insertBefore(item, dropdown.firstChild);
            }

            // Click handler
            item.addEventListener('click', () => {
                this.markAsRead(notification.id);
                if (notification.url) {
                    window.location.href = notification.url;
                }
            });
        }

        /**
         * Mark notification as read
         * @param {number} notificationId - Notification ID
         */
        async markAsRead(notificationId) {
            try {
                const response = await fetch(`/api/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });

                if (response.ok) {
                    const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                    }
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateUnreadBadge();
                }
            } catch (error) {
                console.error('[RTN] Failed to mark notification as read:', error);
            }
        }

        /**
         * Mark all notifications as read
         */
        async markAllAsRead() {
            try {
                const response = await fetch('/api/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });

                if (response.ok) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    this.unreadCount = 0;
                    this.updateUnreadBadge();
                    showToast('Все уведомления прочитаны', 'success');
                }
            } catch (error) {
                console.error('[RTN] Failed to mark all as read:', error);
            }
        }

        /**
         * Load unread count from server
         */
        async loadUnreadCount() {
            try {
                const response = await fetch('/api/notifications/unread-count', {
                    cache: 'no-cache'
                });
                const data = await response.json();
                this.unreadCount = data.unread;
                this.updateUnreadBadge();
            } catch (error) {
                console.error('[RTN] Failed to load unread count:', error);
            }
        }

        /**
         * Update unread badge in UI
         */
        updateUnreadBadge() {
            const badge = document.querySelector('.notification-badge-enhanced');
            if (!badge) return;

            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.classList.remove('d-none');
                badge.classList.add('has-new');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
                badge.classList.remove('has-new');
            }
        }

        /**
         * Update connection status indicator
         * @param {string} status - Connection status
         */
        updateConnectionStatus(status) {
            let indicator = document.querySelector('.connection-status');
            
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'connection-status';
                indicator.innerHTML = `
                    <span class="status-dot"></span>
                    <span class="status-text"></span>
                `;
                document.body.appendChild(indicator);
            }

            const dot = indicator.querySelector('.status-dot');
            const text = indicator.querySelector('.status-text');

            dot.className = 'status-dot';
            
            switch (status) {
                case 'connected':
                    text.textContent = 'Онлайн';
                    indicator.classList.remove('hidden');
                    break;
                case 'connecting':
                    text.textContent = 'Подключение...';
                    dot.classList.add('connecting');
                    indicator.classList.remove('hidden');
                    break;
                case 'disconnected':
                    text.textContent = 'Оффлайн';
                    dot.classList.add('disconnected');
                    // Hide after delay
                    setTimeout(() => indicator.classList.add('hidden'), 5000);
                    break;
            }
        }

        /**
         * Request browser notification permission
         */
        requestNotificationPermission() {
            if (!('Notification' in window)) {
                console.log('[RTN] Browser does not support notifications');
                return;
            }

            if (Notification.permission === 'default') {
                // Show custom prompt first
                this.showPermissionPrompt();
            } else if (Notification.permission === 'granted') {
                console.log('[RTN] Notification permission granted');
            }
        }

        /**
         * Show permission prompt
         */
        showPermissionPrompt() {
            const prompt = document.createElement('div');
            prompt.className = 'notification-permission-prompt';
            prompt.innerHTML = `
                <h5><i class="fas fa-bell me-2"></i>Включить уведомления?</h5>
                <p>Получайте уведомления о новых задачах и событиях прямо в браузере.</p>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="enable-notifications">Включить</button>
                    <button class="btn btn-outline-secondary btn-sm" id="dismiss-notifications">Позже</button>
                </div>
            `;

            document.body.appendChild(prompt);

            document.getElementById('enable-notifications').addEventListener('click', async () => {
                const permission = await Notification.requestPermission();
                prompt.remove();
                
                if (permission === 'granted') {
                    showToast('Уведомления включены', 'success');
                }
            });

            document.getElementById('dismiss-notifications').addEventListener('click', () => {
                prompt.remove();
                localStorage.setItem('notifications_dismissed', 'true');
            });
        }

        /**
         * Play notification sound
         */
        playNotificationSound() {
            // Could implement custom sound here
            // For now, using browser's default notification sound
        }

        /**
         * Get icon class for notification type
         * @param {string} type - Notification type
         * @returns {string} Icon class
         */
        getIconClass(type) {
            const icons = {
                'task_assigned': 'success',
                'task_due': 'warning',
                'task_completed': 'success',
                'comment': 'info',
                'mention': 'info',
                'default': 'info'
            };
            return icons[type] || icons.default;
        }

        /**
         * Get icon for notification type
         * @param {string} type - Notification type
         * @returns {string} Icon name
         */
        getIcon(type) {
            const icons = {
                'task_assigned': 'fa-user-plus',
                'task_due': 'fa-exclamation-triangle',
                'task_completed': 'fa-check-circle',
                'comment': 'fa-comment',
                'mention': 'fa-at',
                'default': 'fa-bell'
            };
            return icons[type] || icons.default;
        }

        /**
         * Format time for display
         * @param {string} dateString - Date string
         * @returns {string} Formatted time
         */
        formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 1) return 'Только что';
            if (minutes < 60) return `${minutes} мин назад`;
            if (hours < 24) return `${hours} ч назад`;
            if (days < 7) return `${days} дн назад`;
            
            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'short'
            });
        }

        /**
         * Destroy instance and cleanup
         */
        destroy() {
            this.disconnect();
            document.removeEventListener('visibilitychange', this);
            window.removeEventListener('online', this);
            window.removeEventListener('offline', this);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.RealTimeNotifications = new RealTimeNotifications();
        });
    } else {
        window.RealTimeNotifications = new RealTimeNotifications();
    }

    // Export for manual usage
    window.RealTimeNotificationsClass = RealTimeNotifications;

})();
