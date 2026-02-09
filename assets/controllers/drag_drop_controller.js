import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'handle'];
    static classes = ['dragging', 'chosen', 'ghost', 'dragOverAbove', 'dragOverBelow'];
    static values = {
        url: String,
        reorderUrl: String,
        animationDuration: { type: Number, default: 300 }
    };

    connect() {
        this.draggedItem = null;
        this.placeholder = null;
        this.currentPosition = null;
        
        this.setupEventListeners();
        this.initializeItems();
    }

    disconnect() {
        this.teardownEventListeners();
    }

    setupEventListeners() {
        // Global events for better UX
        document.addEventListener('dragover', this.handleGlobalDragOver.bind(this));
        document.addEventListener('drop', this.handleGlobalDrop.bind(this));
        document.addEventListener('dragend', this.handleGlobalDragEnd.bind(this));
    }

    teardownEventListeners() {
        document.removeEventListener('dragover', this.handleGlobalDragOver.bind(this));
        document.removeEventListener('drop', this.handleGlobalDrop.bind(this));
        document.removeEventListener('dragend', this.handleGlobalDragEnd.bind(this));
    }

    initializeItems() {
        this.itemTargets.forEach((item, index) => {
            item.setAttribute('draggable', 'true');
            item.dataset.sortOrder = index;
            
            // Add drag handle if not present
            if (!item.querySelector('.drag-handle') && this.hasHandleTarget) {
                const handle = document.createElement('div');
                handle.className = 'drag-handle';
                handle.innerHTML = '<i class="fas fa-grip-lines"></i>';
                item.insertBefore(handle, item.firstChild);
            }
        });
    }

    handleDragStart(event) {
        this.draggedItem = event.target.closest('[data-drag-drop-target="item"]');
        if (!this.draggedItem) return;

        // Add visual feedback
        this.draggedItem.classList.add(...this.draggingClasses);
        document.documentElement.classList.add('dragging');

        // Set drag data
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.draggedItem.dataset.id);

        // Create placeholder
        this.createPlaceholder();

        // Store original position
        this.currentPosition = parseInt(this.draggedItem.dataset.sortOrder);

        // Add ghost image
        const rect = this.draggedItem.getBoundingClientRect();
        const ghost = this.draggedItem.cloneNode(true);
        ghost.style.width = rect.width + 'px';
        ghost.style.height = rect.height + 'px';
        ghost.style.position = 'fixed';
        ghost.style.top = '-1000px';
        ghost.classList.add(...this.ghostClasses);
        
        document.body.appendChild(ghost);
        event.dataTransfer.setDragImage(ghost, 0, 0);
        
        // Remove ghost after a frame
        setTimeout(() => {
            if (ghost.parentNode) {
                ghost.parentNode.removeChild(ghost);
            }
        }, 0);
    }

    handleDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        const targetItem = event.target.closest('[data-drag-drop-target="item"]');
        if (!targetItem || targetItem === this.draggedItem) return;

        // Calculate position relative to target
        const rect = targetItem.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;
        
        // Remove previous drag-over classes
        this.itemTargets.forEach(item => {
            item.classList.remove(...this.dragOverAboveClasses, ...this.dragOverBelowClasses);
        });

        // Add appropriate drag-over class
        if (event.clientY < midpoint) {
            targetItem.classList.add(...this.dragOverAboveClasses);
        } else {
            targetItem.classList.add(...this.dragOverBelowClasses);
        }
    }

    handleDragLeave(event) {
        const targetItem = event.target.closest('[data-drag-drop-target="item"]');
        if (targetItem) {
            targetItem.classList.remove(...this.dragOverAboveClasses, ...this.dragOverBelowClasses);
        }
    }

    handleDrop(event) {
        event.preventDefault();
        
        const targetItem = event.target.closest('[data-drag-drop-target="item"]');
        if (!targetItem || !this.draggedItem) return;

        // Remove drag-over classes
        this.itemTargets.forEach(item => {
            item.classList.remove(...this.dragOverAboveClasses, ...this.dragOverBelowClasses);
        });

        // Calculate drop position
        const rect = targetItem.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;
        const insertBefore = event.clientY < midpoint;

        // Perform the swap/move
        this.moveItem(this.draggedItem, targetItem, insertBefore);

        // Save new positions
        this.saveNewPositions();

        // Cleanup
        this.cleanupDrag();
    }

    handleDragEnd(event) {
        this.cleanupDrag();
    }

    // Global event handlers
    handleGlobalDragOver(event) {
        if (this.draggedItem) {
            event.preventDefault();
        }
    }

    handleGlobalDrop(event) {
        if (this.draggedItem) {
            event.preventDefault();
        }
    }

    handleGlobalDragEnd(event) {
        if (this.draggedItem) {
            this.cleanupDrag();
        }
    }

    createPlaceholder() {
        this.placeholder = document.createElement('div');
        this.placeholder.className = 'sortable-placeholder';
        this.placeholder.style.height = this.draggedItem.offsetHeight + 'px';
        this.placeholder.style.margin = window.getComputedStyle(this.draggedItem).margin;
    }

    moveItem(draggedItem, targetItem, insertBefore) {
        const parent = draggedItem.parentNode;
        
        if (insertBefore) {
            parent.insertBefore(draggedItem, targetItem);
        } else {
            parent.insertBefore(draggedItem, targetItem.nextSibling);
        }

        // Update sort orders
        this.updateSortOrders();
    }

    updateSortOrders() {
        this.itemTargets.forEach((item, index) => {
            item.dataset.sortOrder = index;
        });
    }

    saveNewPositions() {
        const positions = this.itemTargets.map((item, index) => ({
            id: item.dataset.id,
            position: index
        }));

        // Send to server
        if (this.reorderUrlValue) {
            fetch(this.reorderUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ positions })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccessFeedback();
                } else {
                    this.showErrorFeedback(data.message || 'Failed to save order');
                    this.revertPositions();
                }
            })
            .catch(error => {
                console.error('Error saving positions:', error);
                this.showErrorFeedback('Network error occurred');
                this.revertPositions();
            });
        }
    }

    revertPositions() {
        // Re-sort items based on original positions
        const sortedItems = [...this.itemTargets].sort((a, b) => 
            parseInt(a.dataset.originalOrder || a.dataset.sortOrder) - 
            parseInt(b.dataset.originalOrder || b.dataset.sortOrder)
        );

        const parent = this.element;
        sortedItems.forEach(item => {
            parent.appendChild(item);
        });

        this.updateSortOrders();
    }

    cleanupDrag() {
        if (this.draggedItem) {
            this.draggedItem.classList.remove(...this.draggingClasses, ...this.chosenClasses);
            document.documentElement.classList.remove('dragging');
            this.draggedItem = null;
        }

        if (this.placeholder && this.placeholder.parentNode) {
            this.placeholder.parentNode.removeChild(this.placeholder);
            this.placeholder = null;
        }

        this.currentPosition = null;
    }

    showSuccessFeedback() {
        // Show visual feedback
        this.element.classList.add('reorder-success');
        setTimeout(() => {
            this.element.classList.remove('reorder-success');
        }, this.animationDurationValue);
    }

    showErrorFeedback(message) {
        // Show error feedback
        console.error('Drag and drop error:', message);
        this.element.classList.add('reorder-error');
        setTimeout(() => {
            this.element.classList.remove('reorder-error');
        }, this.animationDurationValue);
    }

    // Public methods for external control
    enable() {
        this.itemTargets.forEach(item => {
            item.setAttribute('draggable', 'true');
        });
        this.element.classList.remove('drag-disabled');
    }

    disable() {
        this.itemTargets.forEach(item => {
            item.setAttribute('draggable', 'false');
        });
        this.element.classList.add('drag-disabled');
    }

    refresh() {
        this.initializeItems();
    }
}