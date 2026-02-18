/**
 * Kanban Board Improvements
 * WIP Limits, Color Coding, Drag & Drop
 */

(function() {
    'use strict';

    class KanbanBoard {
        constructor(options = {}) {
            this.options = {
                storageKey: 'kanban_wip_limits',
                ...options
            };

            this.columns = [];
            this.wipLimits = this.loadWipLimits();

            this.init();
        }

        /**
         * Initialize Kanban board
         */
        init() {
            this.columns = Array.from(document.querySelectorAll('.kanban-column'));
            this.columns.forEach(column => this.initColumn(column));
            
            this.initDragAndDrop();
            this.updateWipIndicators();
        }

        /**
         * Initialize single column
         * @param {HTMLElement} column - Column element
         */
        initColumn(column) {
            const status = column.dataset.status;
            const wipLimit = this.wipLimits[status] || null;

            // Add WIP limit indicator
            this.addWipIndicator(column, wipLimit);

            // Add quick add button
            this.addQuickAddButton(column);

            // Add color coding to cards
            this.addColorCoding(column);

            // Add age indicators
            this.addAgeIndicators(column);

            // Add blocker badges
            this.addBlockerBadges(column);
        }

        /**
         * Add WIP limit indicator to column
         * @param {HTMLElement} column - Column element
         * @param {number|null} limit - WIP limit
         */
        addWipIndicator(column, limit) {
            const header = column.querySelector('.kanban-column-header');
            if (!header) return;

            let wipEl = header.querySelector('.wip-limit');
            
            if (!wipEl) {
                wipEl = document.createElement('div');
                wipEl.className = 'wip-limit';
                header.appendChild(wipEl);
            }

            const cards = column.querySelectorAll('.kanban-card').length;
            
            if (limit) {
                wipEl.innerHTML = `
                    <span class="wip-current">${cards}</span>
                    <span>/</span>
                    <span class="wip-max">${limit}</span>
                    <i class="fas fa-cog ms-1" style="cursor: pointer;" onclick="KanbanBoard.editWipLimit('${column.dataset.status}')"></i>
                `;

                // Update warning state
                wipEl.classList.remove('warning', 'exceeded');
                if (cards >= limit) {
                    wipEl.classList.add('exceeded');
                } else if (cards >= limit * 0.8) {
                    wipEl.classList.add('warning');
                }
            } else {
                wipEl.innerHTML = `
                    <span>${cards}</span>
                    <i class="fas fa-cog ms-1" style="cursor: pointer;" onclick="KanbanBoard.editWipLimit('${column.dataset.status}')"></i>
                `;
            }
        }

        /**
         * Edit WIP limit
         * @param {string} status - Column status
         */
        static editWipLimit(status) {
            const currentLimit = prompt(`WIP лимит для колонки "${status}":`, KanbanBoard.instance?.wipLimits[status] || '');
            
            if (currentLimit !== null) {
                const limit = currentLimit === '' ? null : parseInt(currentLimit, 10);
                
                if (KanbanBoard.instance) {
                    KanbanBoard.instance.setWipLimit(status, limit);
                }
            }
        }

        /**
         * Set WIP limit
         * @param {string} status - Column status
         * @param {number|null} limit - WIP limit
         */
        setWipLimit(status, limit) {
            if (limit !== null && (isNaN(limit) || limit < 1)) {
                showToast('WIP лимит должен быть больше 0', 'error');
                return;
            }

            this.wipLimits[status] = limit;
            this.saveWipLimits();
            this.updateWipIndicators();
            
            showToast(limit ? `WIP лимит установлен: ${limit}` : 'WIP лимит снят', 'success');
        }

        /**
         * Update all WIP indicators
         */
        updateWipIndicators() {
            this.columns.forEach(column => {
                const status = column.dataset.status;
                const limit = this.wipLimits[status];
                this.addWipIndicator(column, limit);
            });
        }

        /**
         * Load WIP limits from localStorage
         * @returns {Object} WIP limits
         */
        loadWipLimits() {
            try {
                return JSON.parse(localStorage.getItem(this.options.storageKey)) || {};
            } catch (e) {
                return {};
            }
        }

        /**
         * Save WIP limits to localStorage
         */
        saveWipLimits() {
            localStorage.setItem(this.options.storageKey, JSON.stringify(this.wipLimits));
        }

        /**
         * Add color coding to cards
         * @param {HTMLElement} column - Column element
         */
        addColorCoding(column) {
            const cards = column.querySelectorAll('.kanban-card');
            
            cards.forEach(card => {
                const priority = card.dataset.priority || 'medium';
                
                // Remove existing priority classes
                card.classList.remove('priority-urgent', 'priority-high', 'priority-medium', 'priority-low');
                card.classList.remove('variant-urgent', 'variant-high', 'variant-medium', 'variant-low');
                
                // Add priority class
                card.classList.add(`priority-${priority}`);
                
                // Optional: add background variant
                if (priority === 'urgent' || priority === 'high') {
                    card.classList.add(`variant-${priority}`);
                }
            });
        }

        /**
         * Add age indicators to cards
         * @param {HTMLElement} column - Column element
         */
        addAgeIndicators(column) {
            const cards = column.querySelectorAll('.kanban-card');
            
            cards.forEach(card => {
                const createdAt = card.dataset.createdAt;
                if (!createdAt) return;

                const daysOld = this.getDaysOld(createdAt);
                
                let ageEl = card.querySelector('.kanban-card-age');
                
                if (!ageEl) {
                    ageEl = document.createElement('div');
                    ageEl.className = 'kanban-card-age';
                    card.appendChild(ageEl);
                }

                if (daysOld >= 14) {
                    ageEl.textContent = `${daysOld} дн.`;
                    ageEl.classList.add('very-old');
                } else if (daysOld >= 7) {
                    ageEl.textContent = `${daysOld} дн.`;
                    ageEl.classList.add('old');
                } else {
                    ageEl.textContent = '';
                    ageEl.classList.remove('old', 'very-old');
                }
            });
        }

        /**
         * Add blocker badges to cards
         * @param {HTMLElement} column - Column element
         */
        addBlockerBadges(column) {
            const cards = column.querySelectorAll('.kanban-card');
            
            cards.forEach(card => {
                const isBlocked = card.dataset.blocked === 'true';
                
                if (isBlocked && !card.querySelector('.kanban-card-badge.blocker')) {
                    const badges = card.querySelector('.kanban-card-badges');
                    if (badges) {
                        const blockerBadge = document.createElement('span');
                        blockerBadge.className = 'kanban-card-badge blocker';
                        blockerBadge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Блокировано';
                        badges.appendChild(blockerBadge);
                    }
                }
            });
        }

        /**
         * Add quick add button to column
         * @param {HTMLElement} column - Column element
         */
        addQuickAddButton(column) {
            let addBtn = column.querySelector('.kanban-add-card');
            
            if (!addBtn) {
                addBtn = document.createElement('div');
                addBtn.className = 'kanban-add-card';
                addBtn.innerHTML = '<i class="fas fa-plus"></i> Добавить задачу';
                addBtn.addEventListener('click', () => this.showQuickAddForm(column));
                column.appendChild(addBtn);
            }
        }

        /**
         * Show quick add form
         * @param {HTMLElement} column - Column element
         */
        showQuickAddForm(column) {
            const addBtn = column.querySelector('.kanban-add-card');
            const status = column.dataset.status;

            addBtn.innerHTML = `
                <div class="kanban-quick-add">
                    <textarea placeholder="Название задачи..." id="quick-add-text-${status}"></textarea>
                    <div class="kanban-quick-add-actions">
                        <button class="btn btn-sm btn-outline-secondary" onclick="KanbanBoard.cancelQuickAdd(this)">Отмена</button>
                        <button class="btn btn-sm btn-primary" onclick="KanbanBoard.submitQuickAdd(this, '${status}')">Создать</button>
                    </div>
                </div>
            `;

            const textarea = document.getElementById(`quick-add-text-${status}`);
            textarea.focus();
            
            // Submit on Ctrl+Enter
            textarea.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === 'Enter') {
                    this.submitQuickAdd(addBtn.querySelector('button.btn-primary'), status);
                }
            });
        }

        /**
         * Cancel quick add
         * @param {HTMLElement} btn - Cancel button
         */
        static cancelQuickAdd(btn) {
            const quickAdd = btn.closest('.kanban-quick-add');
            const addBtn = quickAdd.parentElement;
            addBtn.innerHTML = '<i class="fas fa-plus"></i> Добавить задачу';
        }

        /**
         * Submit quick add
         * @param {HTMLElement} btn - Submit button
         * @param {string} status - Column status
         */
        static async submitQuickAdd(btn, status) {
            const textarea = document.getElementById(`quick-add-text-${status}`);
            const title = textarea.value.trim();
            
            if (!title) {
                showToast('Введите название задачи', 'warning');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('/api/v1/tasks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        title: title,
                        status: status
                    })
                });

                if (response.ok) {
                    const task = await response.json();
                    window.location.reload();
                } else {
                    throw new Error('Failed to create task');
                }
            } catch (error) {
                console.error('Error creating task:', error);
                showToast('Ошибка при создании задачи', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Создать';
            }
        }

        /**
         * Initialize drag and drop
         */
        initDragAndDrop() {
            const cards = document.querySelectorAll('.kanban-card');
            
            cards.forEach(card => {
                card.setAttribute('draggable', 'true');
                
                card.addEventListener('dragstart', (e) => this.handleDragStart(e, card));
                card.addEventListener('dragend', (e) => this.handleDragEnd(e, card));
            });

            const columns = document.querySelectorAll('.kanban-column-body');
            
            columns.forEach(column => {
                column.addEventListener('dragover', (e) => this.handleDragOver(e, column));
                column.addEventListener('dragleave', (e) => this.handleDragLeave(e, column));
                column.addEventListener('drop', (e) => this.handleDrop(e, column));
            });
        }

        /**
         * Handle drag start
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} card - Card element
         */
        handleDragStart(e, card) {
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
            e.dataTransfer.effectAllowed = 'move';
        }

        /**
         * Handle drag end
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} card - Card element
         */
        handleDragEnd(e, card) {
            card.classList.remove('dragging');
        }

        /**
         * Handle drag over
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} column - Column element
         */
        handleDragOver(e, column) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            column.style.background = 'var(--bg-card, #fff)';
        }

        /**
         * Handle drag leave
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} column - Column element
         */
        handleDragLeave(e, column) {
            column.style.background = '';
        }

        /**
         * Handle drop
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} column - Column element
         */
        async handleDrop(e, column) {
            e.preventDefault();
            column.style.background = '';
            
            const taskId = e.dataTransfer.getData('text/plain');
            const newStatus = column.dataset.status;
            
            // Check WIP limit
            const cards = column.querySelectorAll('.kanban-card').length;
            const limit = this.wipLimits[newStatus];
            
            if (limit && cards >= limit) {
                showToast(`Превышен WIP лимит для колонки (${cards}/${limit})`, 'error');
                return;
            }

            try {
                const response = await fetch(`/api/tasks/${taskId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ status: newStatus })
                });

                if (response.ok) {
                    this.updateWipIndicators();
                    showToast('Статус обновлён', 'success');
                } else {
                    throw new Error('Failed to update status');
                }
            } catch (error) {
                console.error('Error updating task:', error);
                showToast('Ошибка при обновлении', 'error');
            }
        }

        /**
         * Get days old from date
         * @param {string} dateString - Date string
         * @returns {number} Days old
         */
        getDaysOld(dateString) {
            const created = new Date(dateString);
            const now = new Date();
            const diff = now - created;
            return Math.floor(diff / (1000 * 60 * 60 * 24));
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.KanbanBoard = new KanbanBoard();
            KanbanBoard.instance = window.KanbanBoard;
        });
    } else {
        window.KanbanBoard = new KanbanBoard();
        KanbanBoard.instance = window.KanbanBoard;
    }

    // Expose static methods to global scope
    window.KanbanBoard = window.KanbanBoard || {};
    window.KanbanBoard.editWipLimit = KanbanBoard.editWipLimit;
    window.KanbanBoard.cancelQuickAdd = KanbanBoard.cancelQuickAdd;
    window.KanbanBoard.submitQuickAdd = KanbanBoard.submitQuickAdd;

})();
