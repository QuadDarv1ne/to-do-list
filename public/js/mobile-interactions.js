/**
 * Mobile Interactions v3.0
 * Touch gestures, swipe actions, and mobile optimizations
 */

(function() {
    'use strict';

    /**
     * Touch Gesture Handler
     */
    class TouchGestures {
        constructor(element, options = {}) {
            this.element = element;
            this.options = {
                onSwipeLeft: null,
                onSwipeRight: null,
                onSwipeUp: null,
                onSwipeDown: null,
                onLongPress: null,
                onDoubleTap: null,
                onPinch: null,
                threshold: 50,
                longPressDuration: 500
            };
            this.options = { ...this.options, ...options };

            this.touchStartX = 0;
            this.touchStartY = 0;
            this.touchEndX = 0;
            this.touchEndY = 0;
            this.longPressTimer = null;
            this.lastTapTime = 0;

            this.init();
        }

        init() {
            this.element.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
            this.element.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: true });
            this.element.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });
        }

        handleTouchStart(e) {
            this.touchStartX = e.changedTouches[0].screenX;
            this.touchStartY = e.changedTouches[0].screenY;

            // Long press timer
            if (this.options.onLongPress) {
                this.longPressTimer = setTimeout(() => {
                    this.options.onLongPress(e);
                    this.longPressTimer = null;
                }, this.options.longPressDuration);
            }
        }

        handleTouchMove(e) {
            // Cancel long press if finger moved
            if (this.longPressTimer) {
                clearTimeout(this.longPressTimer);
                this.longPressTimer = null;
            }
        }

        handleTouchEnd(e) {
            // Cancel long press
            if (this.longPressTimer) {
                clearTimeout(this.longPressTimer);
                this.longPressTimer = null;
            }

            this.touchEndX = e.changedTouches[0].screenX;
            this.touchEndY = e.changedTouches[0].screenY;

            this.detectGestures(e);
        }

        detectGestures(e) {
            const diffX = this.touchEndX - this.touchStartX;
            const diffY = this.touchEndY - this.touchStartY;
            const absDiffX = Math.abs(diffX);
            const absDiffY = Math.abs(diffY);

            // Double tap
            const currentTime = new Date().getTime();
            if (currentTime - this.lastTapTime < 300 && this.options.onDoubleTap) {
                this.options.onDoubleTap(e);
            }
            this.lastTapTime = currentTime;

            // Swipe detection
            if (absDiffX > this.threshold || absDiffY > this.threshold) {
                if (absDiffX > absDiffY) {
                    // Horizontal swipe
                    if (diffX > 0 && this.options.onSwipeRight) {
                        this.options.onSwipeRight(e);
                    } else if (diffX < 0 && this.options.onSwipeLeft) {
                        this.options.onSwipeLeft(e);
                    }
                } else {
                    // Vertical swipe
                    if (diffY > 0 && this.options.onSwipeDown) {
                        this.options.onSwipeDown(e);
                    } else if (diffY < 0 && this.options.onSwipeUp) {
                        this.options.onSwipeUp(e);
                    }
                }
            }
        }
    }

    /**
     * Swipe Action Handler
     */
    class SwipeActions {
        constructor(selector = '.swipe-action') {
            this.elements = document.querySelectorAll(selector);
            this.currentSwipe = null;
            this.init();
        }

        init() {
            this.elements.forEach(element => {
                const content = element.querySelector('.swipe-action-content');
                if (!content) return;

                const gestures = new TouchGestures(element, {
                    onSwipeLeft: () => this.revealButtons(element),
                    onSwipeRight: () => this.hideButtons(element),
                    threshold: 30
                });

                // Close on outside click
                document.addEventListener('click', (e) => {
                    if (!element.contains(e.target)) {
                        this.hideButtons(element);
                    }
                });
            });
        }

        revealButtons(element) {
            const content = element.querySelector('.swipe-action-content');
            const buttons = element.querySelector('.swipe-action-buttons');

            if (buttons) {
                const btnWidth = buttons.offsetWidth;
                content.style.transform = `translateX(-${Math.min(btnWidth, 100)}px)`;
                element.classList.add('revealing');
                this.currentSwipe = element;
            }
        }

        hideButtons(element) {
            const content = element.querySelector('.swipe-action-content');
            if (content) {
                content.style.transform = 'translateX(0)';
                element.classList.remove('revealing');
            }
        }

        toggle(element) {
            if (element.classList.contains('revealing')) {
                this.hideButtons(element);
            } else {
                this.revealButtons(element);
            }
        }
    }

    /**
     * Pull to Refresh
     */
    class PullToRefresh {
        constructor(options = {}) {
            this.options = {
                onRefresh: null,
                threshold: 100,
                container: null
            };
            this.options = { ...this.options, ...options };

            this.startY = 0;
            this.currentY = 0;
            this.isRefreshing = false;
            this.ptrBox = null;

            this.init();
        }

        init() {
            const container = this.options.container || document.body;
            
            container.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
            container.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
            container.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });
        }

        handleTouchStart(e) {
            if (window.scrollY === 0) {
                this.startY = e.changedTouches[0].screenY;
            }
        }

        handleTouchMove(e) {
            if (window.scrollY === 0 && this.startY > 0) {
                this.currentY = e.changedTouches[0].screenY;
                const diff = this.currentY - this.startY;

                if (diff > 0 && diff < this.options.threshold) {
                    e.preventDefault();
                    this.updateIndicator(diff);
                }
            }
        }

        handleTouchEnd(e) {
            if (this.currentY - this.startY > this.options.threshold && !this.isRefreshing) {
                this.triggerRefresh();
            }
            this.resetIndicator();
            this.startY = 0;
            this.currentY = 0;
        }

        updateIndicator(progress) {
            let ptrBox = document.querySelector('.ptr--box');
            if (!ptrBox) {
                ptrBox = document.createElement('div');
                ptrBox.className = 'ptr--box';
                ptrBox.innerHTML = '<div class="ptr--arrow"><i class="fas fa-arrow-down"></i></div>';
                document.body.prepend(ptrBox);
            }

            const rotation = (progress / this.options.threshold) * 180;
            const arrow = ptrBox.querySelector('.ptr--arrow');
            if (arrow) {
                arrow.style.transform = `rotate(${rotation}deg)`;
            }
        }

        resetIndicator() {
            const ptrBox = document.querySelector('.ptr--box');
            if (ptrBox) {
                ptrBox.remove();
            }
        }

        async triggerRefresh() {
            if (this.isRefreshing || !this.options.onRefresh) return;

            this.isRefreshing = true;
            const ptrBox = document.querySelector('.ptr--box');
            if (ptrBox) {
                ptrBox.classList.add('refreshing');
            }

            try {
                await this.options.onRefresh();
            } finally {
                this.isRefreshing = false;
                if (ptrBox) {
                    ptrBox.classList.remove('refreshing');
                }
                this.resetIndicator();
            }
        }
    }

    /**
     * Bottom Sheet / Popover
     */
    class BottomSheet {
        constructor() {
            this.currentSheet = null;
        }

        show(content, options = {}) {
            const {
                title = '',
                showClose = true,
                onClose = null
            } = options;

            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'popover-mobile-backdrop';
            backdrop.addEventListener('click', () => this.close());

            // Create sheet
            const sheet = document.createElement('div');
            sheet.className = 'popover-mobile';
            sheet.innerHTML = `
                ${title || showClose ? `
                    <div class="popover-mobile-header">
                        ${title ? `<h5 class="mb-0">${title}</h5>` : ''}
                        ${showClose ? '<button class="btn-icon" aria-label="Close"><i class="fas fa-times"></i></button>' : ''}
                    </div>
                ` : ''}
                <div class="popover-mobile-body">${content}</div>
            `;

            // Close handler
            const closeBtn = sheet.querySelector('.btn-icon');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }

            document.body.appendChild(backdrop);
            document.body.appendChild(sheet);
            this.currentSheet = { sheet, backdrop, onClose };

            // Show animation
            requestAnimationFrame(() => {
                backdrop.classList.add('show');
                sheet.classList.add('show');
            });

            return { close: () => this.close() };
        }

        close() {
            if (!this.currentSheet) return;

            const { sheet, backdrop, onClose } = this.currentSheet;

            sheet.classList.remove('show');
            backdrop.classList.remove('show');

            setTimeout(() => {
                sheet.remove();
                backdrop.remove();
                if (onClose) onClose();
                this.currentSheet = null;
            }, 300);
        }
    }

    /**
     * Mobile Menu Toggle
     */
    class MobileMenu {
        constructor(sidebarSelector = '.sidebar') {
            this.sidebar = document.querySelector(sidebarSelector);
            this.overlay = null;
            this.init();
        }

        init() {
            if (!this.sidebar) return;

            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'sidebar-overlay';
            this.overlay.addEventListener('click', () => this.hide());
            document.body.appendChild(this.overlay);

            // Close on ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
                    this.hide();
                }
            });
        }

        show() {
            this.sidebar.classList.add('show');
            this.overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        hide() {
            this.sidebar.classList.remove('show');
            this.overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        toggle() {
            if (this.overlay.classList.contains('show')) {
                this.hide();
            } else {
                this.show();
            }
        }
    }

    /**
     * Virtual Scroll for Long Lists
     */
    class VirtualScroll {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' 
                ? document.querySelector(container) 
                : container;
            
            this.options = {
                itemHeight: 60,
                bufferSize: 5,
                renderItem: null,
                ...options
            };

            this.items = [];
            this.visibleRange = { start: 0, end: 0 };
            this.containerHeight = 0;
            
            this.init();
        }

        init() {
            this.container.style.overflow = 'auto';
            this.container.addEventListener('scroll', () => this.onScroll(), { passive: true });
            this.updateVisibleRange();
        }

        setItems(items) {
            this.items = items;
            this.containerHeight = items.length * this.options.itemHeight;
            this.container.style.height = `${this.containerHeight}px`;
            this.updateVisibleRange();
        }

        onScroll() {
            this.updateVisibleRange();
            this.render();
        }

        updateVisibleRange() {
            const scrollTop = this.container.scrollTop;
            const containerHeight = this.container.clientHeight;
            
            const start = Math.max(0, Math.floor(scrollTop / this.options.itemHeight) - this.options.bufferSize);
            const end = Math.min(
                this.items.length,
                Math.ceil((scrollTop + containerHeight) / this.options.itemHeight) + this.options.bufferSize
            );

            this.visibleRange = { start, end };
        }

        render() {
            const { start, end } = this.visibleRange;
            const visibleItems = this.items.slice(start, end);

            let content = '';
            visibleItems.forEach((item, index) => {
                const actualIndex = start + index;
                const top = actualIndex * this.options.itemHeight;
                
                content += `<div style="position:absolute;top:${top}px;left:0;right:0;height:${this.options.itemHeight}px;">`;
                content += this.options.renderItem ? this.options.renderItem(item, actualIndex) : item;
                content += '</div>';
            });

            this.container.innerHTML = `<div style="position:relative;height:${this.containerHeight}px;">${content}</div>`;
        }
    }

    /**
     * Detect Mobile Device
     */
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function isTablet() {
        return /iPad|Android(?!.*Mobile)/i.test(navigator.userAgent);
    }

    /**
     * Initialize mobile enhancements
     */
    window.mobileInteractions = {
        TouchGestures,
        SwipeActions,
        PullToRefresh,
        BottomSheet,
        MobileMenu,
        VirtualScroll,
        isMobile,
        isTablet,

        init() {
            if (!isMobile()) return;

            // Initialize swipe actions
            new this.SwipeActions();

            // Initialize mobile menu
            new this.MobileMenu();

            // Add viewport meta tag if missing
            this.setViewport();

            console.log('Mobile interactions initialized');
        },

        setViewport() {
            // Fix for iOS Safari viewport height
            const setVH = () => {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            };
            
            setVH();
            window.addEventListener('resize', setVH);
        }
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.mobileInteractions.init();
        });
    } else {
        window.mobileInteractions.init();
    }

})();
