/**
 * Task Automation
 * Автоматизация задач и умные подсказки
 */

class TaskAutomation {
    constructor() {
        this.rules = [];
        this.suggestions = [];
        this.patterns = new Map();
        this.init();
    }

    init() {
        this.loadRules();
        this.setupEventListeners();
        this.analyzePatterns();
        this.startSuggestionEngine();
    }

    /**
     * Загрузить правила автоматизации
     */
    async loadRules() {
        try {
            const response = await fetch('/api/automation/rules');
            if (response.ok) {
                this.rules = await response.json();
                this.applyRules();
            }
        } catch (error) {
            console.error('Failed to load automation rules:', error);
        }
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Автозаполнение при создании задачи
        document.addEventListener('DOMContentLoaded', () => {
            const taskForm = document.querySelector('form[name="task"]');
            if (taskForm) {
                this.enhanceTaskForm(taskForm);
            }
        });

        // Умные подсказки при вводе
        document.addEventListener('input', (e) => {
            if (e.target.matches('[name*="title"], [name*="description"]')) {
                this.provideSuggestions(e.target);
            }
        });
    }

    /**
     * Улучшить форму задачи
     */
    enhanceTaskForm(form) {
        const titleInput = form.querySelector('[name*="title"]');
        const descriptionInput = form.querySelector('[name*="description"]');
        const prioritySelect = form.querySelector('[name*="priority"]');
        const categorySelect = form.querySelector('[name*="category"]');
        const dueDateInput = form.querySelector('[name*="dueDate"]');

        if (!titleInput) return;

        // Автоопределение приоритета по ключевым словам
        titleInput.addEventListener('blur', () => {
            const title = titleInput.value.toLowerCase();
            
            if (prioritySelect && !prioritySelect.value) {
                if (title.includes('срочно') || title.includes('важно') || title.includes('критично')) {
                    prioritySelect.value = 'high';
                    this.showNotification('Автоматически установлен высокий приоритет', 'info');
                } else if (title.includes('можно') || title.includes('когда-нибудь')) {
                    prioritySelect.value = 'low';
                }
            }

            // Автоопределение категории
            if (categorySelect && !categorySelect.value) {
                const category = this.detectCategory(title);
                if (category) {
                    categorySelect.value = category;
                    this.showNotification(`Автоматически выбрана категория: ${categorySelect.options[categorySelect.selectedIndex].text}`, 'info');
                }
            }

            // Автоопределение срока
            if (dueDateInput && !dueDateInput.value) {
                const dueDate = this.detectDueDate(title);
                if (dueDate) {
                    dueDateInput.value = dueDate;
                    this.showNotification('Автоматически установлен срок выполнения', 'info');
                }
            }
        });

        // Шаблоны задач
        this.addTemplateSelector(form);

        // Дублирование задач
        this.addDuplicateDetection(titleInput);
    }

