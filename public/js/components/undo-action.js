/**
 * Undo Manager - отмена и повтор действий
 */

class UndoManager {
    constructor() {
        this.undoStack = [];
        this.redoStack = [];
        this.maxStackSize = 50;
        this.init();
    }

    init() {
        // Слушаем действия
        document.addEventListener('click', (e) => this.handleAction(e));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                e.preventDefault();
                if (e.shiftKey) {
                    this.redo();
                } else {
                    this.undo();
                }
            }
            // Ctrl+Y для redo
            if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
                e.preventDefault();
                this.redo();
            }
        });

        this.showUndoButton();
    }

    handleAction(e) {
        const target = e.target.closest('[data-undo-action]');
        if (!target) return;

        const actionType = target.dataset.undoAction;
        const actionData = JSON.parse(target.dataset.undoData || '{}');

        // Не записываем мелкие действия
        if (['hover', 'focus', 'blur'].includes(actionType)) return;

        this.recordAction({
            type: actionType,
            data: actionData,
            timestamp: Date.now(),
            description: target.dataset.undoDescription || actionType
        });
    }

    recordAction(action) {
        this.undoStack.push(action);
        
        // Ограничиваем размер стека
        if (this.undoStack.length > this.maxStackSize) {
            this.undoStack.shift();
        }

        // Очищаем redo при новом действии
        this.redoStack = [];
        
        this.updateUI();
    }

    async undo() {
        if (this.undoStack.length === 0) {
            this.notify('Нечего отменить', 'warning');
            return;
        }

        const action = this.undoStack.pop();
        this.redoStack.push(action);

        try {
            await this.executeReverse(action);
            this.notify(`Отменено: ${action.description}`, 'success');
        } catch (error) {
            console.error('Undo failed:', error);
            this.notify('Не удалось отменить действие', 'error');
            // Возвращаем в стек
            this.undoStack.push(action);
        }

        this.updateUI();
    }

    async redo() {
        if (this.redoStack.length === 0) {
            this.notify('Нечего повторить', 'warning');
            return;
        }

        const action = this.redoStack.pop();
        this.undoStack.push(action);

        try {
            await this.executeAction(action);
            this.notify(`Повторено: ${action.description}`, 'success');
        } catch (error) {
            console.error('Redo failed:', error);
            this.notify('Не удалось повторить действие', 'error');
        }

        this.updateUI();
    }

    async executeAction(action) {
        switch (action.type) {
            case 'delete_task':
                await this.apiRequest('/api/tasks', 'POST', action.data);
                break;
            case 'update_task':
                await this.apiRequest(`/api/tasks/${action.data.id}`, 'PUT', action.data);
                break;
            case 'change_status':
                await this.apiRequest(`/api/tasks/${action.data.id}/status`, 'PATCH', {
                    status: action.data.newStatus
                });
                break;
            case 'delete_client':
                await this.apiRequest('/api/clients', 'POST', action.data);
                break;
            case 'delete_deal':
                await this.apiRequest('/api/deals', 'POST', action.data);
                break;
        }
    }

    async executeReverse(action) {
        switch (action.type) {
            case 'delete_task':
                // Восстановить удалённую задачу
                await this.apiRequest(`/api/tasks/${action.data.id}/restore`, 'POST');
                break;
            case 'update_task':
                // Вернуть старое значение
                await this.apiRequest(`/api/tasks/${action.data.id}`, 'PUT', action.data.oldData);
                break;
            case 'change_status':
                // Вернуть старый статус
                await this.apiRequest(`/api/tasks/${action.data.id}/status`, 'PATCH', {
                    status: action.data.oldStatus
                });
                break;
            case 'delete_client':
                await this.apiRequest(`/api/clients/${action.data.id}/restore`, 'POST');
                break;
            case 'delete_deal':
                await this.apiRequest(`/api/deals/${action.data.id}/restore`, 'POST');
                break;
        }
    }

    async apiRequest(url, method, data) {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: method !== 'GET' ? JSON.stringify(data) : undefined
        });

        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }

        return response.json();
    }

    showUndoButton() {
        const container = document.createElement('div');
        container.id = 'undo-container';
        container.className = 'undo-container';
        container.innerHTML = `
            <button class="undo-btn" onclick="undoManager.undo()" title="Отменить (Ctrl+Z)">
                <i class="fas fa-undo"></i>
            </button>
            <button class="redo-btn" onclick="undoManager.redo()" title="Повторить (Ctrl+Y)">
                <i class="fas fa-redo"></i>
            </button>
        `;
        document.body.appendChild(container);
    }

    updateUI() {
        const undoBtn = document.querySelector('.undo-btn');
        const redoBtn = document.querySelector('.redo-btn');

        if (undoBtn) undoBtn.disabled = this.undoStack.length === 0;
        if (redoBtn) redoBtn.disabled = this.redoStack.length === 0;
    }

    notify(message, type = 'info') {
        // Используем существующую систему уведомлений
        if (window.notify) {
            window.notify[type](message);
        } else {
            // Fallback
            alert(message);
        }
    }
}

// Инициализация
const undoManager = new UndoManager();
window.undoManager = undoManager;

// CSS
const style = document.createElement('style');
style.textContent = `
    .undo-container {
        position: fixed;
        bottom: 80px;
        right: 20px;
        display: flex;
        gap: 8px;
        z-index: 9999;
    }

    .undo-btn, .redo-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: white;
        color: var(--text-primary, #212529);
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .undo-btn:hover:not(:disabled), .redo-btn:hover:not(:disabled) {
        background: var(--primary, #667eea);
        color: white;
    }

    .undo-btn:disabled, .redo-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    @media (max-width: 768px) {
        .undo-container {
            bottom: 100px;
            right: 10px;
        }
    }
`;
document.head.appendChild(style);
