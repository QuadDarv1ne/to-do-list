/**
 * Inline Editor - редактирование полей на месте
 * Использование: добавьте data-inline-edit="entityType:fieldName" к элементу
 */

class InlineEditor {
    constructor() {
        this.activeEditor = null;
        this.init();
    }

    init() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-inline-edit]');
            if (trigger && !this.activeEditor) {
                this.startEditing(trigger);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (this.activeEditor) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.save();
                } else if (e.key === 'Escape') {
                    this.cancel();
                }
            }
        });

        document.addEventListener('focusout', (e) => {
            if (this.activeEditor && !e.relatedTarget?.closest('.inline-editor-popup')) {
                this.save();
            }
        });
    }

    startEditing(trigger) {
        const config = trigger.dataset.inlineEdit.split(':');
        const entityType = config[0];
        const fieldName = config[1];
        const entityId = trigger.dataset.entityId;

        const currentValue = trigger.dataset.currentValue || trigger.textContent.trim();

        // Создаем popup редактор
        const popup = document.createElement('div');
        popup.className = 'inline-editor-popup';
        popup.innerHTML = this.getEditorTemplate(fieldName, currentValue);

        // Позиционируем popup
        const rect = trigger.getBoundingClientRect();
        popup.style.position = 'fixed';
        popup.style.left = `${rect.left}px`;
        popup.style.top = `${rect.bottom + 5}px`;
        popup.style.zIndex = '10000';

        document.body.appendChild(popup);

        this.activeEditor = {
            trigger,
            popup,
            entityType,
            fieldName,
            entityId,
            input: popup.querySelector('input, textarea, select')
        };

        // Фокус на input
        setTimeout(() => this.activeEditor?.input?.focus(), 10);
    }

    getEditorTemplate(fieldName, currentValue) {
        // Text input для коротких полей
        if (['title', 'name', 'email', 'phone'].includes(fieldName)) {
            return `
                <div class="inline-editor-content">
                    <input type="text" 
                           class="form-control form-control-sm" 
                           value="${this.escapeHtml(currentValue)}"
                           data-field="${fieldName}">
                    <div class="inline-editor-actions mt-2">
                        <button class="btn btn-sm btn-primary" onclick="inlineEditor.save()">
                            <i class="fas fa-check"></i> Сохранить
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="inlineEditor.cancel()">
                            <i class="fas fa-times"></i> Отмена
                        </button>
                    </div>
                </div>
            `;
        }

        // Textarea для длинных полей
        if (['description', 'notes', 'comment'].includes(fieldName)) {
            return `
                <div class="inline-editor-content">
                    <textarea class="form-control form-control-sm" 
                              rows="3"
                              data-field="${fieldName}">${this.escapeHtml(currentValue)}</textarea>
                    <div class="inline-editor-actions mt-2">
                        <button class="btn btn-sm btn-primary" onclick="inlineEditor.save()">
                            <i class="fas fa-check"></i> Сохранить
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="inlineEditor.cancel()">
                            <i class="fas fa-times"></i> Отмена
                        </button>
                    </div>
                </div>
            `;
        }

        // Select для статусов
        if (['status', 'priority', 'category'].includes(fieldName)) {
            const options = this.getOptionsForField(fieldName);
            return `
                <div class="inline-editor-content">
                    <select class="form-select form-select-sm" data-field="${fieldName}">
                        ${options.map(opt => `
                            <option value="${opt.value}" ${opt.value === currentValue ? 'selected' : ''}>
                                ${opt.label}
                            </option>
                        `).join('')}
                    </select>
                    <div class="inline-editor-actions mt-2">
                        <button class="btn btn-sm btn-primary" onclick="inlineEditor.save()">
                            <i class="fas fa-check"></i> Сохранить
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="inlineEditor.cancel()">
                            <i class="fas fa-times"></i> Отмена
                        </button>
                    </div>
                </div>
            `;
        }

        // По умолчанию text input
        return `
            <div class="inline-editor-content">
                <input type="text" 
                       class="form-control form-control-sm" 
                       value="${this.escapeHtml(currentValue)}"
                       data-field="${fieldName}">
                <div class="inline-editor-actions mt-2">
                    <button class="btn btn-sm btn-primary" onclick="inlineEditor.save()">
                        <i class="fas fa-check"></i> Сохранить
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="inlineEditor.cancel()">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </div>
        `;
    }

    getOptionsForField(fieldName) {
        const options = {
            status: [
                { value: 'pending', label: 'Ожидает' },
                { value: 'in_progress', label: 'В процессе' },
                { value: 'completed', label: 'Завершено' },
                { value: 'cancelled', label: 'Отменено' }
            ],
            priority: [
                { value: 'low', label: 'Низкий' },
                { value: 'medium', label: 'Средний' },
                { value: 'high', label: 'Высокий' },
                { value: 'urgent', label: 'Срочный' }
            ],
            category: [
                { value: 'work', label: 'Работа' },
                { value: 'personal', label: 'Личное' },
                { value: 'shopping', label: 'Покупки' },
                { value: 'health', label: 'Здоровье' }
            ]
        };

        return options[fieldName] || [];
    }

    async save() {
        if (!this.activeEditor) return;

        const { trigger, popup, entityType, fieldName, entityId, input } = this.activeEditor;
        const newValue = input.value;

        // Показываем индикатор загрузки
        trigger.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            // Отправляем запрос на сервер
            const response = await fetch(`/api/inline-update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    entityType,
                    entityId,
                    fieldName,
                    value: newValue
                })
            });

            if (!response.ok) {
                throw new Error('Update failed');
            }

            const data = await response.json();

            // Обновляем значение
            trigger.textContent = data.value || newValue;
            trigger.dataset.currentValue = newValue;

            // Показываем уведомление об успехе
            if (window.notify) {
                window.notify.success('Изменения сохранены');
            }

        } catch (error) {
            console.error('Inline edit error:', error);
            trigger.textContent = trigger.dataset.currentValue;
            
            if (window.notify) {
                window.notify.error('Ошибка сохранения');
            }
        } finally {
            this.closePopup();
        }
    }

    cancel() {
        this.closePopup();
    }

    closePopup() {
        if (this.activeEditor) {
            this.activeEditor.popup.remove();
            this.activeEditor = null;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Инициализация
const inlineEditor = new InlineEditor();
window.inlineEditor = inlineEditor;

// CSS стили
const style = document.createElement('style');
style.textContent = `
    .inline-editor-popup {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 12px;
        min-width: 200px;
        animation: inlineEditorFadeIn 0.2s ease;
    }
    
    @keyframes inlineEditorFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .inline-editor-actions {
        display: flex;
        gap: 8px;
    }
    
    [data-inline-edit] {
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background 0.2s;
    }
    
    [data-inline-edit]:hover {
        background: rgba(0,0,0,0.05);
    }
    
    [data-inline-edit]:focus {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }
`;
document.head.appendChild(style);
