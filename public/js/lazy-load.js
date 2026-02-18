/**
 * Lazy Loading Images
 * Ленивая загрузка изображений для улучшения производительности
 */

class LazyLoader {
    constructor() {
        this.images = [];
        this.observer = null;
        this.init();
    }

    init() {
        // Проверяем поддержку IntersectionObserver
        if ('IntersectionObserver' in window) {
            this.setupObserver();
            this.observeImages();
        } else {
            // Fallback для старых браузеров
            this.loadAllImages();
        }
    }

    setupObserver() {
        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.01
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, options);
    }

    observeImages() {
        // Находим все изображения с data-src
        this.images = document.querySelectorAll('img[data-src], img[loading="lazy"]');
        
        this.images.forEach(img => {
            // Если есть data-src, используем его
            if (img.dataset.src) {
                this.observer.observe(img);
            }
        });
    }

    loadImage(img) {
        const src = img.dataset.src;
        
        if (!src) return;

        // Создаем новое изображение для предзагрузки
        const tempImg = new Image();
        
        tempImg.onload = () => {
            img.src = src;
            img.classList.add('loaded');
            img.removeAttribute('data-src');
        };

        tempImg.onerror = () => {
            img.classList.add('error');
            console.error('Failed to load image:', src);
        };

        tempImg.src = src;
    }

    loadAllImages() {
        this.images.forEach(img => this.loadImage(img));
    }

    // Добавить новые изображения для наблюдения
    observe(elements) {
        if (!this.observer) return;

        const images = elements.querySelectorAll ? 
            elements.querySelectorAll('img[data-src]') : 
            [elements];

        images.forEach(img => {
            if (img.dataset.src) {
                this.observer.observe(img);
            }
        });
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.lazyLoader = new LazyLoader();
});

// Экспорт
window.LazyLoader = LazyLoader;
