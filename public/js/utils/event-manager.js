/**
 * Event Manager - управление слушателями событий для предотвращения утечек памяти
 */
(function() {
    'use strict';
    
    class EventManager {
        constructor() {
            this.listeners = new Map();
        }
        
        /**
         * Добавить слушатель события с автоматическим отслеживанием
         */
        addEventListener(element, event, handler, options = {}) {
            if (!element) return null;
            
            const id = `${event}_${Date.now()}_${Math.random()}`;
            
            // Обернуть handler для возможности удаления
            const wrappedHandler = (e) => handler(e);
            
            element.addEventListener(event, wrappedHandler, options);
            
            this.listeners.set(id, {
                element,
                event,
                handler: wrappedHandler,
                options
            });
            
            return id;
        }
        
        /**
         * Удалить конкретный слушатель
         */
        removeEventListener(id) {
            if (!this.listeners.has(id)) return false;
            
            const { element, event, handler, options } = this.listeners.get(id);
            element.removeEventListener(event, handler, options);
            this.listeners.delete(id);
            return true;
        }
        
        /**
         * Удалить все слушатели для элемента
         */
        removeAllForElement(element) {
            let removed = 0;
            
            this.listeners.forEach((listener, id) => {
                if (listener.element === element) {
                    this.removeEventListener(id);
                    removed++;
                }
            });
            
            return removed;
        }
        
        /**
         * Удалить все слушатели
         */
        removeAll() {
            this.listeners.forEach((listener, id) => {
                this.removeEventListener(id);
            });
        }
        
        /**
         * Делегирование событий (более эффективно для динамического контента)
         */
        delegate(parent, selector, event, handler) {
            const delegateHandler = (e) => {
                const target = e.target.closest(selector);
                if (target && parent.contains(target)) {
                    handler.call(target, e);
                }
            };
            
            return this.addEventListener(parent, event, delegateHandler);
        }
        
        /**
         * Одноразовый слушатель (автоматически удаляется после первого вызова)
         */
        once(element, event, handler) {
            const id = this.addEventListener(element, event, (e) => {
                handler(e);
                this.removeEventListener(id);
            });
            return id;
        }
        
        /**
         * Debounced слушатель
         */
        debounce(element, event, handler, delay = 300) {
            let timeoutId;
            
            return this.addEventListener(element, event, (e) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => handler(e), delay);
            });
        }
        
        /**
         * Throttled слушатель
         */
        throttle(element, event, handler, delay = 300) {
            let lastCall = 0;
            
            return this.addEventListener(element, event, (e) => {
                const now = Date.now();
                if (now - lastCall >= delay) {
                    lastCall = now;
                    handler(e);
                }
            });
        }
        
        /**
         * Получить статистику
         */
        getStats() {
            const byEvent = {};
            
            this.listeners.forEach(listener => {
                byEvent[listener.event] = (byEvent[listener.event] || 0) + 1;
            });
            
            return {
                total: this.listeners.size,
                byEvent
            };
        }
    }
    
    // Глобальный менеджер событий
    window.eventManager = new EventManager();
    
    // Очистка при выгрузке страницы
    window.addEventListener('beforeunload', () => {
        window.eventManager.removeAll();
    });
    
    // Мониторинг утечек памяти в dev режиме
    if (document.querySelector('meta[name="environment"]')?.content === 'dev') {
        setInterval(() => {
            const stats = window.eventManager.getStats();
            if (stats.total > 100) {
                console.warn('[EventManager] Возможная утечка памяти:', stats);
            }
        }, 30000); // Проверка каждые 30 секунд
    }
})();
