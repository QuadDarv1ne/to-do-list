/**
 * Smart Assistant
 * Умный ассистент с рекомендациями и подсказками
 */

class SmartAssistant {
    constructor() {
        this.suggestions = [];
        this.context = {};
        this.isVisible = false;
        this.init();
    }

    init() {
        this.createAssistantUI();
        this.loadContext();
        this.startAnalysis();
        this.setupEventListeners();
    }

    /**
     * Создать UI ассистента
     */
    createAssistantUI() {
        if (document.getElementById('smart-assistant')) return;

        const assistant = document.createElement('div');
        assistant.id = 'smart-assistant';
        assistant.className = 'smart-assistant';
        assistant.innerHTML = `
            <button class="assistant-toggle" id="assistant-toggle" title="Умный ассистент">
                <i class="fas fa-robot"></i>
                <span class="assistant-badge" id="assistant-badge">0</span>
            </button>
            <div class="assistant-panel" id="assistant-panel">
                <div class="assistant-header">
                    <div class="assistant-title">
                        <i class="fas fa-robot me-2"></i>
                        Умный ассистент
                    </div>
                    <button class="assistant-close" id="assistant-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="assistant-body">
                    <div class="assistant-greeting">
                        <p>Привет! Я ваш умный ассистент. Я анализирую вашу работу и даю полезные рекомендации.</p>
                    </div>
                    <div class="assistant-suggestions" id="assistant-suggestions"></div>
                    <div class="assistant-insights" id="assistant-insights"></div>
                </div>
                <div class="assistant-footer">
                    <button class="btn btn-sm btn-outline-primary" id="refresh-suggestions">
                        <i class="fas fa-sync-alt me-1"></i>
                        Обновить
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(assistant);
        this.addAssistantStyles();
    }

    /**
     * Добавить стили ассистента
     */
    addAssistantStyles() {
        if (document.getElementById('assistantStyles')) return;

        const style = document.createElement('style');
        style.id = 'assistantStyles';
        style.textContent = `
            .smart-assistant {
                position: fixed;
                bottom: 90px;
                right: 20px;
                z-index: 1000;
            }

            .assistant-toggle {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
                position: relative;
            }

            .assistant-toggle:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6);
            }

            .assistant-toggle:active {
                transform: scale(0.95);
            }

            .assistant-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #dc3545;
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                font-weight: 600;
                border: 2px solid white;
            }

            .assistant-badge:empty {
                display: none;
            }

            .assistant-panel {
                position: absolute;
                bottom: 70px;
                right: 0;
                width: 400px;
                max-width: 90vw;
                max-height: 600px;
                background: var(--bg-card);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                display: none;
                flex-direction: column;
                animation: slideUp 0.3s ease;
            }

            .assistant-panel.show {
                display: flex;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .assistant-header {
                padding: 1rem;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 12px 12px 0 0;
            }

            .assistant-title {
                font-weight: 600;
                display: flex;
                align-items: center;
            }

            .assistant-close {
                background: transparent;
                border: none;
                color: white;
                font-size: 1.25rem;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                transition: background 0.2s ease;
            }

            .assistant-close:hover {
                background: rgba(255,255,255,0.2);
            }

            .assistant-body {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }

            .assistant-greeting {
                padding: 1rem;
                background: var(--bg-body);
                border-radius: 8px;
                margin-bottom: 1rem;
                font-size: 0.875rem;
                color: var(--text-secondary);
            }

            .assistant-suggestions {
                margin-bottom: 1rem;
            }

            .suggestion-item {
                padding: 1rem;
                background: var(--bg-body);
                border-left: 3px solid var(--primary);
                border-radius: 8px;
                margin-bottom: 0.75rem;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .suggestion-item:hover {
                transform: translateX(4px);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .suggestion-item.priority-high {
                border-left-color: #dc3545;
            }

            .suggestion-item.priority-medium {
                border-left-color: #ffc107;
            }

            .suggestion-item.priority-low {
                border-left-color: #28a745;
            }

            .suggestion-header {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .suggestion-icon {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(102, 126, 234, 0.1);
                color: var(--primary);
            }

            .suggestion-title {
                flex: 1;
                font-weight: 600;
                color: var(--text-primary);
            }

            .suggestion-text {
                font-size: 0.875rem;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
            }

            .suggestion-actions {
                display: flex;
                gap: 0.5rem;
            }

            .suggestion-actions .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.75rem;
            }

            .assistant-insights {
                padding: 1rem;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
                border-radius: 8px;
            }

            .insight-title {
                font-weight: 600;
                margin-bottom: 0.75rem;
                color: var(--text-primary);
            }

            .insight-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 0;
                font-size: 0.875rem;
            }

            .insight-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--primary);
                color: white;
                font-size: 0.75rem;
            }

            .assistant-footer {
                padding: 1rem;
                border-top: 1px solid var(--border);
                display: flex;
                justify-content: center;
            }

            @media (max-width: 768px) {
                .smart-assistant {
                    bottom: 80px;
                    right: 10px;
                }

                .assistant-toggle {
                    width: 50px;
                    height: 50px;
                    font-size: 1.25rem;
                }

                .assistant-panel {
                    width: calc(100vw - 20px);
                    max-height: 500px;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Переключение панели
        document.getElementById('assistant-toggle')?.addEventListener('click', () => {
            this.togglePanel();
        });

        // Закрытие панели
        document.getElementById('assistant-close')?.addEventListener('click', () => {
            this.closePanel();
        });

        // Обновление рекомендаций
        document.getElementById('refresh-suggestions')?.addEventListener('click', () => {
            this.refreshSuggestions();
        });

        // Закрытие при клике вне панели
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('assistant-panel');
            const toggle = document.getElementById('assistant-toggle');
            
            if (this.isVisible && 
                !panel?.contains(e.target) && 
                !toggle?.contains(e.target)) {
                this.closePanel();
            }
        });
    }

    /**
     * Загрузить контекст
     */
    async loadContext() {
        try {
            const response = await fetch('/api/assistant/context');
            if (response.ok) {
                this.context = await response.json();
                this.analyzContext();
            }
        } catch (error) {
            console.error('Failed to load context:', error);
        }
    }

    /**
     * Начать анализ
     */
    startAnalysis() {
        // Анализировать каждые 5 минут
        setInterval(() => {
            this.loadContext();
            this.generateSuggestions();
        }, 300000);

        // Первоначальный анализ
        this.generateSuggestions();
    }

    /**
     * Анализировать контекст
     */
    analyzContext() {
        // Анализ паттернов работы
        this.analyzeWorkPatterns();
        
        // Анализ производительности
        this.analyzeProductivity();
        
        // Анализ задач
        this.analyzeTasks();
    }

    /**
     * Анализировать паттерны работы
     */
    analyzeWorkPatterns() {
        if (!this.context.workPatterns) return;

        const patterns = this.context.workPatterns;
        
        // Определить наиболее продуктивное время
        if (patterns.mostProductiveHour) {
            this.addInsight({
                icon: 'clock',
                text: `Ваше самое продуктивное время: ${patterns.mostProductiveHour}:00`
            });
        }

        // Определить предпочитаемые категории
        if (patterns.favoriteCategory) {
            this.addInsight({
                icon: 'folder',
                text: `Чаще всего работаете с: ${patterns.favoriteCategory}`
            });
        }
    }

    /**
     * Анализировать производительность
     */
    analyzeProductivity() {
        if (!this.context.productivity) return;

        const prod = this.context.productivity;
        
        // Сравнение с прошлой неделей
        if (prod.weeklyChange) {
            const change = prod.weeklyChange;
            const icon = change > 0 ? 'arrow-up' : 'arrow-down';
            const color = change > 0 ? 'success' : 'warning';
            
            this.addInsight({
                icon: icon,
                text: `Производительность ${change > 0 ? 'выросла' : 'снизилась'} на ${Math.abs(change)}%`,
                color: color
            });
        }
    }

    /**
     * Анализировать задачи
     */
    analyzeTasks() {
        if (!this.context.tasks) return;

        const tasks = this.context.tasks;
        
        // Просроченные задачи
        if (tasks.overdue > 0) {
            this.addSuggestion({
                priority: 'high',
                icon: 'exclamation-triangle',
                title: 'Просроченные задачи',
                text: `У вас ${tasks.overdue} просроченных задач. Рекомендую пересмотреть приоритеты.`,
                actions: [
                    { label: 'Показать', action: () => this.showOverdueTasks() }
                ]
            });
        }

        // Задачи без категории
        if (tasks.uncategorized > 5) {
            this.addSuggestion({
                priority: 'medium',
                icon: 'folder-open',
                title: 'Организация задач',
                text: `${tasks.uncategorized} задач без категории. Организуйте их для лучшего контроля.`,
                actions: [
                    { label: 'Организовать', action: () => this.organizeUncategorized() }
                ]
            });
        }

        // Задачи с высоким приоритетом
        if (tasks.highPriority > 10) {
            this.addSuggestion({
                priority: 'medium',
                icon: 'flag',
                title: 'Слишком много приоритетов',
                text: `${tasks.highPriority} задач с высоким приоритетом. Возможно, стоит пересмотреть приоритеты.`,
                actions: [
                    { label: 'Пересмотреть', action: () => this.reviewPriorities() }
                ]
            });
        }
    }

    /**
     * Генерировать рекомендации
     */
    async generateSuggestions() {
        try {
            const response = await fetch('/api/assistant/suggestions');
            if (response.ok) {
                const suggestions = await response.json();
                suggestions.forEach(s => this.addSuggestion(s));
            }
        } catch (error) {
            console.error('Failed to generate suggestions:', error);
        }
    }

    /**
     * Добавить рекомендацию
     */
    addSuggestion(suggestion) {
        this.suggestions.push(suggestion);
        this.renderSuggestions();
        this.updateBadge();
    }

    /**
     * Добавить инсайт
     */
    addInsight(insight) {
        const container = document.getElementById('assistant-insights');
        if (!container) return;

        const item = document.createElement('div');
        item.className = 'insight-item';
        item.innerHTML = `
            <div class="insight-icon">
                <i class="fas fa-${insight.icon}"></i>
            </div>
            <div class="insight-text">${insight.text}</div>
        `;

        container.appendChild(item);
    }

    /**
     * Отрисовать рекомендации
     */
    renderSuggestions() {
        const container = document.getElementById('assistant-suggestions');
        if (!container) return;

        if (this.suggestions.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">Нет новых рекомендаций</p>';
            return;
        }

        container.innerHTML = this.suggestions.map(s => this.createSuggestionHTML(s)).join('');

        // Добавить обработчики
        container.querySelectorAll('.suggestion-item').forEach((item, index) => {
            const suggestion = this.suggestions[index];
            
            item.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const actionIndex = parseInt(btn.dataset.action);
                    suggestion.actions[actionIndex].action();
                    this.dismissSuggestion(index);
                });
            });

            // Клик по всей карточке для dismiss
            item.addEventListener('click', () => {
                this.dismissSuggestion(index);
            });
        });
    }

    /**
     * Создать HTML рекомендации
     */
    createSuggestionHTML(suggestion) {
        return `
            <div class="suggestion-item priority-${suggestion.priority}">
                <div class="suggestion-header">
                    <div class="suggestion-icon">
                        <i class="fas fa-${suggestion.icon}"></i>
                    </div>
                    <div class="suggestion-title">${suggestion.title}</div>
                </div>
                <div class="suggestion-text">${suggestion.text}</div>
                ${suggestion.actions ? `
                    <div class="suggestion-actions">
                        ${suggestion.actions.map((action, index) => `
                            <button class="btn btn-sm btn-primary" data-action="${index}">
                                ${action.label}
                            </button>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }

    /**
     * Отклонить рекомендацию
     */
    dismissSuggestion(index) {
        this.suggestions.splice(index, 1);
        this.renderSuggestions();
        this.updateBadge();
    }

    /**
     * Обновить бейдж
     */
    updateBadge() {
        const badge = document.getElementById('assistant-badge');
        if (badge) {
            badge.textContent = this.suggestions.length;
        }
    }

    /**
     * Переключить панель
     */
    togglePanel() {
        const panel = document.getElementById('assistant-panel');
        if (panel) {
            this.isVisible = !this.isVisible;
            panel.classList.toggle('show');
        }
    }

    /**
     * Закрыть панель
     */
    closePanel() {
        const panel = document.getElementById('assistant-panel');
        if (panel) {
            this.isVisible = false;
            panel.classList.remove('show');
        }
    }

    /**
     * Обновить рекомендации
     */
    async refreshSuggestions() {
        this.suggestions = [];
        this.renderSuggestions();
        await this.loadContext();
        await this.generateSuggestions();
        this.showNotification('Рекомендации обновлены', 'success');
    }

    /**
     * Показать просроченные задачи
     */
    showOverdueTasks() {
        window.location.href = '/tasks?overdue=1';
    }

    /**
     * Организовать задачи без категории
     */
    organizeUncategorized() {
        window.location.href = '/tasks?category=uncategorized';
    }

    /**
     * Пересмотреть приоритеты
     */
    reviewPriorities() {
        window.location.href = '/tasks?priority=high';
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
        window.smartAssistant = new SmartAssistant();
    });
} else {
    window.smartAssistant = new SmartAssistant();
}

// Экспорт
window.SmartAssistant = SmartAssistant;
