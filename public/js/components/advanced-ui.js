/**
 * Advanced UI Components
 * Продвинутые UI компоненты и взаимодействия
 */

class AdvancedUI {
    constructor() {
        this.init();
    }

    init() {
        this.initSmoothScroll();
        this.initLazyLoading();
        this.initInfiniteScroll();
        this.initDragAndDrop();
        this.initContextMenu();
        this.initKeyboardShortcuts();
        this.initNotifications();
        this.initSearchHighlight();
        this.initAutoSave();
    }

    // Плавная прокрутка
    initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const href = anchor.getAttribute('href');
                if (href === '#') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Lazy Loading для изображений
    initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Infinite Scroll
    initInfiniteScroll() {
        const sentinel = document.querySelector('.infinite-scroll-sentinel');
        if (!sentinel) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadMoreContent();
                }
            });
        }, { rootMargin: '100px' });

        observer.observe(sentinel);
    }

    loadMoreContent() {
        // Показываем loader
        const loader = document.createElement('div');
        loader.className = 'spinner';
        loader.innerHTML = '<div class="spinner-dots"><span></span><span></span><span></span></div>';
        document.querySelector('.content-container')?.appendChild(loader);

        // Симуляция загрузки
        setTimeout(() => {
            loader.remove();
            // Здесь будет AJAX запрос для загрузки контента
        }, 1000);
    }

    // Drag and Drop для карточек
    initDragAndDrop() {
        const draggables = document.querySelectorAll('[draggable="true"]');
        const dropzones = document.querySelectorAll('.dropzone');

        draggables.forEach(draggable => {
            draggable.addEventListener('dragstart', (e) => {
                draggable.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', draggable.innerHTML);
            });

            draggable.addEventListener('dragend', () => {
                draggable.classList.remove('dragging');
            });
        });

        dropzones.forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-over');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                const dragging = document.querySelector('.dragging');
                if (dragging) {
                    zone.appendChild(dragging);
                    this.showNotification('Элемент перемещён', 'success');
                }
            });
        });
    }

    // Контекстное меню
    initContextMenu() {
        const contextMenuItems = document.querySelectorAll('[data-context-menu]');
        
        contextMenuItems.forEach(item => {
            item.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                this.showContextMenu(e.pageX, e.pageY, item);
            });
        });

        // Закрываем меню при клике вне его
        document.addEventListener('click', () => {
            this.hideContextMenu();
        });
    }

    showContextMenu(x, y, element) {
        this.hideContextMenu();

        const menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        
        const actions = [
            { icon: 'edit', label: 'Редактировать', action: () => console.log('Edit') },
            { icon: 'copy', label: 'Копировать', action: () => console.log('Copy') },
            { icon: 'trash', label: 'Удалить', action: () => console.log('Delete') }
        ];

        actions.forEach(action => {
            const item = document.createElement('div');
            item.className = 'context-menu-item';
            item.innerHTML = `<i class="fas fa-${action.icon}"></i> ${action.label}`;
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                action.action();
                this.hideContextMenu();
            });
            menu.appendChild(item);
        });

        document.body.appendChild(menu);

        // Анимация появления
        requestAnimationFrame(() => {
            menu.classList.add('show');
        });
    }

    hideContextMenu() {
        const menu = document.querySelector('.context-menu');
        if (menu) {
            menu.classList.remove('show');
            setTimeout(() => menu.remove(), 200);
        }
    }

    // Горячие клавиши
    initKeyboardShortcuts() {
        const shortcuts = {
            'ctrl+k': () => this.focusSearch(),
            'ctrl+n': () => this.createNewTask(),
            'ctrl+s': (e) => { e.preventDefault(); this.saveData(); },
            'esc': () => this.closeModals(),
            '/': () => this.focusSearch()
        };

        document.addEventListener('keydown', (e) => {
            const key = (e.ctrlKey ? 'ctrl+' : '') + 
                       (e.shiftKey ? 'shift+' : '') + 
                       (e.altKey ? 'alt+' : '') + 
                       e.key.toLowerCase();

            if (shortcuts[key]) {
                shortcuts[key](e);
            }
        });
    }

    focusSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    createNewTask() {
        window.location.href = '/tasks/new';
    }

    saveData() {
        this.showNotification('Данные сохранены', 'success');
    }

    closeModals() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
        });
    }

    // Система уведомлений
    initNotifications() {
        // Создаём контейнер для уведомлений
        if (!document.querySelector('.notifications-container')) {
            const container = document.createElement('div');
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        const container = document.querySelector('.notifications-container');
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };

        notification.innerHTML = `
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
            <button class="notification-close"><i class="fas fa-times"></i></button>
        `;

        container.appendChild(notification);

        // Анимация появления
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Закрытие по клику
        notification.querySelector('.notification-close').addEventListener('click', () => {
            this.hideNotification(notification);
        });

        // Автоматическое закрытие
        if (duration > 0) {
            setTimeout(() => {
                this.hideNotification(notification);
            }, duration);
        }
    }

    hideNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }

    // Подсветка результатов поиска
    initSearchHighlight() {
        const searchInput = document.querySelector('.search-input');
        if (!searchInput) return;

        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                this.highlightSearchResults(e.target.value);
            }, 300);
        });
    }

    highlightSearchResults(query) {
        if (!query) {
            this.clearHighlights();
            return;
        }

        const elements = document.querySelectorAll('.searchable');
        elements.forEach(el => {
            const text = el.textContent;
            const regex = new RegExp(`(${query})`, 'gi');
            const highlighted = text.replace(regex, '<mark>$1</mark>');
            el.innerHTML = highlighted;
        });
    }

    clearHighlights() {
        document.querySelectorAll('mark').forEach(mark => {
            const parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
        });
    }

    // Автосохранение
    initAutoSave() {
        const forms = document.querySelectorAll('form[data-autosave]');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(this.autoSaveTimer);
                    this.autoSaveTimer = setTimeout(() => {
                        this.autoSave(form);
                    }, 2000);
                });
            });
        });
    }

    autoSave(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Сохраняем в localStorage
        localStorage.setItem('autosave_' + form.id, JSON.stringify(data));
        
        // Показываем индикатор
        this.showAutoSaveIndicator();
    }

    showAutoSaveIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'autosave-indicator';
        indicator.innerHTML = '<i class="fas fa-check"></i> Сохранено';
        document.body.appendChild(indicator);

        requestAnimationFrame(() => {
            indicator.classList.add('show');
        });

        setTimeout(() => {
            indicator.classList.remove('show');
            setTimeout(() => indicator.remove(), 300);
        }, 2000);
    }

    // Утилиты
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

