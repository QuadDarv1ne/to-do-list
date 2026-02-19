/* === realtime-notifications.js === */
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


/* === offline-support.js === */
/**
 * Offline Support
 * Поддержка работы в оффлайн режиме
 */

class OfflineSupport {
    constructor() {
        this.isOnline = navigator.onLine;
        this.queue = this.loadQueue();
        this.syncInProgress = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.addOfflineIndicatorStyles();
        // Не проверяем соединение при инициализации - используем navigator.onLine
        // checkConnection будет вызван только при событиях online/offline
        this.startPeriodicSync();
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        window.addEventListener('online', () => {
            this.handleOnline();
        });

        window.addEventListener('offline', () => {
            this.handleOffline();
        });

        // Перехват форм для оффлайн режима
        document.addEventListener('submit', (e) => {
            if (!this.isOnline && e.target.matches('[data-offline-support]')) {
                e.preventDefault();
                this.queueFormSubmission(e.target);
            }
        });

        // Перехват AJAX запросов
        this.interceptFetch();
    }

    /**
     * Создать индикатор оффлайн режима (только при необходимости)
     */
    createOfflineIndicator() {
        // Проверяем, не создан ли уже индикатор
        if (document.getElementById('offline-indicator')) {
            return;
        }
        
        const indicator = document.createElement('div');
        indicator.id = 'offline-indicator';
        indicator.className = 'offline-indicator show';
        indicator.innerHTML = `
            <div class="offline-indicator-content">
                <i class="fas fa-wifi-slash"></i>
                <span>Нет связи</span>
            </div>
            <button class="btn btn-sm btn-light" id="retry-connection" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                <i class="fas fa-sync-alt"></i>
            </button>
        `;

        document.body.appendChild(indicator);

        // Кнопка повтора
        document.getElementById('retry-connection').addEventListener('click', () => {
            this.checkConnection();
        });
    }

