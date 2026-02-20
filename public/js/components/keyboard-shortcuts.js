/**
 * Advanced Keyboard Shortcuts
 * Расширенная система горячих клавиш
 */

class KeyboardShortcuts {
    constructor() {
        this.shortcuts = new Map();
        this.commandPalette = null;
        this.isCommandPaletteOpen = false;
        this.recentCommands = this.loadRecentCommands();
        this.init();
    }

    init() {
        this.registerDefaultShortcuts();
        this.createCommandPalette();
        this.bindEvents();
        this.loadCustomShortcuts();
    }

    /**
     * Регистрация стандартных горячих клавиш
     */
    registerDefaultShortcuts() {
        // Навигация
        this.register('g d', 'Перейти на панель управления', () => {
            window.location.href = '/dashboard';
        }, 'navigation');

        this.register('g t', 'Перейти к задачам', () => {
            window.location.href = '/task';
        }, 'navigation');

        this.register('g k', 'Перейти к канбан доске', () => {
            window.location.href = '/kanban';
        }, 'navigation');

        this.register('g c', 'Перейти к календарю', () => {
            window.location.href = '/calendar';
        }, 'navigation');

        this.register('g p', 'Перейти к профилю', () => {
            window.location.href = '/profile';
        }, 'navigation');

        // Действия
        this.register('n', 'Создать новую задачу', () => {
            const fab = document.getElementById('quick-task-fab');
            if (fab) fab.click();
        }, 'actions');

        this.register('/', 'Поиск', () => {
            const search = document.querySelector('[data-quick-search]');
            if (search) search.focus();
        }, 'actions');

        this.register('?', 'Показать горячие клавиши', () => {
            this.showHelp();
        }, 'help');

        // Command Palette
        this.register('ctrl+k', 'Открыть командную палитру', (e) => {
            e.preventDefault();
            this.toggleCommandPalette();
        }, 'system');

        this.register('cmd+k', 'Открыть командную палитру', (e) => {
            e.preventDefault();
            this.toggleCommandPalette();
        }, 'system');

        // Редактирование
        this.register('ctrl+s', 'Сохранить', (e) => {
            e.preventDefault();
            const saveBtn = document.querySelector('[data-save-btn], button[type="submit"]');
            if (saveBtn) saveBtn.click();
        }, 'editing');

        this.register('cmd+s', 'Сохранить', (e) => {
            e.preventDefault();
            const saveBtn = document.querySelector('[data-save-btn], button[type="submit"]');
            if (saveBtn) saveBtn.click();
        }, 'editing');

        this.register('esc', 'Закрыть модальное окно', () => {
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }
        }, 'system');

