/**
 * Enhanced Keyboard Shortcuts System
 * Универсальная система горячих клавиш с визуальной подсказкой
 */

class KeyboardShortcutsManager {
    constructor() {
        this.shortcuts = new Map();
        this.enabled = true;
        this.helpVisible = false;
        this.init();
    }

    init() {
        this.registerDefaultShortcuts();
        this.setupEventListeners();
        this.createHelpOverlay();
        this.loadUserPreferences();
    }

    /**
     * Регистрация горячих клавиш по умолчанию
     */
    registerDefaultShortcuts() {
        // Навигация
        this.register('ctrl+k', 'Быстрый поиск', () => this.openQuickSearch());
        this.register('g h', 'Перейти на главную', () => window.location.href = '/');
        this.register('g t', 'Перейти к задачам', () => window.location.href = '/tasks');
        this.register('g p', 'Перейти к проектам', () => window.location.href = '/projects');
        this.register('g c', 'Перейти к календарю', () => window.location.href = '/calendar');

        // Действия с задачами
        this.register('c', 'Создать задачу', () => this.createNewTask());
        this.register('n', 'Создать задачу (альтернатива)', () => this.createNewTask());
        this.register('e', 'Редактировать задачу', () => this.editCurrentTask());
        this.register('d', 'Удалить задачу', () => this.deleteCurrentTask());
        this.register('x', 'Отметить выполненной', () => this.toggleTaskComplete());

        // Фильтры и сортировка
        this.register('f', 'Открыть фильтры', () => this.openFilters());
        this.register('s', 'Открыть сортировку', () => this.openSort());
        this.register('/', 'Фокус на поиск', () => this.focusSearch());

        // Интерфейс
        this.register('?', 'Показать помощь', () => this.toggleHelp());
        this.register('escape', 'Закрыть модальные окна', () => this.closeModals());
        this.register('ctrl+,', 'Открыть настройки', () => window.location.href = '/settings');

        // Темы
        this.register('alt+t', 'Переключить тему', () => this.toggleTheme());
        this.register('alt+1', 'Светлая тема', () => this.setTheme('light'));
        this.register('alt+2', 'Тёмная тема', () => this.setTheme('dark'));
        this.register('alt+3', 'Оранжевая тема', () => this.setTheme('orange'));
        this.register('alt+4', 'Фиолетовая тема', () => this.setTheme('purple'));

        // Навигация по списку
        this.register('j', 'Следующая задача', () => this.navigateNext());
        this.register('k', 'Предыдущая задача', () => this.navigatePrevious());
        this.register('enter', 'Открыть задачу', () => this.openCurrentTask());

        // Быстрые действия
        this.register('ctrl+s', 'Сохранить', (e) => this.save(e));
        this.register('ctrl+z', 'Отменить', (e) => this.undo(e));
        this.register('ctrl+shift+z', 'Повторить', (e) => this.redo(e));
    }

    /**
     * Регистрация новой горячей клавиши
     */
    register(keys, description, callback, options = {}) {
        const normalizedKeys = this.normalizeKeys(keys);
        this.shortcuts.set(normalizedKeys, {
            keys: normalizedKeys,
            description,
            callback,
            enabled: options.enabled !== false,
            category: options.category || 'general',
            preventDefault: options.preventDefault !== false
        });
    }

    /**
     * Нормализация комбинации клавиш
     */
    normalizeKeys(keys) {
        return keys.toLowerCase()
            .replace(/\s+/g, ' ')
            .split(' ')
            .map(k => k.trim())
            .join(' ');
    }

    /**
     * Настройка обработчиков событий
     */
    setupEventListeners() {
        let sequenceKeys = [];
        let sequenceTimeout = null;

        document.addEventListener('keydown', (e) => {
            if (!this.enabled) return;
            if (this.shouldIgnoreEvent(e)) return;

            const key = this.getKeyString(e);
            
            // Очищаем предыдущий таймаут
            if (sequenceTimeout) {
                clearTimeout(sequenceTimeout);
            }

            // Добавляем клавишу в последовательность
            sequenceKeys.push(key);

            // Проверяем совпадение
            const sequence = sequenceKeys.join(' ');
            const shortcut = this.shortcuts.get(sequence);

            if (shortcut && shortcut.enabled) {
                if (shortcut.preventDefault) {
                    e.preventDefault();
                }
                shortcut.callback(e);
                sequenceKeys = [];
                return;
            }

            // Проверяем одиночную клавишу
            const singleShortcut = this.shortcuts.get(key);
            if (singleShortcut && singleShortcut.enabled) {
                if (singleShortcut.preventDefault) {
                    e.preventDefault();
                }
                singleShortcut.callback(e);
                sequenceKeys = [];
                return;
            }

            // Сбрасываем последовательность через 1 секунду
            sequenceTimeout = setTimeout(() => {
                sequenceKeys = [];
            }, 1000);
        });
    }

