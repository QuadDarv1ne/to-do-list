/**
 * Advanced Interactions
 * Drag & Drop, Context Menus, Keyboard Navigation
 */

class AdvancedInteractions {
    constructor() {
        this.draggedElement = null;
        this.contextMenu = null;
        this.init();
    }

    init() {
        this.initDragAndDrop();
        this.initContextMenu();
        this.initKeyboardNavigation();
        this.initInfiniteScroll();
        this.initLazyLoading();
        this.initSwipeGestures();
    }

    /**
     * Enhanced Drag & Drop
     */
    initDragAndDrop() {
        // Make elements draggable
        document.querySelectorAll('[data-draggable="true"]').forEach(element => {
            element.setAttribute('draggable', 'true');
            
            element.addEventListener('dragstart', (e) => {
                this.draggedElement = element;
                element.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', element.innerHTML);
                
                // Create ghost image
                const ghost = element.cloneNode(true);
                ghost.style.opacity = '0.5';
                document.body.appendChild(ghost);
                e.dataTransfer.setDragImage(ghost, 0, 0);
                setTimeout(() => ghost.remove(), 0);
            });

            element.addEventListener('dragend', () => {
                element.classList.remove('dragging');
                this.draggedElement = null;
            });
        });

        // Make drop zones
        document.querySelectorAll('[data-dropzone="true"]').forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                zone.classList.add('drag-over');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-over');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                
                if (this.draggedElement) {
                    zone.appendChild(this.draggedElement);
                    
                    // Trigger custom event
                    const event = new CustomEvent('itemdropped', {
                        detail: {
                            element: this.draggedElement,
                            zone: zone
                        }
                    });
                    document.dispatchEvent(event);
                    
                    // Show success feedback
                    UIEnhancements.showToast('Элемент перемещен', 'success', 2000);
                }
            });
        });
    }

    /**
     * Context Menu (Right Click)
     */
    initContextMenu() {
        document.addEventListener('contextmenu', (e) => {
            const target = e.target.closest('[data-context-menu]');
            if (!target) return;

            e.preventDefault();
            this.showContextMenu(e.pageX, e.pageY, target);
        });

        // Close on click outside
        document.addEventListener('click', () => {
            this.hideContextMenu();
        });
    }

    showContextMenu(x, y, target) {
        this.hideContextMenu();

        const menuItems = JSON.parse(target.dataset.contextMenu || '[]');
        if (menuItems.length === 0) return;

        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'context-menu animate-scale-in';
        this.contextMenu.style.cssText = `
            position: fixed;
            left: ${x}px;
            top: ${y}px;
            z-index: 9999;
        `;

        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.innerHTML = `
                <i class="fas ${item.icon}"></i>
                <span>${item.label}</span>
            `;
            
            menuItem.addEventListener('click', (e) => {
                e.stopPropagation();
                if (item.action) {
                    window[item.action](target);
                }
                this.hideContextMenu();
            });

            this.contextMenu.appendChild(menuItem);
        });

        document.body.appendChild(this.contextMenu);

        // Adjust position if menu goes off screen
        const rect = this.contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            this.contextMenu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            this.contextMenu.style.top = (y - rect.height) + 'px';
        }
    }

    hideContextMenu() {
        if (this.contextMenu) {
            this.contextMenu.remove();
            this.contextMenu = null;
        }
    }

    /**
     * Advanced Keyboard Navigation
     */
    initKeyboardNavigation() {
        let focusableElements = [];
        let currentIndex = -1;

        // Update focusable elements
        const updateFocusable = () => {
            focusableElements = Array.from(document.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), ' +
                'select:not([disabled]), textarea:not([disabled]), ' +
                '[tabindex]:not([tabindex="-1"])'
            ));
        };

        updateFocusable();

        // Arrow key navigation for lists
        document.addEventListener('keydown', (e) => {
            const activeElement = document.activeElement;
            const list = activeElement.closest('[data-keyboard-nav="true"]');
            
            if (!list) return;

            const items = Array.from(list.querySelectorAll('[data-nav-item]'));
            const currentIndex = items.indexOf(activeElement);

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (currentIndex < items.length - 1) {
                        items[currentIndex + 1].focus();
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (currentIndex > 0) {
                        items[currentIndex - 1].focus();
                    }
                    break;
                case 'Home':
                    e.preventDefault();
                    items[0]?.focus();
                    break;
                case 'End':
                    e.preventDefault();
                    items[items.length - 1]?.focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    activeElement.click();
                    break;
            }
        });

        // Escape key to close modals/dropdowns
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Close modals
                document.querySelectorAll('.modal.show').forEach(modal => {
                    const closeBtn = modal.querySelector('[data-bs-dismiss="modal"]');
                    closeBtn?.click();
                });

                // Close dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });

                // Hide context menu
                this.hideContextMenu();
            }
        });
    }

    /**
     * Infinite Scroll
     */
    initInfiniteScroll() {
        const containers = document.querySelectorAll('[data-infinite-scroll="true"]');
        
        containers.forEach(container => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMoreContent(container);
                    }
                });
            }, {
                rootMargin: '100px'
            });

            // Observe sentinel element
            const sentinel = container.querySelector('[data-scroll-sentinel]');
            if (sentinel) {
                observer.observe(sentinel);
            }
        });
    }

    async loadMoreContent(container) {
        const url = container.dataset.loadUrl;
        const page = parseInt(container.dataset.currentPage || '1') + 1;
        
        if (!url || container.dataset.loading === 'true') return;

        container.dataset.loading = 'true';
        
        try {
            const response = await fetch(`${url}?page=${page}`);
            const html = await response.text();
            
            const sentinel = container.querySelector('[data-scroll-sentinel]');
            if (sentinel) {
                sentinel.insertAdjacentHTML('beforebegin', html);
            }
            
            container.dataset.currentPage = page;
        } catch (error) {
            console.error('Failed to load more content:', error);
        } finally {
            container.dataset.loading = 'false';
        }
    }

    /**
     * Lazy Loading Images
     */
    initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    /**
     * Swipe Gestures for Mobile
     */
    initSwipeGestures() {
        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartY = 0;
        let touchEndY = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            this.handleSwipe(e.target, touchStartX, touchEndX, touchStartY, touchEndY);
        }, { passive: true });
    }

    handleSwipe(target, startX, endX, startY, endY) {
        const swipeElement = target.closest('[data-swipeable="true"]');
        if (!swipeElement) return;

        const diffX = endX - startX;
        const diffY = endY - startY;
        const threshold = 50;

        // Horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > threshold) {
            if (diffX > 0) {
                // Swipe right
                this.triggerSwipeEvent(swipeElement, 'swiperight');
            } else {
                // Swipe left
                this.triggerSwipeEvent(swipeElement, 'swipeleft');
            }
        }
        // Vertical swipe
        else if (Math.abs(diffY) > threshold) {
            if (diffY > 0) {
                // Swipe down
                this.triggerSwipeEvent(swipeElement, 'swipedown');
            } else {
                // Swipe up
                this.triggerSwipeEvent(swipeElement, 'swipeup');
            }
        }
    }

    triggerSwipeEvent(element, direction) {
        const event = new CustomEvent('swipe', {
            detail: { direction }
        });
        element.dispatchEvent(event);

        // Visual feedback
        element.style.transform = direction.includes('left') ? 'translateX(-10px)' : 
                                  direction.includes('right') ? 'translateX(10px)' : 
                                  direction.includes('up') ? 'translateY(-10px)' : 
                                  'translateY(10px)';
        
        setTimeout(() => {
            element.style.transform = '';
        }, 200);
    }

    /**
     * Copy to Clipboard with Feedback
     */
    static copyToClipboard(text, successMessage = 'Скопировано!') {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                UIEnhancements.showToast(successMessage, 'success', 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                UIEnhancements.showToast('Ошибка копирования', 'danger', 2000);
            });
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                UIEnhancements.showToast(successMessage, 'success', 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
                UIEnhancements.showToast('Ошибка копирования', 'danger', 2000);
            }
            
            document.body.removeChild(textarea);
        }
    }

    /**
     * Share API Integration
     */
    static async share(data) {
        if (navigator.share) {
            try {
                await navigator.share(data);
                UIEnhancements.showToast('Успешно поделились', 'success', 2000);
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Share failed:', err);
                }
            }
        } else {
            // Fallback - copy link
            if (data.url) {
                AdvancedInteractions.copyToClipboard(data.url, 'Ссылка скопирована');
            }
        }
    }
}

