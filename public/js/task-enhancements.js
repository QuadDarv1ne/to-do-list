/**
 * Улучшения для страницы задач
 * Фильтрация, сортировка, групповые действия
 */

class TaskEnhancements {
    constructor() {
        this.selectedTasks = new Set();
        this.init();
    }

    init() {
        this.initFilters();
        this.initSort();
        this.initBulkActions();
        this.initQuickView();
        this.initDragDrop();
        this.initKeyboardShortcuts();
    }

    /**
     * Фильтрация задач
     */
    initFilters() {
        const filterButtons = document.querySelectorAll('[data-filter]');
        
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const filter = btn.getAttribute('data-filter');
                this.filterTasks(filter);
                
                // Обновляем активную кнопку
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        // Поиск в реальном времени
        const searchInput = document.querySelector('[data-task-search]');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.searchTasks(e.target.value);
                }, 300);
            });
        }
    }

    filterTasks(filter) {
        const tasks = document.querySelectorAll('[data-task-item]');
        
        tasks.forEach(task => {
            const status = task.getAttribute('data-task-status');
            const priority = task.getAttribute('data-task-priority');
            
            let show = false;
            
            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'pending':
                case 'in_progress':
                case 'completed':
                    show = status === filter;
                    break;
                case 'high':
                case 'medium':
                case 'low':
                    show = priority === filter;
                    break;
            }
            
            task.style.display = show ? '' : 'none';
            
            // Анимация появления
            if (show) {
                task.style.animation = 'fadeInUp 0.3s ease';
            }
        });
    }

    searchTasks(query) {
        const tasks = document.querySelectorAll('[data-task-item]');
        const lowerQuery = query.toLowerCase();
        
        tasks.forEach(task => {
            const title = task.querySelector('[data-task-title]')?.textContent.toLowerCase() || '';
            const description = task.querySelector('[data-task-description]')?.textContent.toLowerCase() || '';
            
            const matches = title.includes(lowerQuery) || description.includes(lowerQuery);
            task.style.display = matches ? '' : 'none';
        });
    }

    /**
     * Сортировка задач
     */
    initSort() {
        const sortButtons = document.querySelectorAll('[data-sort]');
        
        sortButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const sortBy = btn.getAttribute('data-sort');
                const order = btn.getAttribute('data-order') || 'asc';
                
                this.sortTasks(sortBy, order);
                
                // Переключаем порядок
                btn.setAttribute('data-order', order === 'asc' ? 'desc' : 'asc');
                
                // Обновляем иконку
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = order === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                }
            });
        });
    }

    sortTasks(sortBy, order) {
        const container = document.querySelector('[data-tasks-container]');
        if (!container) return;
        
        const tasks = Array.from(container.querySelectorAll('[data-task-item]'));
        
        tasks.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortBy) {
                case 'title':
                    aValue = a.querySelector('[data-task-title]')?.textContent || '';
                    bValue = b.querySelector('[data-task-title]')?.textContent || '';
                    break;
                case 'priority':
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    aValue = priorityOrder[a.getAttribute('data-task-priority')] || 0;
                    bValue = priorityOrder[b.getAttribute('data-task-priority')] || 0;
                    break;
                case 'date':
                    aValue = new Date(a.getAttribute('data-task-date') || 0);
                    bValue = new Date(b.getAttribute('data-task-date') || 0);
                    break;
            }
            
            if (order === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        
        // Перестраиваем DOM
        tasks.forEach(task => container.appendChild(task));
    }

    /**
     * Групповые действия
     */
    initBulkActions() {
        // Чекбоксы для выбора задач
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-task-checkbox]')) {
                const taskId = e.target.getAttribute('data-task-id');
                
                if (e.target.checked) {
                    this.selectedTasks.add(taskId);
                } else {
                    this.selectedTasks.delete(taskId);
                }
                
                this.updateBulkActionsBar();
            }
        });

        // Выбрать все
        const selectAllBtn = document.querySelector('[data-select-all]');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                const checkboxes = document.querySelectorAll('[data-task-checkbox]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                    const taskId = cb.getAttribute('data-task-id');
                    
                    if (!allChecked) {
                        this.selectedTasks.add(taskId);
                    } else {
                        this.selectedTasks.delete(taskId);
                    }
                });
                
                this.updateBulkActionsBar();
            });
        }

        // Действия с выбранными
        document.querySelectorAll('[data-bulk-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-bulk-action');
                this.executeBulkAction(action);
            });
        });
    }

    updateBulkActionsBar() {
        const bar = document.querySelector('[data-bulk-actions-bar]');
        const count = document.querySelector('[data-selected-count]');
        
        if (bar && count) {
            if (this.selectedTasks.size > 0) {
                bar.style.display = 'flex';
                count.textContent = this.selectedTasks.size;
            } else {
                bar.style.display = 'none';
            }
        }
    }

    executeBulkAction(action) {
        if (this.selectedTasks.size === 0) return;
        
        const taskIds = Array.from(this.selectedTasks);
        
        switch(action) {
            case 'delete':
                if (confirm(`Удалить ${taskIds.length} задач(и)?`)) {
                    // Здесь отправка на сервер
                    console.log('Deleting tasks:', taskIds);
                }
                break;
            case 'complete':
                // Отметить как выполненные
                console.log('Completing tasks:', taskIds);
                break;
            case 'archive':
                // Архивировать
                console.log('Archiving tasks:', taskIds);
                break;
        }
    }

    /**
     * Быстрый просмотр задачи
     */
    initQuickView() {
        document.addEventListener('click', (e) => {
            const quickViewBtn = e.target.closest('[data-quick-view]');
            if (!quickViewBtn) return;
            
            e.preventDefault();
            const taskId = quickViewBtn.getAttribute('data-task-id');
            this.showQuickView(taskId);
        });
    }

    showQuickView(taskId) {
        // Создаём модальное окно для быстрого просмотра
        const modal = document.createElement('div');
        modal.className = 'quick-view-modal';
        modal.innerHTML = `
            <div class="quick-view-backdrop" onclick="this.parentElement.remove()"></div>
            <div class="quick-view-content">
                <div class="quick-view-header">
                    <h3>Задача #${taskId}</h3>
                    <button onclick="this.closest('.quick-view-modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="quick-view-body">
                    <p>Загрузка...</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Здесь можно загрузить данные задачи через AJAX
        // fetch(`/task/${taskId}/quick-view`)...
    }

    /**
     * Drag & Drop для изменения статуса
     */
    initDragDrop() {
        const draggables = document.querySelectorAll('[data-task-draggable]');
        const dropZones = document.querySelectorAll('[data-status-zone]');
        
        draggables.forEach(task => {
            task.addEventListener('dragstart', (e) => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('taskId', task.getAttribute('data-task-id'));
                task.classList.add('dragging');
            });
            
            task.addEventListener('dragend', () => {
                task.classList.remove('dragging');
            });
        });
        
        dropZones.forEach(zone => {
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
                
                const taskId = e.dataTransfer.getData('taskId');
                const newStatus = zone.getAttribute('data-status-zone');
                
                this.updateTaskStatus(taskId, newStatus);
            });
        });
    }

    updateTaskStatus(taskId, newStatus) {
        // Отправка на сервер
        console.log(`Updating task ${taskId} to status ${newStatus}`);
        
        // Показываем уведомление
        if (window.showSmartNotification) {
            window.showSmartNotification('Статус задачи обновлён', 'success');
        }
    }

    /**
     * Горячие клавиши
     */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N - новая задача
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = '/task/new';
            }
            
            // Ctrl/Cmd + F - фокус на поиск
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.querySelector('[data-task-search]')?.focus();
            }
            
            // Ctrl/Cmd + A - выбрать все
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                const tasksContainer = document.querySelector('[data-tasks-container]');
                if (tasksContainer && document.activeElement.tagName !== 'INPUT') {
                    e.preventDefault();
                    document.querySelector('[data-select-all]')?.click();
                }
            }
        });
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.taskEnhancements = new TaskEnhancements();
    });
} else {
    window.taskEnhancements = new TaskEnhancements();
}

// CSS стили
const styles = document.createElement('style');
styles.textContent = `
    .quick-view-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .quick-view-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }
    
    .quick-view-content {
        position: relative;
        background: var(--card-bg, white);
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }
    
    .quick-view-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }
    
    .quick-view-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }
    
    .quick-view-header button {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: var(--bg-secondary, #f5f5f5);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .quick-view-header button:hover {
        background: var(--danger, #ef4444);
        color: white;
    }
    
    .quick-view-body {
        padding: 20px;
        max-height: calc(80vh - 80px);
        overflow-y: auto;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    [data-task-item].dragging {
        opacity: 0.5;
    }
    
    [data-status-zone].drag-over {
        background: rgba(99, 102, 241, 0.1);
        border-color: var(--primary-color, #6366f1);
    }
`;
document.head.appendChild(styles);
