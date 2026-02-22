/**
 * Keyboard Navigation - полная навигация с клавиатуры
 */

class KeyboardNavigation {
    constructor() {
        this.shortcuts = {};
        this.init();
    }

    init() {
        document.addEventListener('keydown', (e) => this.handleKeydown(e));
        
        // Загружаем сохранённые шорткаты
        this.loadShortcuts();
        
        // Регистрируем базовые шорткаты
        this.registerDefaultShortcuts();
    }

    handleKeydown(e) {
        // Игнорируем при вводе в формах
        const target = e.target;
        const isInput = target.tagName === 'INPUT' || 
                       target.tagName === 'TEXTAREA' || 
                       target.isContentEditable;

        // Ctrl+K - Command Palette (уже есть)
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            return; // Уже обработано в command-palette
        }

        // Ctrl+S - Save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            this.triggerShortcut('save');
            return;
        }

        // Ctrl+N - New
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            this.triggerShortcut('new');
            return;
        }

        // Ctrl+F - Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('[data-search-input]') || 
                               document.querySelector('.search-input input') ||
                               document.querySelector('#global-search');
            if (searchInput) searchInput.focus();
            return;
        }

        // Ctrl+/ - Help (показать все шорткаты)
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            this.showHelp();
            return;
        }

        // Escape - Close modals
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const closeBtn = modal.querySelector('.btn-close') || 
                                modal.querySelector('[data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
            
            // Закрыть dropdowns
            const dropdown = document.querySelector('.dropdown-menu.show');
            if (dropdown) dropdown.classList.remove('show');
            
            return;
        }

        // Tab - Навигация по элементам
        if (e.key === 'Tab' && !isInput) {
            // Добавляем визуальную индикацию фокуса
            this.addFocusIndicators(e);
        }

        // Стрелки для навигации по спискам
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key) && !isInput) {
            e.preventDefault();
            this.navigateWithArrows(e.key);
        }

        // ? - Показать шорткаты (одиночная клавиша)
        if (e.key === '?' && !isInput) {
            this.showHelp();
        }

        // G + D - Go to Dashboard
        // G + T - Go to Tasks
        // G + C - Go to Calendar
        // G + K - Go to Kanban
        // G + S - Go to Settings
        if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !isInput) {
            this.handleGoTo(e);
        }

        // J/K для навигации по списку (Vim-стиль)
        if (e.key === 'j' && !isInput) {
            this.navigateList(1);
        }
        if (e.key === 'k' && !isInput) {
            this.navigateList(-1);
        }

        // X - Toggle checkbox (для задач)
        if (e.key === 'x' && !isInput) {
            this.toggleSelectedCheckbox();
        }
    }

    registerDefaultShortcuts() {
        this.shortcuts = {
            'save': { label: 'Сохранить', action: () => this.triggerFormSubmit() },
            'new': { label: 'Создать', action: () => this.triggerNewAction() },
            'search': { label: 'Поиск', action: () => this.focusSearch() },
            'help': { label: 'Справка', action: () => this.showHelp() },
            'close': { label: 'Закрыть', action: () => this.closeModal() },
            'dashboard': { label: 'Дашборд', action: () => window.location.href = '/dashboard' },
            'tasks': { label: 'Задачи', action: () => window.location.href = '/tasks' },
            'calendar': { label: 'Календарь', action: () => window.location.href = '/calendar' },
            'kanban': { label: 'Канбан', action: () => window.location.href = '/kanban' },
            'settings': { label: 'Настройки', action: () => window.location.href = '/settings' },
        };
    }

    loadShortcuts() {
        try {
            const saved = localStorage('keyboardShortcuts');
            if (saved) {
                this.customShortcuts = JSON.parse(saved);
            }
        } catch (e) {
            console.warn('Failed to load shortcuts:', e);
        }
    }

    triggerShortcut(name) {
        const shortcut = this.shortcuts[name];
        if (shortcut && shortcut.action) {
            shortcut.action();
        }
    }

    triggerFormSubmit() {
        const form = document.querySelector('form:focus-within') || 
                    document.querySelector('.modal.show form');
        if (form) {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.click();
            else form.submit();
        }
    }

    triggerNewAction() {
        // Определяем текущую страницу и переходим на создание
        const path = window.location.pathname;
        
        if (path.includes('/tasks')) {
            window.location.href = '/tasks/new';
        } else if (path.includes('/deals')) {
            window.location.href = '/deals/new';
        } else if (path.includes('/clients')) {
            window.location.href = '/clients/new';
        } else {
            window.location.href = '/tasks/new';
        }
    }

    focusSearch() {
        const searchInput = document.querySelector('[data-search-input]') || 
                           document.querySelector('.search-input input') ||
                           document.querySelector('#global-search') ||
                           document.querySelector('input[type="search"]');
        if (searchInput) searchInput.focus();
    }

    navigateWithArrows(key) {
        const focusable = document.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        
        const current = document.activeElement;
        const index = Array.from(focusable).indexOf(current);
        
        if (index === -1) return;
        
        let nextIndex;
        switch (key) {
            case 'ArrowDown':
            case 'ArrowRight':
                nextIndex = Math.min(index + 1, focusable.length - 1);
                break;
            case 'ArrowUp':
            case 'ArrowLeft':
                nextIndex = Math.max(index - 1, 0);
                break;
        }
        
        focusable[nextIndex]?.focus();
    }

    navigateList(direction) {
        const items = document.querySelectorAll('.task-item, .deal-item, .client-item, .kanban-card');
        if (items.length === 0) return;
        
        const currentIndex = Array.from(items).findIndex(item => 
            item === document.activeElement || item.contains(document.activeElement)
        );
        
        let nextIndex = currentIndex + direction;
        
        if (nextIndex < 0) nextIndex = items.length - 1;
        if (nextIndex >= items.length) nextIndex = 0;
        
        items[nextIndex]?.focus();
        items[nextIndex]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    toggleSelectedCheckbox() {
        const focused = document.querySelector('.task-item:focus-within, .task-item:focus');
        if (focused) {
            const checkbox = focused.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    handleGoTo(e) {
        // Ждём следующего нажатия
        const handleSecondKey = (e2) => {
            const shortcuts = {
                'd': '/dashboard',
                't': '/tasks',
                'c': '/calendar',
                'k': '/kanban',
                's': '/settings',
                'n': '/notifications',
                'a': '/analytics',
            };
            
            const url = shortcuts[e2.key];
            if (url) {
                window.location.href = url;
            }
            
            document.removeEventListener('keydown', handleSecondKey);
        };
        
        document.addEventListener('keydown', handleSecondKey, { once: true });
        
        // Таймаут если второй клавиши нет
        setTimeout(() => {
            document.removeEventListener('keydown', handleSecondKey);
        }, 1000);
    }

    addFocusIndicators(e) {
        const style = document.createElement('style');
        style.id = 'keyboard-focus-style';
        style.textContent = `
            *:focus-visible {
                outline: 2px solid var(--primary, #667eea) !important;
                outline-offset: 2px !important;
            }
        `;
        document.head.appendChild(style);
        
        // Удаляем через 5 секунд
        setTimeout(() => style.remove(), 5000);
    }

    showHelp() {
        // Создаём модалку со справкой
        const helpHtml = `
            <div class="modal fade show" id="keyboardHelpModal" tabindex="-1" style="display: block;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-keyboard me-2"></i>Клавиши
                            </h5>
                            <button type="button" class="btn-close" onclick="this.closest('.modal').remove()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Основные</h6>
                                    <ul class="list-unstyled">
                                        <li><kbd>Ctrl</kbd> + <kbd>K</kbd> - Поиск</li>
                                        <li><kbd>Ctrl</kbd> + <kbd>N</kbd> - Создать</li>
                                        <li><kbd>Ctrl</kbd> + <kbd>S</kbd> - Сохранить</li>
                                        <li><kbd>Ctrl</kbd> + <kbd>F</kbd> - Фокус поиска</li>
                                        <li><kbd>Ctrl</kbd> + <kbd>/</kbd> - Эта справка</li>
                                        <li><kbd>Esc</kbd> - Закрыть</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Навигация</h6>
                                    <ul class="list-unstyled">
                                        <li><kbd>J</kbd> / <kbd>K</kbd> - Вниз/Вверх</li>
                                        <li><kbd>←</kbd> <kbd>→</kbd> - Навигация</li>
                                        <li><kbd>X</kbd> - Отметить</li>
                                        <li><kbd>G</kbd> + <kbd>D</kbd> - Дашборд</li>
                                        <li><kbd>G</kbd> + <kbd>T</kbd> - Задачи</li>
                                        <li><kbd>G</kbd> + <kbd>K</kbd> - Канбан</li>
                                        <li><kbd>G</kbd> + <kbd>C</kbd> - Календарь</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        `;
        
        const helpModal = document.createElement('div');
        helpModal.innerHTML = helpHtml;
        document.body.appendChild(helpModal);
        
        // Закрытие по клику на backdrop
        helpModal.querySelector('.modal-backdrop').addEventListener('click', () => {
            helpModal.remove();
        });
    }
}

// Инициализация
const keyboardNav = new KeyboardNavigation();
window.keyboardNav = keyboardNav;
