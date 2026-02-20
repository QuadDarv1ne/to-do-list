/**
 * Notification System - Улучшенная система уведомлений
 * С анимациями, звуками и автоматическим закрытием
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.notifications = [];
        this.maxNotifications = 5;
        this.defaultDuration = 5000;
        this.init();
    }

    init() {
        // Создаем контейнер для уведомлений
        this.container = document.createElement('div');
        this.container.className = 'notification-container';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = this.defaultDuration, options = {}) {
        // Ограничиваем количество уведомлений
        if (this.notifications.length >= this.maxNotifications) {
            this.remove(this.notifications[0]);
        }

        const notification = this.create(message, type, options);
        this.container.appendChild(notification);
        this.notifications.push(notification);

        // Анимация появления
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Автоматическое закрытие
        if (duration > 0) {
            const timeoutId = setTimeout(() => {
                this.remove(notification);
            }, duration);
            
            // Сохраняем ID таймера для паузы при hover
            notification.dataset.timeoutId = timeoutId;
        }

        // Воспроизводим звук (опционально)
        if (options.sound !== false) {
            this.playSound(type);
        }

        return notification;
    }

    create(message, type, options = {}) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.setAttribute('role', 'alert');

        const icon = options.icon || this.getIcon(type);
        const closeBtn = document.createElement('button');
        closeBtn.className = 'notification-close';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.setAttribute('aria-label', 'Закрыть уведомление');
        closeBtn.onclick = () => this.remove(notification);

        // Основной контент
        let contentHTML = `
            <div class="notification-icon">
                <i class="${icon}"></i>
            </div>
            <div class="notification-content">
                ${options.title ? `<div class="notification-title">${options.title}</div>` : ''}
                <div class="notification-message">${message}</div>
            </div>
        `;

        notification.innerHTML = contentHTML;
        notification.appendChild(closeBtn);

        // Кнопки действий
        if (options.actions && options.actions.length > 0) {
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'notification-actions';
            
            options.actions.forEach(action => {
                const btn = document.createElement('button');
                btn.className = `notification-action-btn ${action.primary ? 'primary' : ''}`;
                btn.textContent = action.label;
                btn.onclick = () => {
                    if (action.onClick) action.onClick();
                    if (action.closeOnClick !== false) this.remove(notification);
                };
                actionsContainer.appendChild(btn);
            });
            
            notification.appendChild(actionsContainer);
        }

        // Прогресс-бар
        const progress = document.createElement('div');
        progress.className = 'notification-progress';
        notification.appendChild(progress);

        // Пауза анимации при hover
        notification.addEventListener('mouseenter', () => {
            progress.style.animationPlayState = 'paused';
            if (notification.dataset.timeoutId) {
                clearTimeout(parseInt(notification.dataset.timeoutId));
            }
        });

        notification.addEventListener('mouseleave', () => {
            progress.style.animationPlayState = 'running';
        });

        return notification;
    }

    remove(notification) {
        if (!notification || !notification.parentNode) return;

        notification.classList.add('hide');
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
            const index = this.notifications.indexOf(notification);
            if (index > -1) {
                this.notifications.splice(index, 1);
            }
        }, 300);
    }

    getIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    playSound(type) {
        // Проверяем, разрешены ли звуки
        const soundEnabled = localStorage.getItem('notificationSound') !== 'false';
        if (!soundEnabled) return;

        // Создаем аудио контекст для воспроизведения звука
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Настройки звука в зависимости от типа
            const soundConfig = {
                success: { frequency: 800, duration: 0.1 },
                error: { frequency: 400, duration: 0.2 },
                warning: { frequency: 600, duration: 0.15 },
                info: { frequency: 700, duration: 0.1 }
            };

            const config = soundConfig[type] || soundConfig.info;
            
            oscillator.frequency.value = config.frequency;
            gainNode.gain.value = 0.1;
            
            oscillator.start();
            oscillator.stop(audioContext.currentTime + config.duration);
        } catch (e) {
            // Звук не поддерживается или отключен
            console.debug('Sound notification not available');
        }
    }

    // Удобные методы для разных типов
    success(message, duration, options) {
        return this.show(message, 'success', duration, options);
    }

    error(message, duration, options) {
        return this.show(message, 'error', duration, options);
    }

    warning(message, duration, options) {
        return this.show(message, 'warning', duration, options);
    }

    info(message, duration, options) {
        return this.show(message, 'info', duration, options);
    }

    // Специальные типы уведомлений
    confirm(message, onConfirm, onCancel) {
        return this.show(message, 'warning', 0, {
            title: 'Подтверждение',
            icon: 'fas fa-question-circle',
            actions: [
                {
                    label: 'Подтвердить',
                    primary: true,
                    onClick: onConfirm
                },
                {
                    label: 'Отмена',
                    onClick: onCancel
                }
            ]
        });
    }

    loading(message) {
        return this.show(message, 'info', 0, {
            icon: 'fas fa-spinner fa-spin',
            sound: false
        });
    }

    promise(promise, messages = {}) {
        const loadingNotification = this.loading(messages.loading || 'Загрузка...');
        
        return promise
            .then(result => {
                this.remove(loadingNotification);
                this.success(messages.success || 'Успешно выполнено!');
                return result;
            })
            .catch(error => {
                this.remove(loadingNotification);
                this.error(messages.error || 'Произошла ошибка');
                throw error;
            });
    }

    // Очистить все уведомления
    clear() {
        this.notifications.forEach(notification => {
            this.remove(notification);
        });
    }
}

// Инициализация глобального экземпляра
if (typeof window !== 'undefined') {
    window.notificationSystem = new NotificationSystem();
    
    // Удобные глобальные функции
    window.notify = {
        success: (msg, duration, options) => window.notificationSystem.success(msg, duration, options),
        error: (msg, duration, options) => window.notificationSystem.error(msg, duration, options),
        warning: (msg, duration, options) => window.notificationSystem.warning(msg, duration, options),
        info: (msg, duration, options) => window.notificationSystem.info(msg, duration, options),
        confirm: (msg, onConfirm, onCancel) => window.notificationSystem.confirm(msg, onConfirm, onCancel),
        loading: (msg) => window.notificationSystem.loading(msg),
        promise: (promise, messages) => window.notificationSystem.promise(promise, messages)
    };
}

// Экспорт для модулей
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}
