/**
 * Task History Widget
 * Виджет истории изменений задачи
 */

class TaskHistoryWidget {
    constructor(taskId, containerId) {
        this.taskId = taskId;
        this.container = document.getElementById(containerId);
        this.history = [];
        this.loading = false;
        
        if (this.container) {
            this.init();
        }
    }

    async init() {
        this.createWidget();
        await this.loadHistory();
        this.render();
    }

    createWidget() {
        this.container.innerHTML = `
            <div class="task-history-widget">
                <div class="widget-header">
                    <h5 class="widget-title">
                        <i class="fas fa-history"></i>
                        История изменений
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" id="refreshHistoryBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="widget-body" id="historyContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                </div>
                <div class="widget-footer">
                    <a href="/task-history/task/${this.taskId}" class="btn btn-sm btn-link">
                        Показать всю историю
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        `;

        // Обработчик обновления
        document.getElementById('refreshHistoryBtn')?.addEventListener('click', () => {
            this.loadHistory();
        });

        this.addStyles();
    }

    async loadHistory() {
        if (this.loading) return;

        this.loading = true;
        const refreshBtn = document.getElementById('refreshHistoryBtn');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const response = await fetch(`/task-history/api/task/${this.taskId}`);
            if (!response.ok) throw new Error('Failed to load history');
            
            this.history = await response.json();
            this.render();
        } catch (error) {
            console.error('Error loading task history:', error);
            this.renderError();
        } finally {
            this.loading = false;
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            }
        }
    }

    render() {
        const content = document.getElementById('historyContent');
        if (!content) return;

        if (this.history.length === 0) {
            content.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">История изменений пуста</p>
                </div>
            `;
            return;
        }

        // Показываем только последние 5 записей
        const recentHistory = this.history.slice(0, 5);

        content.innerHTML = `
            <div class="history-timeline">
                ${recentHistory.map(item => this.renderHistoryItem(item)).join('')}
            </div>
        `;
    }

    renderHistoryItem(item) {
        const icon = this.getActionIcon(item.action);
        const color = this.getActionColor(item.action);
        const timeAgo = this.getTimeAgo(item.createdAt);

        return `
            <div class="history-item">
                <div class="history-marker" style="background: ${color};">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="history-content">
                    <div class="history-header">
                        <strong>${item.user.name}</strong>
                        <span class="badge" style="background: ${color};">
                            ${this.getActionLabel(item.action)}
                        </span>
                    </div>
                    <div class="history-description">
                        ${item.description}
                    </div>
                    ${item.field ? this.renderChanges(item) : ''}
                    <div class="history-time">
                        <i class="fas fa-clock"></i>
                        ${timeAgo}
                    </div>
                </div>
            </div>
        `;
    }

    renderChanges(item) {
        if (!item.oldValue && !item.newValue) return '';

        return `
            <div class="history-changes">
                ${item.oldValue ? `
                    <div class="change-item">
                        <small class="text-muted">Было:</small>
                        <code>${this.escapeHtml(item.oldValue)}</code>
                    </div>
                ` : ''}
                ${item.newValue ? `
                    <div class="change-item">
                        <small class="text-muted">Стало:</small>
                        <code>${this.escapeHtml(item.newValue)}</code>
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderError() {
        const content = document.getElementById('historyContent');
        if (!content) return;

        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                Ошибка загрузки истории
            </div>
        `;
    }

    getActionIcon(action) {
        const icons = {
            'created': 'plus',
            'updated': 'edit',
            'deleted': 'trash',
            'status_changed': 'exchange-alt',
            'assigned': 'user-plus',
            'comment_added': 'comment'
        };
        return icons[action] || 'circle';
    }

    getActionColor(action) {
        const colors = {
            'created': '#28a745',
            'updated': '#007bff',
            'deleted': '#dc3545',
            'status_changed': '#ffc107',
            'assigned': '#17a2b8',
            'comment_added': '#6c757d'
        };
        return colors[action] || '#6c757d';
    }

    getActionLabel(action) {
        const labels = {
            'created': 'Создано',
            'updated': 'Изменено',
            'deleted': 'Удалено',
            'status_changed': 'Статус',
            'assigned': 'Назначено',
            'comment_added': 'Комментарий'
        };
        return labels[action] || action;
    }

    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'только что';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} мин назад`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} ч назад`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)} дн назад`;
        
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    addStyles() {
        if (document.getElementById('taskHistoryWidgetStyles')) return;

        const style = document.createElement('style');
        style.id = 'taskHistoryWidgetStyles';
        style.textContent = `
            .task-history-widget {
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                overflow: hidden;
            }

            .widget-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.25rem;
                border-bottom: 1px solid var(--border);
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            }

            .widget-title {
                margin: 0;
                font-size: 1rem;
                font-weight: 600;
                color: var(--text-primary);
            }

            .widget-body {
                padding: 1rem;
                max-height: 400px;
                overflow-y: auto;
            }

            .widget-footer {
                padding: 0.75rem 1.25rem;
                border-top: 1px solid var(--border);
                text-align: center;
            }

            .history-timeline {
                position: relative;
            }

            .history-item {
                position: relative;
                padding-left: 3rem;
                padding-bottom: 1.5rem;
            }

            .history-item:last-child {
                padding-bottom: 0;
            }

            .history-item::before {
                content: '';
                position: absolute;
                left: 15px;
                top: 30px;
                bottom: -10px;
                width: 2px;
                background: var(--border);
            }

            .history-item:last-child::before {
                display: none;
            }

            .history-marker {
                position: absolute;
                left: 0;
                top: 0;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.75rem;
                z-index: 1;
            }

            .history-content {
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 0.75rem;
            }

            .history-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
            }

            .history-description {
                color: var(--text-secondary);
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
            }

            .history-changes {
                display: flex;
                gap: 0.5rem;
                margin: 0.5rem 0;
            }

            .change-item {
                flex: 1;
                background: var(--bg-body);
                padding: 0.5rem;
                border-radius: 4px;
            }

            .change-item code {
                display: block;
                margin-top: 0.25rem;
                color: var(--text-primary);
                font-size: 0.8125rem;
            }

            .history-time {
                font-size: 0.75rem;
                color: var(--text-muted);
                margin-top: 0.5rem;
            }

            [data-theme='dark'] .widget-header {
                background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 51, 234, 0.1) 100%);
            }
        `;

        document.head.appendChild(style);
    }
}

// Автоматическая инициализация
document.addEventListener('DOMContentLoaded', function() {
    const historyContainer = document.getElementById('taskHistoryWidget');
    if (historyContainer && historyContainer.dataset.taskId) {
        window.taskHistoryWidget = new TaskHistoryWidget(
            historyContainer.dataset.taskId,
            'taskHistoryWidget'
        );
    }
});

// Экспорт
window.TaskHistoryWidget = TaskHistoryWidget;