// Добавляем стили для новых компонентов
const styles = `
    .context-menu {
        position: fixed;
        background: white;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        padding: 0.5rem 0;
        min-width: 180px;
        z-index: 10000;
        opacity: 0;
        transform: scale(0.95);
        transition: all 0.2s ease;
    }

    .context-menu.show {
        opacity: 1;
        transform: scale(1);
    }

    .context-menu-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: background 0.2s ease;
    }

    .context-menu-item:hover {
        background: #f3f4f6;
    }

    .context-menu-item i {
        width: 16px;
        color: #6b7280;
    }

    .notifications-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .notification {
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .notification.show {
        opacity: 1;
        transform: translateX(0);
    }

    .notification-success {
        border-left: 4px solid #10b981;
    }

    .notification-error {
        border-left: 4px solid #ef4444;
    }

    .notification-warning {
        border-left: 4px solid #f59e0b;
    }

    .notification-info {
        border-left: 4px solid #6366f1;
    }

    .notification i {
        font-size: 1.25rem;
    }

    .notification-success i { color: #10b981; }
    .notification-error i { color: #ef4444; }
    .notification-warning i { color: #f59e0b; }
    .notification-info i { color: #6366f1; }

    .notification-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        transition: color 0.2s ease;
    }

    .notification-close:hover {
        color: #374151;
    }

    .autosave-indicator {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 0.75rem 1.25rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        z-index: 10000;
    }

    .autosave-indicator.show {
        opacity: 1;
        transform: translateY(0);
    }

    .dragging {
        opacity: 0.5;
        cursor: move;
    }

    .drag-over {
        border: 2px dashed #6366f1;
        background: rgba(99, 102, 241, 0.05);
    }

    mark {
        background: #fef08a;
        padding: 0.125rem 0.25rem;
        border-radius: 3px;
    }

    @media (max-width: 768px) {
        .notifications-container {
            left: 20px;
            right: 20px;
        }

        .notification {
            min-width: auto;
        }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.advancedUI = new AdvancedUI();
    });
} else {
    window.advancedUI = new AdvancedUI();
}

// Экспорт
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdvancedUI;
}
