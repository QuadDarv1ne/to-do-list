/**
 * Real-time Notifications
 * Обновление счётчика уведомлений
 */

(function() {
    'use strict';

    class RealTimeNotifications {
        constructor() {
            this.notificationCountElement = document.getElementById('notificationCount');
            this.updateInProgress = false;
            this.lastUpdate = 0;
            this.updateThrottle = 5000; // 5 секунд
            this.init();
        }

        init() {
            this.bindEvents();
            // Первое обновление через 1 секунду
            setTimeout(() => this.updateNotificationCount(), 1000);
            // Затем каждые 5 минут
            setInterval(() => this.updateNotificationCount(), 300000);
        }

        async updateNotificationCount() {
            const now = Date.now();
            
            // Throttle - не обновляем чаще чем раз в 5 секунд
            if (this.updateInProgress || (now - this.lastUpdate) < this.updateThrottle) {
                return;
            }

            this.updateInProgress = true;
            this.lastUpdate = now;

            try {
                const response = await fetch('/api/notifications/unread-count', {
                    cache: 'no-cache',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (this.notificationCountElement) {
                    const count = data.unread || 0;
                    this.notificationCountElement.textContent = count > 99 ? '99+' : count;
                    this.notificationCountElement.classList.toggle('d-none', count === 0);
                    
                    // Анимация при новых уведомлениях
                    if (count > 0) {
                        this.notificationCountElement.style.animation = 'pulse-badge 2s infinite';
                    }
                }
            } catch (error) {
                // Тихая ошибка - не спамим консоль
                if (window.console && console.error) {
                    console.error('Failed to update notification count:', error);
                }
            } finally {
                this.updateInProgress = false;
            }
        }

        bindEvents() {
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    await this.markAllAsRead();
                });
            }
        }

        async markAllAsRead() {
            try {
                const response = await fetch('/api/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    await this.updateNotificationCount();
                }
            } catch (error) {
                if (window.console && console.error) {
                    console.error('Failed to mark notifications as read:', error);
                }
            }
        }
    }

    // Инициализация после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.realTimeNotifications = new RealTimeNotifications();
        });
    } else {
        window.realTimeNotifications = new RealTimeNotifications();
    }
})();
