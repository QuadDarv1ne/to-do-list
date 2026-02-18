/**
 * Modern Interactions - Современные UI взаимодействия
 */

(function() {
    'use strict';
    
    class ModernInteractions {
        constructor() {
            this.init();
        }
        
        init() {
            this.setupRippleEffect();
            this.setupSmoothScroll();
            this.setupContextMenu();
        }
        
        setupRippleEffect() {
            document.addEventListener('click', (e) => {
                const target = e.target.closest('.ripple, .btn');
                if (!target) return;
                
                const ripple = document.createElement('span');
                const rect = target.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple-effect');
                
                target.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        }
        
        setupSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
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
        
        setupContextMenu() {
            // Базовая поддержка контекстного меню
            document.addEventListener('contextmenu', (e) => {
                const target = e.target.closest('[data-context-menu]');
                if (target) {
                    e.preventDefault();
                    // Здесь можно добавить логику показа контекстного меню
                }
            });
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ModernInteractions = new ModernInteractions();
        });
    } else {
        window.ModernInteractions = new ModernInteractions();
    }
    
})();
