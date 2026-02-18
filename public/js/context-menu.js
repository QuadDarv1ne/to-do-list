/**
 * Context Menu
 * Контекстное меню для элементов
 */

class ContextMenu {
    constructor() {
        this.menu = null;
        this.currentTarget = null;
        this.actions = new Map();
        this.init();
    }

    init() {
        this.createMenu();
        this.registerDefaultActions();
        this.bindEvents();
    }

    createMenu() {
        this.menu = document.createElement('div');
        this.menu.className = 'context-menu';
        this.menu.style.display = 'none';
        document.body.appendChild(this.menu);
        this.addStyles();
    }

    registerDefaultActions() {
        // Действия для задач
        this.registerAction('task', [
            {
                icon: 'fa-eye',
                label: 'Просмотреть',
                action: (target) => {
                    const id = target.dataset.id;
                    if (id) window.location.href = `/task/${id}`;
                }
            },
            {
                icon: 'fa-edit',
                label: 'Редактировать',
                action: (target) => {
                    const id = target.dataset.id;
                    if (id) window.location.href = `/task/${id}/edit`;
                }
            },
            {
                icon: 'fa-check',
                label: 'Завершить',
                action: (target) => {
                    this.completeTask(target);
                }
            },
            {
                icon: 'fa-copy',
                label: 'Дублировать',
                action: (target) => {
                    this.duplicateTask(target);
                }
            },
            { divider: true },
            {
                icon: 'fa-trash',
                label: 'Удалить',
                className: 'danger',
                action: (target) => {
                    this.deleteTask(target);
                }
            }
        ]);

        // Действия для текста
        this.registerAction('text', [
            {
                icon: 'fa-copy',
                label: 'Копировать',
                action: () => {
                    document.execCommand('copy');
                }
            },
            {
                icon: 'fa-cut',
                label: 'Вырезать',
                action: () => {
                    document.execCommand('cut');
                }
            },
            {
                icon: 'fa-paste',
                label: 'Вставить',
                action: () => {
                    document.execCommand('paste');
                }
            }
        ]);
    }

    registerAction(type, actions) {
        this.actions.set(type, actions);
    }

    bindEvents() {
        // Контекстное меню для задач
        document.addEventListener('contextmenu', (e) => {
            const taskItem = e.target.closest('.task-item, [data-context="task"]');
            
            if (taskItem) {
                e.preventDefault();
                this.show(e.clientX, e.clientY, taskItem, 'task');
                return;
            }

            // Контекстное меню для выделенного текста
            const selection = window.getSelection();
            if (selection.toString().length > 0) {
                e.preventDefault();
                this.show(e.clientX, e.clientY, e.target, 'text');
                return;
            }
        });

        // Закрытие меню
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.context-menu')) {
                this.hide();
            }
        });

        document.addEventListener('scroll', () => {
            this.hide();
        });

        // ESC для закрытия
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hide();
            }
        });
    }

    show(x, y, target, type) {
        this.currentTarget = target;
        const actions = this.actions.get(type);
        
        if (!actions) return;

        this.menu.innerHTML = actions.map(action => {
            if (action.divider) {
                return '<div class="context-menu-divider"></div>';
            }
            
            return `
                <div class="context-menu-item ${action.className || ''}" data-action="${action.label}">
                    <i class="fas ${action.icon}"></i>
                    <span>${action.label}</span>
                </div>
            `;
        }).join('');

        // Позиционирование
        this.menu.style.display = 'block';
        const rect = this.menu.getBoundingClientRect();
        
        // Проверяем границы экрана
        if (x + rect.width > window.innerWidth) {
            x = window.innerWidth - rect.width - 10;
        }
        if (y + rect.height > window.innerHeight) {
            y = window.innerHeight - rect.height - 10;
        }

        this.menu.style.left = `${x}px`;
        this.menu.style.top = `${y}px`;

        // Обработчики кликов
        this.menu.querySelectorAll('.context-menu-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                const action = actions.filter(a => !a.divider)[
                    Array.from(this.menu.querySelectorAll('.context-menu-item')).indexOf(item)
                ];
                if (action && action.action) {
                    action.action(this.currentTarget);
                }
                this.hide();
            });
        });
    }

    hide() {
        this.menu.style.display = 'none';
        this.currentTarget = null;
    }

    async completeTask(target) {
        const id = target.dataset.id;
        if (!id) return;

        try {
            const response = await fetch(`/api/task/${id}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                if (window.showToast) {
                    window.showToast('Задача завершена', 'success');
                }
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (error) {
            console.error('Complete task error:', error);
        }
    }

    async duplicateTask(target) {
        const id = target.dataset.id;
        if (!id) return;

        try {
            const response = await fetch(`/api/task/${id}/duplicate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                if (window.showToast) {
                    window.showToast('Задача дублирована', 'success');
                }
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (error) {
            console.error('Duplicate task error:', error);
        }
    }

    async deleteTask(target) {
        const id = target.dataset.id;
        if (!id) return;

        if (!confirm('Удалить задачу?')) return;

        try {
            const response = await fetch(`/api/task/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                if (window.showToast) {
                    window.showToast('Задача удалена', 'success');
                }
                target.remove();
            }
        } catch (error) {
            console.error('Delete task error:', error);
        }
    }

    addStyles() {
        if (document.getElementById('contextMenuStyles')) return;

        const style = document.createElement('style');
        style.id = 'contextMenuStyles';
        style.textContent = `
            .context-menu {
                position: fixed;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                padding: 0.5rem 0;
                min-width: 200px;
                z-index: 10005;
                animation: contextMenuShow 0.15s ease;
            }

            @keyframes contextMenuShow {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .context-menu-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.625rem 1rem;
                cursor: pointer;
                color: var(--text-primary);
                transition: background 0.15s ease;
            }

            .context-menu-item:hover {
                background: var(--bg-hover);
            }

            .context-menu-item.danger {
                color: var(--danger);
            }

            .context-menu-item.danger:hover {
                background: rgba(220, 53, 69, 0.1);
            }

            .context-menu-item i {
                width: 16px;
                text-align: center;
            }

            .context-menu-divider {
                height: 1px;
                background: var(--border);
                margin: 0.5rem 0;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.contextMenu = new ContextMenu();
});

window.ContextMenu = ContextMenu;
