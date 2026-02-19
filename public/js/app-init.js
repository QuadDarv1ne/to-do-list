/**
 * Application Initializer
 * Централизованная инициализация всех модулей
 */

(function() {
    'use strict';

    /**
     * Менеджер инициализации модулей
     */
    class AppInitializer {
        constructor() {
            this.modules = new Map();
            this.initialized = false;
        }

        /**
         * Регистрация модуля для инициализации
         */
        register(name, initFn, options = {}) {
            this.modules.set(name, {
                init: initFn,
                priority: options.priority || 10,
                condition: options.condition || (() => true),
                initialized: false
            });
        }

        /**
         * Инициализация всех модулей
         */
        async init() {
            if (this.initialized) return;

            // Сортировка по приоритету (меньше = раньше)
            const sortedModules = Array.from(this.modules.entries())
                .sort((a, b) => a[1].priority - b[1].priority);

            for (const [name, module] of sortedModules) {
                if (module.condition() && !module.initialized) {
                    try {
                        await module.init();
                        module.initialized = true;
                    } catch (error) {
                        console.error(`Failed to initialize module: ${name}`, error);
                    }
                }
            }

            this.initialized = true;
        }

        /**
         * Проверка, инициализирован ли модуль
         */
        isInitialized(name) {
            const module = this.modules.get(name);
            return module ? module.initialized : false;
        }
    }

    // Создаем глобальный экземпляр
    window.appInit = new AppInitializer();

    // Автоматическая инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.appInit.init();
        });
    } else {
        window.appInit.init();
    }

})();
