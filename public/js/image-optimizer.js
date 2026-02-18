/**
 * Image Optimizer
 * Оптимизация изображений для улучшения производительности
 */

class ImageOptimizer {
    constructor() {
        this.quality = 0.8;
        this.maxWidth = 1920;
        this.maxHeight = 1080;
        this.init();
    }

    init() {
        this.setupImageUploadOptimization();
        this.setupResponsiveImages();
    }

    // Оптимизация загружаемых изображений
    setupImageUploadOptimization() {
        document.addEventListener('change', (e) => {
            if (e.target.type === 'file' && e.target.accept && e.target.accept.includes('image')) {
                this.handleImageUpload(e.target);
            }
        });
    }

    async handleImageUpload(input) {
        const files = Array.from(input.files);
        const optimizedFiles = [];

        for (const file of files) {
            if (file.type.startsWith('image/')) {
                try {
                    const optimized = await this.optimizeImage(file);
                    optimizedFiles.push(optimized);
                } catch (error) {
                    console.error('Image optimization error:', error);
                    optimizedFiles.push(file);
                }
            } else {
                optimizedFiles.push(file);
            }
        }

        // Создаем новый DataTransfer для замены файлов
        const dataTransfer = new DataTransfer();
        optimizedFiles.forEach(file => dataTransfer.items.add(file));
        input.files = dataTransfer.files;
    }

    async optimizeImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const img = new Image();
                
                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;

                        // Масштабирование если изображение слишком большое
                        if (width > this.maxWidth || height > this.maxHeight) {
                            const ratio = Math.min(this.maxWidth / width, this.maxHeight / height);
                            width = width * ratio;
                            height = height * ratio;
                        }

                        canvas.width = width;
                        canvas.height = height;

                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        // Конвертируем в blob
                        canvas.toBlob((blob) => {
                            if (blob) {
                                const optimizedFile = new File([blob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                
                                console.log(`Image optimized: ${file.name}`);
                                console.log(`Original size: ${(file.size / 1024).toFixed(2)} KB`);
                                console.log(`Optimized size: ${(optimizedFile.size / 1024).toFixed(2)} KB`);
                                console.log(`Saved: ${((1 - optimizedFile.size / file.size) * 100).toFixed(2)}%`);
                                
                                resolve(optimizedFile);
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', this.quality);
                    } catch (error) {
                        reject(error);
                    }
                };

                img.onerror = reject;
                img.src = e.target.result;
            };

            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // Адаптивные изображения
    setupResponsiveImages() {
        const images = document.querySelectorAll('img:not([srcset])');
        
        images.forEach(img => {
            if (img.src && !img.dataset.optimized) {
                this.makeImageResponsive(img);
            }
        });

        // Наблюдаем за новыми изображениями
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.tagName === 'IMG' && !node.dataset.optimized) {
                        this.makeImageResponsive(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('img:not([data-optimized])').forEach(img => {
                            this.makeImageResponsive(img);
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    makeImageResponsive(img) {
        img.dataset.optimized = 'true';
        
        // Добавляем loading="lazy" если еще не установлено
        if (!img.loading) {
            img.loading = 'lazy';
        }

        // Добавляем decoding="async"
        if (!img.decoding) {
            img.decoding = 'async';
        }

        // Добавляем placeholder пока изображение загружается
        if (!img.complete) {
            img.style.backgroundColor = 'var(--bg-secondary, #f0f0f0)';
            
            img.addEventListener('load', () => {
                img.style.backgroundColor = '';
                img.classList.add('loaded');
            }, { once: true });
        }
    }

    // Предзагрузка критичных изображений
    preloadImages(urls) {
        urls.forEach(url => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = url;
            document.head.appendChild(link);
        });
    }

    // Генерация placeholder для изображения
    generatePlaceholder(width, height, color = '#e0e0e0') {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, width, height);
        
        return canvas.toDataURL();
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.imageOptimizer = new ImageOptimizer();
});

// Экспорт
window.ImageOptimizer = ImageOptimizer;
