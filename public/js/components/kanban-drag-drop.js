/**
 * Enhanced Kanban Drag & Drop - улучшенный Drag & Drop с Touch поддержкой
 */

class KanbanDragDrop {
    constructor() {
        this.draggedItem = null;
        this.dropZones = [];
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.init();
    }

    init() {
        // Desktop drag events
        document.addEventListener('dragstart', (e) => this.handleDragStart(e));
        document.addEventListener('dragend', (e) => this.handleDragEnd(e));
        document.addEventListener('dragover', (e) => this.handleDragOver(e));
        document.addEventListener('drop', (e) => this.handleDrop(e));

        // Touch events для мобильных
        document.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: false });
        document.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        document.addEventListener('touchend', (e) => this.handleTouchEnd(e));

        this.initDropZones();
    }

    initDropZones() {
        const columns = document.querySelectorAll('.kanban-column');
        
        columns.forEach(column => {
            column.addEventListener('dragover', (e) => {
                e.preventDefault();
                column.classList.add('drag-over');
            });

            column.addEventListener('dragleave', () => {
                column.classList.remove('drag-over');
            });

            column.addEventListener('drop', (e) => {
                e.preventDefault();
                column.classList.remove('drag-over');
                
                if (this.draggedItem) {
                    const targetColumn = column.closest('.kanban-column');
                    this.moveCard(this.draggedItem, targetColumn);
                }
            });
        });
    }

    handleDragStart(e) {
        const card = e.target.closest('[data-task-id]');
        if (!card) return;

        this.draggedItem = card;
        card.classList.add('dragging');
        
        // Set drag image
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.taskId);
    }

    handleDragEnd(e) {
        if (this.draggedItem) {
            this.draggedItem.classList.remove('dragging');
            this.draggedItem = null;
        }
        
        // Убираем все классы drag-over
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    }

    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    handleDrop(e) {
        e.preventDefault();
    }

    // Touch handlers для мобильных
    handleTouchStart(e) {
        const card = e.target.closest('[data-task-id]');
        if (!card) return;

        const touch = e.touches[0];
        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;
        
        // Долгое нажатие для начала перетаскивания
        this.touchTimer = setTimeout(() => {
            this.draggedItem = card;
            card.classList.add('dragging');
            card.style.position = 'fixed';
            card.style.zIndex = '9999';
            card.style.opacity = '0.8';
            card.style.width = card.offsetWidth + 'px';
            
            this.updateCardPosition(touch.clientX, touch.clientY);
        }, 300);
    }

    handleTouchMove(e) {
        if (!this.draggedItem) {
            // Проверяем, было ли движение (отмена long press)
            const touch = e.touches[0];
            const deltaX = Math.abs(touch.clientX - this.touchStartX);
            const deltaY = Math.abs(touch.clientY - this.touchStartY);
            
            if (deltaX > 10 || deltaY > 10) {
                clearTimeout(this.touchTimer);
            }
            return;
        }

        e.preventDefault();
        
        const touch = e.touches[0];
        this.updateCardPosition(touch.clientX, touch.clientY);

        // Highlight drop zone
        this.highlightDropZone(touch.clientX, touch.clientY);
    }

    handleTouchEnd(e) {
        clearTimeout(this.touchTimer);
        
        if (!this.draggedItem) return;

        const touch = e.changedTouches[0];
        const dropZone = this.getDropZoneAt(touch.clientX, touch.clientY);

        if (dropZone) {
            this.moveCard(this.draggedItem, dropZone);
        } else {
            // Возврат на место
            this.draggedItem.style.position = '';
            this.draggedItem.style.zIndex = '';
            this.draggedItem.style.opacity = '';
            this.draggedItem.style.width = '';
            this.draggedItem.style.left = '';
            this.draggedItem.style.top = '';
        }

        this.draggedItem.classList.remove('dragging');
        this.draggedItem = null;
        
        // Убираем подсветку
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    }

    updateCardPosition(x, y) {
        if (!this.draggedItem) return;
        
        this.draggedItem.style.left = (x - this.draggedItem.offsetWidth / 2) + 'px';
        this.draggedItem.style.top = (y - 30) + 'px';
    }

    getDropZoneAt(x, y) {
        const elements = document.elementsFromPoint(x, y);
        return elements.find(el => el.classList.contains('kanban-column') || el.closest('.kanban-column'));
    }

    highlightDropZone(x, y) {
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });

        const dropZone = this.getDropZoneAt(x, y);
        if (dropZone) {
            dropZone.classList.add('drag-over');
        }
    }

    async moveCard(card, targetColumn) {
        if (!targetColumn || !card) return;

        const taskId = card.dataset.taskId;
        const newStatus = targetColumn.dataset.status || this.getStatusFromColumn(targetColumn);

        // Optimistic update - сразу перемещаем
        const originalParent = card.parentElement;
        const cardsContainer = targetColumn.querySelector('.kanban-cards') || targetColumn;
        
        if (!cardsContainer.contains(card)) {
            cardsContainer.appendChild(card);
        }

        // Сброс стилей
        card.style.position = '';
        card.style.zIndex = '';
        card.style.opacity = '';
        card.style.width = '';
        card.style.left = '';
        card.style.top = '';

        try {
            // Отправляем на сервер
            const response = await fetch(`/api/tasks/${taskId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (!response.ok) {
                throw new Error('Update failed');
            }

            // Показываем уведомление
            this.showNotification('Задача перемещена', 'success');

        } catch (error) {
            console.error('Move failed:', error);
            
            // Откат при ошибке
            if (originalParent) {
                originalParent.appendChild(card);
            }
            
            this.showNotification('Ошибка перемещения', 'error');
        }
    }

    getStatusFromColumn(column) {
        const statusMap = {
            'todo': 'pending',
            'in-progress': 'in_progress',
            'done': 'completed',
            'backlog': 'backlog'
        };
        
        for (const [key, value] of Object.entries(statusMap)) {
            if (column.classList.contains(key) || column.dataset.status === key) {
                return value;
            }
        }
        
        return 'pending';
    }

    showNotification(message, type) {
        if (window.notify) {
            window.notify[type](message);
        }
    }
}

// Инициализация
const kanbanDragDrop = new KanbanDragDrop();
window.kanbanDragDrop = kanbanDragDrop;

// CSS
const style = document.createElement('style');
style.textContent = `
    .kanban-card.dragging {
        transform: rotate(3deg);
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    .kanban-column.drag-over {
        background: rgba(102, 126, 234, 0.1);
        border: 2px dashed var(--primary, #667eea);
    }
    
    .kanban-column.drag-over::after {
        content: 'Перетащите сюда';
        display: block;
        text-align: center;
        padding: 20px;
        color: var(--primary, #667eea);
        font-weight: 500;
    }

    /* Touch feedback */
    .kanban-card:active {
        transform: scale(1.02);
    }
`;
document.head.appendChild(style);
