import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['image', 'content'];
    static values = { 
        src: String,
        threshold: { type: Number, default: 0.1 },
        rootMargin: { type: String, default: '50px' }
    };

    connect() {
        this.observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            {
                threshold: this.thresholdValue,
                rootMargin: this.rootMarginValue
            }
        );

        // Наблюдаем за изображениями
        this.imageTargets.forEach(img => {
            this.observer.observe(img);
        });

        // Наблюдаем за контентом
        this.contentTargets.forEach(content => {
            this.observer.observe(content);
        });
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.loadElement(entry.target);
                this.observer.unobserve(entry.target);
            }
        });
    }

    loadElement(element) {
        if (element.hasAttribute('data-lazy-load-src-value')) {
            // Загружаем изображение
            this.loadImage(element);
        } else if (element.hasAttribute('data-lazy-content')) {
            // Загружаем контент
            this.loadContent(element);
        }
    }

    loadImage(img) {
        const src = img.dataset.lazyLoadSrcValue || img.dataset.src;
        if (src) {
            // Создаем новое изображение для предзагрузки
            const newImg = new Image();
            newImg.onload = () => {
                img.src = src;
                img.classList.add('loaded');
                img.classList.remove('loading');
            };
            newImg.onerror = () => {
                img.classList.add('error');
                img.classList.remove('loading');
            };
            
            img.classList.add('loading');
            newImg.src = src;
        }
    }

    loadContent(element) {
        const url = element.dataset.lazyContent;
        if (url) {
            element.classList.add('loading');
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    element.innerHTML = html;
                    element.classList.add('loaded');
                    element.classList.remove('loading');
                    
                    // Диспатчим событие для других контроллеров
                    element.dispatchEvent(new CustomEvent('lazy:loaded', {
                        detail: { element, url }
                    }));
                })
                .catch(error => {
                    console.error('Lazy load error:', error);
                    element.classList.add('error');
                    element.classList.remove('loading');
                    element.innerHTML = '<div class="alert alert-warning">Ошибка загрузки контента</div>';
                });
        }
    }

    // Метод для принудительной загрузки всех элементов
    loadAll() {
        this.imageTargets.forEach(img => this.loadImage(img));
        this.contentTargets.forEach(content => this.loadContent(content));
    }

    // Метод для загрузки конкретного элемента
    loadTarget(event) {
        const target = event.currentTarget;
        this.loadElement(target);
    }
}