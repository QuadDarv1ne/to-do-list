/**
 * Performance Optimizer v3.0
 * Lazy loading, image optimization, and performance enhancements
 */

(function() {
    'use strict';

    /**
     * Lazy Load Images
     */
    class LazyLoadImages {
        constructor(options = {}) {
            this.defaults = {
                rootMargin: '50px',
                threshold: 0.01,
                placeholder: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 9"%3E%3C/svg%3E',
                loadHidden: false
            };
            this.config = { ...this.defaults, ...options };
            this.observer = null;
        }

        init() {
            if (!('IntersectionObserver' in window)) {
                this.loadAll();
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: this.config.rootMargin,
                threshold: this.config.threshold
            });

            document.querySelectorAll('img[data-src], img[data-srcset]').forEach(img => {
                this.observer.observe(img);
            });
        }

        loadImage(img) {
            const src = img.dataset.src;
            const srcset = img.dataset.srcset;

            if (src) img.src = src;
            if (srcset) img.srcset = srcset;

            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');
            img.classList.add('loaded');

            // Remove placeholder after load
            if (img.complete) {
                img.classList.add('complete');
            } else {
                img.addEventListener('load', () => {
                    img.classList.add('complete');
                });
            }
        }

        loadAll() {
            document.querySelectorAll('img[data-src], img[data-srcset]').forEach(img => {
                this.loadImage(img);
            });
        }
    }

    /**
     * Lazy Load Components
     */
    class LazyLoadComponents {
        constructor() {
            this.observer = null;
            this.loadedComponents = new Set();
        }

        init() {
            if (!('IntersectionObserver' in window)) {
                this.loadAll();
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.loadedComponents.has(entry.target)) {
                        this.loadComponent(entry.target);
                        this.loadedComponents.add(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '100px',
                threshold: 0.1
            });

            document.querySelectorAll('[data-lazy-component]').forEach(component => {
                this.observer.observe(component);
            });
        }

        loadComponent(component) {
            const componentType = component.dataset.lazyComponent;
            const dataUrl = component.dataset.dataUrl;

            if (dataUrl) {
                this.loadFromUrl(component, dataUrl);
            } else if (componentType) {
                this.loadComponentType(component, componentType);
            }
        }

        loadFromUrl(component, url) {
            component.classList.add('loading');
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    component.innerHTML = html;
                    component.classList.remove('loading');
                    component.classList.add('loaded');
                })
                .catch(error => {
                    console.error('Error loading component:', error);
                    component.classList.remove('loading');
                    component.classList.add('error');
                });
        }

        loadComponentType(component, type) {
            // Dynamic component loading logic
            if (window.logger) window.logger.log(`Loading component type: ${type}`);
            component.classList.add('loaded');
        }

        loadAll() {
            document.querySelectorAll('[data-lazy-component]').forEach(component => {
                this.loadComponent(component);
            });
        }
    }

    /**
     * Image Optimizer
     */
    class ImageOptimizer {
        constructor() {
            this.supportedFormats = ['webp', 'avif'];
        }

        init() {
            this.convertToWebP();
            this.addResponsiveImages();
        }

        convertToWebP() {
            if (!this.supportsWebP()) return;

            document.querySelectorAll('img[src*=".jpg"], img[src*=".jpeg"], img[src*=".png"]').forEach(img => {
                const webpSrc = this.changeExtension(img.src, '.webp');
                const picture = document.createElement('picture');

                const source = document.createElement('source');
                source.srcset = webpSrc;
                source.type = 'image/webp';

                const imgClone = img.cloneNode(false);
                imgClone.src = img.src;

                picture.appendChild(source);
                picture.appendChild(imgClone);

                img.parentNode.replaceChild(picture, img);
            });
        }

        supportsWebP() {
            const elem = document.createElement('canvas');
            if (!!(elem.getContext && elem.getContext('2d'))) {
                return elem.toDataURL('image/webp').indexOf('data:image/webp') === 0;
            }
            return false;
        }

        changeExtension(url, newExt) {
            return url.replace(/\.[^/.]+$/, newExt);
        }

        addResponsiveImages() {
            document.querySelectorAll('img[data-sizes]').forEach(img => {
                img.sizes = img.dataset.sizes;
            });
        }
    }

    /**
     * Debounce & Throttle
     */
    class PerformanceUtils {
        static debounce(func, wait) {
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

        static throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    }

    /**
     * Scroll Performance
     */
    class ScrollPerformance {
        constructor() {
            this.lastScrollTop = 0;
            this.ticking = false;
        }

        init() {
            this.setupScrollListener();
            this.setupSmoothScroll();
        }

        setupScrollListener() {
            window.addEventListener('scroll', () => {
                if (!this.ticking) {
                    window.requestAnimationFrame(() => {
                        this.handleScroll();
                        this.ticking = false;
                    });
                    this.ticking = true;
                }
            }, { passive: true });
        }

        handleScroll() {
            const st = window.pageYOffset || document.documentElement.scrollTop;
            
            // Hide/show header on scroll
            const header = document.querySelector('.navbar, header');
            if (header) {
                if (st > this.lastScrollTop && st > 100) {
                    header.classList.add('scroll-down');
                    header.classList.remove('scroll-up');
                } else {
                    header.classList.add('scroll-up');
                    header.classList.remove('scroll-down');
                }
            }

            // Progress indicator
            this.updateScrollProgress();

            this.lastScrollTop = st <= 0 ? 0 : st;
        }

        updateScrollProgress() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            
            let progressIndicator = document.getElementById('scroll-progress');
            if (!progressIndicator) {
                progressIndicator = document.createElement('div');
                progressIndicator.id = 'scroll-progress';
                progressIndicator.style.cssText = 'position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--primary),var(--success));z-index:9999;transition:width 0.1s;';
                document.body.prepend(progressIndicator);
            }
            
            progressIndicator.style.width = scrolled + '%';
        }

        setupSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const target = document.querySelector(targetId);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }
    }

    /**
     * Memory Management
     */
    class MemoryManager {
        constructor() {
            this.eventListeners = new Map();
        }

        addEventListener(element, event, handler, options = {}) {
            const key = `${element.id || element.className}-${event}-${handler.name || 'anonymous'}`;
            
            element.addEventListener(event, handler, {
                passive: options.passive ?? true,
                capture: options.capture ?? false
            });

            if (!this.eventListeners.has(key)) {
                this.eventListeners.set(key, { element, event, handler });
            }
        }

        cleanup() {
            this.eventListeners.forEach((data, key) => {
                data.element.removeEventListener(data.event, data.handler);
                this.eventListeners.delete(key);
            });
        }
    }

    /**
     * Cache Manager
     */
    class CacheManager {
        constructor(prefix = 'crm_cache_') {
            this.prefix = prefix;
            this.defaultTTL = 3600000; // 1 hour
        }

        set(key, value, ttl = this.defaultTTL) {
            const item = {
                value,
                expiry: Date.now() + ttl
            };
            try {
                localStorage.setItem(this.prefix + key, JSON.stringify(item));
            } catch (e) {
                console.warn('Cache storage failed:', e);
            }
        }

        get(key) {
            try {
                const itemStr = localStorage.getItem(this.prefix + key);
                if (!itemStr) return null;

                const item = JSON.parse(itemStr);
                if (Date.now() > item.expiry) {
                    this.remove(key);
                    return null;
                }
                return item.value;
            } catch (e) {
                console.warn('Cache retrieval failed:', e);
                return null;
            }
        }

        remove(key) {
            try {
                localStorage.removeItem(this.prefix + key);
            } catch (e) {
                console.warn('Cache removal failed:', e);
            }
        }

        clear() {
            try {
                Object.keys(localStorage).forEach(key => {
                    if (key.startsWith(this.prefix)) {
                        localStorage.removeItem(key);
                    }
                });
            } catch (e) {
                console.warn('Cache clear failed:', e);
            }
        }
    }

    /**
     * Initialize all performance optimizations
     */
    window.performanceOptimizer = {
        lazyImages: new LazyLoadImages(),
        lazyComponents: new LazyLoadComponents(),
        imageOptimizer: new ImageOptimizer(),
        scrollPerformance: new ScrollPerformance(),
        memoryManager: new MemoryManager(),
        cache: new CacheManager(),
        utils: PerformanceUtils,

        init() {
            this.lazyImages.init();
            this.lazyComponents.init();
            this.imageOptimizer.init();
            this.scrollPerformance.init();
            
            if (window.logger) window.logger.log('Performance optimizer initialized');
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.performanceOptimizer.init();
        });
    } else {
        window.performanceOptimizer.init();
    }

})();
