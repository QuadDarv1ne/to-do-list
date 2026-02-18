/**
 * Tour Guide
 * Интерактивный тур по приложению для новых пользователей
 */

class TourGuide {
    constructor() {
        this.steps = [];
        this.currentStep = 0;
        this.overlay = null;
        this.tooltip = null;
        this.isActive = false;
        this.init();
    }

    init() {
        this.createOverlay();
        this.createTooltip();
        this.checkFirstVisit();
    }

    checkFirstVisit() {
        const hasSeenTour = localStorage.getItem('tourCompleted');
        if (!hasSeenTour && this.shouldShowTour()) {
            setTimeout(() => {
                this.showWelcome();
            }, 1000);
        }
    }

    shouldShowTour() {
        // Показываем тур только на главной странице
        return window.location.pathname === '/' || window.location.pathname.includes('dashboard');
    }

    showWelcome() {
        const modal = document.createElement('div');
        modal.className = 'tour-welcome-modal';
        modal.innerHTML = `
            <div class="tour-welcome-content">
                <div class="tour-welcome-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h2>Добро пожаловать в CRM Task Management!</h2>
                <p>Хотите совершить быстрый тур по основным возможностям системы?</p>
                <div class="tour-welcome-actions">
                    <button class="btn btn-primary" id="startTour">
                        <i class="fas fa-play"></i> Начать тур
                    </button>
                    <button class="btn btn-secondary" id="skipTour">
                        Пропустить
                    </button>
                </div>
                <label class="tour-welcome-checkbox">
                    <input type="checkbox" id="dontShowAgain">
                    Больше не показывать
                </label>
            </div>
        `;

        document.body.appendChild(modal);

        document.getElementById('startTour').addEventListener('click', () => {
            modal.remove();
            this.startDashboardTour();
        });

        document.getElementById('skipTour').addEventListener('click', () => {
            const dontShow = document.getElementById('dontShowAgain').checked;
            if (dontShow) {
                localStorage.setItem('tourCompleted', 'true');
            }
            modal.remove();
        });

        this.addWelcomeStyles();
    }

    startDashboardTour() {
        this.steps = [
            {
                element: '.navbar-brand-enhanced',
                title: 'Главное меню',
                content: 'Здесь находится логотип и название системы. Нажмите на него, чтобы вернуться на главную страницу.',
                position: 'bottom'
            },
            {
                element: '.navbar-nav',
                title: 'Навигация',
                content: 'Основное меню навигации. Здесь вы найдете доступ к задачам, канбан-доске, календарю и аналитике.',
                position: 'bottom'
            },
            {
                element: '.quick-action-btn-enhanced',
                title: 'Быстрые действия',
                content: 'Кнопка для быстрого создания новой задачи.',
                position: 'bottom'
            },
            {
                element: '.user-avatar-enhanced',
                title: 'Профиль пользователя',
                content: 'Меню вашего профиля. Здесь можно изменить настройки, настроить безопасность и выйти из системы.',
                position: 'bottom'
            },
            {
                element: '#quick-task-fab',
                title: 'Быстрое создание задачи',
                content: 'Плавающая кнопка для быстрого создания задачи. Также доступна по горячей клавише "N".',
                position: 'left'
            }
        ];

        this.start();
    }

    start() {
        this.isActive = true;
        this.currentStep = 0;
        this.showStep();
    }

    showStep() {
        if (this.currentStep >= this.steps.length) {
            this.complete();
            return;
        }

        const step = this.steps[this.currentStep];
        const element = document.querySelector(step.element);

        if (!element) {
            this.currentStep++;
            this.showStep();
            return;
        }

        this.highlightElement(element);
        this.showTooltip(element, step);
        this.overlay.classList.add('active');
    }

    highlightElement(element) {
        const rect = element.getBoundingClientRect();
        
        // Создаем подсветку
        const highlight = document.createElement('div');
        highlight.className = 'tour-highlight';
        highlight.style.cssText = `
            position: fixed;
            top: ${rect.top - 8}px;
            left: ${rect.left - 8}px;
            width: ${rect.width + 16}px;
            height: ${rect.height + 16}px;
            border: 3px solid var(--primary);
            border-radius: 8px;
            pointer-events: none;
            z-index: 10001;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        `;

        // Удаляем старую подсветку
        const oldHighlight = document.querySelector('.tour-highlight');
        if (oldHighlight) oldHighlight.remove();

        document.body.appendChild(highlight);
    }

