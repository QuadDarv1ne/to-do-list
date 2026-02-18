/**
 * CSS Loader - Progressive CSS loading for better performance
 * Loads non-critical CSS asynchronously after page load
 */

(function() {
    'use strict';
    
    // CSS files to load progressively
    const cssFiles = [
        { href: '/css/advanced-components.css', priority: 'low' },
        { href: '/css/theme-transitions.css', priority: 'low' },
        { href: '/css/gradient-effects.css', priority: 'low' }
    ];
    
    // Check if CSS file is already loaded
    function isCSSLoaded(href) {
        const links = document.querySelectorAll('link[rel="stylesheet"]');
        return Array.from(links).some(link => link.href.includes(href));
    }
    
    // Load CSS file asynchronously
    function loadCSS(href, priority = 'low') {
        if (isCSSLoaded(href)) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'print'; // Load as print first (non-blocking)
            link.onload = function() {
                this.media = 'all'; // Switch to all media
                resolve();
            };
            link.onerror = reject;
            
            document.head.appendChild(link);
        });
    }
    
    // Load CSS files based on priority
    function loadCSSFiles() {
        // Load high priority CSS immediately
        const highPriority = cssFiles.filter(file => file.priority === 'high');
        const lowPriority = cssFiles.filter(file => file.priority === 'low');
        
        // Load high priority first
        Promise.all(highPriority.map(file => loadCSS(file.href)))
            .then(() => {
                // Then load low priority
                return Promise.all(lowPriority.map(file => loadCSS(file.href)));
            })
            .catch(error => {
                console.error('Error loading CSS:', error);
            });
    }
    
    // Preload critical fonts
    function preloadFonts() {
        const fonts = [
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-solid-900.woff2',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-regular-400.woff2'
        ];
        
        fonts.forEach(font => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'font';
            link.type = 'font/woff2';
            link.crossOrigin = 'anonymous';
            link.href = font;
            document.head.appendChild(link);
        });
    }
    
    // Optimize images - lazy load images below the fold
    function optimizeImages() {
        if ('loading' in HTMLImageElement.prototype) {
            // Native lazy loading supported
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
        } else {
            // Fallback to Intersection Observer
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    // Defer non-critical JavaScript
    function deferScripts() {
        const scripts = document.querySelectorAll('script[data-defer]');
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            newScript.src = script.dataset.defer;
            newScript.defer = true;
            document.body.appendChild(newScript);
        });
    }
    
    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Mark fonts as loaded
        document.documentElement.classList.add('fonts-loaded');
        
        // Use requestIdleCallback for non-critical tasks
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                loadCSSFiles();
                preloadFonts();
                optimizeImages();
                deferScripts();
            }, { timeout: 2000 });
        } else {
            // Fallback for browsers without requestIdleCallback
            setTimeout(() => {
                loadCSSFiles();
                preloadFonts();
                optimizeImages();
                deferScripts();
            }, 1000);
        }
    }
    
    // Expose API for manual CSS loading
    window.CSSLoader = {
        load: loadCSS,
        loadAll: loadCSSFiles
    };
})();
