/**
 * Task Recurrence
 * Повторяющиеся задачи
 */

class TaskRecurrence {
    constructor() {
        this.recurrences = new Map();
        this.init();
    }

    init() {
        this.loadRecurrences();
        this.addRecurrenceButtons();
        this.checkRecurrences();
        
        // Проверяем каждый час
        setInterval(() => this.checkRecurrences(), 3600000);
    }

    addRecurrenceButtons() {
        const taskItems = document.querySelectorAll('.task-item, [data-task-id]');
        
        taskItems.forEach(item => {
            if (!item.querySelector('.task-recurrence-btn')) {
                const button = document.createElement('button');
                button.className = 'task-recurrence-btn btn btn-sm btn-outline-info';
                button.innerHTML = '<i class="fas fa-redo"></i>';
                button.title = 'Настроить повторение';
                
                const taskId = item.dataset.id || item.dataset.taskId;
                
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showRecurrenceModal(taskId);
                });

                item.appendChild(button);
            }
        });
    }

    showRecurrenceModal(taskId) {
        const existing = this.recurrences.get(taskId);
        
        const modal = document.createElement('div');
        modal.className = 'recurrence-modal';
        modal.innerHTML = `
            <div class="recurrence-modal-content">
                <div class="recurrence-modal-header">
                    <h4><i class="fas fa-redo"></i> Настройка повторения</h4>
                    <button class="recurrence-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="recurrence-modal-body">
                    <div class="form-group">
                        <label>Тип повторения</label>
                        <select class="form-select" id="recurrence-type">
                            <option value="">Не повторять</option>
                            <option value="daily">Ежедневно</option>
                            <option value="weekly">Еженедельно</option>
                            <option value="monthly">Ежемесячно</option>
                            <option value="yearly">Ежегодно</option>
                            <option value="custom">Настраиваемое</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="interval-group" style="display: none;">
                        <label>Интервал (дней)</label>
                        <input type="number" class="form-control" id="recurrence-interval" min="1" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Дата начала</label>
                        <input type="date" class="form-control" id="recurrence-start" value="${new Date().toISOString().split('T')[0]}">
                    </div>
                    
                    <div class="form-group">
                        <label>Дата окончания (опционально)</label>
                        <input type="date" class="form-control" id="recurrence-end">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="recurrence-enabled" ${existing ? 'checked' : ''}>
                            Включить повторение
                        </label>
                    </div>
                </div>
                <div class="recurrence-modal-footer">
                    <button class="btn btn-secondary" id="cancel-recurrence">Отмена</button>
                    <button class="btn btn-primary" id="save-recurrence">Сохранить</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Заполняем существующие данные
        if (existing) {
            modal.querySelector('#recurrence-type').value = existing.type;
            modal.querySelector('#recurrence-interval').value = existing.interval || 1;
            modal.querySelector('#recurrence-start').value = existing.start;
            if (existing.end) {
                modal.querySelector('#recurrence-end').value = existing.end;
            }
        }

        // Показываем/скрываем поле интервала
        const typeSelect = modal.querySelector('#recurrence-type');
        const intervalGroup = modal.querySelector('#interval-group');
        
        typeSelect.addEventListener('change', () => {
            intervalGroup.style.display = typeSelect.value === 'custom' ? 'block' : 'none';
        });

        if (existing && existing.type === 'custom') {
            intervalGroup.style.display = 'block';
        }

        // Обработчики
        modal.querySelector('.recurrence-modal-close').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('#cancel-recurrence').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('#save-recurrence').addEventListener('click', () => {
            this.saveRecurrence(taskId, modal);
            modal.remove();
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });

        this.addStyles();
    }

    saveRecurrence(taskId, modal) {
        const enabled = modal.querySelector('#recurrence-enabled').checked;
        
        if (!enabled) {
            this.recurrences.delete(taskId);
            this.saveRecurrences();
            
            if (window.showToast) {
                window.showToast('Повторение отключено', 'info');
            }
            return;
        }

        const type = modal.querySelector('#recurrence-type').value;
        const interval = parseInt(modal.querySelector('#recurrence-interval').value);
        const start = modal.querySelector('#recurrence-start').value;
        const end = modal.querySelector('#recurrence-end').value;

        if (!type || !start) {
            if (window.showToast) {
                window.showToast('Заполните обязательные поля', 'error');
            }
            return;
        }

        this.recurrences.set(taskId, {
            type,
            interval,
            start,
            end,
            lastCreated: null
        });

        this.saveRecurrences();
        
        if (window.showToast) {
            window.showToast('Повторение настроено', 'success');
        }
    }

    checkRecurrences() {
        const now = new Date();
        
        for (const [taskId, recurrence] of this.recurrences.entries()) {
            if (this.shouldCreateTask(recurrence, now)) {
                this.createRecurringTask(taskId, recurrence);
            }
        }
    }

    shouldCreateTask(recurrence, now) {
        const start = new Date(recurrence.start);
        if (now < start) return false;

        if (recurrence.end) {
            const end = new Date(recurrence.end);
            if (now > end) return false;
        }

        if (!recurrence.lastCreated) return true;

        const lastCreated = new Date(recurrence.lastCreated);
        const daysSince = Math.floor((now - lastCreated) / 86400000);

        switch(recurrence.type) {
            case 'daily':
                return daysSince >= 1;
            case 'weekly':
                return daysSince >= 7;
            case 'monthly':
                return daysSince >= 30;
            case 'yearly':
                return daysSince >= 365;
            case 'custom':
                return daysSince >= recurrence.interval;
            default:
                return false;
        }
    }

    async createRecurringTask(taskId, recurrence) {
        try {
            const response = await fetch(`/api/task/${taskId}/duplicate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    recurring: true
                })
            });

            if (response.ok) {
                recurrence.lastCreated = new Date().toISOString();
                this.saveRecurrences();
                
                if (window.showToast) {
                    window.showToast('Создана повторяющаяся задача', 'success');
                }
            }
        } catch (error) {
            console.error('Failed to create recurring task:', error);
        }
    }

    saveRecurrences() {
        try {
            const data = Array.from(this.recurrences.entries());
            localStorage.setItem('taskRecurrences', JSON.stringify(data));
        } catch (e) {
            console.error('Failed to save recurrences:', e);
        }
    }

    loadRecurrences() {
        try {
            const saved = localStorage.getItem('taskRecurrences');
            if (saved) {
                const data = JSON.parse(saved);
                this.recurrences = new Map(data);
            }
        } catch (e) {
            console.error('Failed to load recurrences:', e);
            this.recurrences = new Map();
        }
    }

    addStyles() {
        if (document.getElementById('taskRecurrenceStyles')) return;

        const style = document.createElement('style');
        style.id = 'taskRecurrenceStyles';
        style.textContent = `
            .recurrence-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10010;
            }

            .recurrence-modal-content {
                background: var(--bg-card);
                border-radius: 12px;
                width: 90%;
                max-width: 500px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .recurrence-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .recurrence-modal-header h4 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-primary);
            }

            .recurrence-modal-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.25rem;
                padding: 4px;
            }

            .recurrence-modal-body {
                padding: 1.5rem;
            }

            .recurrence-modal-body .form-group {
                margin-bottom: 1rem;
            }

            .recurrence-modal-body label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: var(--text-primary);
            }

            .recurrence-modal-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid var(--border);
                display: flex;
                justify-content: flex-end;
                gap: 0.5rem;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.taskRecurrence = new TaskRecurrence();
});

window.TaskRecurrence = TaskRecurrence;
