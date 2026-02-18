/**
 * Command Palette
 * Палитра команд для быстрого доступа к функциям
 */

class CommandPalette {
    constructor() {
        this.isOpen = false;
        this.modal = null;
        this.input = null;
        this.results = null;
        this.commands = [];
        this.selectedIndex = 0;
        this.init();
    }

    init() {
        this.registerCommands();
        this.createModal();
        this.bindEvents();
    }

    registerCommands() {
        this.commands = [
            // Навигация
            { icon: 'fa-home', label: 'Перейти на главную', action: () => window.location.href = '/', keywords: ['главная', 'home', 'dashboard'] },
            { icon: 'fa-tasks', label: 'Открыть задачи', action: () => window.location.href = '/task', keywords: ['задачи', 'tasks'] },
            { icon: 'fa-columns', label: 'Открыть канбан', action: () => window.location.href = '/kanban', keywords: ['канбан', 'kanban', 'доска'] },
            { icon: 'fa-calendar', label: 'Открыть календарь', action: () => window.location.href = '/calendar', keywords: ['календарь', 'calendar'] },
            { icon: 'fa-user', label: 'Открыть профиль', action: () => window.location.href = '/profile', keywords: ['профиль', 'profile'] },
            { icon: 'fa-cog', label: 'Открыть настройки', action: () => window.location.href = '/settings', keywords: ['настройки', 'settings'] },
            
            // Действия
            { icon: 'fa-plus', label: 'Создать задачу', action: () => {
                const btn = document.getElementById('quick-task-fab');
                if (btn) btn.click();
            }, keywords: ['создать', 'новая', 'задача', 'new', 'task'] },
            
            { icon: 'fa-search', label: 'Поиск', action: () => {
                const search = document.querySelector('input[type="search"]');
                if (search) search.focus();
            }, keywords: ['поиск', 'search', 'найти'] },
            
            // Темы
            { icon: 'fa-sun', label: 'Светлая тема', action: () => {
                if (window.themeManager) window.themeManager.setTheme('light');
            }, keywords: ['светлая', 'тема', 'light', 'theme'] },
            
            { icon: 'fa-moon', label: 'Тёмная тема', action: () => {
                if (window.themeManager) window.themeManager.setTheme('dark');
            }, keywords: ['тёмная', 'тема', 'dark', 'theme'] },
            
            // Режимы
            { icon: 'fa-eye-slash', label: 'Режим фокусировки', action: () => {
                if (window.focusMode) window.focusMode.toggle();
            }, keywords: ['фокус', 'focus', 'режим'] },
            
            { icon: 'fa-expand', label: 'Полноэкранный режим', action: () => {
                if (window.fullscreenMode) window.fullscreenMode.toggle();
            }, keywords: ['полный', 'экран', 'fullscreen'] },
            
            // Голосовые команды
            { icon: 'fa-microphone', label: 'Голосовые команды', action: () => {
                if (window.voiceCommands) window.voiceCommands.toggle();
            }, keywords: ['голос', 'voice', 'микрофон'] },
            
            // Тур
            { icon: 'fa-route', label: 'Начать тур', action: () => {
                if (window.tourGuide) window.tourGuide.startDashboardTour();
            }, keywords: ['тур', 'tour', 'помощь', 'help'] },
            
            // Экспорт
            { icon: 'fa-download', label: 'Экспорт данных', action: () => {
                if (window.showToast) window.showToast('Выберите таблицу для экспорта', 'info');
            }, keywords: ['экспорт', 'export', 'скачать'] },
            
            // Печать
            { icon: 'fa-print', label: 'Печать страницы', action: () => {
                window.print();
            }, keywords: ['печать', 'print'] }
        ];
    }

    createModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'command-palette-modal';
        this.modal.style.display = 'none';
        this.modal.innerHTML = `
            <div class="command-palette-content">
                <div class="command-palette-header">
                    <i class="fas fa-terminal"></i>
                    <input type="text" class="command-palette-input" placeholder="Введите команду...">
                </div>
                <div class="command-palette-results"></div>
                <div class="command-palette-footer">
                    <span><kbd>↑↓</kbd> Навигация</span>
                    <span><kbd>Enter</kbd> Выбрать</span>
                    <span><kbd>Esc</kbd> Закрыть</span>
                </div>
            </div>
        `;

        document.body.appendChild(this.modal);
        
        this.input = this.modal.querySelector('.command-palette-input');
        this.results = this.modal.querySelector('.command-palette-results');
        
