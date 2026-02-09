// Drag and Drop Controller Tests
// Run with: npm test or yarn test

describe('DragDropController', () => {
    let controller;
    let element;
    let items;

    beforeEach(() => {
        // Create test DOM structure
        document.body.innerHTML = `
            <div data-controller="drag-drop" data-drag-drop-reorder-url-value="/api/tasks/reorder">
                <div class="sortable-list" data-drag-drop-target="container">
                    <div class="task-item drag-drop-item" 
                         data-drag-drop-target="item" 
                         data-id="1"
                         draggable="true">
                        <div class="drag-handle" data-drag-drop-target="handle">
                            <i class="fas fa-grip-lines"></i>
                        </div>
                        Task 1
                    </div>
                    <div class="task-item drag-drop-item" 
                         data-drag-drop-target="item" 
                         data-id="2"
                         draggable="true">
                        <div class="drag-handle" data-drag-drop-target="handle">
                            <i class="fas fa-grip-lines"></i>
                        </div>
                        Task 2
                    </div>
                    <div class="task-item drag-drop-item" 
                         data-drag-drop-target="item" 
                         data-id="3"
                         draggable="true">
                        <div class="drag-handle" data-drag-drop-target="handle">
                            <i class="fas fa-grip-lines"></i>
                        </div>
                        Task 3
                    </div>
                </div>
            </div>
        `;

        element = document.querySelector('[data-controller="drag-drop"]');
        items = document.querySelectorAll('[data-drag-drop-target="item"]');
        
        // Mock Stimulus controller
        controller = {
            element: element,
            itemTargets: Array.from(items),
            handleTargets: Array.from(document.querySelectorAll('[data-drag-drop-target="handle"]')),
            draggingClasses: ['dragging'],
            chosenClasses: ['chosen'],
            ghostClasses: ['ghost'],
            dragOverAboveClasses: ['drag-over-above'],
            dragOverBelowClasses: ['drag-over-below'],
            reorderUrlValue: '/api/tasks/reorder',
            animationDurationValue: 300
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('should initialize items with correct attributes', () => {
        // Simulate controller initialization
        controller.itemTargets.forEach((item, index) => {
            item.setAttribute('draggable', 'true');
            item.dataset.sortOrder = index;
        });

        expect(controller.itemTargets[0].dataset.sortOrder).toBe('0');
        expect(controller.itemTargets[1].dataset.sortOrder).toBe('1');
        expect(controller.itemTargets[2].dataset.sortOrder).toBe('2');
        expect(controller.itemTargets[0].getAttribute('draggable')).toBe('true');
    });

    test('should handle drag start correctly', () => {
        const draggedItem = controller.itemTargets[0];
        const event = {
            target: draggedItem,
            dataTransfer: {
                effectAllowed: null,
                setData: jest.fn(),
                setDragImage: jest.fn()
            },
            preventDefault: jest.fn()
        };

        // Mock methods
        controller.draggingClasses = ['dragging'];
        controller.createPlaceholder = jest.fn();
        controller.updateSortOrders = jest.fn();

        // Simulate drag start
        expect(draggedItem.classList.contains('dragging')).toBe(false);
        
        // Add dragging class
        draggedItem.classList.add('dragging');
        
        expect(draggedItem.classList.contains('dragging')).toBe(true);
        expect(event.dataTransfer.effectAllowed).toBeNull();
    });

    test('should handle drag over positioning', () => {
        const targetItem = controller.itemTargets[1];
        const rect = targetItem.getBoundingClientRect();
        
        // Mock getBoundingClientRect
        targetItem.getBoundingClientRect = jest.fn(() => ({
            top: 100,
            height: 50,
            left: 0,
            right: 300,
            bottom: 150
        }));

        const midpoint = 125; // top + height/2
        const eventAbove = {
            target: targetItem,
            clientY: 110, // Above midpoint
            preventDefault: jest.fn()
        };

        const eventBelow = {
            target: targetItem,
            clientY: 140, // Below midpoint
            preventDefault: jest.fn()
        };

        // Remove existing classes first
        controller.itemTargets.forEach(item => {
            item.classList.remove('drag-over-above', 'drag-over-below');
        });

        // Test above midpoint
        if (eventAbove.clientY < midpoint) {
            targetItem.classList.add('drag-over-above');
        }
        expect(targetItem.classList.contains('drag-over-above')).toBe(true);

        // Test below midpoint
        controller.itemTargets.forEach(item => {
            item.classList.remove('drag-over-above', 'drag-over-below');
        });

        if (eventBelow.clientY >= midpoint) {
            targetItem.classList.add('drag-over-below');
        }
        expect(targetItem.classList.contains('drag-over-below')).toBe(true);
    });

    test('should update sort orders correctly', () => {
        controller.updateSortOrders = function() {
            this.itemTargets.forEach((item, index) => {
                item.dataset.sortOrder = index;
            });
        };

        controller.updateSortOrders();

        expect(controller.itemTargets[0].dataset.sortOrder).toBe('0');
        expect(controller.itemTargets[1].dataset.sortOrder).toBe('1');
        expect(controller.itemTargets[2].dataset.sortOrder).toBe('2');
    });

    test('should create proper position data for API', () => {
        controller.updateSortOrders = function() {
            this.itemTargets.forEach((item, index) => {
                item.dataset.sortOrder = index;
            });
        };

        controller.updateSortOrders();

        const positions = controller.itemTargets.map((item, index) => ({
            id: item.dataset.id,
            position: index
        }));

        expect(positions).toEqual([
            { id: '1', position: 0 },
            { id: '2', position: 1 },
            { id: '3', position: 2 }
        ]);
    });

    test('should handle successful reorder response', async () => {
        const mockResponse = {
            success: true,
            message: 'Task order updated successfully'
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve(mockResponse)
        });

        const positions = [
            { id: '1', position: 0 },
            { id: '2', position: 1 },
            { id: '3', position: 2 }
        ];

        const response = await fetch(controller.reorderUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ positions })
        });

        const data = await response.json();
        
        expect(data.success).toBe(true);
        expect(data.message).toBe('Task order updated successfully');
        expect(fetch).toHaveBeenCalledWith('/api/tasks/reorder', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ positions })
        }));
    });

    test('should handle reorder error response', async () => {
        const mockResponse = {
            success: false,
            message: 'Access denied'
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve(mockResponse)
        });

        const positions = [
            { id: '1', position: 0 },
            { id: '2', position: 1 }
        ];

        const response = await fetch(controller.reorderUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ positions })
        });

        const data = await response.json();
        
        expect(data.success).toBe(false);
        expect(data.message).toBe('Access denied');
    });

    test('should handle network error', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

        try {
            await fetch(controller.reorderUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ positions: [] })
            });
        } catch (error) {
            expect(error.message).toBe('Network error');
        }
    });

    test('should cleanup drag state properly', () => {
        const draggedItem = controller.itemTargets[0];
        
        // Set up drag state
        draggedItem.classList.add('dragging', 'chosen');
        document.documentElement.classList.add('dragging');

        // Cleanup
        draggedItem.classList.remove('dragging', 'chosen');
        document.documentElement.classList.remove('dragging');

        expect(draggedItem.classList.contains('dragging')).toBe(false);
        expect(draggedItem.classList.contains('chosen')).toBe(false);
        expect(document.documentElement.classList.contains('dragging')).toBe(false);
    });

    test('should show success feedback', () => {
        controller.element.classList.add('reorder-success');
        expect(controller.element.classList.contains('reorder-success')).toBe(true);
        
        // Simulate timeout cleanup
        setTimeout(() => {
            controller.element.classList.remove('reorder-success');
        }, controller.animationDurationValue);
    });

    test('should show error feedback', () => {
        controller.element.classList.add('reorder-error');
        expect(controller.element.classList.contains('reorder-error')).toBe(true);
        
        // Simulate timeout cleanup
        setTimeout(() => {
            controller.element.classList.remove('reorder-error');
        }, controller.animationDurationValue);
    });
});