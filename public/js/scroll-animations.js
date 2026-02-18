/**
 * Scroll Animations
 * Animate elements when they come into view
 */

(function() {
    'use strict';

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initScrollAnimations();
        initParallaxEffects();
        initProgressBars();
        initCounters();
        initStickyElements();
    });

    /**
     * Scroll animations using Intersection Observer
     */
    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll('.animate-on-scroll');
        
        if (!animatedElements.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Optional: unobserve after animation
                    if (entry.target.hasAttribute('data-animate-once')) {
                        observer.unobserve(entry.target);
                    }
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        animatedElements.forEach(element => {
            observer.observe(element);
        });
    }

    /**
     * Parallax scrolling effects
     */
    function initParallaxEffects() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        if (!parallaxElements.length) return;

        window.addEventListener('scroll', throttle(() => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = element.getAttribute('data-parallax') || 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        }, 10));
    }

    /**
     * Animate progress bars when visible
     */
    function initProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar[data-animate]');
        
        if (!progressBars.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bar = entry.target;
                    const targetWidth = bar.getAttribute('aria-valuenow') || bar.style.width;
                    
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = targetWidth + '%';
                    }, 100);
                    
                    observer.unobserve(bar);
                }
            });
        }, { threshold: 0.5 });

        progressBars.forEach(bar => {
            observer.observe(bar);
        });
    }

    /**
     * Animate counters when visible
     */
    function initCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => {
            observer.observe(counter);
        });
    }

    function animateCounter(element) {
        const target = parseInt(element.getAttribute('data-counter'));
        const duration = parseInt(element.getAttribute('data-duration')) || 2000;
        const step = target / (duration / 16);
        let current = 0;

        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                element.textContent = formatNumber(target);
                clearInterval(timer);
            } else {
                element.textContent = formatNumber(Math.floor(current));
            }
        }, 16);
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    /**
     * Sticky elements on scroll
     */
    function initStickyElements() {
        const stickyElements = document.querySelectorAll('[data-sticky]');
        
        if (!stickyElements.length) return;

        window.addEventListener('scroll', throttle(() => {
            stickyElements.forEach(element => {
                const offset = parseInt(element.getAttribute('data-sticky-offset')) || 0;
                const rect = element.getBoundingClientRect();
                
                if (rect.top <= offset) {
                    element.classList.add('is-sticky');
                } else {
                    element.classList.remove('is-sticky');
                }
            });
        }, 10));
    }

    /**
     * Smooth scroll to anchor links
     */
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href === '#') return;
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                const offset = parseInt(this.getAttribute('data-offset')) || 80;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    /**
     * Reveal elements on scroll with stagger
     */
    const revealElements = document.querySelectorAll('.reveal-on-scroll');
    if (revealElements.length) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('revealed');
                    }, index * 100);
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        revealElements.forEach(element => {
            revealObserver.observe(element);
        });
    }

    /**
     * Scroll progress indicator
     */
    function initScrollProgress() {
        let progressBar = document.getElementById('scroll-progress');
        
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = 'scroll-progress';
            progressBar.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 0%;
                height: 3px;
                background: linear-gradient(90deg, #667eea, #764ba2);
                z-index: 9999;
                transition: width 0.1s ease;
            `;
            document.body.appendChild(progressBar);
        }

        window.addEventListener('scroll', throttle(() => {
            const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (window.pageYOffset / windowHeight) * 100;
            progressBar.style.width = scrolled + '%';
        }, 10));
    }

    // Initialize scroll progress if enabled
    if (document.body.hasAttribute('data-scroll-progress')) {
        initScrollProgress();
    }

    /**
     * Back to top button
     */
    function initBackToTop() {
        let backToTopBtn = document.getElementById('back-to-top');
        
        if (!backToTopBtn) {
            backToTopBtn = document.createElement('button');
            backToTopBtn.id = 'back-to-top';
            backToTopBtn.className = 'btn btn-primary btn-icon';
            backToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            backToTopBtn.style.cssText = `
                position: fixed;
                bottom: 30px;
                right: 30px;
                z-index: 1000;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            `;
            backToTopBtn.setAttribute('aria-label', 'Вернуться наверх');
            document.body.appendChild(backToTopBtn);

            backToTopBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }

        window.addEventListener('scroll', throttle(() => {
            if (window.pageYOffset > 300) {
                backToTopBtn.style.opacity = '1';
                backToTopBtn.style.visibility = 'visible';
            } else {
                backToTopBtn.style.opacity = '0';
                backToTopBtn.style.visibility = 'hidden';
            }
        }, 100));
    }

    // Initialize back to top if enabled
    if (document.body.hasAttribute('data-back-to-top')) {
        initBackToTop();
    }

    /**
     * Throttle function for performance
     */
    function throttle(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Add reveal classes to elements
     */
    const cards = document.querySelectorAll('.card:not(.no-animate)');
    cards.forEach((card, index) => {
        if (!card.classList.contains('animate-on-scroll')) {
            card.classList.add('animate-on-scroll');
            card.style.transitionDelay = `${index * 0.1}s`;
        }
    });

    // Expose utility functions
    window.ScrollAnimations = {
        animateCounter: animateCounter,
        initScrollProgress: initScrollProgress,
        initBackToTop: initBackToTop
    };

})();

// Add CSS for scroll animations
if (!document.getElementById('scrollAnimationsStyles')) {
    const style = document.createElement('style');
    style.id = 'scrollAnimationsStyles';
    style.textContent = `
    .animate-on-scroll {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }
    
    .animate-on-scroll.visible {
        opacity: 1;
        transform: translateY(0);
    }
    
    .reveal-on-scroll {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.5s ease-out, transform 0.5s ease-out;
    }
    
    .reveal-on-scroll.revealed {
        opacity: 1;
        transform: translateY(0);
    }
    
    .is-sticky {
        position: fixed;
        top: 0;
        z-index: 1000;
        animation: slideInTop 0.3s ease-out;
    }
    
    @keyframes slideInTop {
        from {
            transform: translateY(-100%);
        }
        to {
            transform: translateY(0);
        }
    }
`;
    document.head.appendChild(style);
}
