/**
 * Category Page Enhancements
 * Animated counters and theme-aware interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Animate stat counters
    animateCounters();
    
    // Add hover effects to category cards
    enhanceCategoryCards();
    
    // Initialize theme-aware tooltips
    initializeTooltips();
});

/**
 * Animate stat value counters
 */
function animateCounters() {
    const counters = document.querySelectorAll('.stat-value[data-count]');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 1000; // 1 second
        const increment = target / (duration / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        
        // Start animation when element is in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(counter);
    });
}

/**
 * Enhance category cards with interactions
 */
function enhanceCategoryCards() {
    const cards = document.querySelectorAll('.category-card');
    
    cards.forEach(card => {
        // Add ripple effect on click
        card.addEventListener('click', function(e) {
            // Don't trigger on dropdown or links
            if (e.target.closest('.dropdown') || e.target.closest('a')) {
                return;
            }
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple-effect');
            
            const rect = card.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            card.style.position = 'relative';
            card.style.overflow = 'hidden';
            card.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
        
        // Animate progress bars
        const progressBar = card.querySelector('.progress-bar');
        if (progressBar) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const width = progressBar.style.width;
                        progressBar.style.width = '0%';
                        setTimeout(() => {
                            progressBar.style.transition = 'width 1s ease-out';
                            progressBar.style.width = width;
                        }, 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(card);
        }
    });
}

/**
 * Initialize theme-aware tooltips
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[title]');
    
    tooltipElements.forEach(element => {
        // Bootstrap tooltips if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(element, {
                trigger: 'hover',
                placement: 'top'
            });
        }
    });
}

/**
 * Add ripple effect styles
 */
if (!document.getElementById('categoryPageStyles')) {
    const style = document.createElement('style');
    style.id = 'categoryPageStyles';
    style.textContent = `
    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(102, 126, 234, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    [data-theme='dark'] .ripple-effect {
        background: rgba(59, 130, 246, 0.3);
    }
    
    [data-theme='orange'] .ripple-effect {
        background: rgba(249, 115, 22, 0.3);
    }
    
    [data-theme='purple'] .ripple-effect {
        background: rgba(168, 85, 247, 0.3);
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
    document.head.appendChild(style);
}

// Listen for theme changes and update animations
window.addEventListener('themechange', function(e) {
    console.log('Theme changed to:', e.detail.theme);
    
    // Re-initialize any theme-dependent features
    const cards = document.querySelectorAll('.category-card');
    cards.forEach(card => {
        // Trigger a subtle animation on theme change
        card.style.transition = 'all 0.3s ease';
    });
});
