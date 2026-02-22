import { Controller } from '@hotwired/stimulus';

export class InfiniteScrollController extends Controller {
    static values = {
        url: String,
        param: { type: String, default: 'page' },
        threshold: { type: Number, default: 200 },
        startPage: { type: Number, default: 1 }
    };

    connect() {
        this.currentPage = this.startPageValue;
        this.isLoading = false;
        this.hasMore = true;

        // Подключаем IntersectionObserver
        this.observer = new IntersectionObserver(
            (entries) => this.onIntersect(entries),
            { rootMargin: `${this.thresholdValue}px` }
        );

        // Наблюдаем за триггером
        const trigger = this.element.querySelector('[data-infinite-scroll-trigger]');
        if (trigger) {
            this.observer.observe(trigger);
        }
    }

    disconnect() {
        this.observer?.disconnect();
    }

    onIntersect(entries) {
        const entry = entries[0];
        if (entry.isIntersecting && !this.isLoading && this.hasMore) {
            this.loadMore();
        }
    }

    async loadMore() {
        if (this.isLoading || !this.hasMore) return;

        this.isLoading = true;
        this.showLoading();

        try {
            const nextPage = this.currentPage + 1;
            const url = new URL(this.urlValue);
            url.searchParams.set(this.paramValue, nextPage);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html, application/x-merge+html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();

            if (!html || html.trim() === '') {
                this.hasMore = false;
                this.hideLoading();
                return;
            }

            // Добавляем новый контент
            const container = this.element.querySelector('[data-infinite-scroll-container]');
            if (container) {
                // Если Turbo Stream - обрабатываем особым образом
                if (response.headers.get('content-type')?.includes('turbo-stream')) {
                    // Для Turbo Stream нужно использовать Turbo
                    if (window.Turbo) {
                        const stream = new DOMParser().parseFromString(html, 'text/html');
                        const turboStream = stream.querySelector('turbo-stream');
                        if (turboStream) {
                            const template = turboStream.querySelector('template');
                            if (template) {
                                container.insertAdjacentHTML('beforeend', template.innerHTML);
                            }
                        }
                    }
                } else {
                    container.insertAdjacentHTML('beforeend', html);
                }
            }

            this.currentPage = nextPage;

            // Проверяем, есть ли ещё страницы
            this.hasMore = html.includes('infinite-scroll-trigger') || html.trim().length > 0;

        } catch (error) {
            console.error('Infinite scroll error:', error);
            this.hasMore = false;
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    showLoading() {
        const loadingEl = this.element.querySelector('[data-infinite-scroll-loading]');
        if (loadingEl) {
            loadingEl.classList.remove('hidden');
        }
    }

    hideLoading() {
        const loadingEl = this.element.querySelector('[data-infinite-scroll-loading]');
        if (loadingEl) {
            loadingEl.classList.add('hidden');
        }
    }

    // Ручная перезагрузка
    reload() {
        const container = this.element.querySelector('[data-infinite-scroll-container]');
        if (container) {
            container.innerHTML = '';
        }
        this.currentPage = this.startPageValue;
        this.hasMore = true;
        this.loadMore();
    }
}
