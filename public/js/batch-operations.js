/**
 * Batch Operations
 * Массовые операции над задачами
 */

class BatchOperations {
    constructor() {
        this.selectedItems = new Set();
        this.selectAllCheckbox = null;
        this.batchToolbar = null;
        this.init();
    }

    init() {
        this.createBatchToolbar();
        this.setupCheckboxes();
        this.bindEvents();
    }

    createBatchToolbar() {
        // Проверяем, есть ли уже toolbar
        if (document.getElementById('batchToolbar')) return;

        const toolbar = document.createElement('div');
        toolbar.id = 'batchToolbar';
        toolbar.className = 'batch-toolbar';
        toolbar.innerHTML = `
            <div class="batch-toolbar-content">
                <div class="batch-toolbar-info">
                    <span class="batch-count">0</span> выбрано
                </div>
                <div class="batch-toolbar-actions">
                    <button class="btn btn-sm btn-primary" data-action="assign">
                        <i class="fas fa-user"></i> Назначить
                    </button>
                    <button class="btn btn-sm btn-success" data-action="complete">
                        <i class="fas fa-check"></i> Завершить
                    </button>
                    <button class="btn btn-sm btn-warning" data-action="priority">
                        <i class="fas fa-flag"></i> Приоритет
                    </button>
                    <button class="btn btn-sm btn-info" data-action="move">
                        <i class="fas fa-folder"></i> Переместить
                    </button>
                    <button class="btn btn-sm btn-danger" data-action="delete">
                        <i class="fas fa-trash"></i> Удалить
                    </button>
                    <button class="btn btn-sm btn-secondary" data-action="cancel">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(toolbar);
        this.batchToolbar = toolbar;
        this.addStyles();
    }

    setupCheckboxes() {
        // Добавляем чекбоксы к элементам списка
        const items = document.querySelectorAll('.task-item, .table tbody tr');
        
        items.forEach(item => {
            if (!item.querySelector('.batch-checkbox')) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'batch-checkbox';
                checkbox.dataset.itemId = item.dataset.id || item.id;
                
                // Вставляем чекбокс в начало элемента
                if (item.classList.contains('task-item')) {
                    item.insertBefore(checkbox, item.firstChild);
                } else if (item.tagName === 'TR') {
                    const td = document.createElement('td');
                    td.appendChild(checkbox);
                    item.insertBefore(td, item.firstChild);
                }
            }
        });

        // Добавляем "Выбрать все"
        const table = document.querySelector('table');
        if (table && !document.querySelector('.batch-select-all')) {
            const thead = table.querySelector('thead tr');
            if (thead) {
                const th = document.createElement('th');
                th.style.width = '40px';
                
                const selectAll = document.createElement('input');
                selectAll.type = 'checkbox';
                selectAll.className = 'batch-select-all';
                
                th.appendChild(selectAll);
                thead.insertBefore(th, thead.firstChild);
                
                this.selectAllCheckbox = selectAll;
            }
        }
    }

    bindEvents() {
        // Клик по чекбоксу элемента
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('batch-checkbox')) {
                this.handleCheckboxChange(e.target);
            }
        });

        // Клик по "Выбрать все"
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', (e) => {
                this.handleSelectAll(e.target.checked);
            });
        }

        // Клики по кнопкам действий
        if (this.batchToolbar) {
            this.batchToolbar.addEventListener('click', (e) => {
                const button = e.target.closest('[data-action]');
                if (button) {
                    const action = button.dataset.action;
                    this.handleAction(action);
                }
            });
        }

        // Горячие клавиши
        document.addEventListener('keydown', (e) => {
            // Ctrl+A для выбора всех
            if (e.ctrlKey && e.key === 'a' && this.isOnListPage()) {
                e.preventDefault();
                this.handleSelectAll(true);
            }
            
            // Escape для отмены выбора
            if (e.key === 'Escape' && this.selectedItems.size > 0) {
                this.clearSelection();
            }
        });
    }

    handleCheckboxChange(checkbox) {
        const itemId = checkbox.dataset.itemId;
        
        if (checkbox.checked) {
            this.selectedItems.add(itemId);
        } else {
            this.selectedItems.delete(itemId);
        }

        this.updateToolbar();
        this.updateSelectAllCheckbox();
    }

    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.batch-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const itemId = checkbox.dataset.itemId;
            
            if (checked) {
                this.selectedItems.add(itemId);
            } else {
                this.selectedItems.delete(itemId);
            }
        });

        this.updateToolbar();
    }

    updateSelectAllCheckbox() {
        if (!this.selectAllCheckbox) return;

        const checkboxes = document.querySelectorAll('.batch-checkbox');
        const checkedCount = document.querySelectorAll('.batch-checkbox:checked').length;

        this.selectAllCheckbox.checked = checkedCount === checkboxes.length && checkboxes.length > 0;
        this.selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }

    updateToolbar() {
        const count = this.selectedItems.size;
        
        if (count > 0) {
            this.batchToolbar.classList.add('show');
            this.batchToolbar.querySelector('.batch-count').textContent = count;
        } else {
            this.batchToolbar.classList.remove('show');
        }
    }

    async handleAction(action) {
        if (this.selectedItems.size === 0) return;

        const items = Array.from(this.selectedItems);

        switch(action) {
            case 'assign':
                await this.assignTasks(items);
                break;
            case 'complete':
                await this.completeTasks(items);
                break;
            case 'priority':
                await this.changePriority(items);
                break;
            case 'move':
                await this.moveTasks(items);
                break;
            case 'delete':
                await this.deleteTasks(items);
                break;
            case 'cancel':
                this.clearSelection();
                break;
        }
    }

    async assignTasks(items) {
        // Показываем модальное окно выбора пользователя
        const userId = await this.showUserSelectModal();
        if (!userId) return;

        try {
            const response = await fetch('/api/tasks/batch-assign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tasks: items, userId })
            });

            if (response.ok) {
                this.showSuccess('Задачи назначены');
                this.clearSelection();
                window.location.reload();
            } else {
                this.showError('Ошибка при назначении задач');
            }
        } catch (error) {
            console.error('Batch assign error:', error);
            this.showError('Ошибка при назначении задач');
        }
    }

    async completeTasks(items) {
        if (!confirm(`Завершить ${items.length} задач(и)?`)) return;

        try {
            const response = await fetch('/api/tasks/batch-complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tasks: items })
            });

            if (response.ok) {
                this.showSuccess('Задачи завершены');
                this.clearSelection();
                window.location.reload();
            } else {
                this.showError('Ошибка при завершении задач');
            }
        } catch (error) {
            console.error('Batch complete error:', error);
            this.showError('Ошибка при завершении задач');
        }
    }

    async changePriority(items) {
        const priority = await this.showPrioritySelectModal();
        if (!priority) return;

        try {
            const response = await fetch('/api/tasks/batch-priority', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tasks: items, priority })
            });

            if (response.ok) {
                this.showSuccess('Приоритет изменен');
                this.clearSelection();
                window.location.reload();
            } else {
                this.showError('Ошибка при изменении приоритета');
            }
        } catch (error) {
            console.error('Batch priority error:', error);
            this.showError('Ошибка при изменении приоритета');
        }
    }

    async moveTasks(items) {
        const categoryId = await this.showCategorySelectModal();
        if (!categoryId) return;

        try {
            const response = await fetch('/api/tasks/batch-move', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tasks: items, categoryId })
            });

            if (response.ok) {
                this.showSuccess('Задачи перемещены');
                this.clearSelection();
                window.location.reload();
            } else {
                this.showError('Ошибка при перемещении задач');
            }
        } catch (error) {
            console.error('Batch move error:', error);
            this.showError('Ошибка при перемещении задач');
        }
    }

    async deleteTasks(items) {
        if (!confirm(`Удалить ${items.length} задач(и)? Это действие нельзя отменить.`)) return;

        try {
            const response = await fetch('/api/tasks/batch-delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tasks: items })
            });

            if (response.ok) {
                this.showSuccess('Задачи удалены');
                this.clearSelection();
                window.location.reload();
            } else {
                this.showError('Ошибка при удалении задач');
            }
        } catch (error) {
            console.error('Batch delete error:', error);
            this.showError('Ошибка при удалении задач');
        }
    }

    clearSelection() {
        this.selectedItems.clear();
        document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = false);
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.checked = false;
            this.selectAllCheckbox.indeterminate = false;
        }
        this.updateToolbar();
    }

    isOnListPage() {
        return document.querySelector('.task-item, table tbody tr') !== null;
    }

    showSuccess(message) {
        if (window.showToast) {
            window.showToast(message, 'success');
        } else {
            alert(message);
        }
    }

    showError(message) {
        if (window.showToast) {
            window.showToast(message, 'error');
        } else {
            alert(message);
        }
    }

    // Модальные окна для выбора
    showUserSelectModal() {
        return new Promise((resolve) => {
            // Простая реализация - можно улучшить
            const userId = prompt('Введите ID пользователя:');
            resolve(userId);
        });
    }

    showPrioritySelectModal() {
        return new Promise((resolve) => {
            const priority = prompt('Выберите приоритет (low/medium/high):');
            resolve(priority);
        });
    }

    showCategorySelectModal() {
        return new Promise((resolve) => {
            const categoryId = prompt('Введите ID категории:');
            resolve(categoryId);
        });
    }

    addStyles() {
        if (document.getElementById('batchOperationsStyles')) return;

        const style = document.createElement('style');
        style.id = 'batchOperationsStyles';
        style.textContent = `
            .batch-toolbar {
                position: fixed;
                bottom: -100px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                padding: 1rem 1.5rem;
                z-index: 1000;
                transition: bottom 0.3s ease;
                min-width: 600px;
            }

            .batch-toolbar.show {
                bottom: 30px;
            }

            .batch-toolbar-content {
                display: flex;
                align-items: center;
                gap: 1.5rem;
            }

            .batch-toolbar-info {
                font-weight: 600;
                color: var(--text-primary);
            }

            .batch-count {
                color: var(--primary);
                font-size: 1.125rem;
            }

            .batch-toolbar-actions {
                display: flex;
                gap: 0.5rem;
            }

            .batch-checkbox {
                width: 18px;
                height: 18px;
                cursor: pointer;
                margin-right: 0.75rem;
            }

            .batch-select-all {
                width: 18px;
                height: 18px;
                cursor: pointer;
            }

            @media (max-width: 768px) {
                .batch-toolbar {
                    min-width: auto;
                    width: calc(100% - 40px);
                    left: 20px;
                    transform: none;
                }

                .batch-toolbar-content {
                    flex-direction: column;
                    gap: 1rem;
                }

                .batch-toolbar-actions {
                    flex-wrap: wrap;
                    justify-content: center;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    // Инициализируем только на страницах со списками
    if (document.querySelector('.task-item, table tbody tr')) {
        window.batchOperations = new BatchOperations();
    }
});

// Экспорт
window.BatchOperations = BatchOperations;
