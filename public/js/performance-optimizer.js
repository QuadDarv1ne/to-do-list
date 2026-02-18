/**
 * Performance Optimizer
 * Improves page load and runtime performance
 */

class PerformanceOptimizer {
    constructor() {
        this.observers = new Map();
        this.init();
    }

    init() {
        this.setupLazyLoading();
        this.setupImageOptimization();
        this.setupResourceHints();
        this.setupCriticalCSS();
        this.monitorPerformance();
    }

    /**
     * Lazy load images and iframes
     */
    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const lazyObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        
                        if (element.tagName === 'IMG') {
                            if (element.dataset.src) {
                                element.src = element.dataset.src;
                                element.removeAttribute('data-src');
                            }
                            if (element.dataset.srcset) {
                                element.srcset = element.dataset.srcset;
                                element.removeAttribute('data-srcset');
                            }
                        } else if (element.tagName === 'IFRAME') {
                            if (element.dataset.src) {
                                element.src = element.dataset.src;
                                element.removeAttribute('data-src');
                            }
                        }
                        
                        element.classList.add('loaded');
                        lazyObserver.unobserve(element);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            // Observe all lazy elements
            document.querySelectorAll('[data-src]').forEach(el => {
                lazyObserver.observe(el);
            });

            this.observers.set('lazy', lazyObserver);
        } else {
            // Fallback for browsers without IntersectionObserver
            this.loadAllLazyElements();
        }
    }

    /**
     * Load all lazy elements (fallback)
     */
    loadAllLazyElements() {
        document.querySelectorAll('[data-src]').forEach(el => {
            if (el.tagName === 'IMG') {
                if (el.dataset.src) el.src = el.dataset.src;
                if (el.dataset.srcset) el.srcset = el.dataset.srcset;
            } else if (el.tagName === 'IFRAME') {
                if (el.dataset.src) el.src = el.dataset.src;
            }
            el.classList.add('loaded');
        });
    }

    /**
     * Optimize images with modern formats
     */
    setupImageOptimization() {
        // Check WebP support
        const supportsWebP = this.checkWebPSupport();
        
        supportsWebP.then(supported => {
            if (supported) {
                document.documentElement.classList.add('webp');
            } else {
                document.documentElement.classList.add('no-webp');
            }
        });

        // Responsive images
        this.setupResponsiveImages();
    }

    /**
     * Check WebP support
     */
    checkWebPSupport() {
        return new Promise(resolve => {
            const webP = new Image();
            webP.onload = webP.onerror = () => {
                resolve(webP.height === 2);
            };
            webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        });
    }

    /**
     * Setup responsive images
     */
    setupResponsiveImages() {
        const images = document.querySelectorAll('img[data-sizes]');
        
        images.forEach(img => {
            const updateSrc = () => {
                const width = img.clientWidth;
                const sizes = JSON.parse(img.dataset.sizes);
                
                let selectedSrc = sizes.default;
                
                for (const [breakpoint, src] of Object.entries(sizes)) {
                    if (breakpoint !== 'default' && width <= parseInt(breakpoint)) {
                        selectedSrc = src;
                        break;
                    }
                }
                
                if (img.src !== selectedSrc) {
                    img.src = selectedSrc;
                }
            };
            
            updateSrc();
            window.addEventListener('resize', this.debounce(updateSrc, 250));
        });
    }

    /**
     * Add resource hints for better loading
     */
    setupResourceHints() {
        // Preconnect to external domains
        const domains = [
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com'
        ];

        domains.forEach(domain => {
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = domain;
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        });

        // Prefetch next page resources
        this.prefetchNextPage();
    }

    /**
     * Prefetch resources for likely next page
     */
    prefetchNextPage() {
        const links = document.querySelectorAll('a[data-prefetch]');
        
        links.forEach(link => {
            link.addEventListener('mouseenter', () => {
                const href = link.getAttribute('href');
                if (href && !link.dataset.prefetched) {
                    const prefetchLink = document.createElement('link');
                    prefetchLink.rel = 'prefetch';
                    prefetchLink.href = href;
                    document.head.appendChild(prefetchLink);
                    link.dataset.prefetched = 'true';
                }
            }, { once: true });
        });
    }

    /**
     * Load critical CSS inline
     */
    setupCriticalCSS() {
        // Defer non-critical CSS
        const deferredStyles = document.querySelectorAll('link[rel="stylesheet"][data-defer]');
        
        deferredStyles.forEach(link => {
            link.media = 'print';
            link.onload = function() {
                this.media = 'all';
            };
        });
    }

    /**
     * Monitor performance metrics
     */
    monitorPerformance() {
        if ('PerformanceObserver' in window) {
            // Monitor Largest Contentful Paint
            try {
                const lcpObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    console.log('LCP:', lastEntry.renderTime || lastEntry.loadTime);
                });
                lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            } catch (e) {
                console.warn('LCP observer not supported');
            }

            // Monitor First Input Delay
            try {
                const fidObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        console.log('FID:', entry.processingStart - entry.startTime);
                    });
                });
                fidObserver.observe({ entryTypes: ['first-input'] });
            } catch (e) {
                console.warn('FID observer not supported');
            }

            // Monitor Cumulative Layout Shift
            try {
                let clsValue = 0;
                const clsObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                            console.log('CLS:', clsValue);
                        }
                    }
                });
                clsObserver.observe({ entryTypes: ['layout-shift'] });
            } catch (e) {
                console.warn('CLS observer not supported');
            }
        }

        // Log page load time
        window.addEventListener('load', () => {
            setTimeout(() => {
                const perfData = window.performance.timing;
                const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
                console.log('Page Load Time:', pageLoadTime + 'ms');
            }, 0);
        });
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
     * Cleanup observers
     */
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
    }
}

/**
 * Resource Loader
 * Load scripts and styles dynamically
 */
class ResourceLoader {
    constructor() {
        this.loaded = new Set();
    }

    /**
     * Load script dynamically
     */
    loadScript(src, options = {}) {
        return new Promise((resolve, reject) => {
            if (this.loaded.has(src)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = options.async !== false;
            script.defer = options.defer || false;

            if (options.module) {
                script.type = 'module';
            }

            script.onload = () => {
                this.loaded.add(src);
                resolve();
            };

            script.onerror = () => {
                reject(new Error(`Failed to load script: ${src}`));
            };

            document.head.appendChild(script);
        });
    }

    /**
     * Load stylesheet dynamically
     */
    loadStylesheet(href, options = {}) {
        return new Promise((resolve, reject) => {
            if (this.loaded.has(href)) {
                resolve();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;

            if (options.media) {
                link.media = options.media;
            }

            link.onload = () => {
                this.loaded.add(href);
                resolve();
            };

            link.onerror = () => {
                reject(new Error(`Failed to load stylesheet: ${href}`));
            };

            document.head.appendChild(link);
        });
    }

    /**
     * Preload resource
     */
    preload(href, as) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.href = href;
        link.as = as;
        document.head.appendChild(link);
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.performanceOptimizer = new PerformanceOptimizer();
        window.resourceLoader = new ResourceLoader();
    });
} else {
    window.performanceOptimizer = new PerformanceOptimizer();
    window.resourceLoader = new ResourceLoader();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PerformanceOptimizer, ResourceLoader };
}
