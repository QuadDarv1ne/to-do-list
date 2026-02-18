/**
 * Advanced Notification System
 * Toast notifications with queue management and animations
 */

(function() {
    'use strict';

    class NotificationSystem {
        constructor() {
            this.container = null;
            this.queue = [];
            this.maxVisible = 3;
            this.defaultDuration = 5000;
            this.init();
        }

        init() {
            this.createContainer();
            this.setupGlobalHandler();
        }

        createContainer() {
            if (document.getElementById('notification-container')) {
                this.container = document.getElementById('notification-container');
                return;
            }

            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.className = 'notification-container';
            document.body.appendChild(this.container);
        }

        setupGlobalHandler() {
            // Listen for custom notification events
            document.addEventListener('notify', (e) => {
                this.show(e.detail.message, e.detail.type, e.detail.duration);
            });
        }

        show(message, type = 'info', duration = null) {
            const notification = this.createNotification(message, type, duration);
            this.queue.push(notification);
            this.processQueue();
        }

        createNotification(message, type, duration) {
            const id = 'notification-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const notification = document.createElement('div');
            notification.id = id;
            notification.className = `notification notification-${type} notification-enter`;
            
            const icon = this.getIcon(type);
            const progressBar = duration !== 0 ? '<div class="notification-progress"></div>' : '';
            
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="notification-message">${message}</div>
                    <button class="notification-close" aria-label="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                ${progressBar}
            `;

            // Close button handler
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                this.hide(id);
            });

            return {
                id: id,
                element: notification,
                type: type,
                duration: duration || this.defaultDuration,
                timer: null
            };
        }

        getIcon(type) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            return icons[type] || icons.info;
        }

        processQueue() {
            const visible = this.container.querySelectorAll('.notification:not(.notification-exit)');
            
            if (visible.length >= this.maxVisible) {
                return;
            }

            const notification = this.queue.shift();
            if (!notification) return;

            this.container.appendChild(notification.element);

            // Trigger animation
            setTimeout(() => {
                notification.element.classList.remove('notification-enter');
                notification.element.classList.add('notification-visible');
            }, 10);

            // Start progress bar animation
            if (notification.duration > 0) {
                const progressBar = notification.element.querySelector('.notification-progress');
                if (progressBar) {
                    progressBar.style.animationDuration = notification.duration + 'ms';
                }

                // Auto hide after duration
                notification.timer = setTimeout(() => {
                    this.hide(notification.id);
                }, notification.duration);
            }

            // Process next in queue
            if (this.queue.length > 0) {
                setTimeout(() => this.processQueue(), 300);
            }
        }

        hide(id) {
            const notification = this.container.querySelector('#' + id);
            if (!notification) return;

            notification.classList.remove('notification-visible');
            notification.classList.add('notification-exit');

            setTimeout(() => {
                notification.remove();
                this.processQueue();
            }, 300);
        }

        success(message, duration) {
            this.show(message, 'success', duration);
        }

        error(message, duration) {
            this.show(message, 'error', duration);
        }

        warning(message, duration) {
            this.show(message, 'warning', duration);
        }

        info(message, duration) {
            this.show(message, 'info', duration);
        }

        clear() {
            const notifications = this.container.querySelectorAll('.notification');
            notifications.forEach(n => {
                n.classList.add('notification-exit');
                setTimeout(() => n.remove(), 300);
            });
            this.queue = [];
        }
    }

    // Initialize and expose globally
    const notificationSystem = new NotificationSystem();
    
    window.notify = {
        show: (message, type, duration) => notificationSystem.show(message, type, duration),
        success: (message, duration) => notificationSystem.success(message, duration),
        error: (message, duration) => notificationSystem.error(message, duration),
        warning: (message, duration) => notificationSystem.warning(message, duration),
        info: (message, duration) => notificationSystem.info(message, duration),
        clear: () => notificationSystem.clear()
    };

    // Override window.showToast if it exists
    if (typeof window.showToast === 'function') {
        const originalShowToast = window.showToast;
        window.showToast = function(message, type) {
            notificationSystem.show(message, type);
        };
    }

})();

// Add CSS for notifications
const style = document.createElement('style');
style.textContent = `
    .notification-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .notification {
        min-width: 320px;
        max-width: 400px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        pointer-events: all;
        position: relative;
    }

    .notification-content {
        display: flex;
        align-items: center;
        padding: 16px;
        gap: 12px;
    }

    .notification-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .notification-message {
        flex: 1;
        font-size: 14px;
        line-height: 1.5;
        color: #333;
    }

    .notification-close {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        border: none;
        background: transparent;
        color: #999;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-close:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #333;
    }

    .notification-progress {
        height: 4px;
        background: rgba(0, 0, 0, 0.1);
        animation: notificationProgress linear forwards;
    }

    @keyframes notificationProgress {
        from { width: 100%; }
        to { width: 0%; }
    }

    /* Type-specific styles */
    .notification-success {
        border-left: 4px solid #28a745;
    }

    .notification-success .notification-icon {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .notification-success .notification-progress {
        background: #28a745;
    }

    .notification-error {
        border-left: 4px solid #dc3545;
    }

    .notification-error .notification-icon {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .notification-error .notification-progress {
        background: #dc3545;
    }

    .notification-warning {
        border-left: 4px solid #ffc107;
    }

    .notification-warning .notification-icon {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }

    .notification-warning .notification-progress {
        background: #ffc107;
    }

    .notification-info {
        border-left: 4px solid #17a2b8;
    }

    .notification-info .notification-icon {
        background: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
    }

    .notification-info .notification-progress {
        background: #17a2b8;
    }

    /* Animations */
    .notification-enter {
        opacity: 0;
        transform: translateX(100%) scale(0.8);
    }

    .notification-visible {
        opacity: 1;
        transform: translateX(0) scale(1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .notification-exit {
        opacity: 0;
        transform: translateX(100%) scale(0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Dark theme support */
    [data-theme='dark'] .notification {
        background: #2d3748;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    }

    [data-theme='dark'] .notification-message {
        color: #f7fafc;
    }

    [data-theme='dark'] .notification-close {
        color: #a0aec0;
    }

    [data-theme='dark'] .notification-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #f7fafc;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .notification-container {
            top: 10px;
            right: 10px;
            left: 10px;
        }

        .notification {
            min-width: auto;
            max-width: 100%;
        }
    }
`;
document.head.appendChild(style);
