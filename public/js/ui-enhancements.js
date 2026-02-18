/**
 * UI Enhancements
 * Ripple effects, animations, and interactive improvements
 */

class UIEnhancements {
    constructor() {
        this.init();
    }

    init() {
        this.initRippleEffect();
        this.initAnimateOnScroll();
        this.initSmoothScroll();
        this.initTableSorting();
        this.initTooltips();
        this.initSkeletonLoading();
    }

    /**
     * Ripple effect on buttons and cards
     */
    initRippleEffect() {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('.btn, .card, .list-group-item');
            if (!target) return;

            const ripple = document.createElement('span');
            const rect = target.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');

            // Add ripple container if not exists
            if (!target.classList.contains('ripple-container')) {
                target.classList.add('ripple-container');
            }

            target.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    }

    /**
     * Animate elements on scroll
     */
    initAnimateOnScroll() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe cards and list items
        document.querySelectorAll('.card, .list-group-item, .stat-card').forEach(el => {
            observer.observe(el);
        });
    }

    /**
     * Smooth scroll for anchor links
     */
    initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Table sorting functionality
     */
    initTableSorting() {
        document.querySelectorAll('.table th.sortable').forEach(th => {
            th.addEventListener('click', () => {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const index = Array.from(th.parentElement.children).indexOf(th);
                const isAsc = th.classList.contains('asc');

                // Remove sort classes from all headers
                table.querySelectorAll('th').forEach(header => {
                    header.classList.remove('asc', 'desc');
                });

                // Add appropriate class
                th.classList.add(isAsc ? 'desc' : 'asc');

                // Sort rows
                rows.sort((a, b) => {
                    const aValue = a.children[index].textContent.trim();
                    const bValue = b.children[index].textContent.trim();

                    // Try to parse as number
                    const aNum = parseFloat(aValue);
                    const bNum = parseFloat(bValue);

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? bNum - aNum : aNum - bNum;
                    }

                    // String comparison
                    return isAsc 
                        ? bValue.localeCompare(aValue)
                        : aValue.localeCompare(bValue);
                });

                // Reorder rows
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    }

    /**
     * Initialize tooltips
     */
    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-content';
            tooltip.textContent = el.getAttribute('data-tooltip');

            const wrapper = document.createElement('div');
            wrapper.className = 'tooltip-wrapper';
            el.parentNode.insertBefore(wrapper, el);
            wrapper.appendChild(el);
            wrapper.appendChild(tooltip);
        });
    }

    /**
     * Skeleton loading for dynamic content
     */
    initSkeletonLoading() {
        // Add skeleton class to loading elements
        document.querySelectorAll('[data-loading="true"]').forEach(el => {
            el.classList.add('skeleton');
        });
    }

    /**
     * Animated counter for numbers
     */
    static animateCounter(element, target, duration = 1000) {
        const start = parseInt(element.textContent) || 0;
        const increment = (target - start) / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= target) || (increment < 0 && current <= target)) {
                element.textContent = target.toLocaleString('ru-RU');
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current).toLocaleString('ru-RU');
            }
        }, 16);
    }

    /**
     * Show loading state
     */
    static showLoading(element) {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.setAttribute('data-loading-spinner', 'true');
        element.appendChild(spinner);
        element.style.position = 'relative';
        element.style.pointerEvents = 'none';
        element.style.opacity = '0.6';
    }

    /**
     * Hide loading state
     */
    static hideLoading(element) {
        const spinner = element.querySelector('[data-loading-spinner]');
        if (spinner) {
            spinner.remove();
        }
        element.style.pointerEvents = '';
        element.style.opacity = '';
    }

    /**
     * Show toast notification
     */
    static showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} animate-slide-in-right`;
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;

        const icons = {
            success: 'fa-check-circle',
            danger: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info} me-2"></i>
            ${message}
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'fadeIn 0.3s ease-out reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /**
     * Confirm dialog
     */
    static confirm(message, onConfirm, onCancel) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-backdrop fade show';
        overlay.style.zIndex = '9998';

        const dialog = document.createElement('div');
        dialog.className = 'modal fade show animate-scale-in';
        dialog.style.cssText = 'display: block; z-index: 9999;';
        dialog.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Подтверждение</h5>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-action="cancel">Отмена</button>
                        <button type="button" class="btn btn-primary" data-action="confirm">Подтвердить</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(dialog);

        const close = () => {
            dialog.remove();
            overlay.remove();
        };

        dialog.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            close();
            if (onConfirm) onConfirm();
        });

        dialog.querySelector('[data-action="cancel"]').addEventListener('click', () => {
            close();
            if (onCancel) onCancel();
        });

        overlay.addEventListener('click', () => {
            close();
            if (onCancel) onCancel();
        });
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.uiEnhancements = new UIEnhancements();
    });
} else {
    window.uiEnhancements = new UIEnhancements();
}

// Export for use in other scripts
window.UIEnhancements = UIEnhancements;
