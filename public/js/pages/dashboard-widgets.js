/**
 * Dashboard Widgets - Drag & Drop
 * Перетаскивание и управление виджетами дашборда
 */

(function() {
    'use strict';

    class DashboardWidgets {
        constructor(options = {}) {
            this.options = {
                storageKey: 'dashboard_widgets_layout',
                animationDuration: 300,
                ...options
            };

            this.grid = null;
            this.widgets = [];
            this.draggedWidget = null;
            this.placeholder = null;
            this.isEditMode = false;

            this.init();
        }

        /**
         * Initialize dashboard widgets
         */
        init() {
            this.grid = document.querySelector('.dashboard-grid');
            if (!this.grid) return;

            // Load saved layout
            this.loadLayout();

            // Initialize widgets
            this.widgets = Array.from(this.grid.querySelectorAll('.dashboard-widget'));
            this.widgets.forEach(widget => this.initWidget(widget));

            // Add "Add Widget" button handler
            const addBtn = document.querySelector('.add-widget-btn');
            if (addBtn) {
                addBtn.addEventListener('click', () => this.showWidgetPicker());
            }

            // Toggle edit mode with keyboard shortcut
            document.addEventListener('keydown', (e) => {
                if (e.key === 'e' && e.altKey) {
                    e.preventDefault();
                    this.toggleEditMode();
                }
            });
        }

        /**
         * Initialize single widget
         * @param {HTMLElement} widget - Widget element
         */
        initWidget(widget) {
            // Make draggable
            if (widget.dataset.draggable !== 'false') {
                widget.classList.add('draggable');
                widget.setAttribute('draggable', 'true');
            }

            // Add drag handle
            const header = widget.querySelector('.dashboard-widget-header');
            if (header && !header.querySelector('.dashboard-widget-drag-handle')) {
                const handle = document.createElement('span');
                handle.className = 'dashboard-widget-drag-handle me-2';
                handle.innerHTML = '<i class="fas fa-grip-vertical"></i>';
                header.insertBefore(handle, header.firstChild);
            }

            // Add action buttons
            this.addWidgetActions(widget);

            // Add resize handle
            this.addResizeHandle(widget);

            // Bind drag events
            this.bindDragEvents(widget);

            // Bind action events
            this.bindActionEvents(widget);

            // Load widget content
            this.loadWidgetContent(widget);
        }

        /**
         * Add action buttons to widget
         * @param {HTMLElement} widget - Widget element
         */
        addWidgetActions(widget) {
            const header = widget.querySelector('.dashboard-widget-header');
            if (!header) return;

            let actions = header.querySelector('.dashboard-widget-actions');
            if (!actions) {
                actions = document.createElement('div');
                actions.className = 'dashboard-widget-actions';
                header.appendChild(actions);
            }

            // Minimize/Maximize button
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'dashboard-widget-action-btn toggle';
            toggleBtn.innerHTML = '<i class="fas fa-minus toggle-icon"></i>';
            toggleBtn.title = 'Свернуть';
            actions.appendChild(toggleBtn);

            // Settings button
            const settingsBtn = document.createElement('button');
            settingsBtn.className = 'dashboard-widget-action-btn settings';
            settingsBtn.innerHTML = '<i class="fas fa-cog"></i>';
            settingsBtn.title = 'Настройки';
            actions.appendChild(settingsBtn);

            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.className = 'dashboard-widget-action-btn remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.title = 'Удалить';
            actions.appendChild(removeBtn);
        }

        /**
         * Add resize handle to widget
         * @param {HTMLElement} widget - Widget element
         */
        addResizeHandle(widget) {
            const handle = document.createElement('div');
            handle.className = 'dashboard-widget-resize-handle';
            widget.appendChild(handle);

            // Bind resize events
            this.bindResizeEvents(widget, handle);
        }

        /**
         * Bind drag events to widget
         * @param {HTMLElement} widget - Widget element
         */
        bindDragEvents(widget) {
            widget.addEventListener('dragstart', (e) => this.handleDragStart(e, widget));
            widget.addEventListener('dragend', (e) => this.handleDragEnd(e, widget));
            widget.addEventListener('dragover', (e) => this.handleDragOver(e, widget));
            widget.addEventListener('dragleave', (e) => this.handleDragLeave(e, widget));
            widget.addEventListener('drop', (e) => this.handleDrop(e, widget));
        }

        /**
         * Bind action events to widget
         * @param {HTMLElement} widget - Widget element
         */
        bindActionEvents(widget) {
            const toggleBtn = widget.querySelector('.dashboard-widget-action-btn.toggle');
            const settingsBtn = widget.querySelector('.dashboard-widget-action-btn.settings');
            const removeBtn = widget.querySelector('.dashboard-widget-action-btn.remove');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => this.toggleWidget(widget));
            }

            if (settingsBtn) {
                settingsBtn.addEventListener('click', () => this.showWidgetSettings(widget));
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', () => this.removeWidget(widget));
            }
        }

        /**
         * Bind resize events
         * @param {HTMLElement} widget - Widget element
         * @param {HTMLElement} handle - Resize handle
         */
        bindResizeEvents(widget, handle) {
            let isResizing = false;
            let startX, startWidth;

            handle.addEventListener('mousedown', (e) => {
                if (!this.isEditMode) return;
                
                isResizing = true;
                startX = e.clientX;
                startWidth = widget.offsetWidth;
                widget.style.transition = 'none';
                
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });

            const onMouseMove = (e) => {
                if (!isResizing) return;
                
                const diff = e.clientX - startX;
                const newWidth = startWidth + diff;
                
                if (newWidth > 300) {
                    widget.style.width = newWidth + 'px';
                }
            };

            const onMouseUp = () => {
                isResizing = false;
                widget.style.transition = '';
                widget.style.width = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                this.saveLayout();
            };
        }

        /**
         * Handle drag start
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} widget - Widget element
         */
        handleDragStart(e, widget) {
            if (!this.isEditMode) {
                e.preventDefault();
                return;
            }

            this.draggedWidget = widget;
            widget.classList.add('dragging');
            
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', widget.dataset.widgetId);

            // Create placeholder
            this.placeholder = document.createElement('div');
            this.placeholder.className = 'dashboard-widget-placeholder';
            this.placeholder.style.height = widget.offsetHeight + 'px';
        }

        /**
         * Handle drag end
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} widget - Widget element
         */
        handleDragEnd(e, widget) {
            widget.classList.remove('dragging');
            this.draggedWidget = null;
            
            if (this.placeholder) {
                this.placeholder.remove();
                this.placeholder = null;
            }

            this.saveLayout();
        }

        /**
         * Handle drag over
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} widget - Widget element
         */
        handleDragOver(e, widget) {
            if (!this.isEditMode || !this.draggedWidget) return;
            
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const rect = widget.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            if (e.clientY < midpoint) {
                this.grid.insertBefore(this.placeholder, widget);
            } else {
                this.grid.insertBefore(this.placeholder, widget.nextSibling);
            }
        }

        /**
         * Handle drag leave
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} widget - Widget element
         */
        handleDragLeave(e, widget) {
            // Placeholder is managed in dragOver
        }

        /**
         * Handle drop
         * @param {DragEvent} e - Drag event
         * @param {HTMLElement} widget - Widget element
         */
        handleDrop(e, widget) {
            if (!this.isEditMode || !this.draggedWidget) return;
            
            e.preventDefault();
            
            const rect = widget.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            if (e.clientY < midpoint) {
                this.grid.insertBefore(this.draggedWidget, widget);
            } else {
                this.grid.insertBefore(this.draggedWidget, widget.nextSibling);
            }

            this.saveLayout();
        }

        /**
         * Toggle widget minimize/expand
         * @param {HTMLElement} widget - Widget element
         */
        toggleWidget(widget) {
            const isCollapsed = widget.classList.contains('collapsed');
            const icon = widget.querySelector('.toggle-icon');
            
            if (isCollapsed) {
                widget.classList.remove('collapsed');
                widget.classList.add('maximizing');
                icon.style.transform = '';
                
                setTimeout(() => {
                    widget.classList.remove('maximizing');
                }, this.options.animationDuration);
            } else {
                widget.classList.add('collapsed');
                widget.classList.add('minimizing');
                icon.style.transform = 'rotate(-90deg)';
                
                setTimeout(() => {
                    widget.classList.remove('minimizing');
                }, this.options.animationDuration);
            }

            // Save state
            this.saveWidgetState(widget.dataset.widgetId, { collapsed: !isCollapsed });
        }

        /**
         * Remove widget
         * @param {HTMLElement} widget - Widget element
         */
        removeWidget(widget) {
            if (!confirm('Удалить этот виджет с дашборда?')) return;

            widget.classList.add('minimizing');
            
            setTimeout(() => {
                widget.remove();
                this.saveLayout();
            }, this.options.animationDuration);
        }

        /**
         * Show widget settings modal
         * @param {HTMLElement} widget - Widget element
         */
        showWidgetSettings(widget) {
            const widgetId = widget.dataset.widgetId;
            const widgetType = widget.dataset.widgetType;
            
            // Could open a modal with settings here
            if (window.logger) window.logger.log('Settings for widget:', widgetId, widgetType);
        }

        /**
         * Show widget picker modal
         */
        showWidgetPicker() {
            // Could open a modal with available widgets here
            const availableWidgets = [
                { id: 'tasks-summary', name: 'Сводка задач', icon: 'fa-tasks', type: 'stats' },
                { id: 'activity-chart', name: 'График активности', icon: 'fa-chart-line', type: 'chart' },
                { id: 'recent-tasks', name: 'Последние задачи', icon: 'fa-list', type: 'list' },
                { id: 'calendar', name: 'Календарь', icon: 'fa-calendar', type: 'calendar' },
                { id: 'goals', name: 'Цели', icon: 'fa-bullseye', type: 'stats' },
                { id: 'habits', name: 'Привычки', icon: 'fa-fire', type: 'stats' }
            ];

            if (window.logger) window.logger.log('Available widgets:', availableWidgets);
        }

        /**
         * Toggle edit mode
         */
        toggleEditMode() {
            this.isEditMode = !this.isEditMode;
            
            this.widgets.forEach(widget => {
                if (this.isEditMode) {
                    widget.classList.add('draggable');
                    widget.setAttribute('draggable', 'true');
                } else {
                    widget.classList.remove('draggable');
                    widget.removeAttribute('draggable');
                }
            });

            showToast(
                this.isEditMode ? 'Режим редактирования включён' : 'Режим редактирования выключен',
                'info'
            );
        }

        /**
         * Save layout to localStorage
         */
        saveLayout() {
            const layout = {
                widgets: this.widgets.map((widget, index) => ({
                    id: widget.dataset.widgetId,
                    position: index,
                    size: widget.dataset.size || 'medium',
                    collapsed: widget.classList.contains('collapsed')
                }))
            };

            localStorage.setItem(this.options.storageKey, JSON.stringify(layout));
        }

        /**
         * Load layout from localStorage
         */
        loadLayout() {
            const saved = localStorage.getItem(this.options.storageKey);
            if (!saved) return;

            try {
                const layout = JSON.parse(saved);
                
                // Reorder widgets based on saved layout
                layout.widgets.forEach(({ id, position }) => {
                    const widget = this.grid.querySelector(`[data-widget-id="${id}"]`);
                    if (widget) {
                        // Move widget to correct position
                        const children = Array.from(this.grid.children);
                        const targetIndex = position;
                        
                        if (children[targetIndex]) {
                            this.grid.insertBefore(widget, children[targetIndex]);
                        } else {
                            this.grid.appendChild(widget);
                        }
                    }
                });
            } catch (e) {
                console.error('Failed to load layout:', e);
            }
        }

        /**
         * Save widget state
         * @param {string} widgetId - Widget ID
         * @param {Object} state - Widget state
         */
        saveWidgetState(widgetId, state) {
            const saved = localStorage.getItem(this.options.storageKey);
            let layout = saved ? JSON.parse(saved) : { widgets: [] };
            
            const widgetIndex = layout.widgets.findIndex(w => w.id === widgetId);
            
            if (widgetIndex !== -1) {
                layout.widgets[widgetIndex] = { ...layout.widgets[widgetIndex], ...state };
            } else {
                layout.widgets.push({ id: widgetId, ...state });
            }
            
            localStorage.setItem(this.options.storageKey, JSON.stringify(layout));
        }

        /**
         * Load widget content via AJAX
         * @param {HTMLElement} widget - Widget element
         */
        async loadWidgetContent(widget) {
            const url = widget.dataset.contentUrl;
            if (!url) return;

            const body = widget.querySelector('.dashboard-widget-body');
            if (!body) return;

            try {
                const response = await fetch(url);
                if (response.ok) {
                    body.innerHTML = await response.text();
                }
            } catch (e) {
                console.error('Failed to load widget content:', e);
                body.innerHTML = '<div class="text-muted">Не удалось загрузить данные</div>';
            }
        }

        /**
         * Add new widget to dashboard
         * @param {Object} widgetConfig - Widget configuration
         */
        addWidget(widgetConfig) {
            const widget = document.createElement('div');
            widget.className = 'dashboard-widget';
            widget.dataset.widgetId = widgetConfig.id;
            widget.dataset.widgetType = widgetConfig.type;
            widget.dataset.size = widgetConfig.size || 'medium';
            
            widget.innerHTML = `
                <div class="dashboard-widget-header">
                    <h5 class="dashboard-widget-title">
                        <i class="fas ${widgetConfig.icon || 'fa-box'}"></i>
                        ${widgetConfig.name}
                    </h5>
                </div>
                <div class="dashboard-widget-body">
                    <div class="dashboard-widget-loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                </div>
            `;

            this.grid.appendChild(widget);
            this.initWidget(widget);
            this.saveLayout();
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.DashboardWidgets = new DashboardWidgets();
        });
    } else {
        window.DashboardWidgets = new DashboardWidgets();
    }

    // Export for manual usage
    window.DashboardWidgetsClass = DashboardWidgets;

})();