    /**
     * Определить категорию по заголовку
     */
    detectCategory(title) {
        const categories = {
            'bug': ['баг', 'ошибка', 'исправить', 'не работает'],
            'feature': ['добавить', 'новый', 'функция', 'возможность'],
            'improvement': ['улучшить', 'оптимизировать', 'ускорить'],
            'documentation': ['документация', 'описание', 'инструкция'],
            'meeting': ['встреча', 'созвон', 'обсуждение', 'митинг']
        };

        for (const [category, keywords] of Object.entries(categories)) {
            if (keywords.some(keyword => title.includes(keyword))) {
                // Найти ID категории по имени
                const categorySelect = document.querySelector('[name*="category"]');
                if (categorySelect) {
                    for (const option of categorySelect.options) {
                        if (option.text.toLowerCase().includes(category)) {
                            return option.value;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Определить срок выполнения
     */
    detectDueDate(title) {
        const today = new Date();
        
        // Сегодня
        if (title.includes('сегодня')) {
            return this.formatDate(today);
        }

        // Завтра
        if (title.includes('завтра')) {
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            return this.formatDate(tomorrow);
        }

        // Через неделю
        if (title.includes('неделю')) {
            const nextWeek = new Date(today);
            nextWeek.setDate(nextWeek.getDate() + 7);
            return this.formatDate(nextWeek);
        }

        // Через месяц
        if (title.includes('месяц')) {
            const nextMonth = new Date(today);
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            return this.formatDate(nextMonth);
        }

        return null;
    }

    /**
     * Форматировать дату
     */
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Добавить селектор шаблонов
     */
    addTemplateSelector(form) {
        const titleInput = form.querySelector('[name*="title"]');
        if (!titleInput) return;

        const templateBtn = document.createElement('button');
        templateBtn.type = 'button';
        templateBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
        templateBtn.innerHTML = '<i class="fas fa-file-alt me-1"></i>Шаблон';
        templateBtn.title = 'Использовать шаблон';

        titleInput.parentElement.appendChild(templateBtn);

        templateBtn.addEventListener('click', () => {
            this.showTemplateSelector(form);
        });
    }

    /**
     * Показать селектор шаблонов
     */
    async showTemplateSelector(form) {
        try {
            const response = await fetch('/api/task-templates');
            if (!response.ok) return;

            const templates = await response.json();

            if (templates.length === 0) {
                this.showNotification('Нет доступных шаблонов', 'info');
                return;
            }

            const modal = this.createTemplateModal(templates, form);
            document.body.appendChild(modal);
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }

    /**
     * Создать модальное окно шаблонов
     */
    createTemplateModal(templates, form) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Выберите шаблон</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            ${templates.map(template => `
                                <button type="button" class="list-group-item list-group-item-action" data-template-id="${template.id}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${template.name}</h6>
                                            <small class="text-muted">${template.description || ''}</small>
                                        </div>
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </button>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Обработчики
        modal.querySelectorAll('[data-template-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const template = templates.find(t => t.id == btn.dataset.templateId);
                this.applyTemplate(form, template);
                bootstrap.Modal.getInstance(modal).hide();
                modal.remove();
            });
        });

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        return modal;
    }

    /**
     * Применить шаблон
     */
    applyTemplate(form, template) {
        const titleInput = form.querySelector('[name*="title"]');
        const descriptionInput = form.querySelector('[name*="description"]');
        const prioritySelect = form.querySelector('[name*="priority"]');
        const categorySelect = form.querySelector('[name*="category"]');

        if (titleInput) titleInput.value = template.title || '';
        if (descriptionInput) descriptionInput.value = template.description || '';
        if (prioritySelect && template.priority) prioritySelect.value = template.priority;
        if (categorySelect && template.categoryId) categorySelect.value = template.categoryId;

        this.showNotification('Шаблон применен', 'success');
    }

    /**
     * Добавить обнаружение дубликатов
     */
    addDuplicateDetection(titleInput) {
        let checkTimeout;

        titleInput.addEventListener('input', () => {
            clearTimeout(checkTimeout);
            
            checkTimeout = setTimeout(async () => {
                const title = titleInput.value.trim();
                if (title.length < 3) return;

                const duplicates = await this.checkDuplicates(title);
                if (duplicates.length > 0) {
                    this.showDuplicateWarning(titleInput, duplicates);
                }
            }, 1000);
        });
    }

    /**
     * Проверить дубликаты
     */
    async checkDuplicates(title) {
        try {
            const response = await fetch(`/api/tasks/check-duplicates?title=${encodeURIComponent(title)}`);
            if (response.ok) {
                return await response.json();
            }
        } catch (error) {
            console.error('Failed to check duplicates:', error);
        }
        return [];
    }

    /**
     * Показать предупреждение о дубликатах
     */
    showDuplicateWarning(input, duplicates) {
        // Удалить старое предупреждение
        const oldWarning = input.parentElement.querySelector('.duplicate-warning');
        if (oldWarning) oldWarning.remove();

        const warning = document.createElement('div');
        warning.className = 'duplicate-warning alert alert-warning mt-2';
        warning.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            Найдены похожие задачи:
            <ul class="mb-0 mt-2">
                ${duplicates.slice(0, 3).map(task => `
                    <li>
                        <a href="/tasks/${task.id}" target="_blank">${task.title}</a>
                    </li>
                `).join('')}
            </ul>
        `;

        input.parentElement.appendChild(warning);
    }

    /**
     * Предоставить подсказки
     */
    async provideSuggestions(input) {
        const text = input.value;
        if (text.length < 5) return;

        // Анализ текста
        const suggestions = this.analyzText(text);
        
        if (suggestions.length > 0) {
            this.showSuggestions(input, suggestions);
        }
    }

    /**
     * Анализировать текст
     */
    analyzText(text) {
        const suggestions = [];

        // Проверка на отсутствие глаголов действия
        const actionVerbs = ['сделать', 'создать', 'исправить', 'добавить', 'удалить', 'обновить'];
        const hasActionVerb = actionVerbs.some(verb => text.toLowerCase().includes(verb));
        
        if (!hasActionVerb && text.length > 20) {
            suggestions.push({
                type: 'improvement',
                text: 'Рекомендуется начинать задачу с глагола действия (сделать, создать, исправить...)'
            });
        }

        // Проверка на слишком длинный заголовок
        if (text.length > 100) {
            suggestions.push({
                type: 'warning',
                text: 'Заголовок слишком длинный. Рекомендуется до 100 символов.'
            });
        }

        return suggestions;
    }

    /**
     * Показать подсказки
     */
    showSuggestions(input, suggestions) {
        // Удалить старые подсказки
        const oldSuggestions = input.parentElement.querySelector('.automation-suggestions');
        if (oldSuggestions) oldSuggestions.remove();

        const container = document.createElement('div');
        container.className = 'automation-suggestions mt-2';
        container.innerHTML = suggestions.map(s => `
            <div class="alert alert-${s.type === 'warning' ? 'warning' : 'info'} alert-sm mb-1">
                <i class="fas fa-lightbulb me-2"></i>
                ${s.text}
            </div>
        `).join('');

        input.parentElement.appendChild(container);

        // Автоудаление через 10 секунд
        setTimeout(() => container.remove(), 10000);
    }

    /**
     * Анализировать паттерны
     */
    async analyzePatterns() {
        try {
            const response = await fetch('/api/tasks/patterns');
            if (response.ok) {
                const patterns = await response.json();
                this.patterns = new Map(Object.entries(patterns));
            }
        } catch (error) {
            console.error('Failed to analyze patterns:', error);
        }
    }

    /**
     * Запустить движок подсказок
     */
    startSuggestionEngine() {
        // Проверять каждые 5 минут
        setInterval(() => {
            this.generateSmartSuggestions();
        }, 300000);
    }

    /**
     * Генерировать умные подсказки
     */
    async generateSmartSuggestions() {
        try {
            const response = await fetch('/api/automation/suggestions');
            if (response.ok) {
                const suggestions = await response.json();
                
                if (suggestions.length > 0) {
                    this.showSmartSuggestion(suggestions[0]);
                }
            }
        } catch (error) {
            console.error('Failed to generate suggestions:', error);
        }
    }

    /**
     * Показать умную подсказку
     */
    showSmartSuggestion(suggestion) {
        if (typeof window.showToast === 'function') {
            window.showToast(suggestion.text, 'info');
        }
    }

    /**
     * Применить правила
     */
    applyRules() {
        this.rules.forEach(rule => {
            if (rule.enabled) {
                this.executeRule(rule);
            }
        });
    }

    /**
     * Выполнить правило
     */
    executeRule(rule) {
        // Логика выполнения правил автоматизации
        console.log('Executing rule:', rule.name);
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.taskAutomation = new TaskAutomation();
    });
} else {
    window.taskAutomation = new TaskAutomation();
}

// Экспорт
window.TaskAutomation = TaskAutomation;
