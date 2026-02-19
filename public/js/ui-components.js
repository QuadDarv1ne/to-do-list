/**
 * Enhanced UI Components Manager v3.0
 * Modern animations, transitions, and interactive elements
 */

(function() {
    'use strict';

    /**
     * Toast Notification System
     */
    class ToastManager {
        constructor() {
            this.container = null;
            this.toasts = [];
            this.defaultDuration = 5000;
            this.maxToasts = 5;
        }

        init() {
            this.createContainer();
        }

        createContainer() {
            if (document.getElementById('toast-container')) return;
            
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }

        show(message, type = 'info', options = {}) {
            const duration = options.duration || this.defaultDuration;
            const position = options.position || 'top-right';
            const icon = this.getIcon(type);

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-message">${this.escapeHtml(message)}</div>
                <button class="toast-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            `;

            // Add close button handler
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => this.remove(toast));

            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => this.remove(toast), duration);
            }

            // Add to container
            this.container.appendChild(toast);
            this.toasts.push(toast);

            // Limit max toasts
            if (this.toasts.length > this.maxToasts) {
                this.remove(this.toasts[0]);
            }

            return toast;
        }

        remove(toast) {
            toast.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => {
                toast.remove();
                this.toasts = this.toasts.filter(t => t !== toast);
            }, 300);
        }

        getIcon(type) {
            const icons = {
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-exclamation-circle"></i>',
                warning: '<i class="fas fa-exclamation-triangle"></i>',
                info: '<i class="fas fa-info-circle"></i>',
                loading: '<i class="fas fa-spinner fa-spin"></i>'
            };
            return icons[type] || icons.info;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        success(message, options) {
            return this.show(message, 'success', options);
        }

        error(message, options) {
            return this.show(message, 'error', options);
        }

        warning(message, options) {
            return this.show(message, 'warning', options);
        }

        info(message, options) {
            return this.show(message, 'info', options);
        }

        loading(message, options) {
            return this.show(message, 'loading', options);
        }
    }

    /**
     * Modal Manager
     */
    class ModalManager {
        constructor() {
            this.activeModal = null;
        }

        show(content, options = {}) {
            const {
                title = '',
                size = 'medium',
                showClose = true,
                onClose = null,
                actions = []
            } = options;

            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal-enhanced';
            modal.innerHTML = `
                <div class="modal-backdrop-enhanced"></div>
                <div class="modal-content-enhanced modal-${size}">
                    ${title ? `
                        <div class="modal-header-enhanced">
                            <h5 class="modal-title">${this.escapeHtml(title)}</h5>
                            ${showClose ? '<button class="modal-close-enhanced" aria-label="Close"><i class="fas fa-times"></i></button>' : ''}
                        </div>
                    ` : ''}
                    <div class="modal-body-enhanced">${content}</div>
                    ${actions.length > 0 ? `
                        <div class="modal-footer-enhanced">
                            ${actions.map(action => `
                                <button class="btn-enhanced btn-${action.type || 'primary'}" data-action="${action.id || ''}">
                                    ${action.label}
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;

            document.body.appendChild(modal);

            // Close handlers
            const closeBtn = modal.querySelector('.modal-close-enhanced');
            const backdrop = modal.querySelector('.modal-backdrop-enhanced');

            const closeModal = () => {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.remove();
                    if (onClose) onClose();
                }, 300);
            };

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);

            // Action handlers
            modal.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = actions.find(a => a.id === btn.dataset.action);
                    if (action && action.onClick) {
                        action.onClick(closeModal);
                    }
                });
            });

            // ESC to close
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            // Show animation
            requestAnimationFrame(() => {
                modal.classList.add('show');
            });

            this.activeModal = modal;
            return { modal, close: closeModal };
        }

        confirm(message, options = {}) {
            const {
                title = 'Подтверждение',
                confirmLabel = 'Подтвердить',
                cancelLabel = 'Отмена',
                type = 'primary'
            } = options;

            return new Promise((resolve) => {
                const content = `<p class="mb-0">${this.escapeHtml(message)}</p>`;
                
                const { close } = this.show(content, {
                    title,
                    actions: [
                        {
                            id: 'cancel',
                            label: cancelLabel,
                            type: 'outline',
                            onClick: () => {
                                close();
                                resolve(false);
                            }
                        },
                        {
                            id: 'confirm',
                            label: confirmLabel,
                            type,
                            onClick: () => {
                                close();
                                resolve(true);
                            }
                        }
                    ]
                });
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Loading Manager
     */
    class LoadingManager {
        constructor() {
            this.overlay = null;
        }

        show(message = 'Загрузка...') {
            if (this.overlay) this.hide();

            this.overlay = document.createElement('div');
            this.overlay.className = 'loading-overlay';
            this.overlay.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                animation: fadeIn 0.3s ease-out;
            `;
            this.overlay.innerHTML = `
                <div class="spinner-enhanced spinner-lg mb-3"></div>
                <div style="color: white; font-weight: 500;">${this.escapeHtml(message)}</div>
            `;

            document.body.appendChild(this.overlay);
        }

        hide() {
            if (!this.overlay) return;

            this.overlay.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => {
                this.overlay.remove();
                this.overlay = null;
            }, 300);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Ripple Effect
     */
    class RippleEffect {
        static init(selector = '.ripple') {
            document.querySelectorAll(selector).forEach(element => {
                element.addEventListener('click', (e) => {
                    this.create(e, element);
                });
            });
        }

        static create(e, element) {
            const circle = document.createElement('span');
            const diameter = Math.max(element.clientWidth, element.clientHeight);
            const radius = diameter / 2;

            const rect = element.getBoundingClientRect();

            circle.style.cssText = `
                position: absolute;
                width: ${diameter}px;
                height: ${diameter}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                left: ${e.clientX - rect.left - radius}px;
                top: ${e.clientY - rect.top - radius}px;
            `;

            element.style.position = 'relative';
            element.style.overflow = 'hidden';
            element.appendChild(circle);

            setTimeout(() => circle.remove(), 600);
        }
    }

    /**
     * Scroll Animations
     */
    class ScrollAnimations {
        constructor() {
            this.observer = null;
            this.defaults = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
        }

        init(options = {}) {
            const config = { ...this.defaults, ...options };

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animate(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, config);

            document.querySelectorAll('[data-animate]').forEach(el => {
                this.observer.observe(el);
            });
        }

        animate(element) {
            const animation = element.dataset.animate || 'fade-in';
            const delay = element.dataset.delay || 0;

            element.style.animationDelay = `${delay}ms`;
            element.classList.add(`animate-${animation}`);
        }
    }

    /**
     * Tooltip Manager
     */
    class TooltipManager {
        constructor() {
            this.tooltip = null;
        }

        init() {
            document.querySelectorAll('[data-tooltip]').forEach(element => {
                element.addEventListener('mouseenter', (e) => this.show(e, element));
                element.addEventListener('mouseleave', () => this.hide());
            });
        }

        show(e, element) {
            const content = element.dataset.tooltip;
            const position = element.dataset.position || 'top';

            this.tooltip = document.createElement('div');
            this.tooltip.className = 'tooltip-enhanced';
            this.tooltip.textContent = content;
            this.tooltip.style.cssText = `
                position: fixed;
                padding: 8px 12px;
                background: #1e293b;
                color: #fff;
                border-radius: 8px;
                font-size: 14px;
                z-index: 1070;
                pointer-events: none;
                animation: fadeIn 0.2s ease-out;
            `;

            document.body.appendChild(this.tooltip);

            this.updatePosition(e, element, position);
        }

        updatePosition(e, element, position) {
            const rect = element.getBoundingClientRect();
            const tooltipRect = this.tooltip.getBoundingClientRect();

            let top, left;

            switch (position) {
                case 'top':
                    top = rect.top - tooltipRect.height - 8;
                    left = rect.left + (rect.width - tooltipRect.width) / 2;
                    break;
                case 'bottom':
                    top = rect.bottom + 8;
                    left = rect.left + (rect.width - tooltipRect.width) / 2;
                    break;
                case 'left':
                    top = rect.top + (rect.height - tooltipRect.height) / 2;
                    left = rect.left - tooltipRect.width - 8;
                    break;
                case 'right':
                    top = rect.top + (rect.height - tooltipRect.height) / 2;
                    left = rect.right + 8;
                    break;
            }

            this.tooltip.style.top = `${top}px`;
            this.tooltip.style.left = `${left}px`;
        }

        hide() {
            if (this.tooltip) {
                this.tooltip.style.animation = 'fadeOut 0.2s ease-out forwards';
                setTimeout(() => {
                    this.tooltip.remove();
                    this.tooltip = null;
                }, 200);
            }
        }
    }

    /**
     * Accordion Manager
     */
    class AccordionManager {
        init(selector = '.accordion-enhanced') {
            document.querySelectorAll(selector).forEach(accordion => {
                this.initAccordion(accordion);
            });
        }

        initAccordion(accordion) {
            accordion.querySelectorAll('.accordion-header').forEach(header => {
                header.addEventListener('click', () => {
                    const isActive = header.classList.contains('active');
                    const body = header.nextElementSibling;

                    // Close all items
                    accordion.querySelectorAll('.accordion-header').forEach(h => {
                        h.classList.remove('active');
                        h.nextElementSibling.style.maxHeight = '0';
                    });

                    // Open clicked item if it wasn't active
                    if (!isActive) {
                        header.classList.add('active');
                        const content = body.querySelector('.accordion-body-content');
                        body.style.maxHeight = `${content.scrollHeight}px`;
                    }
                });
            });
        }
    }

    /**
     * Tabs Manager
     */
    class TabsManager {
        init(selector = '.tabs-enhanced') {
            document.querySelectorAll(selector).forEach(tabs => {
                this.initTabs(tabs);
            });
        }

        initTabs(tabs) {
            tabs.querySelectorAll('.tab-enhanced').forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.target;

                    // Update active tab
                    tabs.querySelectorAll('.tab-enhanced').forEach(t => {
                        t.classList.remove('active');
                    });
                    tab.classList.add('active');

                    // Update active content
                    if (target) {
                        const container = tabs.closest('[data-tabs-container]');
                        if (container) {
                            container.querySelectorAll('.tab-content').forEach(content => {
                                content.classList.remove('active');
                            });
                            const activeContent = document.getElementById(target);
                            if (activeContent) {
                                activeContent.classList.add('active');
                            }
                        }
                    }
                });
            });
        }
    }

    /**
     * Progress Bar Animation
     */
    class ProgressBarManager {
        init() {
            this.observeProgressBars();
        }

        observeProgressBars() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const value = entry.target.dataset.value;
                        if (value) {
                            this.animate(entry.target, parseInt(value));
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            document.querySelectorAll('.progress-bar[data-value]').forEach(bar => {
                observer.observe(bar);
            });
        }

        animate(bar, value) {
            bar.style.width = '0%';
            requestAnimationFrame(() => {
                bar.style.transition = 'width 1s ease-out';
                bar.style.width = `${value}%`;
            });
        }
    }

    /**
     * Counter Animation
     */
    class CounterManager {
        init() {
            this.observeCounters();
        }

        observeCounters() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animate(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            document.querySelectorAll('[data-counter]').forEach(counter => {
                observer.observe(counter);
            });
        }

        animate(element) {
            const target = parseInt(element.dataset.counter);
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;

            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 16);
        }
    }

    /**
     * Initialize all components
     */
    window.uiComponents = {
        toast: new ToastManager(),
        modal: new ModalManager(),
        loading: new LoadingManager(),
        ripple: RippleEffect,
        scrollAnimations: new ScrollAnimations(),
        tooltip: new TooltipManager(),
        accordion: new AccordionManager(),
        tabs: new TabsManager(),
        progressBar: new ProgressBarManager(),
        counter: new CounterManager()
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.uiComponents.toast.init();
            window.uiComponents.tooltip.init();
            window.uiComponents.accordion.init();
            window.uiComponents.tabs.init();
            window.uiComponents.progressBar.init();
            window.uiComponents.counter.init();
            window.uiComponents.scrollAnimations.init();
            window.uiComponents.ripple.init();
        });
    } else {
        window.uiComponents.toast.init();
        window.uiComponents.tooltip.init();
        window.uiComponents.accordion.init();
        window.uiComponents.tabs.init();
        window.uiComponents.progressBar.init();
        window.uiComponents.counter.init();
        window.uiComponents.scrollAnimations.init();
        window.uiComponents.ripple.init();
    }

    // Expose convenient global functions
    window.showToast = (message, type = 'info') => window.uiComponents.toast.show(message, type);
    window.showLoading = (message) => window.uiComponents.loading.show(message);
    window.hideLoading = () => window.uiComponents.loading.hide();
    window.showModal = (content, options) => window.uiComponents.modal.show(content, options);
    window.showConfirm = (message, options) => window.uiComponents.modal.confirm(message, options);

})();
