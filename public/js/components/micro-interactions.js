/**
 * Micro-interactions для улучшения UX
 * Современные паттерны взаимодействия 2024-2025
 */

class MicroInteractions {
    constructor() {
        this.init();
    }

    init() {
        this.initButtonRipple();
        this.initTooltips();
        this.initProgressiveDisclosure();
        this.initSmartSearch();
        this.initContextualHelp();
        this.initOptimisticUI();
        this.initSkeletonLoaders();
        this.initInfiniteScroll();
        this.initDragFeedback();
        this.initFormValidation();
    }

    /**
     * Ripple эффект для кнопок (Material Design)
     */
    initButtonRipple() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('.btn-ripple, .btn, button');
            if (!button) return;

            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
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
            `;

            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });

        // Добавляем CSS анимацию
        if (!document.getElementById('ripple-animation')) {
            const style = document.createElement('style');
            style.id = 'ripple-animation';
            style.textContent = `
                @keyframes ripple {
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
     * Умные подсказки с задержкой
     */
    initTooltips() {
        const tooltips = document.querySelectorAll('[data-tooltip]');
        
        tooltips.forEach(element => {
            let tooltipEl = null;
            let timeout = null;

            element.addEventListener('mouseenter', (e) => {
                timeout = setTimeout(() => {
                    const text = element.getAttribute('data-tooltip');
                    const position = element.getAttribute('data-tooltip-position') || 'top';
                    
                    tooltipEl = document.createElement('div');
                    tooltipEl.className = 'tooltip-micro';
                    tooltipEl.textContent = text;
                    tooltipEl.style.cssText = `
                        position: absolute;
                        background: rgba(0, 0, 0, 0.9);
                        color: white;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 13px;
                        white-space: nowrap;
                        z-index: 10000;
                        pointer-events: none;
                        animation: tooltipFadeIn 0.2s ease;
                    `;

                    document.body.appendChild(tooltipEl);

                    const rect = element.getBoundingClientRect();
                    const tooltipRect = tooltipEl.getBoundingClientRect();

                    let top, left;
                    switch(position) {
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
                        default: // top
                            top = rect.top - tooltipRect.height - 8;
                            left = rect.left + (rect.width - tooltipRect.width) / 2;
                    }

                    tooltipEl.style.top = `${top}px`;
                    tooltipEl.style.left = `${left}px`;
                }, 500);
            });

            element.addEventListener('mouseleave', () => {
                clearTimeout(timeout);
                if (tooltipEl) {
                    tooltipEl.style.animation = 'tooltipFadeOut 0.2s ease';
                    setTimeout(() => tooltipEl.remove(), 200);
                    tooltipEl = null;
                }
            });
        });

        // CSS для анимации
        if (!document.getElementById('tooltip-animation')) {
            const style = document.createElement('style');
            style.id = 'tooltip-animation';
            style.textContent = `
                @keyframes tooltipFadeIn {
                    from { opacity: 0; transform: translateY(-5px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes tooltipFadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Progressive Disclosure - показываем детали по требованию
     */
    initProgressiveDisclosure() {
        document.querySelectorAll('[data-expandable]').forEach(element => {
            const trigger = element.querySelector('[data-expand-trigger]');
            const content = element.querySelector('[data-expand-content]');
            
            if (!trigger || !content) return;

            content.style.maxHeight = '0';
            content.style.overflow = 'hidden';
            content.style.transition = 'max-height 0.3s ease';

            trigger.addEventListener('click', () => {
                const isExpanded = element.classList.contains('expanded');
                
                if (isExpanded) {
                    content.style.maxHeight = '0';
                    element.classList.remove('expanded');
                    trigger.innerHTML = trigger.innerHTML.replace('▼', '▶');
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    element.classList.add('expanded');
                    trigger.innerHTML = trigger.innerHTML.replace('▶', '▼');
                }
            });
        });
    }

    /**
     * Умный поиск с автодополнением
     */
    initSmartSearch() {
        const searchInputs = document.querySelectorAll('[data-smart-search]');
        
        searchInputs.forEach(input => {
            let timeout = null;
            let resultsContainer = null;

            input.addEventListener('input', (e) => {
                clearTimeout(timeout);
                
                if (e.target.value.length < 2) {
                    if (resultsContainer) resultsContainer.remove();
                    return;
                }

                timeout = setTimeout(() => {
                    this.showSearchResults(input, e.target.value);
                }, 300);
            });

            input.addEventListener('blur', () => {
                setTimeout(() => {
                    if (resultsContainer) resultsContainer.remove();
                }, 200);
            });
        });
    }

    showSearchResults(input, query) {
        // Здесь можно добавить реальный поиск через API
        const mockResults = [
            { title: 'Задача 1', type: 'task' },
            { title: 'Проект Alpha', type: 'project' },
            { title: 'Клиент ООО "Рога и копыта"', type: 'client' }
        ].filter(item => item.title.toLowerCase().includes(query.toLowerCase()));

        let resultsContainer = document.querySelector('.search-results-micro');
        
        if (!resultsContainer) {
            resultsContainer = document.createElement('div');
            resultsContainer.className = 'search-results-micro';
            resultsContainer.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--card-bg, white);
                border: 1px solid var(--border-color, #e0e0e0);
                border-radius: 8px;
                margin-top: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                animation: slideDown 0.2s ease;
            `;
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(resultsContainer);
        }

        resultsContainer.innerHTML = mockResults.map(result => `
            <div class="search-result-item" style="
                padding: 12px 16px;
                cursor: pointer;
                transition: background 0.2s;
                border-bottom: 1px solid var(--border-color, #f0f0f0);
            " onmouseover="this.style.background='var(--bg-secondary, #f5f5f5)'" 
               onmouseout="this.style.background='transparent'">
                <div style="font-weight: 500; color: var(--text-primary, #333);">${result.title}</div>
                <div style="font-size: 12px; color: var(--text-muted, #999); margin-top: 2px;">${result.type}</div>
            </div>
        `).join('');

        if (mockResults.length === 0) {
            resultsContainer.innerHTML = `
                <div style="padding: 20px; text-align: center; color: var(--text-muted, #999);">
                    Ничего не найдено
                </div>
            `;
        }
    }

    /**
     * Контекстная помощь
     */
    initContextualHelp() {
        document.querySelectorAll('[data-help]').forEach(element => {
            const helpIcon = document.createElement('span');
            helpIcon.innerHTML = '?';
            helpIcon.style.cssText = `
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: var(--primary-color, #6366f1);
                color: white;
                font-size: 12px;
                font-weight: bold;
                margin-left: 6px;
                cursor: help;
                transition: transform 0.2s;
            `;

            helpIcon.addEventListener('mouseenter', () => {
                helpIcon.style.transform = 'scale(1.2)';
            });

            helpIcon.addEventListener('mouseleave', () => {
                helpIcon.style.transform = 'scale(1)';
            });

            helpIcon.setAttribute('data-tooltip', element.getAttribute('data-help'));
            element.appendChild(helpIcon);
        });
    }

    /**
     * Optimistic UI - мгновенная обратная связь
     */
    initOptimisticUI() {
        document.querySelectorAll('[data-optimistic]').forEach(element => {
            element.addEventListener('click', function(e) {
                // Показываем успех сразу
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Готово!';
                this.style.background = 'var(--success, #10b981)';
                this.disabled = true;

                // Через 2 секунды возвращаем обратно (или обрабатываем реальный ответ)
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
            });
        });
    }

    /**
     * Skeleton loaders для лучшего восприятия загрузки
     */
    initSkeletonLoaders() {
        document.querySelectorAll('[data-skeleton]').forEach(element => {
            const skeletonHTML = `
                <div class="skeleton-loader" style="
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: shimmer 1.5s infinite;
                    border-radius: 4px;
                    height: 20px;
                    margin: 8px 0;
                "></div>
            `;
            
            element.innerHTML = skeletonHTML.repeat(3);
        });

        // CSS для shimmer эффекта
        if (!document.getElementById('shimmer-animation')) {
            const style = document.createElement('style');
            style.id = 'shimmer-animation';
            style.textContent = `
                @keyframes shimmer {
                    0% { background-position: -200% 0; }
                    100% { background-position: 200% 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Infinite scroll с индикатором
     */
    initInfiniteScroll() {
        const containers = document.querySelectorAll('[data-infinite-scroll]');
        
        containers.forEach(container => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMoreContent(container);
                    }
                });
            }, { threshold: 0.1 });

            const sentinel = document.createElement('div');
            sentinel.className = 'infinite-scroll-sentinel';
            sentinel.style.height = '1px';
            container.appendChild(sentinel);
            observer.observe(sentinel);
        });
    }

    loadMoreContent(container) {
        const loader = document.createElement('div');
        loader.className = 'loading-indicator';
        loader.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <div class="spinner" style="
                    width: 40px;
                    height: 40px;
                    border: 4px solid rgba(99, 102, 241, 0.2);
                    border-top-color: var(--primary-color, #6366f1);
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    margin: 0 auto;
                "></div>
                <div style="margin-top: 12px; color: var(--text-muted, #999);">Загрузка...</div>
            </div>
        `;
        container.appendChild(loader);

        // Имитация загрузки
        setTimeout(() => {
            loader.remove();
            // Здесь добавляем новый контент
        }, 1000);
    }

    /**
     * Drag feedback - визуальная обратная связь при перетаскивании
     */
    initDragFeedback() {
        document.querySelectorAll('[draggable="true"]').forEach(element => {
            element.addEventListener('dragstart', (e) => {
                element.style.opacity = '0.5';
                element.style.transform = 'scale(0.95)';
                
                // Создаём ghost image
                const ghost = element.cloneNode(true);
                ghost.style.cssText = `
                    position: absolute;
                    top: -1000px;
                    background: var(--card-bg, white);
                    padding: 12px;
                    border-radius: 8px;
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
                `;
                document.body.appendChild(ghost);
                e.dataTransfer.setDragImage(ghost, 0, 0);
                setTimeout(() => ghost.remove(), 0);
            });

            element.addEventListener('dragend', () => {
                element.style.opacity = '1';
                element.style.transform = 'scale(1)';
            });
        });

        // Drop zones
        document.querySelectorAll('[data-drop-zone]').forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.style.background = 'rgba(99, 102, 241, 0.1)';
                zone.style.borderColor = 'var(--primary-color, #6366f1)';
            });

            zone.addEventListener('dragleave', () => {
                zone.style.background = '';
                zone.style.borderColor = '';
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.style.background = '';
                zone.style.borderColor = '';
                
                // Анимация успешного drop
                zone.style.animation = 'pulse 0.3s ease';
            });
        });
    }

    /**
     * Умная валидация форм с мгновенной обратной связью
     */
    initFormValidation() {
        document.querySelectorAll('input[required], textarea[required]').forEach(input => {
            const createFeedback = (type, message) => {
                let feedback = input.nextElementSibling;
                if (!feedback || !feedback.classList.contains('form-feedback')) {
                    feedback = document.createElement('div');
                    feedback.className = 'form-feedback';
                    feedback.style.cssText = `
                        font-size: 13px;
                        margin-top: 4px;
                        animation: slideDown 0.2s ease;
                    `;
                    input.parentElement.appendChild(feedback);
                }

                feedback.style.color = type === 'error' ? 'var(--danger, #ef4444)' : 'var(--success, #10b981)';
                feedback.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${message}`;
                
                // Анимация поля
                input.style.borderColor = type === 'error' ? 'var(--danger, #ef4444)' : 'var(--success, #10b981)';
                input.style.animation = type === 'error' ? 'shake 0.3s ease' : 'none';
            };

            input.addEventListener('blur', () => {
                if (!input.value && input.required) {
                    createFeedback('error', 'Это поле обязательно для заполнения');
                } else if (input.type === 'email' && input.value && !input.value.includes('@')) {
                    createFeedback('error', 'Введите корректный email');
                } else if (input.value) {
                    createFeedback('success', 'Отлично!');
                }
            });

            input.addEventListener('input', () => {
                if (input.value) {
                    const feedback = input.nextElementSibling;
                    if (feedback && feedback.classList.contains('form-feedback')) {
                        feedback.remove();
                    }
                    input.style.borderColor = '';
                }
            });
        });

        // CSS для shake анимации
        if (!document.getElementById('shake-animation')) {
            const style = document.createElement('style');
            style.id = 'shake-animation';
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);
        }
    }
}

// Инициализация при загрузке
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.microInteractions = new MicroInteractions();
    });
} else {
    window.microInteractions = new MicroInteractions();
}

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MicroInteractions;
}
