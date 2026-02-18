/**
 * Performance Optimizations
 * Оптимизация загрузки JavaScript - Code Splitting & Lazy Loading
 */

(function() {
    'use strict';

    /**
     * Менеджер загрузки скриптов
     */
    class ScriptLoader {
        constructor() {
            this.loadedScripts = new Set();
            this.loadingScripts = new Map();
            this.scriptQueue = [];
            this.isProcessing = false;
        }

        /**
         * Загрузка скрипта с приоритетом
         * @param {string} src - Путь к скрипту
         * @param {string} priority - 'critical' | 'high' | 'normal' | 'low'
         * @param {boolean} defer - Отложить выполнение
         */
        load(src, priority = 'normal', defer = false) {
            // Уже загружен
            if (this.loadedScripts.has(src)) {
                return Promise.resolve();
            }

            // Уже загружается
            if (this.loadingScripts.has(src)) {
                return this.loadingScripts.get(src);
            }

            const promise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.async = !defer;
                script.defer = defer;

                script.onload = () => {
                    this.loadedScripts.add(src);
                    this.loadingScripts.delete(src);
                    resolve();
                };

                script.onerror = () => {
                    console.warn(`Failed to load script: ${src}`);
                    this.loadingScripts.delete(src);
                    reject(new Error(`Failed to load ${src}`));
                };

                document.head.appendChild(script);
            });

            this.loadingScripts.set(src, promise);
            return promise;
        }

        /**
         * Предзагрузка скрипта (без выполнения)
         */
        preload(src) {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'script';
            link.href = src;
            document.head.appendChild(link);
        }

        /**
         * Загрузка группы скриптов
         */
        async loadBatch(scripts, priority = 'normal') {
            const promises = scripts.map(src => this.load(src, priority));
            return Promise.all(promises);
        }

        /**
         * Отложенная загрузка
         */
        scheduleLoad(src, delay = 1000) {
            setTimeout(() => this.load(src), delay);
        }

        /**
         * Загрузка по расписанию
         */
        scheduleBatch(scripts, delay = 1000) {
            setTimeout(() => this.loadBatch(scripts), delay);
        }

        /**
         * Статистика
         */
        getStats() {
            return {
                loaded: this.loadedScripts.size,
                loading: this.loadingScripts.size,
                scripts: Array.from(this.loadedScripts)
            };
        }
    }

    // Глобальный экземпляр
    window.scriptLoader = new ScriptLoader();

    /**
     * Lazy Loading для компонентов
     */
    class ComponentLazyLoader {
        constructor() {
            this.observers = new Map();
            this.init();
        }

        init() {
            if (!('IntersectionObserver' in window)) {
                // Fallback - загрузить всё сразу
                this.loadAll();
                return;
            }

            // Наблюдаем за lazy-компонентами
            this.observeLazyComponents();
        }

        observeLazyComponents() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadComponent(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '100px',
                threshold: 0.01
            });

            document.querySelectorAll('[data-lazy-component]').forEach(el => {
                observer.observe(el);
            });
        }

        loadComponent(element) {
            const componentName = element.dataset.lazyComponent;
            const componentMap = {
                'chart': '/js/charts.js',
                'calendar': '/js/calendar-enhanced.js',
                'kanban': '/js/kanban-enhanced.js',
                'dashboard': '/js/dashboard-enhanced.js',
                'notifications': '/js/realtime-notifications.js',
                'search': '/js/quick-search.js',
                'table': '/js/table-enhancements.js'
            };

            const scriptPath = componentMap[componentName];
            if (scriptPath) {
                window.scriptLoader.load(scriptPath, 'high');
            }
        }

        loadAll() {
            // Загрузить все компоненты
            document.querySelectorAll('[data-lazy-component]').forEach(el => {
                this.loadComponent(el);
            });
        }
    }

    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.componentLoader = new ComponentLazyLoader();
        });
    } else {
        window.componentLoader = new ComponentLazyLoader();
    }

    /**
     * Оптимизация событий
     */
    class EventOptimizer {
        constructor() {
            this.debounceTimers = new Map();
            this.throttleTimers = new Map();
        }

        /**
         * Debounce функция
         */
        debounce(func, wait, id = 'default') {
            return (...args) => {
                clearTimeout(this.debounceTimers.get(id));
                this.debounceTimers.set(id, setTimeout(() => func.apply(this, args), wait));
            };
        }

        /**
         * Throttle функция
         */
        throttle(func, limit, id = 'default') {
            return (...args) => {
                if (!this.throttleTimers.has(id)) {
                    func.apply(this, args);
                    this.throttleTimers.set(id, setTimeout(() => {
                        this.throttleTimers.delete(id);
                    }, limit));
                }
            };
        }

        /**
         * Passive event listener helper
         */
        addPassiveListener(element, event, handler, options = {}) {
            element.addEventListener(event, handler, {
                passive: true,
                capture: false,
                ...options
            });
        }
    }

    window.eventOptimizer = new EventOptimizer();

    /**
     * Request Idle Callback polyfill
     */
    window.requestIdleCallback = window.requestIdleCallback || function(cb) {
        const start = Date.now();
        return setTimeout(function() {
            cb({
                didTimeout: false,
                timeRemaining: function() {
                    return Math.max(0, 50 - (Date.now() - start));
                }
            });
        }, 1);
    };

    window.cancelIdleCallback = window.cancelIdleCallback || function(id) {
        clearTimeout(id);
    };

    /**
     * Загрузка не критичных функций
     */
    function loadNonCriticalFeatures() {
        // Аналитика и мониторинг
        if (window.location.pathname.includes('/analytics') || 
            window.location.pathname.includes('/dashboard')) {
            window.scriptLoader.load('/js/dashboard-widgets.js', 'high');
        }

        // Календарь
        if (window.location.pathname.includes('/calendar')) {
            window.scriptLoader.load('/js/calendar-enhanced.js', 'high');
        }

        // Канбан
        if (window.location.pathname.includes('/kanban')) {
            window.scriptLoader.load('/js/kanban-enhanced.js', 'high');
        }

        // Таблицы
        if (window.location.pathname.includes('/task')) {
            window.scriptLoader.load('/js/table-enhancements.js', 'high');
            window.scriptLoader.load('/js/task-inline-edit.js', 'high');
        }

        // Поиск
        window.scriptLoader.load('/js/quick-search.js', 'normal');

        // Уведомления (только для авторизованных)
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            window.scriptLoader.load('/js/realtime-notifications.js', 'normal');
        }

        // Глобальные улучшения UI
        window.scriptLoader.scheduleLoad('/js/global-interactions.js', 500);
        window.scriptLoader.scheduleLoad('/js/ui-enhancements.js', 1000);

        // Оффлайн поддержка
        window.scriptLoader.scheduleLoad('/js/offline-support.js', 2000);

        // Мониторинг производительности (только dev)
        const isDev = document.body.dataset.environment === 'dev';
        if (isDev) {
            window.scriptLoader.scheduleLoad('/js/performance-monitor.js', 3000);
        }
    }

    /**
     * Оптимизация скролла
     */
    function optimizeScroll() {
        // Passive listeners для скролла
        const scrollElements = document.querySelectorAll('.overflow-auto, .overflow-scroll');
        scrollElements.forEach(el => {
            window.eventOptimizer.addPassiveListener(el, 'scroll', function(e) {
                // Обработка скролла
            });
        });
    }

    /**
     * Оптимизация resize
     */
    const handleResize = window.eventOptimizer.debounce(function() {
        // Пересчет layout
        document.documentElement.style.setProperty('--vh', window.innerHeight + 'px');
    }, 250, 'resize');

    window.addEventListener('resize', handleResize, { passive: true });

    /**
     * Инициализация
     */
    function init() {
        // Загрузка не критичных функций
        if ('requestIdleCallback' in window) {
            requestIdleCallback(loadNonCriticalFeatures, { timeout: 2000 });
        } else {
            setTimeout(loadNonCriticalFeatures, 1000);
        }

        // Оптимизация скролла
        optimizeScroll();

        // Preload важных страниц при наведении
        document.addEventListener('mouseover', function(e) {
            const link = e.target.closest('a[href]');
            if (link && link.href && link.href.startsWith(window.location.origin)) {
                const path = new URL(link.href).pathname;
                
                // Preload JS для следующей страницы
                if (path.includes('/calendar')) {
                    window.scriptLoader.preload('/js/calendar-enhanced.js');
                } else if (path.includes('/kanban')) {
                    window.scriptLoader.preload('/js/kanban-enhanced.js');
                } else if (path.includes('/analytics')) {
                    window.scriptLoader.preload('/js/dashboard-widgets.js');
                }
            }
        }, { passive: true });

        // Cleanup при unload
        window.addEventListener('beforeunload', function() {
            // Очистка таймеров
            window.eventOptimizer.debounceTimers.forEach(timer => clearTimeout(timer));
            window.eventOptimizer.throttleTimers.forEach(timer => clearTimeout(timer));
        });
    }

    // Запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /**
     * Экспорт API
     */
    window.performanceOptimizer = {
        loadScript: (src, priority) => window.scriptLoader.load(src, priority),
        preloadScript: (src) => window.scriptLoader.preload(src),
        debounce: (fn, wait) => window.eventOptimizer.debounce(fn, wait),
        throttle: (fn, limit) => window.eventOptimizer.throttle(fn, limit),
        getStats: () => ({
            scripts: window.scriptLoader.getStats(),
            memory: performance.memory ? {
                usedJSHeapSize: performance.memory.usedJSHeapSize,
                totalJSHeapSize: performance.memory.totalJSHeapSize
            } : null
        })
    };

})();