// Add context menu styles
if (!document.getElementById('contextMenuStyles')) {
    const style = document.createElement('style');
    style.id = 'contextMenuStyles';
    style.textContent = `
    .context-menu {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 0.25rem;
        min-width: 180px;
    }

    .context-menu-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        border-radius: var(--radius);
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .context-menu-item:hover {
        background: var(--primary);
        color: white;
    }

    .context-menu-item i {
        width: 16px;
        text-align: center;
        font-size: 0.875rem;
    }

    .dragging {
        opacity: 0.5;
        cursor: grabbing;
    }

    .drag-over {
        background: rgba(102, 126, 234, 0.1);
        border-color: var(--primary);
    }

    [data-draggable="true"] {
        cursor: grab;
    }

    [data-draggable="true"]:active {
        cursor: grabbing;
    }

    img[data-src] {
        background: linear-gradient(
            90deg,
            #f0f0f0 25%,
            #e0e0e0 50%,
            #f0f0f0 75%
        );
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
    }

    img.loaded {
        animation: fadeIn 0.3s ease-out;
    }
`;
    document.head.appendChild(style);
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.advancedInteractions = new AdvancedInteractions();
    });
} else {
    window.advancedInteractions = new AdvancedInteractions();
}

// Export for use in other scripts
window.AdvancedInteractions = AdvancedInteractions;
