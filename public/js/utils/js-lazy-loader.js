/**
 * JS Lazy Loader - Ленивая загрузка JavaScript модулей
 * Загружает JS только когда он действительно нужен
 */

(function() {
    'use strict';
    
    class JSLazyLoader {
        constructor() {
            this.loaded = new Set();
            this.loading = new Map();
            
            // Модули по категориям
            this.modules = {
                // Загружаются при взаимодействии
                interaction: [
                    '/js/hotkeys.js',
                    '/js/keyboard-shortcuts.js',
                    '/js/quick-search.js'
                ],
                // Загружаются при скролле
                scroll: [
                    '/js/lazy-load.js',
                    '/js/image-optimizer.js'
                ],
                // Загружаются при редактировании форм
                forms: [
                    '/js/auto-save.js',
                    '/js/form-utilities.js'
                ],
                // Загружаются при drag&drop
                dragdrop: [
                    '/js/drag-drop-manager.js'
                ],
                // UI улучшения - загружаются через 2 секунды
                ui: [
                    '/js/ui-enhancements.js',
                    '/js/modern-interactions.js',
                    '/js/component-manager.js'
                ]
            };
            
            this.init();
        }
        
        init() {
            // Загружаем UI модули через 2 секунды
            setTimeout(() => this.loadCategory('ui'), 2000);
            
            // Загружаем interaction модули при первом взаимодействии
            this.setupInteractionLoader();
            
            // Загружаем scroll модули при первом скролле
            this.setupScrollLoader();
            
            // Загружаем form модули при фокусе на форме
            this.setupFormLoader();
            
            // Загружаем dragdrop при наведении на draggable элементы
            this.setupDragDropLoader();
        }
        
        setupInteractionLoader() {
            const events = ['click', 'keydown', 'touchstart'];
            const handler = () => {
                this.loadCategory('interaction');
                events.forEach(e => document.removeEventListener(e, handler, true));
            };
            
            events.forEach(event => {
                document.addEventListener(event, handler, { once: true, capture: true });
            });
        }
        
        setupScrollLoader() {
            const handler = () => {
                this.loadCategory('scroll');
                window.removeEventListener('scroll', handler);
            };
            
            window.addEventListener('scroll', handler, { once: true, passive: true });
        }
        
        setupFormLoader() {
            document.addEventListener('focusin', (e) => {
                if (e.target.matches('input, textarea, select')) {
                    this.loadCategory('forms');
                }
            }, { once: true });
        }
        
        setupDragDropLoader() {
            document.addEventListener('mouseover', (e) => {
                if (e.target.closest('[draggable="true"]')) {
                    this.loadCategory('dragdrop');
                }
            }, { once: true });
        }
        
        loadCategory(category) {
            const modules = this.modules[category];
            if (!modules) return;
            
            modules.forEach(src => this.loadScript(src));
        }
        
        loadScript(src) {
            // Уже загружен
            if (this.loaded.has(src)) {
                return Promise.resolve();
            }
            
            // Уже загружается
            if (this.loading.has(src)) {
                return this.loading.get(src);
            }
            
            const promise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.defer = true;
                
                script.onload = () => {
                    this.loaded.add(src);
                    this.loading.delete(src);
                    resolve();
                };
                
                script.onerror = () => {
                    this.loading.delete(src);
                    console.warn(`Failed to load: ${src}`);
                    reject(new Error(`Failed to load ${src}`));
                };
                
                document.head.appendChild(script);
            });
            
            this.loading.set(src, promise);
            return promise;
        }
        
        // Принудительная загрузка модуля
        load(src) {
            return this.loadScript(src);
        }
        
        // Принудительная загрузка категории
        loadAll(category) {
            return this.loadCategory(category);
        }
        
        getStats() {
            return {
                loaded: this.loaded.size,
                loading: this.loading.size,
                modules: Array.from(this.loaded)
            };
        }
    }
    
    // Инициализация
    window.JSLazyLoader = new JSLazyLoader();
    
    // API
    window.jsLoader = {
        load: (src) => window.JSLazyLoader.load(src),
        loadCategory: (cat) => window.JSLazyLoader.loadAll(cat),
        stats: () => window.JSLazyLoader.getStats()
    };
    
})();
