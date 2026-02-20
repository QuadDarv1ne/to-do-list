/**
 * Enhanced Interactions
 * Улучшенные интерактивные элементы и анимации
 */

document.addEventListener('DOMContentLoaded', function() {
    initRippleEffect();
    initTooltips();
    initSmoothScroll();
    initLazyLoad();
    initCardAnimations();
    initFormEnhancements();
    initNotificationSystem();
    initScrollAnimations();
});

/**
 * Ripple эффект для кнопок
 */
function initRippleEffect() {
    document.querySelectorAll('.btn, .ripple-effect').forEach(element => {
        element.addEventListener('click', function(e) {
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
                animation: ripple-animation 0.6s ease-out;
                pointer-events: none;
            `;

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Добавляем CSS анимацию
    if (!document.getElementById('ripple-animation-style')) {
        const style = document.createElement('style');
        style.id = 'ripple-animation-style';
        style.textContent = `
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Улучшенные тултипы
 */
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        const tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.textContent = element.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transform: translateY(-5px);
            transition: opacity 0.2s, transform 0.2s;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        `;

        element.addEventListener('mouseenter', function(e) {
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            }, 10);
        });

        element.addEventListener('mouseleave', function() {
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(-5px)';
            setTimeout(() => tooltip.remove(), 200);
        });
    });
}

/**
 * Плавная прокрутка
 */
function initSmoothScroll() {
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

    // Кнопка "Наверх"
    const backToTop = document.createElement('button');
    backToTop.innerHTML = '<i class="fas fa-arrow-up"></i>';
    backToTop.className = 'back-to-top';
    backToTop.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent-primary);
        color: white;
        border: none;
        cursor: pointer;
        opacity: 0;
        transform: scale(0);
        transition: all 0.3s;
        z-index: 999;
        box-shadow: var(--shadow-lg);
    `;

    document.body.appendChild(backToTop);

    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTop.style.opacity = '1';
            backToTop.style.transform = 'scale(1)';
        } else {
            backToTop.style.opacity = '0';
            backToTop.style.transform = 'scale(0)';
        }
    });

    backToTop.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * Ленивая загрузка изображений
 */
function initLazyLoad() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('fade-in');
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

/**
 * Анимации карточек при появлении
 */
function initCardAnimations() {
    const cards = document.querySelectorAll('.card-modern, .stat-card-modern');
    
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('slide-in-up');
                }, index * 100);
                cardObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });

    cards.forEach(card => {
        card.style.opacity = '0';
        cardObserver.observe(card);
    });
}

/**
 * Улучшения форм
 */
function initFormEnhancements() {
    // Анимация label при фокусе
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
    });

    // Валидация в реальном времени
    document.querySelectorAll('input[required], textarea[required]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('shake');
                setTimeout(() => this.classList.remove('shake'), 500);
            }
        });
    });

    // Показ/скрытие пароля
    document.querySelectorAll('input[type="password"]').forEach(input => {
        const wrapper = input.parentElement;
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.innerHTML = '<i class="fas fa-eye"></i>';
        toggle.className = 'password-toggle';
        toggle.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
        `;

        wrapper.style.position = 'relative';
        wrapper.appendChild(toggle);

        toggle.addEventListener('click', function() {
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });
}

/**
 * Система уведомлений
 */
function initNotificationSystem() {
    window.showNotification = function(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} toast-enter`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        notification.innerHTML = `
            <i class="fas ${icons[type]}"></i>
            <span>${message}</span>
            <button class="notification-close"><i class="fas fa-times"></i></button>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 1100;
            min-width: 300px;
            border-left: 4px solid var(--accent-${type === 'error' ? 'danger' : type});
        `;

        document.body.appendChild(notification);

        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.25rem;
            margin-left: auto;
        `;

        const close = () => {
            notification.classList.add('toast-exit');
            setTimeout(() => notification.remove(), 300);
        };

        closeBtn.addEventListener('click', close);

        if (duration > 0) {
            setTimeout(close, duration);
        }

        return notification;
    };
}

/**
 * Анимации при прокрутке
 */
function initScrollAnimations() {
    const elements = document.querySelectorAll('[data-scroll-animation]');
    
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animation = entry.target.dataset.scrollAnimation || 'fade-in';
                entry.target.classList.add(animation);
                scrollObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    elements.forEach(el => {
        el.style.opacity = '0';
        scrollObserver.observe(el);
    });
}

/**
 * Утилиты
 */

// Копирование в буфер обмена
window.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Скопировано в буфер обмена', 'success', 2000);
    }).catch(() => {
        showNotification('Ошибка копирования', 'error', 2000);
    });
};

// Подтверждение действия
window.confirmAction = function(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal scale-in';
    modal.innerHTML = `
        <div class="confirm-modal-backdrop"></div>
        <div class="confirm-modal-content">
            <h5>${message}</h5>
            <div class="confirm-modal-actions">
                <button class="btn btn-secondary" data-action="cancel">Отмена</button>
                <button class="btn btn-danger" data-action="confirm">Подтвердить</button>
            </div>
        </div>
    `;

    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1200;
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    document.body.appendChild(modal);

    modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
        callback();
        modal.remove();
    });

    modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
        modal.remove();
    });

    modal.querySelector('.confirm-modal-backdrop').addEventListener('click', () => {
        modal.remove();
    });
};

// Экспорт для глобального использования
if (typeof window !== 'undefined') {
    window.EnhancedInteractions = {
        showNotification: window.showNotification,
        copyToClipboard: window.copyToClipboard,
        confirmAction: window.confirmAction
    };
}
