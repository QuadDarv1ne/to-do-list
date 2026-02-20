/**
 * Virtual Scroll
 * Виртуализация списков для оптимизации производительности
 */

class VirtualScroll {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            itemHeight: options.itemHeight || 60,
            buffer: options.buffer || 5,
            renderItem: options.renderItem || this.defaultRenderItem,
            onScroll: options.onScroll || null,
            ...options
        };
        
        this.items = [];
        this.visibleItems = [];
        this.scrollTop = 0;
        this.containerHeight = 0;
        
        this.init();
    }

    init() {
        this.setupContainer();
        this.bindEvents();
        this.updateVisibleItems();
    }

    setupContainer() {
        this.container.style.position = 'relative';
        this.container.style.overflow = 'auto';
        
        // Создаём контейнер для элементов
        this.viewport = document.createElement('div');
        this.viewport.className = 'virtual-scroll-viewport';
        this.viewport.style.position = 'relative';
        
        // Создаём spacer для правильной высоты скролла
        this.spacer = document.createElement('div');
        this.spacer.className = 'virtual-scroll-spacer';
        
        this.viewport.appendChild(this.spacer);
        this.container.appendChild(this.viewport);
        
        this.containerHeight = this.container.clientHeight;
    }

    bindEvents() {
        this.container.addEventListener('scroll', () => {
            this.scrollTop = this.container.scrollTop;
            this.updateVisibleItems();
            
            if (this.options.onScroll) {
                this.options.onScroll(this.scrollTop);
            }
        });

        // Обновляем при изменении размера
        const resizeObserver = new ResizeObserver(() => {
            this.containerHeight = this.container.clientHeight;
            this.updateVisibleItems();
        });
        
        resizeObserver.observe(this.container);
    }

    setItems(items) {
        this.items = items;
        this.updateSpacer();
        this.updateVisibleItems();
    }

    updateSpacer() {
        const totalHeight = this.items.length * this.options.itemHeight;
        this.spacer.style.height = `${totalHeight}px`;
    }

    updateVisibleItems() {
        const startIndex = Math.max(0, 
            Math.floor(this.scrollTop / this.options.itemHeight) - this.options.buffer
        );
        
        const visibleCount = Math.ceil(this.containerHeight / this.options.itemHeight) + 
                           (this.options.buffer * 2);
        
        const endIndex = Math.min(
            this.items.length,
            startIndex + visibleCount
        );

        // Удаляем старые элементы
        this.clearViewport();

        // Рендерим видимые элементы
        for (let i = startIndex; i < endIndex; i++) {
            const item = this.items[i];
            const element = this.options.renderItem(item, i);
            
            element.style.position = 'absolute';
            element.style.top = `${i * this.options.itemHeight}px`;
            element.style.left = '0';
            element.style.right = '0';
            element.style.height = `${this.options.itemHeight}px`;
            
            this.viewport.appendChild(element);
        }

        this.visibleItems = this.items.slice(startIndex, endIndex);
    }

    clearViewport() {
        // Удаляем все элементы кроме spacer
        while (this.viewport.children.length > 1) {
            this.viewport.removeChild(this.viewport.lastChild);
        }
    }

    defaultRenderItem(item, index) {
        const div = document.createElement('div');
        div.className = 'virtual-scroll-item';
        div.textContent = item.toString();
        return div;
    }

    scrollToIndex(index) {
        const scrollTop = index * this.options.itemHeight;
        this.container.scrollTop = scrollTop;
    }

    scrollToTop() {
        this.container.scrollTop = 0;
    }

    scrollToBottom() {
        this.container.scrollTop = this.spacer.offsetHeight;
    }

    destroy() {
        this.clearViewport();
        this.container.innerHTML = '';
    }
}

// Стили для виртуального скролла
const styles = `
    .virtual-scroll-viewport {
        width: 100%;
    }

    .virtual-scroll-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        background: white;
        transition: background 0.2s ease;
    }

    .virtual-scroll-item:hover {
        background: #f9fafb;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);

// Экспорт
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VirtualScroll;
}
