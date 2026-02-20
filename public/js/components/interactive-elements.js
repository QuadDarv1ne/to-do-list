/**
 * Interactive Elements - Интерактивные элементы интерфейса
 * Ripple эффекты, hover анимации, smooth scrolling
 */

class InteractiveElements {
    constructor() {
        this.init();
    }

    init() {
        this.initRippleEffect();
        this.initSmoothScroll();
        this.initParallaxEffect();
        this.initHoverAnimations();
        this.initTooltips();
        this.initCardFlip();
    }

    // Ripple эффект для кнопок
    initRippleEffect() {
        const buttons = document.querySelectorAll('.btn, .nav-link, .card-modern, [data-ripple]');
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.5);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s ease-out;
                    pointer-events: none;
                    z-index: 1;
                `;
                
                ripple.classList.add('ripple-effect');
                
                // Убеждаемся что у кнопки есть position: relative
                if (getComputedStyle(this).position === 'static') {
                    this.style.position = 'relative';
                }
                
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Добавляем CSS для анимации
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Плавная прокрутка
    initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '#!') return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Обновляем URL без перезагрузки
                    if (history.pushState) {
                        history.pushState(null, null, href);
                    }
                }
            });
        });
    }

    // Parallax эффект для фона
    initParallaxEffect() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        if (parallaxElements.length === 0) return;
        
        let ticking = false;
        
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    const scrolled = window.pageYOffset;
                    
                    parallaxElements.forEach(element => {
                        const speed = element.dataset.parallax || 0.5;
                        const yPos = -(scrolled * speed);
                        element.style.transform = `translateY(${yPos}px)`;
                    });
                    
                    ticking = false;
                });
                
                ticking = true;
            }
        });
    }

    // Hover анимации для карточек
    initHoverAnimations() {
        const cards = document.querySelectorAll('.card-modern, [data-hover-lift]');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
                this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });
        
        // 3D tilt эффект
        const tiltCards = document.querySelectorAll('[data-tilt]');
        
        tiltCards.forEach(card => {
            card.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
            });
        });
    }

    // Простые тултипы
    initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = element.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: rgba(0, 0, 0, 0.9);
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 0.875rem;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transform: translateY(10px);
                transition: all 0.3s ease;
                z-index: 10000;
            `;
            
            element.addEventListener('mouseenter', function(e) {
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
                tooltip.style.top = rect.bottom + 10 + 'px';
                
                requestAnimationFrame(() => {
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateY(0)';
                });
            });
            
            element.addEventListener('mouseleave', function() {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'translateY(10px)';
                setTimeout(() => tooltip.remove(), 300);
            });
        });
    }

    // Flip карточки
    initCardFlip() {
        const flipCards = document.querySelectorAll('[data-flip-card]');
        
        flipCards.forEach(card => {
            card.addEventListener('click', function() {
                this.classList.toggle('flipped');
            });
        });
    }

    // Анимация появления элементов при скролле
    static observeElements() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        document.querySelectorAll('[data-animate]').forEach(element => {
            observer.observe(element);
        });
    }

    // Конфетти эффект для успешных действий
    static confetti(x, y) {
        const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe'];
        const particleCount = 30;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            const color = colors[Math.floor(Math.random() * colors.length)];
            const size = Math.random() * 8 + 4;
            const angle = (Math.PI * 2 * i) / particleCount;
            const velocity = Math.random() * 200 + 100;
            
            particle.style.cssText = `
                position: fixed;
                left: ${x}px;
                top: ${y}px;
                width: ${size}px;
                height: ${size}px;
                background: ${color};
                border-radius: 50%;
                pointer-events: none;
                z-index: 10000;
            `;
            
            document.body.appendChild(particle);
            
            const vx = Math.cos(angle) * velocity;
            const vy = Math.sin(angle) * velocity;
            
            let posX = x;
            let posY = y;
            let velocityY = vy;
            const gravity = 500;
            const startTime = Date.now();
            
            function animate() {
                const elapsed = (Date.now() - startTime) / 1000;
                
                posX += vx * elapsed;
                posY += velocityY * elapsed;
                velocityY += gravity * elapsed;
                
                particle.style.left = posX + 'px';
                particle.style.top = posY + 'px';
                particle.style.opacity = Math.max(0, 1 - elapsed * 2);
                
                if (elapsed < 1) {
                    requestAnimationFrame(animate);
                } else {
                    particle.remove();
                }
            }
            
            animate();
        }
    }

    // Shake анимация для ошибок
    static shake(element) {
        element.style.animation = 'shake 0.5s';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    // Pulse анимация
    static pulse(element) {
        element.style.animation = 'pulse 0.5s';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    new InteractiveElements();
    InteractiveElements.observeElements();
});

// Глобальные утилиты
window.InteractiveElements = InteractiveElements;

console.log('✨ Interactive Elements загружен!');
