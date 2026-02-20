/**
 * Real-time обновления и live data
 * Паттерны из современных CRM систем
 */

class RealTimeUpdates {
    constructor() {
        this.updateInterval = null;
        this.websocket = null;
        this.init();
    }

    init() {
        this.initLiveMetrics();
        this.initActivityStream();
        this.initNotificationCenter();
        this.initCollaborativeEditing();
        this.initPresenceIndicators();
    }

    /**
     * Live метрики с анимированными обновлениями
     */
    initLiveMetrics() {
        const metrics = document.querySelectorAll('[data-live-metric]');
        
        metrics.forEach(metric => {
            const metricId = metric.getAttribute('data-live-metric');
            this.startMetricUpdates(metric, metricId);
        });
    }

    startMetricUpdates(element, metricId) {
        // Симуляция real-time обновлений
        setInterval(() => {
            const currentValue = parseFloat(element.textContent.replace(/[^\d.-]/g, ''));
            const change = (Math.random() - 0.5) * 100;
            const newValue = Math.max(0, currentValue + change);
            
            this.animateValueChange(element, currentValue, newValue);
            this.showChangeIndicator(element, change);
        }, 5000);
    }

    animateValueChange(element, from, to) {
        const duration = 1000;
        const start = Date.now();
        
        const animate = () => {
            const elapsed = Date.now() - start;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = from + (to - from) * this.easeOutCubic(progress);
            element.textContent = Math.floor(current).toLocaleString('ru-RU');
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        animate();
    }

    showChangeIndicator(element, change) {
        const indicator = document.createElement('span');
        indicator.className = `change-indicator ${change > 0 ? 'positive' : 'negative'}`;
        indicator.textContent = `${change > 0 ? '+' : ''}${change.toFixed(0)}`;
        indicator.style.cssText = `
            position: absolute;
            top: -10px;
            right: 0;
            font-size: 12px;
            font-weight: 600;
            color: ${change > 0 ? '#10b981' : '#ef4444'};
            animation: floatUp 2s ease forwards;
        `;
        
        element.parentElement.style.position = 'relative';
        element.parentElement.appendChild(indicator);
        
        setTimeout(() => indicator.remove(), 2000);
    }

    /**
     * Лента активности в реальном времени
     */
    initActivityStream() {
        const stream = document.querySelector('[data-activity-stream]');
        if (!stream) return;

        // Симуляция новых событий
        setInterval(() => {
            this.addActivityItem(stream);
        }, 10000);
    }

    addActivityItem(stream) {
        const activities = [
            { user: 'Система', action: 'обновила', target: 'статистику продаж', icon: 'fa-sync', color: '#6366f1' },
            { user: 'Новый клиент', action: 'зарегистрировался', target: '', icon: 'fa-user-plus', color: '#10b981' },
            { user: 'Заказ #' + Math.floor(Math.random() * 1000), action: 'оформлен', target: '', icon: 'fa-shopping-cart', color: '#f59e0b' }
        ];
        
        const activity = activities[Math.floor(Math.random() * activities.length)];
        
        const item = document.createElement('div');
        item.className = 'activity-stream-item';
        item.style.cssText = `
            display: flex;
            gap: 12px;
            padding: 12px;
            background: var(--card-bg);
            border-radius: 8px;
            border-left: 3px solid ${activity.color};
            animation: slideInRight 0.3s ease;
            margin-bottom: 8px;
        `;
        
        item.innerHTML = `
            <div style="
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: ${activity.color}20;
                color: ${activity.color};
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <i class="fas ${activity.icon}"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 14px; color: var(--text-primary);">
                    <strong>${activity.user}</strong> ${activity.action} ${activity.target}
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    только что
                </div>
            </div>
        `;
        
        stream.insertBefore(item, stream.firstChild);
        
        // Удаляем старые элементы
        if (stream.children.length > 10) {
            stream.lastChild.remove();
        }
    }

    /**
     * Центр уведомлений с приоритетами
     */
    initNotificationCenter() {
        const bell = document.querySelector('[data-notification-bell]');
        if (!bell) return;

        let unreadCount = 0;
        
        // Симуляция новых уведомлений
        setInterval(() => {
            unreadCount++;
            this.updateNotificationBadge(bell, unreadCount);
            this.showToastNotification();
        }, 30000);

        bell.addEventListener('click', () => {
            this.openNotificationPanel();
            unreadCount = 0;
            this.updateNotificationBadge(bell, 0);
        });
    }

    updateNotificationBadge(bell, count) {
        let badge = bell.querySelector('.notification-badge');
        
        if (!badge && count > 0) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            badge.style.cssText = `
                position: absolute;
                top: -5px;
                right: -5px;
                background: #ef4444;
                color: white;
                border-radius: 10px;
                padding: 2px 6px;
                font-size: 11px;
                font-weight: 700;
                min-width: 18px;
                text-align: center;
                animation: bounce 0.5s ease;
            `;
            bell.appendChild(badge);
        }
        
        if (badge) {
            badge.textContent = count > 99 ? '99+' : count;
            if (count === 0) badge.remove();
        }
    }

