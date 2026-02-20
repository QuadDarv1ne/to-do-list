/**
 * Плавные анимации и переходы
 * Современные паттерны анимации 2025
 */

class SmoothAnimations {
    constructor() {
        this.init();
    }

    init() {
        this.initScrollAnimations();
        this.initParallaxEffects();
        this.initCounterAnimations();
        this.initProgressBars();
        this.initMorphingShapes();
        this.initPageTransitions();
        this.initLoadingStates();
    }

    /**
     * Анимации при скролле
     */
    initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    
                    // Stagger эффект для дочерних элементов
                    const children = entry.target.querySelectorAll('[data-stagger-child]');
                    children.forEach((child, index) => {
                        setTimeout(() => {
                            child.classList.add('animate-in');
                        }, index * 100);
                    });
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('[data-animate-on-scroll]').forEach(el => {
            observer.observe(el);
        });
    }

    /**
     * Parallax эффекты
     */
    initParallaxEffects() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        if (parallaxElements.length === 0) return;

        let ticking = false;
        
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    this.updateParallax(parallaxElements);
                    ticking = false;
                });
                ticking = true;
            }
        });
    }

    updateParallax(elements) {
        const scrolled = window.pageYOffset;
        
        elements.forEach(el => {
            const speed = parseFloat(el.getAttribute('data-parallax')) || 0.5;
            const yPos = -(scrolled * speed);
            el.style.transform = `translateY(${yPos}px)`;
        });
    }

    /**
     * Анимированные счётчики
     */
    initCounterAnimations() {
        const counters = document.querySelectorAll('[data-counter]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        });

        counters.forEach(counter => observer.observe(counter));
    }

    animateCounter(element) {
        const target = parseFloat(element.getAttribute('data-counter'));
        const duration = parseInt(element.getAttribute('data-duration')) || 2000;
        const start = 0;
        const startTime = Date.now();

        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = start + (target - start) * easeOutQuart;
            
            element.textContent = Math.floor(current).toLocaleString('ru-RU');
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                element.textContent = target.toLocaleString('ru-RU');
            }
        };

        animate();
    }

    /**
     * Анимированные прогресс-бары
     */
    initProgressBars() {
        const progressBars = document.querySelectorAll('[data-progress]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bar = entry.target;
                    const value = parseFloat(bar.getAttribute('data-progress'));
                    
                    setTimeout(() => {
                        bar.style.width = `${value}%`;
                    }, 100);
                    
                    observer.unobserve(bar);
                }
            });
        });

        progressBars.forEach(bar => observer.observe(bar));
    }

    /**
     * Морфинг фигур (для декоративных элементов)
     */
    initMorphingShapes() {
        const shapes = document.querySelectorAll('[data-morph]');
        
        shapes.forEach(shape => {
            setInterval(() => {
                shape.style.animation = 'none';
                setTimeout(() => {
                    shape.style.animation = 'morph 10s ease-in-out infinite';
                }, 10);
            }, 10000);
        });
    }

    /**
     * Плавные переходы между страницами
     */
    initPageTransitions() {
        // Fade out при переходе
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            
            if (link && !link.hasAttribute('target') && 
                link.href.startsWith(window.location.origin) &&
                !link.href.includes('#')) {
                
                e.preventDefault();
                
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    window.location.href = link.href;
                }, 300);
            }
        });

        // Fade in при загрузке
        window.addEventListener('load', () => {
            document.body.style.opacity = '1';
        });
    }

    /**
     * Состояния загрузки
     */
    initLoadingStates() {
        // Skeleton loaders
        document.querySelectorAll('[data-skeleton-loader]').forEach(loader => {
            setTimeout(() => {
                loader.classList.add('loaded');
            }, 1000);
        });

        // Shimmer эффект
        this.addShimmerEffect();
    }

    addShimmerEffect() {
        const style = document.createElement('style');
        style.textContent = `
            [data-skeleton-loader] {
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%;
                animation: shimmer 1.5s infinite;
                border-radius: 4px;
            }
            
            [data-skeleton-loader].loaded {
                animation: none;
                background: transparent;
            }
            
            @keyframes shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
            
            @keyframes morph {
                0%, 100% { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; }
                50% { border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%; }
            }
            
            .animate-in {
                animation: fadeInUp 0.6s ease forwards;
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.smoothAnimations = new SmoothAnimations();
    });
} else {
    window.smoothAnimations = new SmoothAnimations();
}
