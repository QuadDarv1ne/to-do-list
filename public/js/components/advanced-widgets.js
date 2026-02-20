/**
 * Продвинутые виджеты для CRM дашборда
 * Основано на лучших практиках 2024-2025
 */

class AdvancedWidgets {
    constructor() {
        this.init();
    }

    init() {
        this.initKPICards();
        this.initActivityFeed();
        this.initQuickStats();
        this.initProgressTrackers();
        this.initSmartNotifications();
        this.initCommandPalette();
        this.initDataCards();
    }

    /**
     * KPI карточки с анимированными числами
     */
    initKPICards() {
        document.querySelectorAll('[data-kpi-value]').forEach(card => {
            const targetValue = parseFloat(card.getAttribute('data-kpi-value'));
            const duration = 1500;
            const startTime = Date.now();
            
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function для плавности
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const currentValue = targetValue * easeOutQuart;
                
                card.textContent = Math.floor(currentValue).toLocaleString('ru-RU');
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            // Запускаем анимацию при появлении в viewport
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animate();
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe(card);
        });
    }

    /**
     * Лента активности в реальном времени
     */
    initActivityFeed() {
        const feedContainer = document.querySelector('[data-activity-feed]');
        if (!feedContainer) return;

        const activities = [
            { user: 'Иван Петров', action: 'создал задачу', target: 'Разработка API', time: '2 мин назад', icon: 'fa-plus', color: '#10b981' },
            { user: 'Мария Сидорова', action: 'завершила', target: 'Тестирование модуля', time: '15 мин назад', icon: 'fa-check', color: '#6366f1' },
            { user: 'Алексей Иванов', action: 'прокомментировал', target: 'Дизайн главной', time: '1 час назад', icon: 'fa-comment', color: '#f59e0b' }
        ];

        feedContainer.innerHTML = activities.map((activity, index) => `
            <div class="activity-item fade-in-up" style="animation-delay: ${index * 0.1}s;">
                <div class="activity-icon" style="background: ${activity.color}20; color: ${activity.color};">
                    <i class="fas ${activity.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">
                        <strong>${activity.user}</strong> ${activity.action} 
                        <span class="activity-target">${activity.target}</span>
                    </div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Быстрая статистика с прогресс-барами
     */
    initQuickStats() {
        document.querySelectorAll('[data-progress-bar]').forEach(bar => {
            const value = parseFloat(bar.getAttribute('data-progress-value'));
            const progressFill = bar.querySelector('.progress-fill');
            
            if (progressFill) {
                setTimeout(() => {
                    progressFill.style.width = `${value}%`;
                }, 100);
            }
        });
    }

    /**
     * Трекеры прогресса с визуализацией
     */
    initProgressTrackers() {
        document.querySelectorAll('[data-circular-progress]').forEach(tracker => {
            const value = parseFloat(tracker.getAttribute('data-progress-value'));
            const circle = tracker.querySelector('circle.progress-ring');
            
            if (circle) {
                const radius = circle.r.baseVal.value;
                const circumference = 2 * Math.PI * radius;
                const offset = circumference - (value / 100) * circumference;
                
                circle.style.strokeDasharray = `${circumference} ${circumference}`;
                circle.style.strokeDashoffset = circumference;
                
                setTimeout(() => {
                    circle.style.strokeDashoffset = offset;
                }, 100);
            }
        });
    }

    /**
     * Умные уведомления с приоритетами
     */
    initSmartNotifications() {
        const notificationQueue = [];
        
        window.showSmartNotification = (message, type = 'info', priority = 'normal') => {
            const notification = {
                id: Date.now(),
                message,
                type,
                priority,
                timestamp: new Date()
            };
            
            notificationQueue.push(notification);
            this.displayNotification(notification);
        };
    }

    displayNotification(notification) {
        const container = document.getElementById('smart-notifications') || this.createNotificationContainer();
        
        const notifEl = document.createElement('div');
        notifEl.className = `smart-notification ${notification.type} slide-in-right`;
        notifEl.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-message">${notification.message}</div>
                <div class="notification-time">${this.formatTime(notification.timestamp)}</div>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notifEl);
        
        // Автоудаление через 5 секунд
        setTimeout(() => {
            notifEl.style.animation = 'slide-out-right 0.3s ease';
            setTimeout(() => notifEl.remove(), 300);
        }, 5000);
    }

    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'smart-notifications';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'bell';
    }

    formatTime(date) {
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'только что';
        if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`;
        if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`;
        return date.toLocaleDateString('ru-RU');
    }

    /**
     * Command Palette (как в VS Code)
     */
    initCommandPalette() {
        let paletteOpen = false;
        
        // Открытие по Ctrl+K или Cmd+K
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.toggleCommandPalette();
            }
            
            // Закрытие по Escape
            if (e.key === 'Escape' && paletteOpen) {
                this.closeCommandPalette();
            }
        });
    }

    toggleCommandPalette() {
        let palette = document.getElementById('command-palette');
        
        if (!palette) {
            palette = this.createCommandPalette();
        }
        
        if (palette.style.display === 'flex') {
            this.closeCommandPalette();
        } else {
            palette.style.display = 'flex';
            palette.querySelector('input').focus();
        }
    }

    createCommandPalette() {
        const palette = document.createElement('div');
        palette.id = 'command-palette';
        palette.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 15vh;
            z-index: 10001;
            animation: fadeIn 0.2s ease;
        `;
        
        palette.innerHTML = `
            <div class="command-palette-content" style="
                background: var(--card-bg, white);
                border-radius: 12px;
                width: 90%;
                max-width: 600px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                overflow: hidden;
                animation: slideDown 0.3s ease;
            ">
                <div style="padding: 16px; border-bottom: 1px solid var(--border-color, #e0e0e0);">
                    <input type="text" 
                           placeholder="Введите команду или поиск..." 
                           style="
                               width: 100%;
                               border: none;
                               background: transparent;
                               font-size: 16px;
                               color: var(--text-primary, #333);
                               outline: none;
                           "
                           oninput="window.advancedWidgets.filterCommands(this.value)">
                </div>
                <div class="command-list" style="
                    max-height: 400px;
                    overflow-y: auto;
                    padding: 8px;
                ">
                    ${this.getCommandList()}
                </div>
            </div>
        `;
        
        palette.addEventListener('click', (e) => {
            if (e.target === palette) {
                this.closeCommandPalette();
            }
        });
        
        document.body.appendChild(palette);
        return palette;
    }

    getCommandList() {
        const commands = [
            { icon: 'fa-plus', label: 'Создать задачу', action: 'new-task', shortcut: 'Ctrl+N' },
            { icon: 'fa-folder-plus', label: 'Создать проект', action: 'new-project' },
            { icon: 'fa-chart-line', label: 'Открыть аналитику', action: 'analytics' },
            { icon: 'fa-users', label: 'Управление пользователями', action: 'users' },
            { icon: 'fa-cog', label: 'Настройки', action: 'settings' },
            { icon: 'fa-palette', label: 'Сменить тему', action: 'theme' }
        ];
        
        return commands.map(cmd => `
            <div class="command-item" data-action="${cmd.action}" onclick="window.advancedWidgets.executeCommand('${cmd.action}')" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-radius: 8px;
                cursor: pointer;
                transition: background 0.2s;
            " onmouseover="this.style.background='var(--bg-secondary, #f5f5f5)'" 
               onmouseout="this.style.background='transparent'">
                <i class="fas ${cmd.icon}" style="width: 20px; color: var(--primary-color, #6366f1);"></i>
                <span style="flex: 1; color: var(--text-primary, #333);">${cmd.label}</span>
                ${cmd.shortcut ? `<span style="font-size: 12px; color: var(--text-muted, #999);">${cmd.shortcut}</span>` : ''}
            </div>
        `).join('');
    }

    filterCommands(query) {
        const items = document.querySelectorAll('.command-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query.toLowerCase()) ? 'flex' : 'none';
        });
    }