        this.addStyles();
    }

    bindEvents() {
        // Ctrl+K или Cmd+K для открытия
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.toggle();
            }
            
            // ESC для закрытия
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Поиск при вводе
        this.input.addEventListener('input', (e) => {
            this.search(e.target.value);
        });

        // Навигация клавишами
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.selectNext();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.selectPrevious();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.executeSelected();
            }
        });

        // Клик вне модального окна
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.modal.style.display = 'flex';
        this.input.value = '';
        this.input.focus();
        this.search('');
    }

    close() {
        this.isOpen = false;
        this.modal.style.display = 'none';
    }

    search(query) {
        const lowerQuery = query.toLowerCase();
        
        let filtered = this.commands;
        
        if (query) {
            filtered = this.commands.filter(cmd => {
                return cmd.label.toLowerCase().includes(lowerQuery) ||
                       cmd.keywords.some(k => k.includes(lowerQuery));
            });
        }

        this.renderResults(filtered);
        this.selectedIndex = 0;
        this.updateSelection();
    }

    renderResults(commands) {
        if (commands.length === 0) {
            this.results.innerHTML = '<div class="command-palette-empty">Команды не найдены</div>';
            return;
        }

        this.results.innerHTML = commands.map((cmd, index) => `
            <div class="command-palette-item" data-index="${index}">
                <i class="fas ${cmd.icon}"></i>
                <span>${cmd.label}</span>
            </div>
        `).join('');

        // Обработчики кликов
        this.results.querySelectorAll('.command-palette-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.selectedIndex = index;
                this.executeSelected();
            });
        });
    }

    selectNext() {
        const items = this.results.querySelectorAll('.command-palette-item');
        if (items.length === 0) return;
        
        this.selectedIndex = (this.selectedIndex + 1) % items.length;
        this.updateSelection();
    }

    selectPrevious() {
        const items = this.results.querySelectorAll('.command-palette-item');
        if (items.length === 0) return;
        
        this.selectedIndex = (this.selectedIndex - 1 + items.length) % items.length;
        this.updateSelection();
    }

    updateSelection() {
        const items = this.results.querySelectorAll('.command-palette-item');
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    executeSelected() {
        const query = this.input.value.toLowerCase();
        let commands = this.commands;
        
        if (query) {
            commands = this.commands.filter(cmd => {
                return cmd.label.toLowerCase().includes(query) ||
                       cmd.keywords.some(k => k.includes(query));
            });
        }

        const command = commands[this.selectedIndex];
        if (command && command.action) {
            command.action();
            this.close();
        }
    }

    addStyles() {
        if (document.getElementById('commandPaletteStyles')) return;

        const style = document.createElement('style');
        style.id = 'commandPaletteStyles';
        style.textContent = `
            .command-palette-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding-top: 10vh;
                z-index: 10007;
                animation: fadeIn 0.2s ease;
            }

            .command-palette-content {
                background: var(--bg-card);
                border-radius: 12px;
                width: 90%;
                max-width: 600px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                overflow: hidden;
            }

            .command-palette-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .command-palette-header i {
                color: var(--primary);
                font-size: 1.25rem;
            }

            .command-palette-input {
                flex: 1;
                border: none;
                background: none;
                font-size: 1rem;
                color: var(--text-primary);
                outline: none;
            }

            .command-palette-results {
                max-height: 400px;
                overflow-y: auto;
            }

            .command-palette-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 0.875rem 1.5rem;
                cursor: pointer;
                transition: background 0.15s ease;
            }

            .command-palette-item:hover,
            .command-palette-item.selected {
                background: var(--bg-hover);
            }

            .command-palette-item i {
                width: 20px;
                text-align: center;
                color: var(--text-secondary);
            }

            .command-palette-item span {
                color: var(--text-primary);
            }

            .command-palette-empty {
                padding: 2rem;
                text-align: center;
                color: var(--text-secondary);
            }

            .command-palette-footer {
                display: flex;
                gap: 1.5rem;
                padding: 0.75rem 1.5rem;
                border-top: 1px solid var(--border);
                font-size: 0.75rem;
                color: var(--text-secondary);
            }

            .command-palette-footer kbd {
                background: var(--bg-body);
                padding: 0.125rem 0.375rem;
                border-radius: 4px;
                font-family: monospace;
                font-size: 0.75rem;
            }

            @media (max-width: 768px) {
                .command-palette-content {
                    width: 95%;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.commandPalette = new CommandPalette();
});

window.CommandPalette = CommandPalette;
