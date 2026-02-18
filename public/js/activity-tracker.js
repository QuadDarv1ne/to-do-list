/**
 * Activity Tracker
 * Отслеживание активности пользователя
 */

class ActivityTracker {
    constructor() {
        this.activities = [];
        this.maxActivities = 100;
        this.sessionStart = Date.now();
        this.lastActivity = Date.now();
        this.isActive = true;
        this.idleTimeout = 300000; // 5 минут
        this.init();
    }

    init() {
        this.loadActivities();
        this.trackPageView();
        this.setupEventListeners();
        this.startIdleDetection();
        this.setupBeforeUnload();
    }

    setupEventListeners() {
        // Отслеживание кликов
        document.addEventListener('click', (e) => {
            this.trackEvent('click', {
                element: e.target.tagName,
                class: e.target.className,
                id: e.target.id
            });
            this.updateActivity();
        });

        // Отслеживание навигации
        window.addEventListener('popstate', () => {
            this.trackPageView();
        });

        // Отслеживание скроллинга
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.trackEvent('scroll', {
                    position: window.scrollY,
                    percentage: (window.scrollY / (document.body.scrollHeight - window.innerHeight) * 100).toFixed(2)
                });
            }, 1000);
            this.updateActivity();
        });

        // Отслеживание отправки форм
        document.addEventListener('submit', (e) => {
            this.trackEvent('form_submit', {
                form: e.target.id || e.target.className
            });
        });

        // Отслеживание ошибок
        window.addEventListener('error', (e) => {
            this.trackEvent('error', {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno
            });
        });
    }

    trackPageView() {
        this.trackEvent('page_view', {
            url: window.location.pathname,
            title: document.title,
            referrer: document.referrer
        });
    }

    trackEvent(type, data = {}) {
        const activity = {
            type,
            data,
            timestamp: Date.now(),
            url: window.location.pathname,
            sessionId: this.getSessionId()
        };

        this.activities.push(activity);

        // Ограничиваем размер массива
        if (this.activities.length > this.maxActivities) {
            this.activities.shift();
        }

        this.saveActivities();
        this.sendToServer(activity);
    }

    updateActivity() {
        this.lastActivity = Date.now();
        if (!this.isActive) {
            this.isActive = true;
            this.trackEvent('user_active');
        }
    }

    startIdleDetection() {
        setInterval(() => {
            const idleTime = Date.now() - this.lastActivity;
            
            if (idleTime > this.idleTimeout && this.isActive) {
                this.isActive = false;
                this.trackEvent('user_idle', {
                    idleTime: idleTime
                });
            }
        }, 60000); // Проверяем каждую минуту
    }

    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            const sessionDuration = Date.now() - this.sessionStart;
            this.trackEvent('session_end', {
                duration: sessionDuration,
                activitiesCount: this.activities.length
            });
        });
    }

    async sendToServer(activity) {
        // Отправляем активность на сервер
        try {
            // Используем sendBeacon для надежной отправки
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(activity)], { type: 'application/json' });
                navigator.sendBeacon('/api/activity/track', blob);
            } else {
                // Fallback для старых браузеров
                fetch('/api/activity/track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(activity),
                    keepalive: true
                }).catch(error => {
                    console.error('Activity tracking error:', error);
                });
            }
        } catch (error) {
            console.error('Activity tracking error:', error);
        }
    }

    getSessionId() {
        let sessionId = sessionStorage.getItem('sessionId');
        if (!sessionId) {
            sessionId = this.generateSessionId();
            sessionStorage.setItem('sessionId', sessionId);
        }
        return sessionId;
    }

    generateSessionId() {
        return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    saveActivities() {
        try {
            localStorage.setItem('activities', JSON.stringify(this.activities));
        } catch (e) {
            console.error('Failed to save activities:', e);
        }
    }

    loadActivities() {
        try {
            const saved = localStorage.getItem('activities');
            if (saved) {
                this.activities = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load activities:', e);
            this.activities = [];
        }
    }

    getActivities(filter = {}) {
        let filtered = this.activities;

        if (filter.type) {
            filtered = filtered.filter(a => a.type === filter.type);
        }

        if (filter.startTime) {
            filtered = filtered.filter(a => a.timestamp >= filter.startTime);
        }

        if (filter.endTime) {
            filtered = filtered.filter(a => a.timestamp <= filter.endTime);
        }

        return filtered;
    }

    getStats() {
        const now = Date.now();
        const sessionDuration = now - this.sessionStart;
        const idleTime = now - this.lastActivity;

        return {
            sessionDuration,
            activitiesCount: this.activities.length,
            isActive: this.isActive,
            idleTime,
            sessionId: this.getSessionId(),
            activityTypes: this.getActivityTypeStats()
        };
    }

    getActivityTypeStats() {
        const stats = {};
        this.activities.forEach(activity => {
            stats[activity.type] = (stats[activity.type] || 0) + 1;
        });
        return stats;
    }

    clearActivities() {
        this.activities = [];
        this.saveActivities();
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.activityTracker = new ActivityTracker();
    
    // Добавляем команды в консоль
    window.getActivityStats = () => {
        const stats = window.activityTracker.getStats();
        console.log('Activity Statistics:', stats);
        return stats;
    };
    
    window.getActivities = (filter) => {
        const activities = window.activityTracker.getActivities(filter);
        console.log('Activities:', activities);
        return activities;
    };
});

// Экспорт
window.ActivityTracker = ActivityTracker;