    executeCommand(action) {
        const actions = {
            'new-task': () => window.location.href = '/tasks/new',
            'new-project': () => window.location.href = '/project/new',
            'analytics': () => window.location.href = '/analytics',
            'users': () => window.location.href = '/user',
            'settings': () => window.location.href = '/settings',
            'theme': () => document.querySelector('.theme-toggle-btn')?.click()
        };
        
        if (actions[action]) {
            actions[action]();
            this.closeCommandPalette();
        }
    }

    closeCommandPalette() {
        const palette = document.getElementById('command-palette');
        if (palette) {
            palette.style.animation = 'fadeOut 0.2s ease';
            setTimeout(() => {
                palette.style.display = 'none';
            }, 200);
        }
    }

    /**
     * Интерактивные карточки данных
     */
    initDataCards() {
        document.querySelectorAll('[data-interactive-card]').forEach(card => {
            // Эффект наклона при наведении (tilt effect)
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            });
        });
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.advancedWidgets = new AdvancedWidgets();
    });
} else {
    window.advancedWidgets = new AdvancedWidgets();
}

// CSS стили для виджетов
const widgetStyles = document.createElement('style');
widgetStyles.textContent = `
    .activity-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        border-radius: 8px;
        transition: background 0.2s;
    }
    
    .activity-item:hover {
        background: var(--bg-secondary, #f5f5f5);
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .activity-content {
        flex: 1;
        min-width: 0;
    }
    
    .activity-text {
        font-size: 14px;
        color: var(--text-primary, #333);
        margin-bottom: 4px;
    }
    
    .activity-target {
        color: var(--primary-color, #6366f1);
        font-weight: 500;
    }
    
    .activity-time {
        font-size: 12px;
        color: var(--text-muted, #999);
    }
    
    .smart-notification {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: white;
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 4px solid var(--primary-color, #6366f1);
        min-width: 300px;
    }
    
    .smart-notification.success { border-left-color: #10b981; }
    .smart-notification.error { border-left-color: #ef4444; }
    .smart-notification.warning { border-left-color: #f59e0b; }
    
    .notification-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color, #6366f1);
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-message {
        font-size: 14px;
        color: #333;
        margin-bottom: 4px;
    }
    
    .notification-time {
        font-size: 12px;
        color: #999;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
    }
    
    .notification-close:hover {
        color: #333;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes slideDown {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes slide-in-right {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slide-out-right {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-color, #6366f1), var(--primary-hover, #4f46e5));
        border-radius: inherit;
        transition: width 1s cubic-bezier(0.65, 0, 0.35, 1);
    }
    
    .progress-ring {
        transition: stroke-dashoffset 1s cubic-bezier(0.65, 0, 0.35, 1);
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
`;
document.head.appendChild(widgetStyles);
