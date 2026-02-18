/**
 * Task Templates
 * Шаблоны задач для быстрого создания
 */

class TaskTemplates {
    constructor() {
        this.templates = [];
        this.modal = null;
        this.init();
    }

    init() {
        this.loadTemplates();
        this.registerDefaultTemplates();
        this.createButton();
    }

    registerDefaultTemplates() {
        const defaults = [
            {
                id: 'bug-report',
                name: 'Отчет об ошибке',
                icon: 'fa-bug',
                color: '#dc3545',
                template: {
                    title: 'Ошибка: ',
                    description: '**Описание проблемы:**\n\n**Шаги для воспроизведения:**\n1. \n2. \n3. \n\n**Ожидаемое поведение:**\n\n**Фактическое поведение:**\n\n**Окружение:**\n- Браузер: \n- ОС: ',
                    priority: 'high',
                    tags: ['bug', 'urgent']
                }
            },
            {
                id: 'feature-request',
                name: 'Запрос функции',
                icon: 'fa-lightbulb',
                color: '#ffc107',
                template: {
                    title: 'Функция: ',
                    description: '**Описание функции:**\n\n**Зачем это нужно:**\n\n**Предлагаемое решение:**\n\n**Альтернативы:**',
                    priority: 'medium',
                    tags: ['feature', 'enhancement']
                }
            },
            {
                id: 'meeting',
                name: 'Встреча',
                icon: 'fa-users',
                color: '#17a2b8',
                template: {
                    title: 'Встреча: ',
                    description: '**Повестка дня:**\n- \n\n**Участники:**\n- \n\n**Дата и время:**\n\n**Место:**',
                    priority: 'medium',
                    tags: ['meeting']
                }
            },
            {
                id: 'code-review',
                name: 'Ревью кода',
                icon: 'fa-code',
                color: '#6f42c1',
                template: {
                    title: 'Ревью: ',
                    description: '**PR/MR:**\n\n**Что проверить:**\n- [ ] Код соответствует стандартам\n- [ ] Тесты написаны\n- [ ] Документация обновлена\n- [ ] Нет конфликтов\n\n**Комментарии:**',
                    priority: 'high',
                    tags: ['code-review', 'development']
                }
            },
            {
                id: 'documentation',
                name: 'Документация',
                icon: 'fa-book',
                color: '#28a745',
                template: {
                    title: 'Документация: ',
                    description: '**Что документировать:**\n\n**Целевая аудитория:**\n\n**Структура:**\n1. \n2. \n3. \n\n**Примеры:**',
                    priority: 'low',
                    tags: ['documentation']
                }
            },
            {
                id: 'deployment',
                name: 'Деплой',
                icon: 'fa-rocket',
                color: '#fd7e14',
                template: {
                    title: 'Деплой: ',
                    description: '**Версия:**\n\n**Изменения:**\n- \n\n**Чеклист:**\n- [ ] Тесты пройдены\n- [ ] База данных обновлена\n- [ ] Конфигурация проверена\n- [ ] Бэкап создан\n\n**Откат:**',
                    priority: 'high',
                    tags: ['deployment', 'production']
                }
            }
        ];

        // Добавляем только если нет сохраненных
        if (this.templates.length === 0) {
            this.templates = defaults;
            this.saveTemplates();
        }
    }

    createButton() {
        // Добавляем кнопку в меню быстрых действий
        const quickTaskModal = document.getElementById('quickTaskModal');
        if (quickTaskModal) {
            const modalBody = quickTaskModal.querySelector('.modal-body');
            if (modalBody) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-primary mb-3';
                button.innerHTML = '<i class="fas fa-file-alt"></i> Использовать шаблон';
                button.addEventListener('click', () => this.showTemplates());
                
                modalBody.insertBefore(button, modalBody.firstChild);
            }
        }

