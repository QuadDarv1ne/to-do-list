/**
 * Image Optimizer - Оптимизация загрузки изображений
 */

(function() {
    'use strict';
    
    class ImageOptimizer {
        constructor() {
            this.observer = null;
            this.init();
        }
        
        init() {
            this.setupLazyLoading();
        }
        
        setupLazyLoading() {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImage(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
                
                this.observeImages();
            } else {
                // Fallback для старых браузеров
                this.loadAllImages();
            }
        }
        
        observeImages() {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => {
                this.observer.observe(img);
            });
        }
        
        loadImage(img) {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.classList.add('loaded');
            }
        }
        
        loadAllImages() {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => this.loadImage(img));
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ImageOptimizer = new ImageOptimizer();
        });
    } else {
        window.ImageOptimizer = new ImageOptimizer();
    }
    
})();
