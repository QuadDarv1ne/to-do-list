/**
 * Timer Manager - управление таймерами для предотвращения утечек памяти
 */
(function() {
    'use strict';
    
    class TimerManager {
        constructor() {
            this.timers = new Map();
            this.intervals = new Map();
        }
        
        /**
         * Создать setTimeout с автоматической очисткой
         */
        setTimeout(callback, delay, id = null) {
            const timerId = id || `timeout_${Date.now()}_${Math.random()}`;
            
            // Очистить существующий таймер с таким ID
            if (this.timers.has(timerId)) {
                clearTimeout(this.timers.get(timerId));
            }
            
            const timer = setTimeout(() => {
                callback();
                this.timers.delete(timerId);
            }, delay);
            
            this.timers.set(timerId, timer);
            return timerId;
        }
        
        /**
         * Создать setInterval с автоматической очисткой
         */
        setInterval(callback, delay, id = null) {
            const intervalId = id || `interval_${Date.now()}_${Math.random()}`;
            
            // Очистить существующий интервал с таким ID
            if (this.intervals.has(intervalId)) {
                clearInterval(this.intervals.get(intervalId));
            }
            
            const interval = setInterval(callback, delay);
            this.intervals.set(intervalId, interval);
            return intervalId;
        }
        
        /**
         * Очистить конкретный таймер
         */
        clearTimeout(id) {
            if (this.timers.has(id)) {
                clearTimeout(this.timers.get(id));
                this.timers.delete(id);
                return true;
            }
            return false;
        }
        
        /**
         * Очистить конкретный интервал
         */
        clearInterval(id) {
            if (this.intervals.has(id)) {
                clearInterval(this.intervals.get(id));
                this.intervals.delete(id);
                return true;
            }
            return false;
        }
        
        /**
         * Очистить все таймеры
         */
        clearAllTimers() {
            this.timers.forEach(timer => clearTimeout(timer));
            this.timers.clear();
        }
        
        /**
         * Очистить все интервалы
         */
        clearAllIntervals() {
            this.intervals.forEach(interval => clearInterval(interval));
            this.intervals.clear();
        }
        
        /**
         * Очистить всё
         */
        clearAll() {
            this.clearAllTimers();
            this.clearAllIntervals();
        }
        
        /**
         * Получить количество активных таймеров
         */
        getStats() {
            return {
                timers: this.timers.size,
                intervals: this.intervals.size,
                total: this.timers.size + this.intervals.size
            };
        }
    }
    
    // Глобальный менеджер таймеров
    window.timerManager = new TimerManager();
    
    // Очистка при выгрузке страницы
    window.addEventListener('beforeunload', () => {
        window.timerManager.clearAll();
    });
    
    // Очистка при скрытии страницы (для SPA)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Можно приостановить некритичные интервалы
            const stats = window.timerManager.getStats();
            if (stats.total > 10) {
                console.warn(`[TimerManager] ${stats.total} активных таймеров при скрытии страницы`);
            }
        }
    });
})();
