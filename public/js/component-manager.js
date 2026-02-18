/**
 * Component Manager
 * Manages advanced UI components
 */

class ComponentManager {
    constructor() {
        this.components = new Map();
        this.init();
    }

    init() {
        this.initFAB();
        this.initAccordion();
        this.initTabs();
        this.initSnackbar();
        this.initRating();
        this.initChips();
    }

    /**
     * Floating Action Button
     */
    initFAB() {
        const fab = document.querySelector('.fab');
        const fabMenu = document.querySelector('.fab-menu');

        if (fab && fabMenu) {
            fab.addEventListener('click', () => {
                fabMenu.classList.toggle('active');
                fab.classList.toggle('active');
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!fab.contains(e.target) && !fabMenu.contains(e.target)) {
                    fabMenu.classList.remove('active');
                    fab.classList.remove('active');
                }
            });
        }
    }

    /**
     * Accordion
     */
    initAccordion() {
        const accordionHeaders = document.querySelectorAll('.accordion-header');

        accordionHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const item = header.closest('.accordion-item');
                const isActive = item.classList.contains('active');

                // Close all accordions in the same group
                const group = item.closest('.accordion');
                if (group) {
                    group.querySelectorAll('.accordion-item').forEach(i => {
                        i.classList.remove('active');
                    });
                }

                // Toggle current accordion
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        });
    }

    /**
     * Tabs
     */
    initTabs() {
        const tabButtons = document.querySelectorAll('.tab');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.tab;
                const tabGroup = button.closest('.tabs-container');

                if (!tabGroup) return;

                // Remove active class from all tabs and contents
                tabGroup.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tabGroup.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                button.classList.add('active');
                const targetContent = tabGroup.querySelector(`#${targetId}`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    }

    /**
     * Snackbar / Toast
     */
    initSnackbar() {
        window.showSnackbar = (message, action = null, duration = 3000) => {
            // Remove existing snackbar
            const existing = document.querySelector('.snackbar');
            if (existing) {
                existing.remove();
            }

            // Create snackbar
            const snackbar = document.createElement('div');
            snackbar.className = 'snackbar';
            
            const messageSpan = document.createElement('span');
            messageSpan.textContent = message;
            snackbar.appendChild(messageSpan);

            if (action) {
                const actionButton = document.createElement('button');
                actionButton.className = 'snackbar-action';
                actionButton.textContent = action.text;
                actionButton.onclick = action.callback;
                snackbar.appendChild(actionButton);
            }

            document.body.appendChild(snackbar);

            // Show snackbar
            setTimeout(() => {
                snackbar.classList.add('show');
            }, 10);

            // Hide and remove snackbar
            setTimeout(() => {
                snackbar.classList.remove('show');
                setTimeout(() => {
                    snackbar.remove();
                }, 300);
            }, duration);
        };
    }

    /**
     * Rating
     */
    initRating() {
        const ratings = document.querySelectorAll('.rating');

        ratings.forEach(rating => {
            const stars = rating.querySelectorAll('.rating-star');
            let currentRating = parseInt(rating.dataset.rating) || 0;

            // Set initial rating
            this.updateRating(stars, currentRating);

            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    currentRating = index + 1;
                    rating.dataset.rating = currentRating;
                    this.updateRating(stars, currentRating);

                    // Trigger custom event
                    const event = new CustomEvent('ratingChange', {
                        detail: { rating: currentRating }
                    });
                    rating.dispatchEvent(event);
                });

                star.addEventListener('mouseenter', () => {
                    this.updateRating(stars, index + 1);
                });

                rating.addEventListener('mouseleave', () => {
                    this.updateRating(stars, currentRating);
                });
            });
        });
    }

    updateRating(stars, rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('filled');
            } else {
                star.classList.remove('filled');
            }
        });
    }

    /**
     * Chips with close button
     */
    initChips() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.chip-close')) {
                const chip = e.target.closest('.chip');
                if (chip) {
                    chip.style.animation = 'chipRemove 0.3s ease';
                    setTimeout(() => {
                        chip.remove();
                    }, 300);
                }
            }
        });
    }

    /**
     * Create chip programmatically
     */
    createChip(text, options = {}) {
        const chip = document.createElement('div');
        chip.className = 'chip';

        if (options.avatar) {
            const avatar = document.createElement('div');
            avatar.className = 'chip-avatar';
            avatar.textContent = options.avatar;
            chip.appendChild(avatar);
        }

        const textSpan = document.createElement('span');
        textSpan.textContent = text;
        chip.appendChild(textSpan);

        if (options.closable !== false) {
            const close = document.createElement('div');
            close.className = 'chip-close';
            close.innerHTML = '×';
            chip.appendChild(close);
        }

        return chip;
    }

    /**
     * Stepper navigation
     */
    initStepper() {
        window.stepperNext = (stepperId) => {
            const stepper = document.getElementById(stepperId);
            if (!stepper) return;

            const steps = stepper.querySelectorAll('.stepper-step');
            const activeStep = stepper.querySelector('.stepper-step.active');
            const activeIndex = Array.from(steps).indexOf(activeStep);

            if (activeIndex < steps.length - 1) {
                activeStep.classList.remove('active');
                activeStep.classList.add('completed');
                steps[activeIndex + 1].classList.add('active');
            }
        };

        window.stepperPrev = (stepperId) => {
            const stepper = document.getElementById(stepperId);
            if (!stepper) return;

            const steps = stepper.querySelectorAll('.stepper-step');
            const activeStep = stepper.querySelector('.stepper-step.active');
            const activeIndex = Array.from(steps).indexOf(activeStep);

            if (activeIndex > 0) {
                activeStep.classList.remove('active');
                steps[activeIndex - 1].classList.remove('completed');
                steps[activeIndex - 1].classList.add('active');
            }
        };
    }

    /**
     * Timeline animation
     */
    initTimeline() {
        const timelineItems = document.querySelectorAll('.timeline-item');

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInLeft 0.6s ease forwards';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            timelineItems.forEach(item => observer.observe(item));
        }
    }

    /**
     * Avatar group tooltip
     */
    initAvatarGroup() {
        const avatars = document.querySelectorAll('.avatar-group .avatar');

        avatars.forEach(avatar => {
            if (avatar.dataset.name) {
                avatar.title = avatar.dataset.name;
            }
        });
    }

    /**
     * Pagination
     */
    initPagination() {
        const paginationItems = document.querySelectorAll('.pagination-item');

        paginationItems.forEach(item => {
            item.addEventListener('click', (e) => {
                if (item.classList.contains('disabled') || item.classList.contains('active')) {
                    e.preventDefault();
                    return;
                }

                // Remove active from all items
                paginationItems.forEach(i => i.classList.remove('active'));
                
                // Add active to clicked item
                if (!item.classList.contains('pagination-prev') && 
                    !item.classList.contains('pagination-next')) {
                    item.classList.add('active');
                }
            });
        });
    }

    /**
     * Breadcrumb truncation for mobile
     */
    initBreadcrumb() {
        const breadcrumbs = document.querySelectorAll('.breadcrumb');

        breadcrumbs.forEach(breadcrumb => {
            const items = breadcrumb.querySelectorAll('.breadcrumb-item');
            
            if (window.innerWidth < 768 && items.length > 3) {
                // Hide middle items on mobile
                items.forEach((item, index) => {
                    if (index > 0 && index < items.length - 1) {
                        item.style.display = 'none';
                    }
                });

                // Add ellipsis
                if (!breadcrumb.querySelector('.breadcrumb-ellipsis')) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'breadcrumb-item breadcrumb-ellipsis';
                    ellipsis.textContent = '...';
                    items[1].parentNode.insertBefore(ellipsis, items[1]);
                }
            }
        });
    }

    /**
     * Destroy all components
     */
    destroy() {
        this.components.clear();
    }
}

// CSS for chip remove animation
const style = document.createElement('style');
style.textContent = `
    @keyframes chipRemove {
        0% {
            opacity: 1;
            transform: scale(1);
        }
        100% {
            opacity: 0;
            transform: scale(0.5);
        }
    }
    
    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.componentManager = new ComponentManager();
    });
} else {
    window.componentManager = new ComponentManager();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ComponentManager;
}
