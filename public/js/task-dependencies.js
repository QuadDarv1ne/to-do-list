/**
 * Task Dependencies
 * Управление зависимостями между задачами
 */

class TaskDependencies {
    constructor() {
        this.dependencies = new Map();
        this.init();
    }

    init() {
        this.loadDependencies();
        this.addDependencyButtons();
        this.visualizeDependencies();
    }

    addDependencyButtons() {
        const taskItems = document.querySelectorAll('.task-item, [data-task-id]');
        
        taskItems.forEach(item => {
            if (!item.querySelector('.task-dependency-btn')) {
                const button = document.createElement('button');
                button.className = 'task-dependency-btn btn btn-sm btn-outline-secondary';
                button.innerHTML = '<i class="fas fa-link"></i>';
                button.title = 'Управление зависимостями';
                
                const taskId = item.dataset.id || item.dataset.taskId;
                
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showDependencyModal(taskId);
                });

                item.appendChild(button);
            }
        });
    }

    showDependencyModal(taskId) {
        const modal = document.createElement('div');
        modal.className = 'dependency-modal';
        modal.innerHTML = `
            <div class="dependency-modal-content">
                <div class="dependency-modal-header">
                    <h4><i class="fas fa-link"></i> Зависимости задачи</h4>
                    <button class="dependency-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="dependency-modal-body">
                    <div class="dependency-section">
                        <h5>Блокирует задачи:</h5>
                        <div class="dependency-list" id="blocks-list"></div>
                        <button class="btn btn-sm btn-primary" id="add-blocks">
                            <i class="fas fa-plus"></i> Добавить
                        </button>
                    </div>
                    <div class="dependency-section">
                        <h5>Заблокирована задачами:</h5>
                        <div class="dependency-list" id="blocked-by-list"></div>
                        <button class="btn btn-sm btn-primary" id="add-blocked-by">
                            <i class="fas fa-plus"></i> Добавить
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Заполняем списки
        this.renderDependencyLists(taskId, modal);

        // Обработчики
        modal.querySelector('.dependency-modal-close').addEventListener('click', () => {
            modal.remove();
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });

        this.addStyles();
    }

    renderDependencyLists(taskId, modal) {
        const blocks = this.getBlockedTasks(taskId);
        const blockedBy = this.getBlockingTasks(taskId);

        const blocksList = modal.querySelector('#blocks-list');
        const blockedByList = modal.querySelector('#blocked-by-list');

        blocksList.innerHTML = blocks.length > 0 
            ? blocks.map(id => `<div class="dependency-item">#${id} <button class="btn-remove" data-id="${id}"><i class="fas fa-times"></i></button></div>`).join('')
            : '<div class="dependency-empty">Нет зависимостей</div>';

        blockedByList.innerHTML = blockedBy.length > 0
            ? blockedBy.map(id => `<div class="dependency-item">#${id} <button class="btn-remove" data-id="${id}"><i class="fas fa-times"></i></button></div>`).join('')
            : '<div class="dependency-empty">Нет зависимостей</div>';
    }

    getBlockedTasks(taskId) {
        return this.dependencies.get(taskId) || [];
    }

    getBlockingTasks(taskId) {
        const blocking = [];
        for (const [id, deps] of this.dependencies.entries()) {
            if (deps.includes(taskId)) {
                blocking.push(id);
            }
        }
        return blocking;
    }

    addDependency(fromTask, toTask) {
        if (!this.dependencies.has(fromTask)) {
            this.dependencies.set(fromTask, []);
        }
        
        const deps = this.dependencies.get(fromTask);
        if (!deps.includes(toTask)) {
            deps.push(toTask);
            this.saveDependencies();
            this.visualizeDependencies();
        }
    }

    removeDependency(fromTask, toTask) {
        if (this.dependencies.has(fromTask)) {
            const deps = this.dependencies.get(fromTask);
            const index = deps.indexOf(toTask);
            if (index > -1) {
                deps.splice(index, 1);
                this.saveDependencies();
                this.visualizeDependencies();
            }
        }
    }

    visualizeDependencies() {
        // Добавляем визуальные индикаторы к задачам
        const taskItems = document.querySelectorAll('.task-item, [data-task-id]');
        
        taskItems.forEach(item => {
            const taskId = item.dataset.id || item.dataset.taskId;
            const blocks = this.getBlockedTasks(taskId);
            const blockedBy = this.getBlockingTasks(taskId);

            // Удаляем старые индикаторы
            item.querySelectorAll('.dependency-indicator').forEach(el => el.remove());

            if (blocks.length > 0 || blockedBy.length > 0) {
                const indicator = document.createElement('div');
                indicator.className = 'dependency-indicator';
                indicator.innerHTML = `
                    ${blocks.length > 0 ? `<span class="blocks-count" title="Блокирует ${blocks.length} задач(и)"><i class="fas fa-arrow-right"></i> ${blocks.length}</span>` : ''}
                    ${blockedBy.length > 0 ? `<span class="blocked-by-count" title="Заблокирована ${blockedBy.length} задачами"><i class="fas fa-arrow-left"></i> ${blockedBy.length}</span>` : ''}
                `;
                item.appendChild(indicator);
            }
        });
    }

    saveDependencies() {
        try {
            const data = Array.from(this.dependencies.entries());
            localStorage.setItem('taskDependencies', JSON.stringify(data));
        } catch (e) {
            console.error('Failed to save dependencies:', e);
        }
    }

    loadDependencies() {
        try {
            const saved = localStorage.getItem('taskDependencies');
            if (saved) {
                const data = JSON.parse(saved);
                this.dependencies = new Map(data);
            }
        } catch (e) {
            console.error('Failed to load dependencies:', e);
            this.dependencies = new Map();
        }
    }

    addStyles() {
        if (document.getElementById('taskDependenciesStyles')) return;

        const style = document.createElement('style');
        style.id = 'taskDependenciesStyles';
        style.textContent = `
            .dependency-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10009;
            }

            .dependency-modal-content {
                background: var(--bg-card);
                border-radius: 12px;
                width: 90%;
                max-width: 600px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .dependency-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .dependency-modal-header h4 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-primary);
            }

            .dependency-modal-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.25rem;
                padding: 4px;
            }

            .dependency-modal-body {
                padding: 1.5rem;
                overflow-y: auto;
                flex: 1;
            }

            .dependency-section {
                margin-bottom: 1.5rem;
            }

            .dependency-section h5 {
                margin: 0 0 0.75rem 0;
                font-size: 0.875rem;
                color: var(--text-secondary);
                text-transform: uppercase;
            }

            .dependency-list {
                margin-bottom: 0.75rem;
            }

            .dependency-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem;
                background: var(--bg-body);
                border-radius: 6px;
                margin-bottom: 0.5rem;
                color: var(--text-primary);
            }

            .dependency-item .btn-remove {
                background: none;
                border: none;
                color: var(--danger);
                cursor: pointer;
                padding: 4px;
            }

            .dependency-empty {
                padding: 1rem;
                text-align: center;
                color: var(--text-secondary);
                font-style: italic;
            }

            .dependency-indicator {
                display: flex;
                gap: 0.5rem;
                margin-top: 0.5rem;
                font-size: 0.75rem;
            }

            .blocks-count,
            .blocked-by-count {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                padding: 0.25rem 0.5rem;
                border-radius: 12px;
                background: var(--bg-body);
                color: var(--text-secondary);
            }

            .blocks-count {
                border-left: 3px solid #28a745;
            }

            .blocked-by-count {
                border-left: 3px solid #dc3545;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.taskDependencies = new TaskDependencies();
});

window.TaskDependencies = TaskDependencies;
