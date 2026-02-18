/**
 * Enhanced Tasks Page Functionality
 * Provides interactive features for task management
 */

class TasksManager {
    constructor() {
        this.selectedTasks = new Set();
        this.currentView = 'grid';
        this.init();
    }

    init() {
        this.setupViewSwitcher();
        this.setupBulkActions();
        this.setupFilters();
        this.setupSearch();
        this.setupTaskCards();
        this.setupQuickActions();
        this.setupKeyboardShortcuts();
    }

    /**
     * View Switcher (Grid/List)
     */
    setupViewSwitcher() {
        const viewBtns = document.querySelectorAll('.view-btn');
        const tasksGrid = document.querySelector('.tasks-grid');
        const tasksTable = document.querySelector('.tasks-table');

        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                this.switchView(view);
                
                // Update active state
                viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Save preference
                localStorage.setItem('tasksView', view);
            });
        });

        // Load saved preference
        const savedView = localStorage.getItem('tasksView');
        if (savedView) {
            this.switchView(savedView);
            document.querySelector(`[data-view="${savedView}"]`)?.classList.add('active');
        }
    }

    switchView(view) {
        const tasksGrid = document.querySelector('.tasks-grid');
        const tasksTable = document.querySelector('.tasks-table');

        if (view === 'grid') {
            tasksGrid?.classList.remove('d-none');
            tasksTable?.classList.add('d-none');
        } else {
            tasksGrid?.classList.add('d-none');
            tasksTable?.classList.remove('d-none');
        }

        this.currentView = view;
    }

    /**
     * Bulk Actions
     */
    setupBulkActions() {
        const checkboxes = document.querySelectorAll('.task-checkbox');
        const selectAllCheckbox = document.getElementById('selectAllTasks');
        const bulkActionsBar = document.querySelector('.bulk-actions-bar');
        const bulkActionsCount = document.querySelector('.bulk-actions-count');

        // Select all
        selectAllCheckbox?.addEventListener('change', (e) => {
            checkboxes.forEach(cb => {
                cb.checked = e.target.checked;
                if (e.target.checked) {
                    this.selectedTasks.add(cb.value);
                } else {
                    this.selectedTasks.delete(cb.value);
                }
            });
            this.updateBulkActionsBar();
        });

        // Individual checkboxes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.selectedTasks.add(e.target.value);
                } else {
                    this.selectedTasks.delete(e.target.value);
                }
                this.updateBulkActionsBar();
            });
        });

        // Bulk action buttons
        document.getElementById('bulkComplete')?.addEventListener('click', () => {
            this.bulkUpdateStatus('completed');
        });

        document.getElementById('bulkDelete')?.addEventListener('click', () => {
            this.bulkDelete();
        });

        document.getElementById('bulkExport')?.addEventListener('click', () => {
            this.bulkExport();
        });
    }

    updateBulkActionsBar() {
        const bulkActionsBar = document.querySelector('.bulk-actions-bar');
        const bulkActionsCount = document.querySelector('.bulk-actions-count');

        if (this.selectedTasks.size > 0) {
            bulkActionsBar?.classList.add('active');
            if (bulkActionsCount) {
                bulkActionsCount.textContent = `${this.selectedTasks.size} задач выбрано`;
            }
        } else {
            bulkActionsBar?.classList.remove('active');
        }
    }

    async bulkUpdateStatus(status) {
        if (!confirm(`Изменить статус ${this.selectedTasks.size} задач?`)) {
            return;
        }

        try {
            const response = await fetch('/api/tasks/bulk-update-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    taskIds: Array.from(this.selectedTasks),
                    status: status
                })
            });

            if (response.ok) {
                this.showNotification('Статус задач обновлен', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to update tasks');
            }
        } catch (error) {
            this.showNotification('Ошибка при обновлении задач', 'error');
        }
    }

    async bulkDelete() {
        if (!confirm(`Удалить ${this.selectedTasks.size} задач? Это действие нельзя отменить.`)) {
            return;
        }

        try {
            const response = await fetch('/api/tasks/bulk-delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    taskIds: Array.from(this.selectedTasks)
                })
            });

            if (response.ok) {
                this.showNotification('Задачи удалены', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to delete tasks');
            }
        } catch (error) {
            this.showNotification('Ошибка при удалении задач', 'error');
        }
    }

    bulkExport() {
        const taskIds = Array.from(this.selectedTasks).join(',');
        window.location.href = `/tasks/export?ids=${taskIds}`;
    }

    /**
     * Filters
     */
    setupFilters() {
        const filterForm = document.querySelector('.filter-panel form');
        const clearFiltersBtn = document.getElementById('clearFilters');

        // Auto-submit on filter change
        const filterInputs = filterForm?.querySelectorAll('select, input[type="checkbox"]');
        filterInputs?.forEach(input => {
            input.addEventListener('change', () => {
                // Add small delay for better UX
                setTimeout(() => filterForm.submit(), 300);
            });
        });

        // Clear filters
        clearFiltersBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = window.location.pathname;
        });

        // Filter chips
        this.renderFilterChips();
    }

    renderFilterChips() {
        const filterChipsContainer = document.querySelector('.filter-chips');
        if (!filterChipsContainer) return;

        const urlParams = new URLSearchParams(window.location.search);
        filterChipsContainer.innerHTML = '';

        const filterLabels = {
            status: 'Статус',
            priority: 'Приоритет',
            category: 'Категория',
            tag: 'Тег',
            assigned_to_me: 'Назначено мне',
            created_by_me: 'Создано мной',
            overdue: 'Просрочено'
        };

        urlParams.forEach((value, key) => {
            if (value && filterLabels[key]) {
                const chip = document.createElement('div');
                chip.className = 'filter-chip';
                chip.innerHTML = `
                    <span>${filterLabels[key]}: ${value}</span>
                    <span class="filter-chip-remove" data-filter="${key}">
                        <i class="fas fa-times"></i>
                    </span>
                `;
                filterChipsContainer.appendChild(chip);

                // Remove filter on click
                chip.querySelector('.filter-chip-remove').addEventListener('click', () => {
                    urlParams.delete(key);
                    window.location.search = urlParams.toString();
                });
            }
        });
    }

    /**
     * Search
     */
    setupSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        let searchTimeout;

        searchInput?.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Auto-submit search after 500ms of no typing
                e.target.form.submit();
            }, 500);
        });
    }

    /**
     * Task Cards Interactions
     */
    setupTaskCards() {
        const taskCards = document.querySelectorAll('.task-card');

        taskCards.forEach(card => {
            // Add hover effect
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px)';
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });

            // Quick status change
            const statusBadge = card.querySelector('.task-card-badge[data-status]');
            statusBadge?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.showStatusMenu(card, statusBadge);
            });
        });
    }

    showStatusMenu(card, badge) {
        const taskId = card.dataset.taskId;
        const currentStatus = badge.dataset.status;

        const statuses = [
            { value: 'pending', label: 'В ожидании', class: 'status-pending' },
            { value: 'in_progress', label: 'В процессе', class: 'status-in_progress' },
            { value: 'completed', label: 'Завершено', class: 'status-completed' }
        ];

        // Create menu
        const menu = document.createElement('div');
        menu.className = 'status-quick-menu';
        menu.style.cssText = `
            position: absolute;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0.5rem;
            z-index: 1000;
        `;

        statuses.forEach(status => {
            if (status.value !== currentStatus) {
                const item = document.createElement('div');
                item.className = `status-menu-item ${status.class}`;
                item.textContent = status.label;
                item.style.cssText = `
                    padding: 0.5rem 1rem;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: background 0.2s;
                `;
                item.addEventListener('click', () => {
                    this.updateTaskStatus(taskId, status.value);
                    menu.remove();
                });
                menu.appendChild(item);
            }
        });

        badge.appendChild(menu);

        // Close menu on outside click
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 0);
    }

    async updateTaskStatus(taskId, status) {
        try {
            const response = await fetch(`/api/tasks/${taskId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status })
            });

            if (response.ok) {
                this.showNotification('Статус обновлен', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                throw new Error('Failed to update status');
            }
        } catch (error) {
            this.showNotification('Ошибка при обновлении статуса', 'error');
        }
    }

    /**
     * Quick Actions
     */
    setupQuickActions() {
        const quickActionBtn = document.getElementById('quickActionsBtn');
        const quickActionsMenu = document.getElementById('quickActionsMenu');

        quickActionBtn?.addEventListener('click', () => {
            quickActionsMenu?.classList.toggle('active');
        });

        // Close menu on outside click
        document.addEventListener('click', (e) => {
            if (!quickActionBtn?.contains(e.target) && !quickActionsMenu?.contains(e.target)) {
                quickActionsMenu?.classList.remove('active');
            }
        });
    }

    /**
     * Keyboard Shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('input[name="search"]')?.focus();
            }

            // Ctrl/Cmd + N: New task
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = '/tasks/new';
            }

            // Ctrl/Cmd + A: Select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                document.getElementById('selectAllTasks')?.click();
            }

            // Escape: Clear selection
            if (e.key === 'Escape') {
                this.selectedTasks.clear();
                document.querySelectorAll('.task-checkbox').forEach(cb => cb.checked = false);
                this.updateBulkActionsBar();
            }
        });
    }

    /**
     * Notifications
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.style.cssText = `
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new TasksManager();
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);