    /**
     * Добавить стили индикатора
     */
    addOfflineIndicatorStyles() {
        if (document.getElementById('offlineIndicatorStyles')) return;

        const style = document.createElement('style');
        style.id = 'offlineIndicatorStyles';
        style.textContent = `
            .offline-indicator {
                position: fixed;
                top: 70px;
                right: 20px;
                background: #f59e0b;
                color: white;
                padding: 0.5rem 0.875rem;
                border-radius: 6px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                z-index: 1050;
                display: none;
                font-size: 0.875rem;
                max-width: 280px;
                flex-direction: row;
                align-items: center;
                gap: 0.75rem;
                animation: slideIn 0.3s ease-out;
            }

            .offline-indicator.show {
                display: flex;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            .offline-indicator-content {
                display: flex;
                align-items: center;
                font-weight: 500;
                font-size: 0.875rem;
                flex: 1;
            }

            .offline-queue-info {
                font-size: 0.75rem;
                opacity: 0.9;
                white-space: nowrap;
            }

            #queue-count {
                font-weight: 600;
            }

            .offline-badge {
                display: none;
                box-shadow: var(--shadow);
                z-index: 1000;
                display: none;
            }

            .offline-badge.show {
                display: block;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Обработка перехода в онлайн
     */
    handleOnline() {
        this.isOnline = true;
        
        const indicator = document.getElementById('offline-indicator');
        if (indicator) {
            indicator.classList.remove('show');
        }

        this.showToast('Соединение восстановлено', 'success');
        
        // Синхронизация очереди
        if (this.queue.length > 0) {
            this.syncQueue();
        }
    }

    /**
     * Обработка перехода в оффлайн
     */
    handleOffline() {
        this.isOnline = false;
        
        // Создать индикатор только когда действительно оффлайн
        this.createOfflineIndicator();
        
        // Не показываем toast - достаточно индикатора
    }

    /**
     * Проверить подключение
     */
    async checkConnection() {
        try {
            // Проверяем соединение через публичный endpoint
            const response = await fetch('/login', {
                method: 'HEAD',
                cache: 'no-cache'
            });
            
            if (response.ok) {
                if (!this.isOnline) {
                    this.handleOnline();
                }
            } else {
                if (this.isOnline) {
                    this.handleOffline();
                }
            }
        } catch (error) {
            if (this.isOnline) {
                this.handleOffline();
            }
        }
    }

    /**
     * Добавить отправку формы в очередь
     */
    queueFormSubmission(form) {
        const formData = new FormData(form);
        const data = {
            id: Date.now(),
            type: 'form',
            url: form.action,
            method: form.method || 'POST',
            data: Object.fromEntries(formData.entries()),
            timestamp: new Date().toISOString()
        };

        this.queue.push(data);
        this.saveQueue();
        this.updateQueueCount();

        this.showToast('Действие добавлено в очередь и будет выполнено при восстановлении соединения', 'info');
    }

    /**
     * Добавить запрос в очередь
     */
    queueRequest(url, options) {
        const data = {
            id: Date.now(),
            type: 'fetch',
            url: url,
            method: options.method || 'GET',
            headers: options.headers || {},
            body: options.body,
            timestamp: new Date().toISOString()
        };

        this.queue.push(data);
        this.saveQueue();
        this.updateQueueCount();
    }

    /**
     * Синхронизировать очередь
     */
    async syncQueue() {
        if (this.syncInProgress || this.queue.length === 0) return;

        this.syncInProgress = true;
        this.showToast(`Синхронизация ${this.queue.length} действий...`, 'info');

        const results = {
            success: 0,
            failed: 0
        };

        for (const item of [...this.queue]) {
            try {
                if (item.type === 'form') {
                    await this.syncFormSubmission(item);
                } else if (item.type === 'fetch') {
                    await this.syncFetchRequest(item);
                }

                // Удаляем из очереди при успехе
                this.queue = this.queue.filter(q => q.id !== item.id);
                results.success++;
            } catch (error) {
                console.error('Sync error:', error);
                results.failed++;
            }
        }

        this.saveQueue();
        this.updateQueueCount();
        this.syncInProgress = false;

        if (results.success > 0) {
            this.showToast(`Синхронизировано ${results.success} действий`, 'success');
        }

        if (results.failed > 0) {
            this.showToast(`Не удалось синхронизировать ${results.failed} действий`, 'error');
        }
    }

    /**
     * Синхронизировать отправку формы
     */
    async syncFormSubmission(item) {
        const formData = new FormData();
        for (const [key, value] of Object.entries(item.data)) {
            formData.append(key, value);
        }

        const response = await fetch(item.url, {
            method: item.method,
            body: formData
        });

        if (!response.ok) {
            throw new Error('Form submission failed');
        }

        return response;
    }

    /**
     * Синхронизировать fetch запрос
     */
    async syncFetchRequest(item) {
        const response = await fetch(item.url, {
            method: item.method,
            headers: item.headers,
            body: item.body
        });

        if (!response.ok) {
            throw new Error('Fetch request failed');
        }

        return response;
    }

    /**
     * Перехватить fetch запросы
     */
    interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            if (!this.isOnline) {
                const [url, options = {}] = args;
                
                // Только для POST, PUT, PATCH, DELETE
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method?.toUpperCase())) {
                    this.queueRequest(url, options);
                    
                    // Возвращаем фейковый успешный ответ
                    return new Response(JSON.stringify({ queued: true }), {
                        status: 202,
                        headers: { 'Content-Type': 'application/json' }
                    });
                }
            }

            return originalFetch(...args);
        };
    }

    /**
     * Обновить счетчик очереди
     */
    updateQueueCount() {
        const countEl = document.getElementById('queue-count');
        if (countEl) {
            countEl.textContent = this.queue.length;
        }

        // Показать индикатор только если есть элементы в очереди и оффлайн
        const indicator = document.getElementById('offline-indicator');
        if (indicator && !this.isOnline && this.queue.length > 0) {
            indicator.classList.add('show');
        }
    }

    /**
     * Загрузить очередь
     */
    loadQueue() {
        try {
            const stored = localStorage.getItem('offlineQueue');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Сохранить очередь
     */
    saveQueue() {
        try {
            localStorage.setItem('offlineQueue', JSON.stringify(this.queue));
        } catch (e) {
            console.error('Failed to save queue:', e);
        }
    }

    /**
     * Периодическая синхронизация
     */
    startPeriodicSync() {
        setInterval(() => {
            if (this.isOnline && this.queue.length > 0) {
                this.syncQueue();
            }
        }, 60000); // Каждую минуту
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

    /**
     * Очистить очередь
     */
    clearQueue() {
        this.queue = [];
        this.saveQueue();
        this.updateQueueCount();
    }

    /**
     * Получить статистику
     */
    getStats() {
        return {
            isOnline: this.isOnline,
            queueLength: this.queue.length,
            oldestItem: this.queue.length > 0 ? this.queue[0].timestamp : null
        };
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineSupport = new OfflineSupport();
    });
} else {
    window.offlineSupport = new OfflineSupport();
}

// Экспорт
window.OfflineSupport = OfflineSupport;


/* === pwa-install.js === */
/**
 * PWA Install Prompt & Offline Support
 * Установка приложения и офлайн-поддержка
 */

(function() {
    'use strict';

    class PWAInstall {
        constructor(options = {}) {
            this.options = {
                promptDelay: 3000,
                storageKey: 'pwa_install_dismissed',
                ...options
            };

            this.deferredPrompt = null;
            this.isInstalled = this.checkIfInstalled();

            this.init();
        }

        /**
         * Initialize PWA features
         */
        init() {
            // Register Service Worker
            this.registerServiceWorker();

            // Setup install prompt
            this.setupInstallPrompt();

            // Handle online/offline events
            this.setupOnlineOfflineHandlers();

            // Check if app is running as PWA
            this.checkPWAMode();

            // Show install prompt if eligible
            if (!this.isInstalled && !this.hasDismissed()) {
                setTimeout(() => this.showInstallPrompt(), this.options.promptDelay);
            }
        }

        /**
         * Register Service Worker
         */
        async registerServiceWorker() {
            if ('serviceWorker' in navigator) {
                try {
                    const registration = await navigator.serviceWorker.register('/sw.js', {
                        scope: '/'
                    });

                    console.log('[PWA] Service Worker registered:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                this.showUpdatePrompt();
                            }
                        });
                    });

                    // Handle messages from SW
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        this.handleSWMessage(event.data);
                    });

                } catch (error) {
                    console.error('[PWA] Service Worker registration failed:', error);
                }
            }
        }

        /**
         * Setup beforeinstallprompt handler
         */
        setupInstallPrompt() {
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('[PWA] beforeinstallprompt event fired');
                
                // Prevent Chrome from showing prompt automatically
                e.preventDefault();
                
                // Store the event for later use
                this.deferredPrompt = e;
                
                // Update UI to show install button
                this.showInstallButton();
            });

            // Handle app installed event
            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App installed successfully');
                this.deferredPrompt = null;
                this.isInstalled = true;
                this.hideInstallPrompt();
                localStorage.setItem(this.options.storageKey, 'true');
            });
        }

        /**
         * Setup online/offline event handlers
         */
        setupOnlineOfflineHandlers() {
            window.addEventListener('online', () => {
                console.log('[PWA] Connection restored');
                this.showConnectionToast('online');
                this.syncOfflineData();
            });

            window.addEventListener('offline', () => {
                console.log('[PWA] Connection lost');
                this.showConnectionToast('offline');
            });
        }

        /**
         * Check if app is running as PWA
         */
        checkPWAMode() {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            const isStandaloneWindow = window.navigator.standalone === true;
            
            if (isStandalone || isStandaloneWindow) {
                console.log('[PWA] Running as standalone PWA');
                document.body.classList.add('pwa-mode');
            } else {
                console.log('[PWA] Running in browser');
            }
        }

        /**
         * Check if app is installed
         * @returns {boolean} Is installed
         */
        checkIfInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches ||
                   window.navigator.standalone === true ||
                   localStorage.getItem(this.options.storageKey) === 'true';
        }

        /**
         * Check if user dismissed install prompt
         * @returns {boolean} Has dismissed
         */
        hasDismissed() {
            return localStorage.getItem(this.options.storageKey) === 'true';
        }

        /**
         * Show install button
         */
        showInstallButton() {
            // Check if button already exists
            if (document.querySelector('.pwa-install-btn')) {
                return;
            }

            const installBtn = document.createElement('button');
            installBtn.className = 'btn btn-primary pwa-install-btn';
            installBtn.innerHTML = `
                <i class="fas fa-download me-2"></i>
                Установить приложение
            `;
            installBtn.addEventListener('click', () => this.promptInstall());

            // Add to navbar or header
            const navbar = document.querySelector('.navbar-nav.ms-auto');
            if (navbar) {
                const li = document.createElement('li');
                li.className = 'nav-item';
                li.appendChild(installBtn);
                navbar.insertBefore(li, navbar.firstChild);
            }
        }

        /**
         * Show install prompt modal
         */
        showInstallPrompt() {
            if (this.deferredPrompt || this.canInstall()) {
                const modal = document.createElement('div');
                modal.className = 'modal fade pwa-install-modal';
                modal.id = 'pwaInstallModal';
                modal.tabIndex = '-1';
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-mobile-alt me-2"></i>
                                    Установить приложение
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                    <h4>CRM Задачи всегда под рукой</h4>
                                    <p class="text-muted">
                                        Установите наше приложение для быстрого доступа и работы в офлайн-режиме
                                    </p>
                                </div>
                                
                                <div class="list-group mb-4">
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-bolt text-warning fa-lg"></i>
                                        <div>
                                            <strong>Быстрый доступ</strong>
                                            <p class="mb-0 text-muted small">Запускайте приложение в один клик</p>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-wifi text-success fa-lg"></i>
                                        <div>
                                            <strong>Офлайн-режим</strong>
                                            <p class="mb-0 text-muted small">Работайте без интернета</p>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-bell text-info fa-lg"></i>
                                        <div>
                                            <strong>Уведомления</strong>
                                            <p class="mb-0 text-muted small">Получайте push-уведомления</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" onclick="PWAInstall.dismiss()">
                                    Позже
                                </button>
                                <button type="button" class="btn btn-primary" onclick="PWAInstall.install()">
                                    <i class="fas fa-download me-2"></i>
                                    Установить
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        }

        /**
         * Check if can install (iOS Safari)
         * @returns {boolean} Can install
         */
        canInstall() {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const isStandalone = window.navigator.standalone === true;
            
            return isIOS && !isStandalone;
        }

        /**
         * Prompt user to install
         */
        async promptInstall() {
            // Close modal if open
            const modal = document.getElementById('pwaInstallModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
            }

            if (this.deferredPrompt) {
                // Android/Desktop - use beforeinstallprompt
                this.deferredPrompt.prompt();
                
                const { outcome } = await this.deferredPrompt.userChoice;
                console.log('[PWA] User choice:', outcome);
                
                if (outcome === 'accepted') {
                    console.log('[PWA] User accepted install prompt');
                }
                
                this.deferredPrompt = null;
            } else if (this.canInstall()) {
                // iOS - show manual instructions
                this.showIOSInstallInstructions();
            }
        }

        /**
         * Show iOS install instructions
         */
        showIOSInstallInstructions() {
            const modal = document.createElement('div');
            modal.className = 'modal fade pwa-install-modal';
            modal.id = 'pwaIOSModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fab fa-apple me-2"></i>
                                Установка на iOS
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p class="mb-4">Чтобы установить приложение на iOS:</p>
                            
                            <div class="text-start">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">1</span>
                                    <span>Нажмите кнопку "Поделиться"</span>
                                    <i class="fas fa-share-square fa-lg text-primary"></i>
                                </div>
                                
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">2</span>
                                    <span>Выберите "На экран «Домой»"</span>
                                    <i class="fas fa-plus-square fa-lg text-primary"></i>
                                </div>
                                
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">3</span>
                                    <span>Нажмите "Добавить"</span>
                                    <i class="fas fa-check-square fa-lg text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        /**
         * Dismiss install prompt
         */
        dismiss() {
            localStorage.setItem(this.options.storageKey, 'true');
            this.hideInstallPrompt();
        }

        /**
         * Hide install prompt
         */
        hideInstallPrompt() {
            const btn = document.querySelector('.pwa-install-btn');
            if (btn) {
                btn.remove();
            }
            
            const modal = document.getElementById('pwaInstallModal');
            if (modal) {
                modal.remove();
            }
        }

        /**
         * Show update prompt
         */
        showUpdatePrompt() {
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed bottom-0 end-0 m-3';
            toast.role = 'alert';
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-sync fa-spin me-2 text-primary"></i>
                    <strong class="me-auto">Доступно обновление</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    Новая версия приложения готова. 
                    <button class="btn btn-sm btn-primary ms-2" onclick="location.reload()">
                        Обновить
                    </button>
                </div>
            `;

            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { autohide: false });
            bsToast.show();
        }

        /**
         * Show connection toast
         * @param {string} status - 'online' or 'offline'
         */
        showConnectionToast(status) {
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed top-0 start-50 translate-middle-x m-3';
            toast.role = 'alert';
            
            const isSuccess = status === 'online';
            
            toast.innerHTML = `
                <div class="toast-header bg-${isSuccess ? 'success' : 'warning'} text-white">
                    <i class="fas fa-${isSuccess ? 'wifi' : 'wifi-slash'} me-2"></i>
                    <strong class="me-auto">${isSuccess ? 'Подключение восстановлено' : 'Нет подключения'}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                ${!isSuccess ? `
                    <div class="toast-body">
                        Вы можете просматривать кэшированные страницы
                        <a href="/offline-page.html" class="btn btn-sm btn-outline-warning ms-2">
                            Офлайн-режим
                        </a>
                    </div>
                ` : ''}
            `;

            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
        }

        /**
         * Sync offline data
         */
        async syncOfflineData() {
            if ('serviceWorker' in navigator && 'sync' in window.registration) {
                try {
                    await navigator.serviceWorker.ready;
                    await window.registration.sync.register('sync-offline-actions');
                    console.log('[PWA] Offline data synced');
                } catch (error) {
                    console.log('[PWA] Sync not available:', error);
                }
            }
        }

        /**
         * Handle Service Worker messages
         * @param {Object} data - Message data
         */
        handleSWMessage(data) {
            console.log('[PWA] Message from SW:', data);
            
            switch (data.type) {
                case 'TASKS_SYNCED':
                    showToast(`Синхронизировано задач: ${data.count}`, 'success');
                    break;
                case 'CACHE_CLEARED':
                    console.log('[PWA] Cache cleared');
                    break;
            }
        }

        /**
         * Install app (exposed to global scope)
         */
        install() {
            this.promptInstall();
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.PWAInstall = new PWAInstall();
        });
    } else {
        window.PWAInstall = new PWAInstall();
    }

    // Expose methods to global scope for onclick handlers
    window.PWAInstall = window.PWAInstall || {};
    window.PWAInstall.install = () => window.PWAInstall.promptInstall();
    window.PWAInstall.dismiss = () => window.PWAInstall.dismiss();

})();


/* === notifications.js === */
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

    /**
     * Экранирование HTML для предотвращения XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
                        ${this.escapeHtml(notification.title)}
                    </div>
                    <div class="notification-item-time">${this.formatTime(notification.createdAt)}</div>
                </div>
                <div class="notification-item-content">${this.escapeHtml(notification.message)}</div>
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
        audio.play().catch(() => {});
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



