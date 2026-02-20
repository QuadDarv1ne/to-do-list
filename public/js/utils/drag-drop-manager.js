/**
 * Drag & Drop Manager
 * Универсальная система drag & drop для задач, файлов и элементов
 */

class DragDropManager {
    constructor() {
        this.draggedElement = null;
        this.dropZones = new Map();
        this.draggedData = null;
        this.init();
    }

    init() {
        this.setupTaskDragDrop();
        this.setupFileDragDrop();
        this.setupKanbanDragDrop();
        this.setupReordering();
        this.addStyles();
    }

    /**
     * Настроить drag & drop для задач
     */
    setupTaskDragDrop() {
        document.querySelectorAll('[data-draggable="task"]').forEach(task => {
            this.makeTaskDraggable(task);
        });

        document.querySelectorAll('[data-drop-zone="task"]').forEach(zone => {
            this.makeTaskDropZone(zone);
        });
    }

    /**
     * Сделать задачу перетаскиваемой
     */
    makeTaskDraggable(element) {
        element.setAttribute('draggable', 'true');
        
        element.addEventListener('dragstart', (e) => {
            this.draggedElement = element;
            this.draggedData = {
                type: 'task',
                id: element.dataset.taskId,
                status: element.dataset.status,
                priority: element.dataset.priority
            };

            element.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', JSON.stringify(this.draggedData));

            // Создать ghost image
            this.createDragGhost(element);
        });

        element.addEventListener('dragend', (e) => {
            element.classList.remove('dragging');
            this.draggedElement = null;
            this.draggedData = null;
            this.removeDragGhost();
        });
    }

    /**
     * Создать ghost image для перетаскивания
     */
    createDragGhost(element) {
        const ghost = element.cloneNode(true);
        ghost.id = 'drag-ghost';
        ghost.style.position = 'absolute';
        ghost.style.top = '-9999px';
        ghost.style.opacity = '0.8';
        ghost.style.transform = 'rotate(5deg)';
        document.body.appendChild(ghost);
    }

    /**
     * Удалить ghost image
     */
    removeDragGhost() {
        const ghost = document.getElementById('drag-ghost');
        if (ghost) ghost.remove();
    }

