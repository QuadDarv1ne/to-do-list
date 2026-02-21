/**
 * DOM Helper Utilities
 * Централизованные функции для работы с DOM
 */

export const DOMHelpers = {
    /**
     * Безопасный querySelector с проверкой
     */
    qs(selector, parent = document) {
        return parent.querySelector(selector);
    },

    /**
     * Безопасный querySelectorAll с проверкой
     */
    qsa(selector, parent = document) {
        return Array.from(parent.querySelectorAll(selector));
    },

    /**
     * Добавить обработчик события с автоматической очисткой
     */
    on(element, event, handler, options = {}) {
        if (!element) return null;
        element.addEventListener(event, handler, options);
        return () => element.removeEventListener(event, handler, options);
    },

    /**
     * Делегирование событий
     */
    delegate(parent, selector, event, handler) {
        return this.on(parent, event, (e) => {
            const target = e.target.closest(selector);
            if (target) {
                handler.call(target, e);
            }
        });
    },

    /**
     * Debounce функция
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle функция
     */
    throttle(func, limit = 300) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Проверка видимости элемента
     */
    isVisible(element) {
        return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    },

    /**
     * Плавная прокрутка к элементу
     */
    scrollTo(element, options = {}) {
        if (!element) return;
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
            ...options
        });
    },

    /**
     * Создание элемента из HTML строки
     */
    createElement(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        return template.content.firstChild;
    },

    /**
     * Проверка готовности DOM
     */
    ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    },

    /**
     * Получить данные из data-атрибутов
     */
    getData(element, key) {
        if (!element) return null;
        return element.dataset[key];
    },

    /**
     * Установить данные в data-атрибуты
     */
    setData(element, key, value) {
        if (!element) return;
        element.dataset[key] = value;
    }
};

// Глобальный доступ
window.DOMHelpers = DOMHelpers;