    showToastNotification() {
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #6366f1;
            min-width: 300px;
            animation: slideInUp 0.3s ease;
            z-index: 9999;
        `;
        
        toast.innerHTML = `
            <div style="display: flex; gap: 12px; align-items: start;">
                <i class="fas fa-bell" style="color: #6366f1; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;">Новое уведомление</div>
                    <div style="font-size: 14px; color: var(--text-muted);">
                        У вас новая активность в системе
                    </div>
                </div>
                <button onclick="this.closest('.toast-notification').remove()" style="
                    background: none;
                    border: none;
                    color: var(--text-muted);
                    cursor: pointer;
                    padding: 0;
                ">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    openNotificationPanel() {
        // Создаём панель уведомлений
        const panel = document.createElement('div');
        panel.className = 'notification-panel';
        panel.style.cssText = `
            position: fixed;
            top: 70px;
            right: 20px;
            width: 400px;
            max-height: 600px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            z-index: 9999;
            animation: slideDown 0.3s ease;
        `;
        
        panel.innerHTML = `
            <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 18px;">Уведомления</h3>
                    <button onclick="this.closest('.notification-panel').remove()" style="
                        background: none;
                        border: none;
                        color: var(--text-muted);
                        cursor: pointer;
                        font-size: 20px;
                    ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div style="max-height: 500px; overflow-y: auto; padding: 12px;">
                ${this.generateNotificationItems()}
            </div>
        `;
        
        document.body.appendChild(panel);
        
        // Закрытие при клике вне панели
        setTimeout(() => {
            document.addEventListener('click', function closePanel(e) {
                if (!panel.contains(e.target) && !e.target.closest('[data-notification-bell]')) {
                    panel.remove();
                    document.removeEventListener('click', closePanel);
                }
            });
        }, 100);
    }

    generateNotificationItems() {
        const notifications = [
            { title: 'Новый заказ', text: 'Заказ #1234 ожидает обработки', time: '5 мин назад', icon: 'fa-shopping-cart', color: '#10b981' },
            { title: 'Задача завершена', text: 'Иван завершил задачу "Обновление каталога"', time: '1 час назад', icon: 'fa-check-circle', color: '#6366f1' },
            { title: 'Новый комментарий', text: 'Мария оставила комментарий к задаче', time: '2 часа назад', icon: 'fa-comment', color: '#f59e0b' }
        ];
        
        return notifications.map(notif => `
            <div style="
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 8px;
                cursor: pointer;
                transition: background 0.2s;
            " onmouseover="this.style.background='var(--bg-secondary)'" 
               onmouseout="this.style.background='transparent'">
                <div style="display: flex; gap: 12px;">
                    <div style="
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: ${notif.color}20;
                        color: ${notif.color};
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="fas ${notif.icon}"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px;">
                            ${notif.title}
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">
                            ${notif.text}
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            ${notif.time}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Collaborative editing indicators
     */
    initCollaborativeEditing() {
        const editableFields = document.querySelectorAll('[data-collaborative]');
        
        editableFields.forEach(field => {
            field.addEventListener('focus', () => {
                this.showEditingIndicator(field);
            });
            
            field.addEventListener('blur', () => {
                this.hideEditingIndicator(field);
            });
        });
    }

    showEditingIndicator(field) {
        const indicator = document.createElement('div');
        indicator.className = 'editing-indicator';
        indicator.style.cssText = `
            position: absolute;
            top: -30px;
            left: 0;
            background: #6366f1;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            animation: fadeIn 0.2s ease;
        `;
        indicator.textContent = 'Вы редактируете...';
        
        field.parentElement.style.position = 'relative';
        field.parentElement.appendChild(indicator);
    }

    hideEditingIndicator(field) {
        const indicator = field.parentElement.querySelector('.editing-indicator');
        if (indicator) indicator.remove();
    }

    /**
     * Presence indicators - кто онлайн
     */
    initPresenceIndicators() {
        const users = document.querySelectorAll('[data-user-presence]');
        
        users.forEach(user => {
            const status = Math.random() > 0.5 ? 'online' : 'offline';
            this.updatePresenceStatus(user, status);
        });
    }

    updatePresenceStatus(element, status) {
        let indicator = element.querySelector('.presence-indicator');
        
        if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'presence-indicator';
            indicator.style.cssText = `
                position: absolute;
                bottom: 0;
                right: 0;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                border: 2px solid var(--card-bg);
            `;
            element.style.position = 'relative';
            element.appendChild(indicator);
        }
        
        indicator.style.background = status === 'online' ? '#10b981' : '#9ca3af';
        
        if (status === 'online') {
            indicator.style.animation = 'pulse 2s infinite';
        }
    }

    easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.realTimeUpdates = new RealTimeUpdates();
    });
} else {
    window.realTimeUpdates = new RealTimeUpdates();
}

// CSS анимации
const styles = document.createElement('style');
styles.textContent = `
    @keyframes floatUp {
        0% {
            opacity: 1;
            transform: translateY(0);
        }
        100% {
            opacity: 0;
            transform: translateY(-30px);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOutDown {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(20px);
        }
    }
    
    @keyframes bounce {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
`;
document.head.appendChild(styles);