    /**
     * Сделать зону для drop задач
     */
    makeTaskDropZone(zone) {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (this.draggedData?.type === 'task') {
                zone.classList.add('drag-over');
                this.showDropIndicator(zone, e);
            }
        });

        zone.addEventListener('dragleave', (e) => {
            if (e.target === zone) {
                zone.classList.remove('drag-over');
                this.hideDropIndicator();
            }
        });

        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            this.hideDropIndicator();

            if (this.draggedData?.type === 'task') {
                await this.handleTaskDrop(zone, this.draggedData);
            }
        });
    }

    /**
     * Показать индикатор drop
     */
    showDropIndicator(zone, event) {
        let indicator = zone.querySelector('.drop-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'drop-indicator';
            zone.appendChild(indicator);
        }

        // Позиционировать индикатор
        const rect = zone.getBoundingClientRect();
        const y = event.clientY - rect.top;
        indicator.style.top = `${y}px`;
    }

    /**
     * Скрыть индикатор drop
     */
    hideDropIndicator() {
        document.querySelectorAll('.drop-indicator').forEach(el => el.remove());
    }

    /**
     * Обработать drop задачи
     */
    async handleTaskDrop(zone, data) {
        const newStatus = zone.dataset.status;
        const taskId = data.id;

        try {
            const response = await fetch(`/api/v1/tasks/${taskId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (response.ok) {
                // Переместить элемент визуально
                if (this.draggedElement) {
                    zone.appendChild(this.draggedElement);
                    this.draggedElement.dataset.status = newStatus;
                }
                
                this.showNotification('Статус задачи обновлен', 'success');
                this.triggerStatusChange(taskId, newStatus);
            } else {
                throw new Error('Failed to update status');
            }
        } catch (error) {
            console.error('Drop error:', error);
            this.showNotification('Ошибка обновления статуса', 'error');
        }
    }

    /**
     * Настроить drag & drop для файлов
     */
    setupFileDragDrop() {
        document.querySelectorAll('[data-file-drop]').forEach(zone => {
            this.makeFileDropZone(zone);
        });
    }

    /**
     * Сделать зону для drop файлов
     */
    makeFileDropZone(zone) {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            zone.classList.add('file-drag-over');
        });

        zone.addEventListener('dragleave', (e) => {
            if (e.target === zone) {
                zone.classList.remove('file-drag-over');
            }
        });

        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('file-drag-over');

            const files = Array.from(e.dataTransfer.files);
            if (files.length > 0) {
                await this.handleFileDrop(zone, files);
            }
        });

        // Клик для выбора файлов
        zone.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.onchange = async (e) => {
                const files = Array.from(e.target.files);
                await this.handleFileDrop(zone, files);
            };
            input.click();
        });
    }

    /**
     * Обработать drop файлов
     */
    async handleFileDrop(zone, files) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = zone.dataset.allowedTypes?.split(',') || [];

        // Валидация файлов
        const validFiles = files.filter(file => {
            if (file.size > maxSize) {
                this.showNotification(`Файл ${file.name} слишком большой (макс. 10MB)`, 'error');
                return false;
            }

            if (allowedTypes.length > 0) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(ext)) {
                    this.showNotification(`Тип файла ${ext} не поддерживается`, 'error');
                    return false;
                }
            }

            return true;
        });

        if (validFiles.length === 0) return;

        // Показать прогресс
        const progressContainer = this.createProgressContainer(zone);

        for (const file of validFiles) {
            await this.uploadFile(file, zone, progressContainer);
        }

        // Удалить контейнер прогресса
        setTimeout(() => progressContainer.remove(), 2000);
    }

    /**
     * Создать контейнер прогресса
     */
    createProgressContainer(zone) {
        const container = document.createElement('div');
        container.className = 'upload-progress-container';
        zone.appendChild(container);
        return container;
    }

    /**
     * Загрузить файл
     */
    async uploadFile(file, zone, progressContainer) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('entityType', zone.dataset.entityType || 'task');
        formData.append('entityId', zone.dataset.entityId || '');

        // Создать элемент прогресса
        const progressItem = document.createElement('div');
        progressItem.className = 'upload-progress-item';
        progressItem.innerHTML = `
            <div class="upload-file-name">${file.name}</div>
            <div class="upload-progress-bar">
                <div class="upload-progress-fill" style="width: 0%"></div>
            </div>
            <div class="upload-status">Загрузка...</div>
        `;
        progressContainer.appendChild(progressItem);

        const progressBar = progressItem.querySelector('.upload-progress-fill');
        const statusEl = progressItem.querySelector('.upload-status');

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = `${percent}%`;
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    statusEl.textContent = 'Готово';
                    progressItem.classList.add('success');
                    this.showNotification(`Файл ${file.name} загружен`, 'success');
                } else {
                    throw new Error('Upload failed');
                }
            });

            xhr.addEventListener('error', () => {
                statusEl.textContent = 'Ошибка';
                progressItem.classList.add('error');
                this.showNotification(`Ошибка загрузки ${file.name}`, 'error');
            });

            xhr.open('POST', '/api/files/upload');
            xhr.send(formData);
        } catch (error) {
            console.error('Upload error:', error);
            statusEl.textContent = 'Ошибка';
            progressItem.classList.add('error');
        }
    }

    /**
     * Настроить drag & drop для канбан доски
     */
    setupKanbanDragDrop() {
        document.querySelectorAll('.kanban-column').forEach(column => {
            this.makeKanbanDropZone(column);
        });

        document.querySelectorAll('.kanban-card').forEach(card => {
            this.makeKanbanCardDraggable(card);
        });
    }

    /**
     * Сделать карточку канбан перетаскиваемой
     */
    makeKanbanCardDraggable(card) {
        card.setAttribute('draggable', 'true');

        card.addEventListener('dragstart', (e) => {
            this.draggedElement = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            this.draggedElement = null;
        });
    }

    /**
     * Сделать колонку канбан зоной drop
     */
    makeKanbanDropZone(column) {
        const cardsContainer = column.querySelector('.kanban-cards');
        if (!cardsContainer) return;

        cardsContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
            
            const afterElement = this.getDragAfterElement(cardsContainer, e.clientY);
            const dragging = document.querySelector('.dragging');
            
            if (afterElement == null) {
                cardsContainer.appendChild(dragging);
            } else {
                cardsContainer.insertBefore(dragging, afterElement);
            }
        });

        cardsContainer.addEventListener('drop', async (e) => {
            e.preventDefault();
            
            if (this.draggedElement) {
                const newStatus = column.dataset.status;
                const taskId = this.draggedElement.dataset.taskId;
                
                await this.handleTaskDrop(column, { id: taskId, type: 'task' });
            }
        });
    }

    /**
     * Получить элемент после которого нужно вставить
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.kanban-card:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    /**
     * Настроить переупорядочивание
     */
    setupReordering() {
        document.querySelectorAll('[data-reorderable]').forEach(list => {
            this.makeReorderable(list);
        });
    }

    /**
     * Сделать список переупорядочиваемым
     */
    makeReorderable(list) {
        const items = list.querySelectorAll('[data-reorder-item]');
        
        items.forEach(item => {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', (e) => {
                this.draggedElement = item;
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                this.saveOrder(list);
            });
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = this.getDragAfterElement(list, e.clientY);
            const dragging = document.querySelector('.dragging');
            
            if (afterElement == null) {
                list.appendChild(dragging);
            } else {
                list.insertBefore(dragging, afterElement);
            }
        });
    }

    /**
     * Сохранить порядок
     */
    async saveOrder(list) {
        const items = [...list.querySelectorAll('[data-reorder-item]')];
        const order = items.map((item, index) => ({
            id: item.dataset.itemId,
            position: index
        }));

        try {
            await fetch('/api/v1/tasks/reorder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: list.dataset.reorderable,
                    order: order
                })
            });

            this.showNotification('Порядок сохранен', 'success');
        } catch (error) {
            console.error('Save order error:', error);
            this.showNotification('Ошибка сохранения порядка', 'error');
        }
    }

    /**
     * Триггер изменения статуса
     */
    triggerStatusChange(taskId, newStatus) {
        const event = new CustomEvent('taskStatusChanged', {
            detail: { taskId, newStatus }
        });
        document.dispatchEvent(event);
    }

    /**
     * Добавить стили
     */
    addStyles() {
        if (document.getElementById('dragDropStyles')) return;

        const style = document.createElement('style');
        style.id = 'dragDropStyles';
        style.textContent = `
            [draggable="true"] {
                cursor: move;
            }

            .dragging {
                opacity: 0.5;
                transform: rotate(5deg);
            }

            .drag-over {
                background: rgba(102, 126, 234, 0.1);
                border: 2px dashed var(--primary);
            }

            .file-drag-over {
                background: rgba(102, 126, 234, 0.1);
                border: 2px dashed var(--primary);
                transform: scale(1.02);
            }

            .drop-indicator {
                position: absolute;
                left: 0;
                right: 0;
                height: 2px;
                background: var(--primary);
                pointer-events: none;
                z-index: 1000;
            }

            .drop-indicator::before {
                content: '';
                position: absolute;
                left: 0;
                top: -4px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--primary);
            }

            .upload-progress-container {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: var(--bg-card);
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                min-width: 300px;
                z-index: 1000;
            }

            .upload-progress-item {
                margin-bottom: 1rem;
            }

            .upload-progress-item:last-child {
                margin-bottom: 0;
            }

            .upload-file-name {
                font-size: 0.875rem;
                font-weight: 500;
                margin-bottom: 0.5rem;
                color: var(--text-primary);
            }

            .upload-progress-bar {
                height: 4px;
                background: var(--bg-body);
                border-radius: 2px;
                overflow: hidden;
                margin-bottom: 0.25rem;
            }

            .upload-progress-fill {
                height: 100%;
                background: var(--primary);
                transition: width 0.3s ease;
            }

            .upload-progress-item.success .upload-progress-fill {
                background: var(--success);
            }

            .upload-progress-item.error .upload-progress-fill {
                background: var(--danger);
            }

            .upload-status {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .upload-progress-item.success .upload-status {
                color: var(--success);
            }

            .upload-progress-item.error .upload-status {
                color: var(--danger);
            }

            [data-file-drop] {
                border: 2px dashed var(--border);
                border-radius: 8px;
                padding: 2rem;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
            }

            [data-file-drop]:hover {
                border-color: var(--primary);
                background: rgba(102, 126, 234, 0.05);
            }

            [data-file-drop]::before {
                content: '📁';
                font-size: 3rem;
                display: block;
                margin-bottom: 1rem;
            }

            [data-file-drop]::after {
                content: 'Перетащите файлы сюда или нажмите для выбора';
                display: block;
                color: var(--text-muted);
                font-size: 0.875rem;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dragDropManager = new DragDropManager();
    });
} else {
    window.dragDropManager = new DragDropManager();
}

// Экспорт
window.DragDropManager = DragDropManager;
