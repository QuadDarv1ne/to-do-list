/**
 * Auto Save
 * Автоматическое сохранение форм
 */

class AutoSave {
    constructor() {
        this.forms = new Map();
        this.saveDelay = 2000; // 2 секунды
        this.init();
    }

    init() {
        this.discoverForms();
        this.setupEventListeners();
    }

    /**
     * Найти формы с автосохранением
     */
    discoverForms() {
        const forms = document.querySelectorAll('[data-auto-save]');
        
        forms.forEach(form => {
            const formId = form.id || `form-${Date.now()}`;
            if (!form.id) form.id = formId;

            const config = {
                element: form,
                id: formId,
                saveUrl: form.dataset.autoSaveUrl || form.action,
                saveDelay: parseInt(form.dataset.autoSaveDelay) || this.saveDelay,
                timer: null,
                lastSaved: null,
                isDirty: false,
                savedData: this.loadDraft(formId)
            };

            this.forms.set(formId, config);
            this.setupForm(config);
        });
    }

    /**
     * Настроить форму
     */
    setupForm(config) {
        const form = config.element;

        // Восстановить сохраненные данные
        if (config.savedData) {
            this.restoreFormData(form, config.savedData);
            this.showRestoreNotification(form, config);
        }

        // Добавить индикатор сохранения
        this.addSaveIndicator(form);

        // Отслеживать изменения
        form.addEventListener('input', (e) => {
            this.handleInput(config, e);
        });

        form.addEventListener('change', (e) => {
            this.handleInput(config, e);
        });

        // Перехватить отправку формы
        form.addEventListener('submit', (e) => {
            this.handleSubmit(config, e);
        });
    }

    /**
     * Добавить индикатор сохранения
     */
    addSaveIndicator(form) {
        const indicator = document.createElement('div');
        indicator.className = 'auto-save-indicator';
        indicator.innerHTML = `
            <span class="auto-save-status">
                <i class="fas fa-circle"></i>
                <span class="auto-save-text">Все изменения сохранены</span>
            </span>
            <span class="auto-save-time"></span>
        `;

        // Вставляем перед кнопками формы или в конец
        const buttons = form.querySelector('.form-actions, .modal-footer, [type="submit"]');
        if (buttons) {
            buttons.parentNode.insertBefore(indicator, buttons);
        } else {
            form.appendChild(indicator);
        }

        this.addAutoSaveStyles();
    }

