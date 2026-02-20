/**
 * Lazy Loading Images - Optimized
 * Ленивая загрузка изображений с поддержкой WebP и адаптивной загрузкой
 */

class LazyLoader {
    constructor() {
        this.images = [];
        this.observer = null;
        this.supportsWebP = this.detectWebP();
        this.placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwIiBoZWlnaHQ9IjkwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNlNWU3ZWIiLz48L3N2Zz4=';
        this.init();
    }

    // Проверка поддержки WebP
    detectWebP() {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        try {
            return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
        } catch (e) {
            return false;
        }
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
            rootMargin: '100px', // Увеличенный margin для предзагрузки
            threshold: [0, 0.01, 0.1, 0.5, 1] // Несколько порогов для лучшей точности
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Приоритетная загрузка для видимых изображений
                    if (entry.intersectionRatio > 0.1) {
                        this.loadImage(img);
                    } else {
                        // Отложенная загрузка для частично видимых
                        setTimeout(() => this.loadImage(img), 200);
                    }
                    
                    this.observer.unobserve(img);
                }
            });
        }, options);
    }

    observeImages() {
        // Находим все изображения для ленивой загрузки
        this.images = document.querySelectorAll('img[data-src], img[loading="lazy"], picture source[data-srcset]');

        this.images.forEach((img, index) => {
            // Добавляем placeholder для предотвращения layout shift
            if (!img.src && !img.currentSrc) {
                img.src = this.placeholder;
            }

            // Если есть data-src, используем его
            if (img.dataset.src) {
                // Проверяем поддержку WebP и конвертируем URL если нужно
                if (this.supportsWebP && img.dataset.srcWebp) {
                    img.dataset.src = img.dataset.srcWebp;
                }
                this.observer.observe(img);
            }

            // Обработка srcset для адаптивных изображений
            if (img.dataset.srcset) {
                this.setupSrcset(img);
            }
        });

        // Наблюдение за picture элементами
        document.querySelectorAll('picture[data-lazy]').forEach(picture => {
            this.observePictureElement(picture);
        });
    }

    // Настройка адаптивных изображений
    setupSrcset(img) {
        const srcset = img.dataset.srcset;
        const sizes = img.dataset.sizes;

        if (srcset) {
            // Конвертируем в WebP если поддерживается
            if (this.supportsWebP && img.dataset.srcsetWebp) {
                img.srcset = img.dataset.srcsetWebp;
            } else {
                img.srcset = srcset;
            }

            if (sizes) {
                img.sizes = sizes;
            }
        }
    }

    // Обработка picture элементов
    observePictureElement(picture) {
        const img = picture.querySelector('img');
        if (img && img.dataset.src) {
            this.observer.observe(img);
        }
    }

    loadImage(img) {
        const src = img.dataset.src;

        if (!src) return;

        // Создаем новое изображение для предзагрузки
        const tempImg = new Image();

        // Добавляем параметр качества для оптимизации
        const qualityParam = img.dataset.quality || '80';
        const separator = src.includes('?') ? '&' : '?';
        const optimizedSrc = `${src}${separator}q=${qualityParam}`;

        tempImg.onload = () => {
            // Сохраняем пропорции для предотвращения layout shift
            if (img.width && img.height) {
                img.style.aspectRatio = `${img.width}/${img.height}`;
            }

            img.src = optimizedSrc;
            img.classList.add('loaded');
            img.classList.remove('loading');
            img.removeAttribute('data-src');

            // Dispatch custom event
            img.dispatchEvent(new CustomEvent('imageLoaded', { bubbles: true }));
        };

        tempImg.onerror = () => {
            img.classList.add('error');
            img.removeAttribute('data-src');
            
            // Показываем fallback если есть
            if (img.dataset.fallback) {
                img.src = img.dataset.fallback;
            }
            
            console.warn('Failed to load image:', src);
        };

        tempImg.src = optimizedSrc;
    }

    loadAllImages() {
        this.images.forEach(img => this.loadImage(img));
    }

    // Добавить новые изображения для наблюдения
    observe(elements) {
        if (!this.observer) return;

        const images = elements.querySelectorAll ?
            elements.querySelectorAll('img[data-src], img[loading="lazy"]') :
            (elements.dataset && elements.dataset.src ? [elements] : []);

        images.forEach(img => {
            if (img.dataset.src && !img.src) {
                this.observer.observe(img);
            }
        });
    }

    // Очистка
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
        this.images = [];
    }

    // Статистика
    getStats() {
        const allImages = document.querySelectorAll('img');
        let loaded = 0;
        let lazy = 0;
        let error = 0;

        allImages.forEach(img => {
            if (img.classList.contains('loaded')) loaded++;
            if (img.classList.contains('error')) error++;
            if (img.dataset.src || img.loading === 'lazy') lazy++;
        });

        return { total: allImages.length, loaded, lazy, error };
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.lazyLoader = new LazyLoader();
});

// Экспорт
window.LazyLoader = LazyLoader;

// Глобальная функция для получения статистики
window.getImageStats = () => window.lazyLoader ? window.lazyLoader.getStats() : null;