        // Выбор
        this.register('ctrl+a', 'Выбрать все', (e) => {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const checkboxes = document.querySelectorAll('[data-item-checkbox]');
                checkboxes.forEach(cb => cb.checked = true);
                window.dispatchEvent(new Event('selectionchange'));
            }
        }, 'selection');

        // Тема
        this.register('ctrl+shift+t', 'Переключить тему', (e) => {
            e.preventDefault();
            const themeBtn = document.querySelector('.theme-toggle-btn');
            if (themeBtn) themeBtn.click();
        }, 'system');
    }

    /**
     * Регистрация горячей клавиши
     */
    register(key, description, callback, category = 'general') {
        const normalizedKey = this.normalizeKey(key);
        this.shortcuts.set(normalizedKey, {
            key: normalizedKey,
            originalKey: key,
            description,
            callback,
            category,
            enabled: true
        });
    }

    /**
     * Нормализация клавиши
     */
    normalizeKey(key) {
        return key.toLowerCase()
            .replace('command', 'cmd')
            .replace('control', 'ctrl')
            .trim();
    }

    /**
     * Привязка событий
     */
    bindEvents() {
        let keySequence = [];
        let sequenceTimer = null;

        document.addEventListener('keydown', (e) => {
            // Игнорируем если фокус на input/textarea (кроме специальных клавиш)
            if ((e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && 
                !e.ctrlKey && !e.metaKey && !e.altKey && e.key !== 'Escape') {
                return;
            }

            // Формируем строку клавиши
            const parts = [];
            if (e.ctrlKey) parts.push('ctrl');
            if (e.metaKey) parts.push('cmd');
            if (e.altKey) parts.push('alt');
            if (e.shiftKey && e.key.length > 1) parts.push('shift');
            
            const key = e.key.toLowerCase();
            if (key !== 'control' && key !== 'meta' && key !== 'alt' && key !== 'shift') {
                parts.push(key);
            }

            const keyString = parts.join('+');

            // Проверяем прямое совпадение
            const directMatch = this.shortcuts.get(keyString);
            if (directMatch && directMatch.enabled) {
                directMatch.callback(e);
                this.addToRecentCommands(directMatch);
                return;
            }

            // Обработка последовательностей (например, "g d")
            if (!e.ctrlKey && !e.metaKey && !e.altKey && key.length === 1) {
                keySequence.push(key);
                
                clearTimeout(sequenceTimer);
                sequenceTimer = setTimeout(() => {
                    keySequence = [];
                }, 1000);

                const sequence = keySequence.join(' ');
                const sequenceMatch = this.shortcuts.get(sequence);
                
                if (sequenceMatch && sequenceMatch.enabled) {
                    e.preventDefault();
                    sequenceMatch.callback(e);
                    this.addToRecentCommands(sequenceMatch);
                    keySequence = [];
                    clearTimeout(sequenceTimer);
                }
            }
        });
    }

    /**
     * Создать командную палитру
     */
    createCommandPalette() {
        const palette = document.createElement('div');
        palette.id = 'command-palette';
        palette.className = 'command-palette';
        palette.innerHTML = `
            <div class="command-palette-backdrop"></div>
            <div class="command-palette-container">
                <div class="command-palette-header">
                    <input type="text" 
                           class="command-palette-input" 
                           placeholder="Введите команду или поиск..."
                           autocomplete="off">
                </div>
                <div class="command-palette-content">
                    <div class="command-palette-section">
                        <div class="command-palette-section-title">Недавние команды</div>
                        <div class="command-palette-recent"></div>
                    </div>
                    <div class="command-palette-section">
                        <div class="command-palette-section-title">Все команды</div>
                        <div class="command-palette-commands"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(palette);
        this.commandPalette = palette;

        this.addCommandPaletteStyles();
        this.setupCommandPaletteEvents();
    }

    /**
     * Добавить стили командной палитры
     */
    addCommandPaletteStyles() {
        if (document.getElementById('commandPaletteStyles')) return;

        const style = document.createElement('style');
        style.id = 'commandPaletteStyles';
        style.textContent = `
            .command-palette {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 9999;
                display: none;
            }

            .command-palette.show {
                display: block;
                animation: fadeIn 0.2s ease-out;
            }

            .command-palette-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
            }

            .command-palette-container {
                position: absolute;
                top: 20%;
                left: 50%;
                transform: translateX(-50%);
                width: 90%;
                max-width: 600px;
                background: var(--bg-card);
                border-radius: var(--radius-lg);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                overflow: hidden;
            }

            .command-palette-header {
                padding: 1rem;
                border-bottom: 1px solid var(--border);
            }

            .command-palette-input {
                width: 100%;
                padding: 0.75rem;
                border: none;
                background: var(--bg-body);
                color: var(--text-primary);
                font-size: 1rem;
                border-radius: var(--radius);
                outline: none;
            }

            .command-palette-input:focus {
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .command-palette-content {
                max-height: 400px;
                overflow-y: auto;
                padding: 0.5rem;
            }

            .command-palette-section {
                margin-bottom: 1rem;
            }

            .command-palette-section-title {
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--text-muted);
                text-transform: uppercase;
                padding: 0.5rem 0.75rem;
                letter-spacing: 0.5px;
            }

            .command-palette-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.75rem;
                border-radius: var(--radius);
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .command-palette-item:hover,
            .command-palette-item.selected {
                background: var(--primary);
                color: white;
            }

            .command-palette-item-left {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .command-palette-item-icon {
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(102, 126, 234, 0.1);
                border-radius: var(--radius);
                font-size: 0.875rem;
            }

            .command-palette-item:hover .command-palette-item-icon {
                background: rgba(255, 255, 255, 0.2);
            }

            .command-palette-item-info {
                display: flex;
                flex-direction: column;
            }

            .command-palette-item-title {
                font-weight: 500;
                font-size: 0.875rem;
            }

            .command-palette-item-category {
                font-size: 0.75rem;
                opacity: 0.7;
            }

            .command-palette-item-shortcut {
                display: flex;
                gap: 0.25rem;
            }

            .command-palette-key {
                padding: 0.125rem 0.5rem;
                background: rgba(0, 0, 0, 0.1);
                border-radius: 4px;
                font-size: 0.75rem;
                font-family: monospace;
            }

            .command-palette-item:hover .command-palette-key {
                background: rgba(255, 255, 255, 0.2);
            }

            .command-palette-empty {
                padding: 2rem;
                text-align: center;
                color: var(--text-muted);
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить события командной палитры
     */
    setupCommandPaletteEvents() {
        const input = this.commandPalette.querySelector('.command-palette-input');
        const backdrop = this.commandPalette.querySelector('.command-palette-backdrop');

        // Закрытие по клику на backdrop
        backdrop.addEventListener('click', () => {
            this.closeCommandPalette();
        });

        // Поиск команд
        input.addEventListener('input', (e) => {
            this.filterCommands(e.target.value);
        });

        // Навигация клавишами
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeCommandPalette();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.selectNextCommand();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.selectPreviousCommand();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.executeSelectedCommand();
            }
        });
    }

    /**
     * Переключить командную палитру
     */
    toggleCommandPalette() {
        if (this.isCommandPaletteOpen) {
            this.closeCommandPalette();
        } else {
            this.openCommandPalette();
        }
    }

    /**
     * Открыть командную палитру
     */
    openCommandPalette() {
        this.commandPalette.classList.add('show');
        this.isCommandPaletteOpen = true;
        
        const input = this.commandPalette.querySelector('.command-palette-input');
        input.value = '';
        input.focus();

        this.renderCommands();
    }

    /**
     * Закрыть командную палитру
     */
    closeCommandPalette() {
        this.commandPalette.classList.remove('show');
        this.isCommandPaletteOpen = false;
    }

    /**
     * Отрисовать команды
     */
    renderCommands(filter = '') {
        const recentContainer = this.commandPalette.querySelector('.command-palette-recent');
        const commandsContainer = this.commandPalette.querySelector('.command-palette-commands');

        // Недавние команды
        if (this.recentCommands.length > 0 && !filter) {
            recentContainer.innerHTML = this.recentCommands
                .slice(0, 5)
                .map(cmd => this.renderCommandItem(cmd))
                .join('');
        } else {
            recentContainer.innerHTML = '';
        }

        // Все команды
        const commands = Array.from(this.shortcuts.values())
            .filter(cmd => {
                if (!filter) return true;
                const searchStr = filter.toLowerCase();
                return cmd.description.toLowerCase().includes(searchStr) ||
                       cmd.category.toLowerCase().includes(searchStr);
            });

        if (commands.length > 0) {
            commandsContainer.innerHTML = commands
                .map(cmd => this.renderCommandItem(cmd))
                .join('');
        } else {
            commandsContainer.innerHTML = '<div class="command-palette-empty">Команды не найдены</div>';
        }

        // Привязываем события клика
        this.commandPalette.querySelectorAll('.command-palette-item').forEach(item => {
            item.addEventListener('click', () => {
                const key = item.dataset.key;
                const command = this.shortcuts.get(key);
                if (command) {
                    command.callback(new Event('click'));
                    this.addToRecentCommands(command);
                    this.closeCommandPalette();
                }
            });
        });
    }

    /**
     * Отрисовать элемент команды
     */
    renderCommandItem(command) {
        const icon = this.getCategoryIcon(command.category);
        const keys = command.originalKey.split('+').map(k => 
            `<span class="command-palette-key">${k}</span>`
        ).join('');

        return `
            <div class="command-palette-item" data-key="${command.key}">
                <div class="command-palette-item-left">
                    <div class="command-palette-item-icon">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="command-palette-item-info">
                        <div class="command-palette-item-title">${command.description}</div>
                        <div class="command-palette-item-category">${command.category}</div>
                    </div>
                </div>
                <div class="command-palette-item-shortcut">${keys}</div>
            </div>
        `;
    }

    /**
     * Получить иконку категории
     */
    getCategoryIcon(category) {
        const icons = {
            'navigation': 'compass',
            'actions': 'bolt',
            'editing': 'edit',
            'selection': 'check-square',
            'system': 'cog',
            'help': 'question-circle'
        };
        return icons[category] || 'star';
    }

    /**
     * Фильтровать команды
     */
    filterCommands(query) {
        this.renderCommands(query);
    }

    /**
     * Выбрать следующую команду
     */
    selectNextCommand() {
        const items = this.commandPalette.querySelectorAll('.command-palette-item');
        const selected = this.commandPalette.querySelector('.command-palette-item.selected');
        
        if (!selected && items.length > 0) {
            items[0].classList.add('selected');
            items[0].scrollIntoView({ block: 'nearest' });
        } else if (selected) {
            const index = Array.from(items).indexOf(selected);
            if (index < items.length - 1) {
                selected.classList.remove('selected');
                items[index + 1].classList.add('selected');
                items[index + 1].scrollIntoView({ block: 'nearest' });
            }
        }
    }

    /**
     * Выбрать предыдущую команду
     */
    selectPreviousCommand() {
        const items = this.commandPalette.querySelectorAll('.command-palette-item');
        const selected = this.commandPalette.querySelector('.command-palette-item.selected');
        
        if (selected) {
            const index = Array.from(items).indexOf(selected);
            if (index > 0) {
                selected.classList.remove('selected');
                items[index - 1].classList.add('selected');
                items[index - 1].scrollIntoView({ block: 'nearest' });
            }
        }
    }

    /**
     * Выполнить выбранную команду
     */
    executeSelectedCommand() {
        const selected = this.commandPalette.querySelector('.command-palette-item.selected');
        if (selected) {
            selected.click();
        } else {
            const firstItem = this.commandPalette.querySelector('.command-palette-item');
            if (firstItem) firstItem.click();
        }
    }

    /**
     * Добавить в недавние команды
     */
    addToRecentCommands(command) {
        // Удаляем если уже есть
        this.recentCommands = this.recentCommands.filter(c => c.key !== command.key);
        
        // Добавляем в начало
        this.recentCommands.unshift(command);
        
        // Ограничиваем до 10
        this.recentCommands = this.recentCommands.slice(0, 10);
        
        // Сохраняем
        this.saveRecentCommands();
    }

    /**
     * Загрузить недавние команды
     */
    loadRecentCommands() {
        try {
            const stored = localStorage.getItem('recentCommands');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Сохранить недавние команды
     */
    saveRecentCommands() {
        try {
            localStorage.setItem('recentCommands', JSON.stringify(this.recentCommands));
        } catch (e) {
            console.error('Failed to save recent commands:', e);
        }
    }

    /**
     * Загрузить пользовательские горячие клавиши
     */
    loadCustomShortcuts() {
        try {
            const stored = localStorage.getItem('customShortcuts');
            if (stored) {
                const custom = JSON.parse(stored);
                custom.forEach(shortcut => {
                    this.register(
                        shortcut.key,
                        shortcut.description,
                        new Function(shortcut.callback),
                        shortcut.category
                    );
                });
            }
        } catch (e) {
            console.error('Failed to load custom shortcuts:', e);
        }
    }

    /**
     * Показать справку
     */
    showHelp() {
        const categories = {};
        
        this.shortcuts.forEach(shortcut => {
            if (!categories[shortcut.category]) {
                categories[shortcut.category] = [];
            }
            categories[shortcut.category].push(shortcut);
        });

        const html = Object.entries(categories).map(([category, shortcuts]) => `
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-3">${category}</h6>
                <div class="row">
                    ${shortcuts.map(s => `
                        <div class="col-md-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>${s.description}</span>
                                <kbd class="kbd-shortcut">${s.originalKey}</kbd>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-keyboard me-2"></i>
                            Горячие клавиши
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${html}
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Нажмите <kbd>Ctrl+K</kbd> или <kbd>Cmd+K</kbd> чтобы открыть командную палитру
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.keyboardShortcuts = new KeyboardShortcuts();
    });
} else {
    window.keyboardShortcuts = new KeyboardShortcuts();
}

// Экспорт
window.KeyboardShortcuts = KeyboardShortcuts;