    showTooltip(element, step) {
        const rect = element.getBoundingClientRect();
        
        this.tooltip.innerHTML = `
            <div class="tour-tooltip-header">
                <h4>${step.title}</h4>
                <button class="tour-close" aria-label="Закрыть тур">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="tour-tooltip-body">
                <p>${step.content}</p>
            </div>
            <div class="tour-tooltip-footer">
                <div class="tour-progress">
                    ${this.currentStep + 1} из ${this.steps.length}
                </div>
                <div class="tour-actions">
                    ${this.currentStep > 0 ? '<button class="btn btn-sm btn-secondary tour-prev">Назад</button>' : ''}
                    <button class="btn btn-sm btn-primary tour-next">
                        ${this.currentStep < this.steps.length - 1 ? 'Далее' : 'Завершить'}
                    </button>
                </div>
            </div>
        `;

        // Позиционируем tooltip
        this.positionTooltip(rect, step.position);
        this.tooltip.classList.add('active');

        // Обработчики
        this.tooltip.querySelector('.tour-close').addEventListener('click', () => this.stop());
        this.tooltip.querySelector('.tour-next').addEventListener('click', () => this.next());
        
        const prevBtn = this.tooltip.querySelector('.tour-prev');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.prev());
        }
    }

    positionTooltip(rect, position) {
        const tooltipRect = this.tooltip.getBoundingClientRect();
        let top, left;

        switch(position) {
            case 'bottom':
                top = rect.bottom + 16;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'top':
                top = rect.top - tooltipRect.height - 16;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.left - tooltipRect.width - 16;
                break;
            case 'right':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.right + 16;
                break;
            default:
                top = rect.bottom + 16;
                left = rect.left;
        }

        // Проверяем границы экрана
        if (left < 16) left = 16;
        if (left + tooltipRect.width > window.innerWidth - 16) {
            left = window.innerWidth - tooltipRect.width - 16;
        }
        if (top < 16) top = 16;

        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.left = `${left}px`;
    }

    next() {
        this.currentStep++;
        this.showStep();
    }

    prev() {
        this.currentStep--;
        this.showStep();
    }

    stop() {
        this.isActive = false;
        this.overlay.classList.remove('active');
        this.tooltip.classList.remove('active');
        
        const highlight = document.querySelector('.tour-highlight');
        if (highlight) highlight.remove();
    }

    complete() {
        this.stop();
        localStorage.setItem('tourCompleted', 'true');
        
        if (window.showToast) {
            window.showToast('Тур завершен! Приятной работы!', 'success');
        }
    }

    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'tour-overlay';
        document.body.appendChild(this.overlay);
    }

    createTooltip() {
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tour-tooltip';
        document.body.appendChild(this.tooltip);
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById('tourGuideStyles')) return;

        const style = document.createElement('style');
        style.id = 'tourGuideStyles';
        style.textContent = `
            .tour-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 10000;
                display: none;
            }

            .tour-overlay.active {
                display: block;
            }

            .tour-tooltip {
                position: fixed;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                padding: 0;
                max-width: 400px;
                z-index: 10002;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .tour-tooltip.active {
                opacity: 1;
                visibility: visible;
            }

            .tour-tooltip-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.25rem;
                border-bottom: 1px solid var(--border);
            }

            .tour-tooltip-header h4 {
                margin: 0;
                font-size: 1.125rem;
                color: var(--text-primary);
            }

            .tour-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                padding: 4px;
                font-size: 1.125rem;
            }

            .tour-close:hover {
                color: var(--text-primary);
            }

            .tour-tooltip-body {
                padding: 1.25rem;
            }

            .tour-tooltip-body p {
                margin: 0;
                color: var(--text-secondary);
                line-height: 1.6;
            }

            .tour-tooltip-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.25rem;
                border-top: 1px solid var(--border);
            }

            .tour-progress {
                font-size: 0.875rem;
                color: var(--text-secondary);
            }

            .tour-actions {
                display: flex;
                gap: 0.5rem;
            }

            .tour-welcome-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10003;
                animation: fadeIn 0.3s ease;
            }

            .tour-welcome-content {
                background: var(--bg-card);
                border-radius: 16px;
                padding: 2rem;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .tour-welcome-icon {
                font-size: 4rem;
                color: var(--primary);
                margin-bottom: 1rem;
            }

            .tour-welcome-content h2 {
                margin-bottom: 1rem;
                color: var(--text-primary);
            }

            .tour-welcome-content p {
                margin-bottom: 1.5rem;
                color: var(--text-secondary);
            }

            .tour-welcome-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .tour-welcome-checkbox {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: var(--text-secondary);
                cursor: pointer;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;

        document.head.appendChild(style);
    }

    addWelcomeStyles() {
        // Стили уже добавлены в addStyles()
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.tourGuide = new TourGuide();
    
    // Глобальная функция для запуска тура
    window.startTour = () => {
        window.tourGuide.startDashboardTour();
    };
});

window.TourGuide = TourGuide;
