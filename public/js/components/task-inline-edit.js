/**
 * Task Inline Editing & Saved Filters
 * Inline редактирование и сохранение фильтров
 */

(function() {
    'use strict';

    // ============================================
    // INLINE TASK EDITING
    // ============================================

    /**
     * Initialize inline status editing
     */
    function initInlineStatusEditing() {
        // Find all editable status badges
        const editableBadges = document.querySelectorAll('.badge-editable[data-task-id]');
        
        editableBadges.forEach(badge => {
            badge.addEventListener('click', (e) => {
                e.stopPropagation();
                showStatusDropdown(badge);
            });
        });

        // Close dropdown on outside click
        document.addEventListener('click', () => {
            closeAllDropdowns();
        });
    }

    /**
     * Show status dropdown
     * @param {HTMLElement} badge - Badge element
     */
    function showStatusDropdown(badge) {
        closeAllDropdowns();

        const taskId = badge.dataset.taskId;
        const currentStatus = badge.dataset.status;
        const rect = badge.getBoundingClientRect();

        const dropdown = document.createElement('div');
        dropdown.className = 'inline-edit-dropdown';
        dropdown.style.position = 'fixed';
        dropdown.style.top = `${rect.bottom + window.scrollY + 5}px`;
        dropdown.style.left = `${rect.left + window.scrollX}px`;

        const statuses = [
            { value: 'pending', label: 'В ожидании', icon: 'fa-clock', color: 'warning' },
            { value: 'in_progress', label: 'В процессе', icon: 'fa-play-circle', color: 'info' },
            { value: 'completed', label: 'Завершено', icon: 'fa-check-circle', color: 'success' }
        ];

        statuses.forEach(status => {
            const item = document.createElement('div');
            item.className = `inline-edit-dropdown-item ${status.value === currentStatus ? 'active' : ''}`;
            item.innerHTML = `
                <i class="fas ${status.icon}"></i>
                <span>${status.label}</span>
            `;
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                updateTaskStatus(taskId, status.value, badge);
            });
            dropdown.appendChild(item);
        });

        document.body.appendChild(dropdown);
    }

    /**
     * Close all dropdowns
     */
    function closeAllDropdowns() {
        document.querySelectorAll('.inline-edit-dropdown').forEach(d => d.remove());
    }

    /**
     * Update task status via API
     * @param {number} taskId - Task ID
     * @param {string} newStatus - New status
     * @param {HTMLElement} badge - Badge element
     */
    async function updateTaskStatus(taskId, newStatus, badge) {
        const oldStatus = badge.dataset.status;
        
        try {
            const response = await fetch(`/api/v1/tasks/${taskId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (response.ok) {
                // Update badge appearance
                updateBadgeAppearance(badge, newStatus);
                badge.dataset.status = newStatus;
                
                // Show success toast with undo
                showUndoToast(taskId, oldStatus, newStatus);
                
                // Dispatch event for other scripts
                window.dispatchEvent(new CustomEvent('task-status-updated', {
                    detail: { taskId, oldStatus, newStatus }
                }));
            } else {
                throw new Error('Failed to update status');
            }
        } catch (error) {
            console.error('Error updating task status:', error);
            showToast('Ошибка при обновлении статуса', 'error');
        }
    }

    /**
     * Update badge appearance based on status
     * @param {HTMLElement} badge - Badge element
     * @param {string} status - New status
     */
    function updateBadgeAppearance(badge, status) {
        const statusConfig = {
            pending: { class: 'warning', label: 'В ожидании', icon: 'fa-clock' },
            in_progress: { class: 'info', label: 'В процессе', icon: 'fa-play-circle' },
            completed: { class: 'success', label: 'Завершено', icon: 'fa-check-circle' }
        };

        const config = statusConfig[status];
        
        // Remove old status classes
        badge.classList.remove('bg-warning', 'bg-info', 'bg-success');
        
        // Add new status class
        badge.classList.add(`bg-${config.class}`);
        
        // Update text
        badge.innerHTML = `
            <i class="fas ${config.icon} me-1"></i>
            ${config.label}
            <i class="fas fa-chevron-down chevron"></i>
        `;
    }

    /**
     * Show undo toast
     * @param {number} taskId - Task ID
     * @param {string} oldStatus - Old status
     * @param {string} newStatus - New status
     */
    function showUndoToast(taskId, oldStatus, newStatus) {
        const toast = document.createElement('div');
        toast.className = 'inline-edit-toast';
        toast.innerHTML = `
            <i class="fas fa-check-circle text-success"></i>
            <span>Статус изменён</span>
            <button class="undo-btn">Отменить</button>
        `;

        document.body.appendChild(toast);

        // Undo functionality
        toast.querySelector('.undo-btn').addEventListener('click', async () => {
            await updateTaskStatus(taskId, oldStatus, toast.closest('.inline-edit-toast').previousElementSibling);
            toast.remove();
        });

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // ============================================
    // SAVED FILTERS
    // ============================================

    const FILTERS_STORAGE_KEY = 'task_filters_saved';

    /**
     * Initialize saved filters
     */
    function initSavedFilters() {
        // Load saved filters on page load
        loadSavedFilters();
        
        // Add save filter button
        addSaveFilterButton();
        
        // Add saved filters display
        renderSavedFilters();
    }

    /**
     * Get current filter state from URL
     * @returns {Object} Current filter state
     */
    function getCurrentFilterState() {
        const params = new URLSearchParams(window.location.search);
        const filters = {};
        
        const filterKeys = ['search', 'status', 'priority', 'category', 'tag', 'hide_completed', 'sort_by', 'sort_direction'];
        
        filterKeys.forEach(key => {
            if (params.has(key)) {
                filters[key] = params.get(key);
            }
        });
        
        return filters;
    }

    /**
     * Save current filters to localStorage
     */
    function saveCurrentFilters() {
        const filters = getCurrentFilterState();
        const savedFilters = getSavedFilters();
        
        // Check if this filter combination already exists
        const existingIndex = savedFilters.findIndex(f => 
            JSON.stringify(f.filters) === JSON.stringify(filters)
        );
        
        if (existingIndex === -1) {
            const name = prompt('Название для этого фильтра:', 'Мой фильтр');
            if (name) {
                savedFilters.push({
                    id: Date.now(),
                    name: name,
                    filters: filters,
                    created: new Date().toISOString()
                });
                localStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(savedFilters));
                renderSavedFilters();
                showToast('Фильтр сохранён', 'success');
            }
        } else {
            if (confirm('Этот фильтр уже сохранён. Обновить?')) {
                savedFilters[existingIndex].name = prompt('Название фильтра:', savedFilters[existingIndex].name);
                localStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(savedFilters));
                renderSavedFilters();
                showToast('Фильтр обновлён', 'success');
            }
        }
    }

    /**
     * Get saved filters from localStorage
     * @returns {Array} Saved filters
     */
    function getSavedFilters() {
        try {
            return JSON.parse(localStorage.getItem(FILTERS_STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Load saved filters and apply if matching
     */
    function loadSavedFilters() {
        const savedFilters = getSavedFilters();
        const currentFilters = getCurrentFilterState();
        
        // Check if current filters match any saved filter
        const matchingFilter = savedFilters.find(f => 
            JSON.stringify(f.filters) === JSON.stringify(currentFilters)
        );
        
        if (matchingFilter) {
            console.log('Applied saved filter:', matchingFilter.name);
        }
    }

    /**
     * Apply saved filter
     * @param {Object} filter - Filter to apply
     */
    function applyFilter(filter) {
        const params = new URLSearchParams(window.location.search);
        
        // Clear existing filters
        params.delete('search');
        params.delete('status');
        params.delete('priority');
        params.delete('category');
        params.delete('tag');
        params.delete('hide_completed');
        params.delete('sort_by');
        params.delete('sort_direction');
        
        // Apply new filters
        Object.entries(filter.filters).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });
        
        window.location.href = `?${params.toString()}`;
    }

    /**
     * Delete saved filter
     * @param {number} filterId - Filter ID
     */
    function deleteFilter(filterId) {
        if (!confirm('Удалить этот фильтр?')) return;
        
        const savedFilters = getSavedFilters();
        const index = savedFilters.findIndex(f => f.id === filterId);
        
        if (index !== -1) {
            savedFilters.splice(index, 1);
            localStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(savedFilters));
            renderSavedFilters();
            showToast('Фильтр удалён', 'success');
        }
    }

    /**
     * Add save filter button to page
     */
    function addSaveFilterButton() {
        const filterForm = document.querySelector('.card-body form.row.g-3');
        if (!filterForm) return;
        
        const buttonGroup = filterForm.querySelector('.btn-group.w-100');
        if (!buttonGroup) return;
        
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-outline-primary';
        saveBtn.innerHTML = '<i class="fas fa-bookmark me-1"></i>Сохранить';
        saveBtn.title = 'Сохранить текущий фильтр';
        saveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            saveCurrentFilters();
        });
        
        buttonGroup.appendChild(saveBtn);
    }

    /**
     * Render saved filters UI
     */
    function renderSavedFilters() {
        const savedFilters = getSavedFilters();
        
        // Remove existing saved filters container
        const existingContainer = document.querySelector('.saved-filters-container');
        if (existingContainer) {
            existingContainer.remove();
        }
        
        if (savedFilters.length === 0) return;
        
        // Create container
        const container = document.createElement('div');
        container.className = 'saved-filters-container card border-0 shadow-sm mb-4';
        
        container.innerHTML = `
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-bookmark me-2 text-primary"></i>
                    Сохранённые фильтры
                </h5>
            </div>
            <div class="card-body">
                <div class="quick-filters">
                    ${savedFilters.map(filter => `
                        <div class="filter-chip" data-filter-id="${filter.id}">
                            <i class="fas fa-bookmark chip-icon"></i>
                            <span>${filter.name}</span>
                            <button class="btn btn-sm btn-link p-0 ms-1 delete-filter" data-filter-id="${filter.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        // Add click handlers
        container.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                if (!e.target.closest('.delete-filter')) {
                    const filterId = parseInt(chip.dataset.filterId);
                    const filter = savedFilters.find(f => f.id === filterId);
                    if (filter) {
                        applyFilter(filter);
                    }
                }
            });
        });
        
        container.querySelectorAll('.delete-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const filterId = parseInt(btn.dataset.filterId);
                deleteFilter(filterId);
            });
        });
        
        // Insert after filter form
        const filterForm = document.querySelector('.card-body form.row.g-3')?.closest('.card');
        if (filterForm) {
            filterForm.after(container);
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================

    function init() {
        initInlineStatusEditing();
        initSavedFilters();
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Reinitialize after Turbo navigation
    document.addEventListener('turbo:load', init);

    // Expose to global scope
    window.TaskInlineEdit = {
        updateStatus: updateTaskStatus,
        saveFilter: saveCurrentFilters,
        applyFilter: applyFilter,
        deleteFilter: deleteFilter
    };

})();