    /**
     * Получение строки клавиши из события
     */
    getKeyString(e) {
        const parts = [];
        
        if (e.ctrlKey) parts.push('ctrl');
        if (e.altKey) parts.push('alt');
        if (e.shiftKey && e.key.length > 1) parts.push('shift');
        
        const key = e.key.toLowerCase();
        if (key !== 'control' && key !== 'alt' && key !== 'shift' && key !== 'meta') {
            parts.push(key);
        }

        return parts.join('+');
    }

    /**
     * Проверка, нужно ли игнорировать событие
     */
    shouldIgnoreEvent(e) {
        const target = e.target;
        const tagName = target.tagName.toLowerCase();
        
        // Игнорируем в полях ввода (кроме Escape)
        if (e.key !== 'Escape' && (
            tagName === 'input' ||
            tagName === 'textarea' ||
            tagName === 'select' ||
            target.isContentEditable
        )) {
            return true;
        }

        return false;
    }

    /**
     * Создание оверлея с подсказками
     */
    createHelpOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'keyboard-shortcuts-help';
        overlay.className = 'keyboard-shortcuts-overlay';
        overlay.style.display = 'none';
        
        overlay.innerHTML = `
            <div class="shortcuts-modal">
                <div class="shortcuts-header">
                    <h2>Горячие клавиши</h2>
                    <button class="shortcuts-close" aria-label="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="shortcuts-content">
                    ${this.renderShortcutsHelp()}
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Закрытие по клику
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('.shortcuts-close')) {
                this.toggleHelp();
            }
        });
    }

    /**
     * Рендер списка горячих клавиш
     */
    renderShortcutsHelp() {
        const categories = {
            navigation: 'Навигация',
            tasks: 'Задачи',
            filters: 'Фильтры',
            interface: 'Интерфейс',
            themes: 'Темы',
            general: 'Общее'
        };

        const grouped = {};
        
        this.shortcuts.forEach((shortcut) => {
            const category = shortcut.category || 'general';
            if (!grouped[category]) {
                grouped[category] = [];
            }
            grouped[category].push(shortcut);
        });

        let html = '';
        
        Object.entries(categories).forEach(([key, title]) => {
            if (grouped[key] && grouped[key].length > 0) {
                html += `
                    <div class="shortcuts-category">
                        <h3>${title}</h3>
                        <div class="shortcuts-list">
                            ${grouped[key].map(s => `
                                <div class="shortcut-item">
                                    <span class="shortcut-keys">${this.formatKeys(s.keys)}</span>
                                    <span class="shortcut-description">${s.description}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        });

        return html;
    }

    /**
     * Форматирование клавиш для отображения
     */
    formatKeys(keys) {
        return keys.split(' ').map(combo => {
            return combo.split('+').map(key => {
                const keyMap = {
                    'ctrl': 'Ctrl',
                    'alt': 'Alt',
                    'shift': 'Shift',
                    'enter': 'Enter',
                    'escape': 'Esc',
                    'arrowup': '↑',
                    'arrowdown': '↓',
                    'arrowleft': '←',
                    'arrowright': '→'
                };
                return `<kbd>${keyMap[key] || key.toUpperCase()}</kbd>`;
            }).join('+');
        }).join(' ');
    }

    /**
     * Переключение отображения помощи
     */
    toggleHelp() {
        this.helpVisible = !this.helpVisible;
        const overlay = document.getElementById('keyboard-shortcuts-help');
        if (overlay) {
            overlay.style.display = this.helpVisible ? 'flex' : 'none';
        }
    }

    // ============================================
    // ДЕЙСТВИЯ
    // ============================================

    openQuickSearch() {
        const searchInput = document.querySelector('#quick-search-input, [data-search-input]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        } else {
            // Открываем command palette если есть
            const commandPalette = document.querySelector('[data-command-palette]');
            if (commandPalette) {
                commandPalette.click();
            }
        }
    }

    createNewTask() {
        const createBtn = document.querySelector('[data-action="create-task"], .btn-create-task');
        if (createBtn) {
            createBtn.click();
        } else {
            window.location.href = '/tasks/new';
        }
    }

    editCurrentTask() {
        const selected = document.querySelector('.task-item.selected, .task-item:focus');
        if (selected) {
            const editBtn = selected.querySelector('[data-action="edit"]');
            if (editBtn) editBtn.click();
        }
    }

    deleteCurrentTask() {
        const selected = document.querySelector('.task-item.selected, .task-item:focus');
        if (selected) {
            const deleteBtn = selected.querySelector('[data-action="delete"]');
            if (deleteBtn && confirm('Удалить задачу?')) {
                deleteBtn.click();
            }
        }
    }

    toggleTaskComplete() {
        const selected = document.querySelector('.task-item.selected, .task-item:focus');
        if (selected) {
            const checkbox = selected.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.click();
        }
    }

    openFilters() {
        const filterBtn = document.querySelector('[data-action="filters"], .btn-filters');
        if (filterBtn) filterBtn.click();
    }

    openSort() {
        const sortBtn = document.querySelector('[data-action="sort"], .btn-sort');
        if (sortBtn) sortBtn.click();
    }

    focusSearch() {
        const searchInput = document.querySelector('input[type="search"], [data-search]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    closeModals() {
        // Закрываем все модальные окна
        document.querySelectorAll('.modal.show, [data-modal].active').forEach(modal => {
            const closeBtn = modal.querySelector('[data-dismiss="modal"], .modal-close');
            if (closeBtn) closeBtn.click();
        });

        // Закрываем помощь
        if (this.helpVisible) {
            this.toggleHelp();
        }
    }

    toggleTheme() {
        if (window.ThemeManager) {
            window.ThemeManager.cycleTheme();
        }
    }

    setTheme(theme) {
        if (window.ThemeManager) {
            window.ThemeManager.setTheme(theme);
        }
    }

    navigateNext() {
        const items = Array.from(document.querySelectorAll('.task-item, .list-item'));
        const current = document.querySelector('.task-item.selected, .list-item.selected');
        const currentIndex = items.indexOf(current);
        
        if (currentIndex < items.length - 1) {
            items[currentIndex + 1]?.focus();
            items[currentIndex + 1]?.classList.add('selected');
            current?.classList.remove('selected');
        }
    }

    navigatePrevious() {
        const items = Array.from(document.querySelectorAll('.task-item, .list-item'));
        const current = document.querySelector('.task-item.selected, .list-item.selected');
        const currentIndex = items.indexOf(current);
        
        if (currentIndex > 0) {
            items[currentIndex - 1]?.focus();
            items[currentIndex - 1]?.classList.add('selected');
            current?.classList.remove('selected');
        }
    }

    openCurrentTask() {
        const selected = document.querySelector('.task-item.selected, .task-item:focus');
        if (selected) {
            const link = selected.querySelector('a[href]');
            if (link) link.click();
        }
    }

    save(e) {
        e.preventDefault();
        const saveBtn = document.querySelector('[data-action="save"], .btn-save, button[type="submit"]');
        if (saveBtn) saveBtn.click();
    }

    undo(e) {
        e.preventDefault();
        document.execCommand('undo');
    }

    redo(e) {
        e.preventDefault();
        document.execCommand('redo');
    }

    /**
     * Загрузка пользовательских настроек
     */
    loadUserPreferences() {
        const saved = localStorage.getItem('keyboard-shortcuts-preferences');
        if (saved) {
            try {
                const prefs = JSON.parse(saved);
                this.enabled = prefs.enabled !== false;
            } catch (e) {
                console.error('Failed to load keyboard shortcuts preferences', e);
            }
        }
    }

    /**
     * Сохранение настроек
     */
    savePreferences() {
        localStorage.setItem('keyboard-shortcuts-preferences', JSON.stringify({
            enabled: this.enabled
        }));
    }

    /**
     * Включение/выключение горячих клавиш
     */
    toggle() {
        this.enabled = !this.enabled;
        this.savePreferences();
        return this.enabled;
    }
}

// Инициализация
const keyboardShortcuts = new KeyboardShortcutsManager();

// Экспорт для глобального использования
window.KeyboardShortcuts = keyboardShortcuts;

// Показываем подсказку при первом посещении
if (!localStorage.getItem('keyboard-shortcuts-hint-shown')) {
    setTimeout(() => {
        const hint = document.createElement('div');
        hint.className = 'keyboard-shortcuts-hint';
        hint.innerHTML = `
            <div class="hint-content">
                <i class="fas fa-keyboard"></i>
                <span>Нажмите <kbd>?</kbd> чтобы увидеть все горячие клавиши</span>
                <button class="hint-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(hint);
        
        localStorage.setItem('keyboard-shortcuts-hint-shown', 'true');
        
        setTimeout(() => {
            hint.style.opacity = '0';
            setTimeout(() => hint.remove(), 300);
        }, 5000);
    }, 2000);
}
