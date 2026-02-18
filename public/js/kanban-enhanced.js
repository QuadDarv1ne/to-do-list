/**
 * Kanban Board Enhanced
 * Modern kanban with theme support and smooth animations
 */

document.addEventListener('DOMContentLoaded', function() {
    initKanbanBoard();
});

function initKanbanBoard() {
    initDragAndDrop();
    initCardActions();
    initColumnActions();
    initQuickAdd();
    initThemeAwareKanban();
}

/**
 * Drag and Drop
 */
function initDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    cards.forEach(card => {
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
            e.dataTransfer.setData('taskId', this.dataset.taskId);
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
    });
    
    columns.forEach(column => {
        const cardContainer = column.querySelector('.kanban-cards');
        if (!cardContainer) return;
        
        cardContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            const dragging = document.querySelector('.dragging');
            const afterElement = getDragAfterElement(cardContainer, e.clientY);
            
            if (afterElement == null) {
                cardContainer.appendChild(dragging);
            } else {
                cardContainer.insertBefore(dragging, afterElement);
            }
        });
        
        cardContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            const taskId = e.dataTransfer.getData('taskId');
            const newStatus = column.dataset.status;
            
            if (taskId && newStatus) {
                updateTaskStatus(taskId, newStatus);
            }
        });
    });
}

/**
 * Get drag after element
 */
function getDragAfterElement(container, y) {
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
 * Update task status
 */
function updateTaskStatus(taskId, status) {
    fetch(`/api/v1/tasks/${taskId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Статус задачи обновлен', 'success');
            updateColumnCounts();
        } else {
            showToast('Ошибка обновления статуса', 'error');
            location.reload(); // Reload to restore correct state
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка обновления статуса', 'error');
        location.reload();
    });
}

/**
 * Card Actions
 */
function initCardActions() {
    // Quick edit
    const editButtons = document.querySelectorAll('[data-action="edit"]');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.closest('.kanban-card').dataset.taskId;
            window.location.href = `/task/${taskId}/edit`;
        });
    });
    
    // Quick delete
    const deleteButtons = document.querySelectorAll('[data-action="delete"]');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.closest('.kanban-card').dataset.taskId;
            if (confirm('Удалить задачу?')) {
                deleteTask(taskId);
            }
        });
    });
    
    // Card click to view
    const cards = document.querySelectorAll('.kanban-card');
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons
            if (e.target.closest('button') || e.target.closest('a')) return;
            
            const taskId = this.dataset.taskId;
            window.location.href = `/task/${taskId}`;
        });
    });
}

/**
 * Delete task
 */
function deleteTask(taskId) {
    fetch(`/api/v1/tasks/${taskId}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Задача удалена', 'success');
            const card = document.querySelector(`[data-task-id="${taskId}"]`);
            if (card) {
                card.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    card.remove();
                    updateColumnCounts();
                }, 300);
            }
        } else {
            showToast('Ошибка удаления задачи', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка удаления задачи', 'error');
    });
}

/**
 * Column Actions
 */
function initColumnActions() {
    // Collapse/expand columns
    const collapseButtons = document.querySelectorAll('[data-action="collapse"]');
    collapseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const column = this.closest('.kanban-column');
            column.classList.toggle('collapsed');
            
            const icon = this.querySelector('i');
            if (column.classList.contains('collapsed')) {
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
            } else {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
            }
        });
    });
}

/**
 * Quick Add
 */
function initQuickAdd() {
    const quickAddButtons = document.querySelectorAll('[data-action="quick-add"]');
    
    quickAddButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const column = this.closest('.kanban-column');
            const status = column.dataset.status;
            showQuickAddForm(column, status);
        });
    });
}

/**
 * Show quick add form
 */
function showQuickAddForm(column, status) {
    const cardContainer = column.querySelector('.kanban-cards');
    
    // Remove existing form if any
    const existingForm = column.querySelector('.quick-add-form');
    if (existingForm) {
        existingForm.remove();
        return;
    }
    
    const form = document.createElement('div');
    form.className = 'quick-add-form';
    form.innerHTML = `
        <input type="text" class="form-control mb-2" placeholder="Название задачи..." id="quick-task-title">
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" id="quick-add-save">Добавить</button>
            <button class="btn btn-sm btn-secondary" id="quick-add-cancel">Отмена</button>
        </div>
    `;
    
    cardContainer.insertBefore(form, cardContainer.firstChild);
    
    const titleInput = form.querySelector('#quick-task-title');
    const saveBtn = form.querySelector('#quick-add-save');
    const cancelBtn = form.querySelector('#quick-add-cancel');
    
    titleInput.focus();
    
    saveBtn.addEventListener('click', function() {
        const title = titleInput.value.trim();
        if (title) {
            createQuickTask(title, status, form);
        }
    });
    
    cancelBtn.addEventListener('click', function() {
        form.remove();
    });
    
    titleInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            saveBtn.click();
        } else if (e.key === 'Escape') {
            cancelBtn.click();
        }
    });
}

/**
 * Create quick task
 */
function createQuickTask(title, status, form) {
    fetch('/api/v1/tasks/quick-create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ title, status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Задача создана', 'success');
            form.remove();
            location.reload(); // Reload to show new task
        } else {
            showToast('Ошибка создания задачи', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка создания задачи', 'error');
    });
}

/**
 * Update column counts
 */
function updateColumnCounts() {
    const columns = document.querySelectorAll('.kanban-column');
    columns.forEach(column => {
        const cards = column.querySelectorAll('.kanban-card');
        const countBadge = column.querySelector('.column-count');
        if (countBadge) {
            countBadge.textContent = cards.length;
        }
    });
}

/**
 * Theme-aware kanban
 */
function initThemeAwareKanban() {
    window.addEventListener('themechange', function(e) {
        updateKanbanColors(e.detail.theme);
    });
}

/**
 * Update kanban colors
 */
function updateKanbanColors(theme) {
    const cards = document.querySelectorAll('.kanban-card');
    // Cards use CSS variables, automatically updated
}

/**
 * Helper functions
 */
function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}

// Export for use in other scripts
window.KanbanEnhanced = {
    updateTaskStatus,
    deleteTask,
    updateColumnCounts
};
