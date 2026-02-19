import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        debounceDelay: { type: Number, default: 300 },
        throttleDelay: { type: Number, default: 100 }
    };

    connect() {
        this.setupPerformanceOptimizations();
        this.monitorPerformance();
    }

    setupPerformanceOptimizations() {
        // Оптимизация скролла
        this.optimizeScroll();
        
        // Оптимизация ресайза
        this.optimizeResize();
        
        // Предзагрузка ссылок при наведении
        this.setupLinkPrefetch();
        
        // Оптимизация форм
        this.optimizeForms();
    }

    optimizeScroll() {
        let ticking = false;
        
        const handleScroll = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.dispatch('scroll', { 
                        detail: { 
                            scrollY: window.scrollY,
                            scrollX: window.scrollX 
                        } 
                    });
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
    }

    optimizeResize() {
        let resizeTimer;
        
        const handleResize = () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.dispatch('resize', {
                    detail: {
                        width: window.innerWidth,
                        height: window.innerHeight
                    }
                });
            }, this.debounceDelayValue);
        };

        window.addEventListener('resize', handleResize, { passive: true });
    }

    setupLinkPrefetch() {
        const links = document.querySelectorAll('a[href^="/"]');
        
        links.forEach(link => {
            link.addEventListener('mouseenter', this.prefetchLink.bind(this), { once: true });
            link.addEventListener('touchstart', this.prefetchLink.bind(this), { once: true });
        });
    }

    prefetchLink(event) {
        const link = event.currentTarget;
        const href = link.getAttribute('href');
        
        if (href && !link.dataset.prefetched) {
            const prefetchLink = document.createElement('link');
            prefetchLink.rel = 'prefetch';
            prefetchLink.href = href;
            document.head.appendChild(prefetchLink);
            
            link.dataset.prefetched = 'true';
        }
    }

    optimizeForms() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            // Дебаунс для полей ввода
            const inputs = form.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', this.debounce((event) => {
                    this.dispatch('input', { 
                        detail: { 
                            input: event.target,
                            value: event.target.value 
                        } 
                    });
                }, this.debounceDelayValue));
            });
        });
    }

    monitorPerformance() {
        // Мониторинг Web Vitals
        if ('web-vital' in window) {
            this.measureWebVitals();
        }
        
        // Мониторинг памяти
        if ('memory' in performance) {
            this.monitorMemory();
        }
        
        // Мониторинг сетевых запросов
        this.monitorNetworkRequests();
    }

    measureWebVitals() {
        // Largest Contentful Paint
        new PerformanceObserver((list) => {
            const entries = list.getEntries();
            const lastEntry = entries[entries.length - 1];
            console.log('LCP:', lastEntry.startTime);
        }).observe({ entryTypes: ['largest-contentful-paint'] });

        // First Input Delay
        new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                console.log('FID:', entry.processingStart - entry.startTime);
            });
        }).observe({ entryTypes: ['first-input'] });

        // Cumulative Layout Shift
        new PerformanceObserver((list) => {
            let clsValue = 0;
            const entries = list.getEntries();
            entries.forEach(entry => {
                if (!entry.hadRecentInput) {
                    clsValue += entry.value;
                }
            });
            console.log('CLS:', clsValue);
        }).observe({ entryTypes: ['layout-shift'] });
    }

    monitorMemory() {
        setInterval(() => {
            const memory = performance.memory;
            if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.9) {
                console.warn('Высокое использование памяти:', {
                    used: Math.round(memory.usedJSHeapSize / 1048576) + 'MB',
                    limit: Math.round(memory.jsHeapSizeLimit / 1048576) + 'MB'
                });
            }
        }, 30000); // Каждые 30 секунд
    }

    monitorNetworkRequests() {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                if (entry.duration > 1000) { // Медленные запросы > 1с
                    console.warn('Медленный запрос:', {
                        url: entry.name,
                        duration: Math.round(entry.duration) + 'ms'
                    });
                }
            });
        });
        
        observer.observe({ entryTypes: ['navigation', 'resource'] });
    }

    // Утилиты
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Методы для внешнего использования
    measurePageLoad() {
        window.addEventListener('load', () => {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                console.log('Page Load Metrics:', {
                    'DNS Lookup': perfData.domainLookupEnd - perfData.domainLookupStart,
                    'TCP Connection': perfData.connectEnd - perfData.connectStart,
                    'Request': perfData.responseStart - perfData.requestStart,
                    'Response': perfData.responseEnd - perfData.responseStart,
                    'DOM Processing': perfData.domComplete - perfData.domLoading,
                    'Total Load Time': perfData.loadEventEnd - perfData.navigationStart
                });
            }, 0);
        });
    }

    clearCache() {
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
        }
    }
}