    /**
     * Добавить стили
     */
    addAutoSaveStyles() {
        if (document.getElementById('autoSaveStyles')) return;

        const style = document.createElement('style');
        style.id = 'autoSaveStyles';
        style.textContent = `
            .auto-save-indicator {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.75rem;
                margin: 1rem 0;
                background: var(--bg-body);
                border-radius: var(--radius);
                font-size: 0.875rem;
            }

            .auto-save-status {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .auto-save-status i {
                font-size: 0.5rem;
                color: var(--success);
                animation: pulse 2s infinite;
            }

            .auto-save-indicator.saving .auto-save-status i {
                color: var(--warning);
                animation: spin 1s linear infinite;
            }

            .auto-save-indicator.error .auto-save-status i {
                color: var(--danger);
                animation: none;
            }

            .auto-save-text {
                color: var(--text-secondary);
            }

            .auto-save-time {
                color: var(--text-muted);
                font-size: 0.75rem;
            }

            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }
                to {
                    transform: rotate(360deg);
                }
            }

            .draft-restore-banner {
                position: fixed;
                top: 70px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--info);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: var(--radius-lg);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                z-index: 9997;
                display: flex;
                align-items: center;
                gap: 1rem;
                animation: slideDown 0.3s ease-out;
            }

            .draft-restore-banner-content {
                flex: 1;
            }

            .draft-restore-banner-actions {
                display: flex;
                gap: 0.5rem;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Обработать ввод
     */
    handleInput(config, event) {
        config.isDirty = true;
        this.updateIndicator(config.element, 'unsaved');

        // Отменяем предыдущий таймер
        clearTimeout(config.timer);

        // Устанавливаем новый таймер
        config.timer = setTimeout(() => {
            this.saveForm(config);
        }, config.saveDelay);
    }

    /**
     * Сохранить форму
     */
    async saveForm(config) {
        if (!config.isDirty) return;

        const form = config.element;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        this.updateIndicator(form, 'saving');

        try {
            // Сохраняем локально
            this.saveDraft(config.id, data);

            // Отправляем на сервер если указан URL
            if (config.saveUrl && config.saveUrl !== '#') {
                const response = await fetch(config.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Auto-Save': 'true',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                });

                if (!response.ok) {
                    throw new Error('Save failed');
                }
            }

            config.isDirty = false;
            config.lastSaved = new Date();
            this.updateIndicator(form, 'saved', config.lastSaved);

        } catch (error) {
            console.error('Auto-save error:', error);
            this.updateIndicator(form, 'error');
        }
    }

    /**
     * Обновить индикатор
     */
    updateIndicator(form, status, time = null) {
        const indicator = form.querySelector('.auto-save-indicator');
        if (!indicator) return;

        const statusIcon = indicator.querySelector('.auto-save-status i');
        const statusText = indicator.querySelector('.auto-save-text');
        const timeText = indicator.querySelector('.auto-save-time');

        indicator.className = 'auto-save-indicator ' + status;

        switch (status) {
            case 'saving':
                statusIcon.className = 'fas fa-spinner';
                statusText.textContent = 'Сохранение...';
                break;
            case 'saved':
                statusIcon.className = 'fas fa-check-circle';
                statusText.textContent = 'Все изменения сохранены';
                if (time) {
                    timeText.textContent = this.formatTime(time);
                }
                break;
            case 'unsaved':
                statusIcon.className = 'fas fa-circle';
                statusText.textContent = 'Несохраненные изменения';
                break;
            case 'error':
                statusIcon.className = 'fas fa-exclamation-circle';
                statusText.textContent = 'Ошибка сохранения';
                break;
        }
    }

    /**
     * Форматировать время
     */
    formatTime(date) {
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) {
            return 'только что';
        } else if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return `${minutes} мин назад`;
        } else {
            return date.toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }

    /**
     * Обработать отправку формы
     */
    handleSubmit(config, event) {
        // Очищаем черновик при успешной отправке
        this.clearDraft(config.id);
        config.isDirty = false;
    }

    /**
     * Сохранить черновик
     */
    saveDraft(formId, data) {
        try {
            const draft = {
                data: data,
                timestamp: new Date().toISOString()
            };
            localStorage.setItem(`draft_${formId}`, JSON.stringify(draft));
        } catch (e) {
            console.error('Failed to save draft:', e);
        }
    }

    /**
     * Загрузить черновик
     */
    loadDraft(formId) {
        try {
            const stored = localStorage.getItem(`draft_${formId}`);
            if (stored) {
                const draft = JSON.parse(stored);
                
                // Проверяем возраст черновика (не старше 7 дней)
                const age = Date.now() - new Date(draft.timestamp).getTime();
                if (age < 7 * 24 * 60 * 60 * 1000) {
                    return draft.data;
                } else {
                    this.clearDraft(formId);
                }
            }
        } catch (e) {
            console.error('Failed to load draft:', e);
        }
        return null;
    }

    /**
     * Очистить черновик
     */
    clearDraft(formId) {
        try {
            localStorage.removeItem(`draft_${formId}`);
        } catch (e) {
            console.error('Failed to clear draft:', e);
        }
    }

    /**
     * Восстановить данные формы
     */
    restoreFormData(form, data) {
        for (const [name, value] of Object.entries(data)) {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = value === 'on' || value === true;
                } else if (field.type === 'radio') {
                    const radio = form.querySelector(`[name="${name}"][value="${value}"]`);
                    if (radio) radio.checked = true;
                } else {
                    field.value = value;
                }
            }
        }
    }

    /**
     * Показать уведомление о восстановлении
     */
    showRestoreNotification(form, config) {
        const banner = document.createElement('div');
        banner.className = 'draft-restore-banner';
        banner.innerHTML = `
            <div class="draft-restore-banner-content">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Найден несохраненный черновик</strong>
                <div class="small mt-1">Восстановить данные из предыдущей сессии?</div>
            </div>
            <div class="draft-restore-banner-actions">
                <button type="button" class="btn btn-sm btn-light" data-action="restore">
                    Восстановить
                </button>
                <button type="button" class="btn btn-sm btn-outline-light" data-action="discard">
                    Отклонить
                </button>
            </div>
        `;

        document.body.appendChild(banner);

        // Обработчики кнопок
        banner.querySelector('[data-action="restore"]').addEventListener('click', () => {
            banner.remove();
            this.showToast('Черновик восстановлен', 'success');
        });

        banner.querySelector('[data-action="discard"]').addEventListener('click', () => {
            this.clearDraft(config.id);
            form.reset();
            banner.remove();
            this.showToast('Черновик удален', 'info');
        });

        // Автоматически скрыть через 10 секунд
        setTimeout(() => {
            if (banner.parentNode) {
                banner.remove();
            }
        }, 10000);
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Переоткрытие форм при навигации
        document.addEventListener('turbo:load', () => {
            this.discoverForms();
        });

        // Предупреждение при закрытии страницы с несохраненными данными
        window.addEventListener('beforeunload', (e) => {
            const hasDirtyForms = Array.from(this.forms.values()).some(config => config.isDirty);
            
            if (hasDirtyForms) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?';
                return e.returnValue;
            }
        });
    }

    /**
     * Принудительное сохранение всех форм
     */
    saveAll() {
        this.forms.forEach(config => {
            if (config.isDirty) {
                this.saveForm(config);
            }
        });
    }

    /**
     * Очистить все черновики
     */
    clearAllDrafts() {
        this.forms.forEach((config, formId) => {
            this.clearDraft(formId);
        });
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.autoSave = new AutoSave();
    });
} else {
    window.autoSave = new AutoSave();
}

// Экспорт
window.AutoSave = AutoSave;
