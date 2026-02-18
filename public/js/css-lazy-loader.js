/**
 * CSS Lazy Loader - Интеллектуальная загрузка CSS
 * Загружает стили по приоритету и необходимости
 */

(function() {
    'use strict';
    
    class CSSLazyLoader {
        constructor() {
            this.loadedFiles = new Set();
            this.loadingFiles = new Map();
            
            // Приоритеты загрузки
            this.priorities = {
                critical: [
                    '/css/optimized-core.css',
                    '/css/utilities.css'
                ],
                high: [
                    '/css/components-optimized.css',
                    '/css/themes.css',
                    '/css/dark-modern-theme.css',
                    '/css/button-system.css',
                    '/css/form-system.css'
                ],
                medium: [
                    '/css/card-system.css',
                    '/css/layout-system.css',
                    '/css/navigation-system.css',
                    '/css/table-system.css',
                    '/css/typography-system.css',
                    '/css/modal-system.css',
                    '/css/dashboard-styles.css'
                ],
                low: [
                    '/css/animations.css',
                    '/css/animation-library.css',
                    '/css/micro-animations.css',
                    '/css/gradient-effects.css',
                    '/css/modern-interactions.css',
                    '/css/responsive-enhancements.css',
                    '/css/accessibility.css',
                    '/css/performance-optimizations.css',
                    '/css/footer-enhanced.css',
                    '/css/advanced-components.css',
                    '/css/theme-transitions.css',
                    '/css/mobile-table-adaptation.css'
                ],
                // Страничные стили - загружаются только при необходимости
                page: [
                    '/css/pages.css',
                    '/css/main.css'
                ]
            };
            
            this.init();
        }
        
        init() {
            // Загружаем критические стили немедленно
            this.loadPriority('critical');
            
            // Загружаем высокоприоритетные после DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.loadPriority('high');
                    this.scheduleRemainingLoads();
                });
            } else {
                this.loadPriority('high');
                this.scheduleRemainingLoads();
            }
        }
        
        // Планируем загрузку остальных стилей
        scheduleRemainingLoads() {
            // Средний приоритет - после небольшой задержки
            setTimeout(() => {
                this.loadPriority('medium');
            }, 100);
            
            // Низкий приоритет - когда браузер свободен
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => {
                    this.loadPriority('low');
                }, { timeout: 2000 });
            } else {
                setTimeout(() => {
                    this.loadPriority('low');
                }, 500);
            }
            
            // Страничные стили - только если нужны
            this.loadPageSpecificCSS();
        }
        
        // Загрузка по приоритету
        loadPriority(priority) {
            const files = this.priorities[priority];
            if (!files) return;
            
            files.forEach(file => {
                this.loadCSS(file, priority);
            });
        }
        
        // Загрузка одного CSS файла
        loadCSS(href, priority = 'medium') {
            // Проверяем, не загружен ли уже
            if (this.loadedFiles.has(href)) {
                return Promise.resolve();
            }
            
            // Проверяем, не загружается ли сейчас
            if (this.loadingFiles.has(href)) {
                return this.loadingFiles.get(href);
            }
            
            const promise = new Promise((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.media = priority === 'low' ? 'print' : 'all';
                
                link.onload = () => {
                    // Для низкоприоритетных меняем media на all
                    if (priority === 'low') {
                        link.media = 'all';
                    }
                    
                    this.loadedFiles.add(href);
                    this.loadingFiles.delete(href);
                    resolve();
                };
                
                link.onerror = () => {
                    console.warn(`Failed to load CSS: ${href}`);
                    this.loadingFiles.delete(href);
                    reject(new Error(`Failed to load ${href}`));
                };
                
                document.head.appendChild(link);
            });
            
            this.loadingFiles.set(href, promise);
            return promise;
        }
        
        // Загрузка страничных стилей по необходимости
        loadPageSpecificCSS() {
            const path = window.location.pathname;
            
            // Определяем, какие стили нужны для текущей страницы
            const pageMap = {
                '/dashboard': ['/css/pages.css', '/css/main.css'],
                '/calendar': ['/css/pages.css', '/css/main.css'],
                '/analytics': ['/css/pages.css', '/css/main.css'],
                '/profile': ['/css/pages.css'],
                '/task': ['/css/pages.css']
            };
            
            // Проверяем совпадения
            for (const [route, files] of Object.entries(pageMap)) {
                if (path.includes(route)) {
                    files.forEach(file => {
                        if ('requestIdleCallback' in window) {
                            requestIdleCallback(() => this.loadCSS(file, 'page'));
                        } else {
                            setTimeout(() => this.loadCSS(file, 'page'), 1000);
                        }
                    });
                    break;
                }
            }
        }
        
        // Предзагрузка для следующей страницы
        preloadForRoute(route) {
            const pageMap = {
                '/dashboard': ['/css/pages.css', '/css/main.css'],
                '/calendar': ['/css/pages.css', '/css/main.css'],
                '/profile': ['/css/pages.css']
            };
            
            const files = pageMap[route];
            if (files) {
                files.forEach(file => {
                    const link = document.createElement('link');
                    link.rel = 'preload';
                    link.as = 'style';
                    link.href = file;
                    document.head.appendChild(link);
                });
            }
        }
        
        // Удаление неиспользуемых стилей
        removeUnusedCSS() {
            const allLinks = document.querySelectorAll('link[rel="stylesheet"]');
            const usedSelectors = this.getUsedSelectors();
            
            allLinks.forEach(link => {
                if (!link.href || link.href.includes('bootstrap') || link.href.includes('font-awesome')) {
                    return; // Не трогаем внешние библиотеки
                }
                
                try {
                    const sheet = link.sheet;
                    if (!sheet) return;
                    
                    const rules = Array.from(sheet.cssRules || []);
                    let hasUsedRules = false;
                    
                    rules.forEach(rule => {
                        if (rule.selectorText) {
                            const selector = rule.selectorText.split(':')[0].trim();
                            if (usedSelectors.has(selector) || document.querySelector(selector)) {
                                hasUsedRules = true;
                            }
                        }
                    });
                    
                    // Если нет используемых правил, можно удалить
                    if (!hasUsedRules && rules.length > 0) {
                        console.log(`Potentially unused stylesheet: ${link.href}`);
                    }
                } catch (e) {
                    // Cross-origin или другая ошибка
                }
            });
        }
        
        // Получение используемых селекторов
        getUsedSelectors() {
            const selectors = new Set();
            const elements = document.querySelectorAll('*');
            
            elements.forEach(el => {
                // Классы
                if (el.classList.length > 0) {
                    el.classList.forEach(cls => selectors.add('.' + cls));
                }
                // ID
                if (el.id) {
                    selectors.add('#' + el.id);
                }
                // Теги
                selectors.add(el.tagName.toLowerCase());
            });
            
            return selectors;
        }
        
        // Статистика загрузки
        getStats() {
            return {
                loaded: this.loadedFiles.size,
                loading: this.loadingFiles.size,
                total: Object.values(this.priorities).flat().length,
                files: Array.from(this.loadedFiles)
            };
        }
        
        // Принудительная загрузка всех стилей
        loadAll() {
            Object.keys(this.priorities).forEach(priority => {
                this.loadPriority(priority);
            });
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.CSSLazyLoader = new CSSLazyLoader();
        });
    } else {
        window.CSSLazyLoader = new CSSLazyLoader();
    }
    
    // API для внешнего использования
    window.cssLoader = {
        load: (href, priority) => window.CSSLazyLoader && window.CSSLazyLoader.loadCSS(href, priority),
        preload: (route) => window.CSSLazyLoader && window.CSSLazyLoader.preloadForRoute(route),
        stats: () => window.CSSLazyLoader && window.CSSLazyLoader.getStats(),
        loadAll: () => window.CSSLazyLoader && window.CSSLazyLoader.loadAll()
    };
    
    // Предзагрузка при наведении на ссылки
    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a[href]');
        if (link && link.href && link.href.startsWith(window.location.origin)) {
            const path = new URL(link.href).pathname;
            if (window.CSSLazyLoader) {
                window.CSSLazyLoader.preloadForRoute(path);
            }
        }
    }, { passive: true });
    
})();
