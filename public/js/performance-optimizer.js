/**
 * Performance Optimizer
 * Optimize page load and runtime performance
 */

class PerformanceOptimizer {
    constructor() {
        this.observers = [];
        this.lazyImages = [];
        this.deferredScripts = [];
        this.init();
    }

    init() {
        this.initLazyLoading();
        this.initIntersectionObserver();
        this.initResourceHints();
        this.initServiceWorker();
        this.monitorPerformance();
    }

    /**
     * Lazy loading for images
     */
    initLazyLoading() {
        this.lazyImages = document.querySelectorAll('img[data-src], img[loading="lazy"]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px'
            });

            this.lazyImages.forEach(img => imageObserver.observe(img));
            this.observers.push(imageObserver);
        } else {
            // Fallback for browsers without IntersectionObserver
            this.lazyImages.forEach(img => {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
            });
        }
    }

    /**
     * Intersection Observer for animations
     */
    initIntersectionObserver() {
        const animatedElements = document.querySelectorAll('[data-animate]');
        
        if ('IntersectionObserver' in window && animatedElements.length > 0) {
            const animationObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const animation = element.dataset.animate;
                        
                        element.classList.add('animated', animation);
                        animationObserver.unobserve(element);
                    }
                });
            }, {
                threshold: 0.1
            });

            animatedElements.forEach(el => animationObserver.observe(el));
            this.observers.push(animationObserver);
        }
    }

    /**
     * Resource hints
     */
    initResourceHints() {
        // Preconnect to external domains
        const domains = [
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com'
        ];

        domains.forEach(domain => {
            if (!document.querySelector(`link[href="${domain}"]`)) {
                const link = document.createElement('link');
                link.rel = 'preconnect';
                link.href = domain;
                document.head.appendChild(link);
            }
        });
    }

    /**
     * Service Worker
     */
    initServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration.scope);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    this.showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            });
        }
    }

    /**
     * Show update notification
     */
    showUpdateNotification() {
        if (typeof window.showToast === 'function') {
            window.showToast('Доступно обновление приложения', 'info');
        }
    }

    /**
     * Monitor performance
     */
    monitorPerformance() {
        if ('PerformanceObserver' in window) {
            // Monitor long tasks
            try {
                const longTaskObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.duration > 50) {
                            console.warn('Long task detected:', entry.duration, 'ms');
                        }
                    }
                });
                
                longTaskObserver.observe({ entryTypes: ['longtask'] });
                this.observers.push(longTaskObserver);
            } catch (e) {
                // longtask not supported
            }

            // Monitor layout shifts
            try {
                const clsObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput && entry.value > 0.1) {
                            console.warn('Layout shift detected:', entry.value);
                        }
                    }
                });
                
                clsObserver.observe({ entryTypes: ['layout-shift'] });
                this.observers.push(clsObserver);
            } catch (e) {
                // layout-shift not supported
            }
        }

        // Log performance metrics on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.logPerformanceMetrics();
            }, 0);
        });
    }

    /**
     * Log performance metrics
     */
    logPerformanceMetrics() {
        if ('performance' in window) {
            const perfData = window.performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            const connectTime = perfData.responseEnd - perfData.requestStart;
            const renderTime = perfData.domComplete - perfData.domLoading;

            console.log('Performance Metrics:', {
                'Page Load Time': pageLoadTime + 'ms',
                'Connect Time': connectTime + 'ms',
                'Render Time': renderTime + 'ms'
            });

            // Send to analytics if needed
            this.sendPerformanceMetrics({
                pageLoadTime,
                connectTime,
                renderTime
            });
        }
    }

    /**
     * Send performance metrics
     */
    sendPerformanceMetrics(metrics) {
        // Send to backend analytics endpoint
        if (navigator.sendBeacon) {
            const data = JSON.stringify({
                url: window.location.href,
                metrics: metrics,
                timestamp: Date.now()
            });
            
            navigator.sendBeacon('/api/analytics/performance', data);
        }
    }

    /**
     * Prefetch links
     */
    prefetchLinks() {
        const links = document.querySelectorAll('a[data-prefetch]');
        
        if ('IntersectionObserver' in window) {
            const prefetchObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const link = entry.target;
                        const href = link.href;
                        
                        if (href && !link.dataset.prefetched) {
                            this.prefetchResource(href);
                            link.dataset.prefetched = 'true';
                        }
                        
                        prefetchObserver.unobserve(link);
                    }
                });
            });

            links.forEach(link => prefetchObserver.observe(link));
            this.observers.push(prefetchObserver);
        }
    }

    /**
     * Prefetch resource
     */
    prefetchResource(url) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }

    /**
     * Debounce function
     */
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

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Request idle callback polyfill
     */
    requestIdleCallback(callback) {
        if ('requestIdleCallback' in window) {
            return window.requestIdleCallback(callback);
        } else {
            return setTimeout(callback, 1);
        }
    }

    /**
     * Cleanup
     */
    cleanup() {
        this.observers.forEach(observer => {
            if (observer && observer.disconnect) {
                observer.disconnect();
            }
        });
        this.observers = [];
    }
}

// Initialize performance optimizer
let performanceOptimizer;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        performanceOptimizer = new PerformanceOptimizer();
        window.performanceOptimizer = performanceOptimizer;
    });
} else {
    performanceOptimizer = new PerformanceOptimizer();
    window.performanceOptimizer = performanceOptimizer;
}

// Export utilities
window.debounce = (func, wait) => performanceOptimizer.debounce(func, wait);
window.throttle = (func, limit) => performanceOptimizer.throttle(func, limit);

// Export
window.PerformanceOptimizer = PerformanceOptimizer;
