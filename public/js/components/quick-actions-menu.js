/**
 * Quick Actions Menu
 * Плавающее меню быстрых действий
 */

class QuickActionsMenu {
    constructor() {
        this.isOpen = false;
        this.menu = null;
        this.fab = null;
        this.init();
    }

    init() {
        this.createMenu();
        this.addStyles();
        this.bindEvents();
    }

    createMenu() {
        // Создаем FAB кнопку
        this.fab = document.createElement('button');
        this.fab.className = 'quick-actions-fab';
        this.fab.innerHTML = '<i class="fas fa-plus"></i>';
        this.fab.setAttribute('aria-label', 'Быстрые действия');
        this.fab.setAttribute('title', 'Быстрые действия (Q)');

        // Создаем меню
        this.menu = document.createElement('div');
        this.menu.className = 'quick-actions-menu';
        this.menu.innerHTML = `
            <div class="quick-action-item" data-action="new-task">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Новая задача</div>
                    <div class="quick-action-subtitle">Создать задачу</div>
                </div>
            </div>
            
            <div class="quick-action-item" data-action="new-project">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-folder-plus"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Новый проект</div>
                    <div class="quick-action-subtitle">Создать проект</div>
                </div>
            </div>
            
            <div class="quick-action-item" data-action="new-team">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Новая команда</div>
                    <div class="quick-action-subtitle">Создать команду</div>
                </div>
            </div>
            
            <div class="quick-action-item" data-action="search">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-search"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Поиск</div>
                    <div class="quick-action-subtitle">Найти задачи</div>
                </div>
            </div>
            
            <div class="quick-action-item" data-action="notifications">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Уведомления</div>
                    <div class="quick-action-subtitle">Просмотр</div>
                </div>
            </div>
        `;

        // Добавляем на страницу
        document.body.appendChild(this.fab);
        document.body.appendChild(this.menu);
    }

    bindEvents() {
        // Клик по FAB
        this.fab.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Клик по пунктам меню
        this.menu.querySelectorAll('.quick-action-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const action = item.dataset.action;
                this.handleAction(action);
                this.close();
            });
        });

        // Закрытие при клике вне меню
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.menu.contains(e.target) && e.target !== this.fab) {
                this.close();
            }
        });

        // Горячая клавиша Q
        document.addEventListener('keydown', (e) => {
            if (e.key === 'q' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                // Игнорируем если фокус в input/textarea
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                e.preventDefault();
                this.toggle();
            }
        });

        // ESC для закрытия
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.fab.classList.add('active');
        this.menu.classList.add('show');
        
        // Анимация появления пунктов
        const items = this.menu.querySelectorAll('.quick-action-item');
        items.forEach((item, index) => {
            setTimeout(() => {
                item.style.animation = `slideInUp 0.3s ease-out ${index * 0.05}s both`;
            }, 0);
        });
    }

    close() {
        this.isOpen = false;
        this.fab.classList.remove('active');
        this.menu.classList.remove('show');
    }

    handleAction(action) {
        switch(action) {
            case 'new-task':
                // Открыть модальное окно создания задачи
                const quickTaskBtn = document.getElementById('quick-task-fab');
                if (quickTaskBtn) {
                    quickTaskBtn.click();
                } else {
                    window.location.href = '/task/new';
                }
                break;
                
            case 'new-project':
                window.location.href = '/project/new';
                break;
                
            case 'new-team':
                window.location.href = '/team/new';
                break;
                
            case 'search':
                // Фокус на поиск
                const searchInput = document.querySelector('input[type="search"], #search');
                if (searchInput) {
                    searchInput.focus();
                } else {
                    window.location.href = '/search';
                }
                break;
                
            case 'notifications':
                window.location.href = '/notifications';
                break;
        }
    }

    addStyles() {
        if (document.getElementById('quickActionsMenuStyles')) return;

        const style = document.createElement('style');
        style.id = 'quickActionsMenuStyles';
        style.textContent = `
            .quick-actions-fab {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                z-index: 1000;
                transition: all 0.3s ease;
            }

            .quick-actions-fab:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            }

            .quick-actions-fab.active {
                transform: rotate(45deg);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }

            .quick-actions-fab i {
                transition: transform 0.3s ease;
            }

            .quick-actions-menu {
                position: fixed;
                bottom: 100px;
                right: 30px;
                width: 280px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                padding: 0.5rem;
                z-index: 999;
                opacity: 0;
                visibility: hidden;
                transform: translateY(20px);
                transition: all 0.3s ease;
            }

            .quick-actions-menu.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .quick-action-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 0.875rem;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
                margin-bottom: 0.25rem;
            }

            .quick-action-item:last-child {
                margin-bottom: 0;
            }

            .quick-action-item:hover {
                background: var(--bg-body);
                transform: translateX(-4px);
            }

            .quick-action-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.125rem;
                flex-shrink: 0;
            }

            .quick-action-content {
                flex: 1;
            }

            .quick-action-title {
                font-size: 0.9375rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 0.125rem;
            }

            .quick-action-subtitle {
                font-size: 0.75rem;
                color: var(--text-secondary);
            }

            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @media (max-width: 768px) {
                .quick-actions-fab {
                    bottom: 20px;
                    right: 20px;
                    width: 56px;
                    height: 56px;
                    font-size: 1.25rem;
                }

                .quick-actions-menu {
                    bottom: 85px;
                    right: 20px;
                    width: calc(100vw - 40px);
                    max-width: 320px;
                }
            }

            [data-theme='dark'] .quick-actions-menu {
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, не существует ли уже FAB кнопка для задач
    const existingFab = document.getElementById('quick-task-fab');
    
    // Если есть старая кнопка, скрываем её
    if (existingFab) {
        existingFab.style.display = 'none';
    }
    
    window.quickActionsMenu = new QuickActionsMenu();
});

// Экспорт
window.QuickActionsMenu = QuickActionsMenu;