        this.addStyles();
    }

    showTemplates() {
        if (this.modal) {
            this.modal.remove();
        }

        this.modal = document.createElement('div');
        this.modal.className = 'task-templates-modal';
        this.modal.innerHTML = `
            <div class="task-templates-content">
                <div class="task-templates-header">
                    <h4><i class="fas fa-file-alt"></i> Выберите шаблон</h4>
                    <button class="task-templates-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="task-templates-body">
                    ${this.templates.map(t => this.renderTemplate(t)).join('')}
                </div>
                <div class="task-templates-footer">
                    <button class="btn btn-sm btn-secondary" id="manageTemplates">
                        <i class="fas fa-cog"></i> Управление шаблонами
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(this.modal);

        // Обработчики
        this.modal.querySelector('.task-templates-close').addEventListener('click', () => {
            this.modal.remove();
        });

        this.modal.querySelectorAll('.task-template-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.applyTemplate(this.templates[index]);
                this.modal.remove();
            });
        });

        this.modal.querySelector('#manageTemplates').addEventListener('click', () => {
            this.showManageTemplates();
        });

        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.modal.remove();
            }
        });
    }

    renderTemplate(template) {
        return `
            <div class="task-template-item">
                <div class="task-template-icon" style="background: ${template.color}">
                    <i class="fas ${template.icon}"></i>
                </div>
                <div class="task-template-info">
                    <h5>${template.name}</h5>
                    <p>${template.template.title}</p>
                </div>
            </div>
        `;
    }

    applyTemplate(template) {
        // Заполняем форму быстрого создания задачи
        const titleInput = document.getElementById('quick-task-title');
        const descInput = document.getElementById('quick-task-description');
        const prioritySelect = document.getElementById('quick-task-priority');

        if (titleInput) titleInput.value = template.template.title;
        if (descInput) descInput.value = template.template.description;
        if (prioritySelect) prioritySelect.value = template.template.priority;

        if (window.showToast) {
            window.showToast(`Шаблон "${template.name}" применен`, 'success');
        }
    }

    showManageTemplates() {
        // Показываем интерфейс управления шаблонами
        if (window.showToast) {
            window.showToast('Управление шаблонами в разработке', 'info');
        }
    }

    saveTemplates() {
        try {
            localStorage.setItem('taskTemplates', JSON.stringify(this.templates));
        } catch (e) {
            console.error('Failed to save templates:', e);
        }
    }

    loadTemplates() {
        try {
            const saved = localStorage.getItem('taskTemplates');
            if (saved) {
                this.templates = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load templates:', e);
            this.templates = [];
        }
    }

    addStyles() {
        if (document.getElementById('taskTemplatesStyles')) return;

        const style = document.createElement('style');
        style.id = 'taskTemplatesStyles';
        style.textContent = `
            .task-templates-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10008;
                animation: fadeIn 0.2s ease;
            }

            .task-templates-content {
                background: var(--bg-card);
                border-radius: 12px;
                width: 90%;
                max-width: 700px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .task-templates-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .task-templates-header h4 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-primary);
            }

            .task-templates-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.25rem;
                padding: 4px;
            }

            .task-templates-body {
                padding: 1.5rem;
                overflow-y: auto;
                flex: 1;
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1rem;
            }

            .task-template-item {
                display: flex;
                gap: 1rem;
                padding: 1rem;
                background: var(--bg-body);
                border: 2px solid transparent;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .task-template-item:hover {
                border-color: var(--primary);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .task-template-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.25rem;
                flex-shrink: 0;
            }

            .task-template-info h5 {
                margin: 0 0 0.25rem 0;
                font-size: 1rem;
                color: var(--text-primary);
            }

            .task-template-info p {
                margin: 0;
                font-size: 0.875rem;
                color: var(--text-secondary);
            }

            .task-templates-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid var(--border);
                display: flex;
                justify-content: flex-end;
            }

            @media (max-width: 768px) {
                .task-templates-body {
                    grid-template-columns: 1fr;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.taskTemplates = new TaskTemplates();
});

window.TaskTemplates = TaskTemplates;
