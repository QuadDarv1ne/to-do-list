/**
 * Tasks Page Enhanced
 * Modern task list with theme support and advanced interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    initTasksPage();
});

function initTasksPage() {
    initTaskFilters();
    initTaskActions();
    initBulkActions();
    initTaskSearch();
    initViewModes();
    initThemeAwareTaskList();
}

/**
 * Task Filters
 */
function initTaskFilters() {
    const filterForm = document.querySelector('form[method="get"]');
    if (!filterForm) return;
    
    // Auto-submit on filter change
    const filterInputs = filterForm.querySelectorAll('select, input[type="checkbox"]');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            filterForm.submit();
        });
    });
    
    // Clear filters button
    const clearBtn = document.getElementById('clear-filters');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            filterForm.reset();
            filterForm.submit();
        });
    }
}

/**
 * Task Actions
 */
function initTaskActions() {
    // Quick status change
    const statusButtons = document.querySelectorAll('[data-task-status]');
    statusButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.dataset.taskId;
            const newStatus = this.dataset.taskStatus;
            updateTaskStatus(taskId, newStatus);
        });
    });
    
    // Quick priority change
    const priorityButtons = document.querySelectorAll('[data-task-priority]');
    priorityButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.dataset.taskId;
            const newPriority = this.dataset.taskPriority;
            updateTaskPriority(taskId, newPriority);
        });
    });
}

/**
 * Update task status
 */
function updateTaskStatus(taskId, status) {
    fetch(`/api/tasks/${taskId}/status`, {
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
            // Update UI
            const taskRow = document.querySelector(`[data-task-id="${taskId}"]`);
            if (taskRow) {
                const statusBadge = taskRow.querySelector('.badge-status');
                if (statusBadge) {
                    statusBadge.className = `badge badge-${status}`;
                    statusBadge.textContent = getStatusLabel(status);
                }
            }
        } else {
            showToast('Ошибка обновления статуса', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка обновления статуса', 'error');
    });
}

/**
 * Update task priority
 */
function updateTaskPriority(taskId, priority) {
    fetch(`/api/tasks/${taskId}/priority`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ priority })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Приоритет задачи обновлен', 'success');
            // Update UI
            const taskRow = document.querySelector(`[data-task-id="${taskId}"]`);
            if (taskRow) {
                const priorityBadge = taskRow.querySelector('.badge-priority');
                if (priorityBadge) {
                    priorityBadge.className = `badge badge-priority-${priority}`;
                    priorityBadge.textContent = getPriorityLabel(priority);
                }
            }
        } else {
            showToast('Ошибка обновления приоритета', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка обновления приоритета', 'error');
    });
}

/**
 * Bulk Actions
 */
function initBulkActions() {
    const selectAllCheckbox = document.getElementById('select-all-tasks');
    const taskCheckboxes = document.querySelectorAll('.task-checkbox');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    
    if (!selectAllCheckbox || !bulkActionsBar) return;
    
    // Select all
    selectAllCheckbox.addEventListener('change', function() {
        taskCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActionsBar();
    });
    
    // Individual checkboxes
    taskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkActionsBar();
            
            // Update select all checkbox
            const allChecked = Array.from(taskCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(taskCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        });
    });
    
    // Bulk action buttons
    const bulkDeleteBtn = document.getElementById('bulk-delete');
    const bulkCompleteBtn = document.getElementById('bulk-complete');
    const bulkArchiveBtn = document.getElementById('bulk-archive');
    
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedIds = getSelectedTaskIds();
            if (selectedIds.length > 0 && confirm(`Удалить ${selectedIds.length} задач?`)) {
                bulkDeleteTasks(selectedIds);
            }
        });
    }
    
    if (bulkCompleteBtn) {
        bulkCompleteBtn.addEventListener('click', function() {
            const selectedIds = getSelectedTaskIds();
            if (selectedIds.length > 0) {
                bulkUpdateStatus(selectedIds, 'completed');
            }
        });
    }
    
    if (bulkArchiveBtn) {
        bulkArchiveBtn.addEventListener('click', function() {
            const selectedIds = getSelectedTaskIds();
            if (selectedIds.length > 0) {
                bulkArchiveTasks(selectedIds);
            }
        });
    }
}

/**
 * Update bulk actions bar
 */
function updateBulkActionsBar() {
    const selectedCount = getSelectedTaskIds().length;
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const selectedCountSpan = document.getElementById('selected-count');
    
    if (bulkActionsBar && selectedCountSpan) {
        if (selectedCount > 0) {
            bulkActionsBar.classList.add('show');
            selectedCountSpan.textContent = selectedCount;
        } else {
            bulkActionsBar.classList.remove('show');
        }
    }
}

/**
 * Get selected task IDs
 */
function getSelectedTaskIds() {
    const checkboxes = document.querySelectorAll('.task-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

/**
 * Task Search
 */
function initTaskSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
}

/**
 * View Modes
 */
function initViewModes() {
    const viewModeButtons = document.querySelectorAll('[data-view-mode]');
    const taskList = document.getElementById('task-list');
    
    if (!taskList) return;
    
    viewModeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.dataset.viewMode;
            
            // Update active button
            viewModeButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update view
            taskList.className = `task-list-${mode}`;
            localStorage.setItem('taskViewMode', mode);
        });
    });
    
    // Restore saved view mode
    const savedMode = localStorage.getItem('taskViewMode');
    if (savedMode) {
        const btn = document.querySelector(`[data-view-mode="${savedMode}"]`);
        if (btn) btn.click();
    }
}

/**
 * Theme-aware task list
 */
function initThemeAwareTaskList() {
    window.addEventListener('themechange', function(e) {
        // Update task list colors
        updateTaskListColors(e.detail.theme);
    });
}

/**
 * Update task list colors
 */
function updateTaskListColors(theme) {
    const taskItems = document.querySelectorAll('.task-item');
    // Task items use CSS variables, automatically updated
}

/**
 * Helper functions
 */
function getStatusLabel(status) {
    const labels = {
        pending: 'В ожидании',
        in_progress: 'В процессе',
        completed: 'Завершено',
        cancelled: 'Отменено'
    };
    return labels[status] || status;
}

function getPriorityLabel(priority) {
    const labels = {
        low: 'Низкий',
        medium: 'Средний',
        high: 'Высокий'
    };
    return labels[priority] || priority;
}

function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}

// Export for use in other scripts
window.TasksEnhanced = {
    updateTaskStatus,
    updateTaskPriority,
    getSelectedTaskIds
};
