/**
 * Form Validation & Auto-Save System
 * Real-time валидация, авто-сохранение черновиков
 * 
 * Версия: 2.0
 * Дата: 19 февраля 2026
 */

(function() {
    'use strict';

    // ========================================================================
    // CONFIGURATION
    // ========================================================================
    const CONFIG = {
        validationDelay: 300,           // Задержка перед валидацией (ms)
        autoSaveDelay: 2000,            // Задержка авто-сохранения (ms)
        autoSaveInterval: 30000,        // Интервал автосохранения (ms)
        minSearchLength: 3,             // Мин. длина для поиска
        debounceDelay: 300,             // Задержка debounce
        toastDuration: 5000,            // Длительность toast (ms)
        draftPrefix: 'form_draft_',     // Префикс для черновиков в localStorage
        validationClasses: {
            success: 'is-valid',
            error: 'is-invalid',
            warning: 'is-warning'
        }
    };

    // ========================================================================
    // VALIDATION RULES
    // ========================================================================
    const ValidationRules = {
        required: (value) => {
            if (typeof value === 'string') {
                return value.trim().length > 0;
            }
            return value !== null && value !== undefined;
        },

        email: (value) => {
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(value);
        },

        minLength: (value, min) => {
            return value && value.length >= min;
        },

        maxLength: (value, max) => {
            return value && value.length <= max;
        },

        min: (value, min) => {
            return value && parseFloat(value) >= min;
        },

        max: (value, max) => {
            return value && parseFloat(value) <= max;
        },

        pattern: (value, pattern) => {
            const regex = new RegExp(pattern);
            return regex.test(value);
        },

        numeric: (value) => {
            return !isNaN(parseFloat(value)) && isFinite(value);
        },

        url: (value) => {
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        },

        phone: (value) => {
            const pattern = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
            return pattern.test(value.replace(/\s/g, ''));
        },

        password: (value) => {
            // Минимум 8 символов, 1 буква, 1 цифра
            const pattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
            return pattern.test(value);
        },

        match: (value, field) => {
            const targetField = document.querySelector(field);
            return targetField && value === targetField.value;
        },

        notEqual: (value, notEqualValue) => {
            return value !== notEqualValue;
        },

        custom: (value, callback) => {
            return callback(value);
        }
    };

    // ========================================================================
    // MESSAGES
    // ========================================================================
    const Messages = {
        required: 'Это поле обязательно для заполнения',
        email: 'Введите корректный email адрес',
        minLength: 'Минимальная длина - {min} символов',
        maxLength: 'Максимальная длина - {max} символов',
        min: 'Минимальное значение - {min}',
        max: 'Максимальное значение - {max}',
        pattern: 'Введите значение в правильном формате',
        numeric: 'Введите числовое значение',
        url: 'Введите корректный URL',
        phone: 'Введите корректный номер телефона',
        password: 'Пароль должен содержать минимум 8 символов, 1 букву и 1 цифру',
        match: 'Значения не совпадают',
        notEqual: 'Это значение уже используется'
    };

    // ========================================================================
    // FORM VALIDATOR CLASS
    // ========================================================================
    class FormValidator {
        constructor(formElement, options = {}) {
            this.form = formElement;
            this.options = {
                ...CONFIG,
                ...options
            };
            this.errors = new Map();
            this.fields = new Map();
            this.isValid = false;
            
            this.init();
        }

        init() {
            this.collectFields();
            this.attachListeners();
            this.loadDraft();
        }

        collectFields() {
            const inputs = this.form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                const rules = this.parseRules(input);
                if (rules.length > 0) {
                    this.fields.set(input.name || input.id, {
                        element: input,
                        rules: rules,
                        isValid: true,
                        value: input.value
                    });
                }
            });
        }

        parseRules(input) {
            const rules = [];
            
            // Required
            if (input.hasAttribute('required')) {
                rules.push({ name: 'required', message: Messages.required });
            }

            // Type-based rules
            if (input.type === 'email') {
                rules.push({ name: 'email', message: Messages.email });
            }

            if (input.type === 'number') {
                if (input.min) {
                    rules.push({ name: 'min', value: parseFloat(input.min), message: Messages.min });
                }
                if (input.max) {
                    rules.push({ name: 'max', value: parseFloat(input.max), message: Messages.max });
                }
            }

            // Data-validate attribute
            if (input.hasAttribute('data-validate')) {
                const validations = JSON.parse(input.getAttribute('data-validate'));
                validations.forEach(validation => {
                    if (typeof validation === 'string') {
                        rules.push({ name: validation, message: Messages[validation] || 'Ошибка валидации' });
                    } else if (typeof validation === 'object') {
                        rules.push({
                            name: validation.rule,
                            value: validation.value,
                            message: validation.message || Messages[validation.rule] || 'Ошибка валидации'
                        });
                    }
                });
            }

            // Min/max length
            if (input.minLength > 0) {
                rules.push({ name: 'minLength', value: input.minLength, message: Messages.minLength });
            }
            if (input.maxLength > 0) {
                rules.push({ name: 'maxLength', value: input.maxLength, message: Messages.maxLength });
            }

            // Pattern
            if (input.pattern) {
                rules.push({ name: 'pattern', value: input.pattern, message: Messages.pattern });
            }

            return rules;
        }

        attachListeners() {
            // Real-time валидация при вводе
            this.fields.forEach((field, name) => {
                const input = field.element;
                
                // Debounced валидация
                let timeout;
                input.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        this.validateField(name);
                    }, this.options.validationDelay);
                });

                // Валидация при потере фокуса
                input.addEventListener('blur', () => {
                    this.validateField(name);
                });

                // Сброс ошибки при начале ввода
                input.addEventListener('focus', () => {
                    this.clearFieldError(name);
                });
            });

            // Валидация при отправке формы
            this.form.addEventListener('submit', (e) => {
                if (!this.validateAll()) {
                    e.preventDefault();
                    this.showSummary();
                }
            });
        }

        validateField(fieldName) {
            const field = this.fields.get(fieldName);
            if (!field) return true;

            const value = field.element.value;
            let isValid = true;
            let errorMessage = '';

            // Проверка всех правил
            for (const rule of field.rules) {
                const validator = ValidationRules[rule.name];
                if (!validator) continue;

                const params = rule.value !== undefined ? [value, rule.value] : [value];
                
                if (!validator(...params)) {
                    isValid = false;
                    errorMessage = this.formatMessage(rule.message, rule);
                    break;
                }
            }

            field.isValid = isValid;
            field.value = value;

            if (isValid) {
                this.clearFieldError(fieldName);
            } else {
                this.showFieldError(fieldName, errorMessage);
            }

            return isValid;
        }

        validateAll() {
            let allValid = true;

            this.fields.forEach((field, name) => {
                if (!this.validateField(name)) {
                    allValid = false;
                }
            });

            this.isValid = allValid;
            return allValid;
        }

        showFieldError(fieldName, message) {
            const field = this.fields.get(fieldName);
            if (!field) return;

            const input = field.element;
            const formGroup = input.closest('.mb-3, .form-group, .form-control');
            
            // Удаляем предыдущие ошибки
            this.clearFieldError(fieldName);

            // Добавляем класс ошибки
            input.classList.add(this.options.validationClasses.error);
            input.classList.remove(this.options.validationClasses.success);

            // Создаём и добавляем сообщение об ошибке
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
            errorDiv.setAttribute('data-error-for', input.name || input.id);

            // Вставляем после input или после .input-group
            if (input.parentElement.classList.contains('input-group')) {
                input.parentElement.after(errorDiv);
            } else {
                input.after(errorDiv);
            }

            // Анимация появления
            errorDiv.style.opacity = '0';
            setTimeout(() => {
                errorDiv.style.transition = 'opacity 0.3s ease';
                errorDiv.style.opacity = '1';
            }, 10);

            this.errors.set(fieldName, { message, element: errorDiv });
        }

        clearFieldError(fieldName) {
            const field = this.fields.get(fieldName);
            if (!field) return;

            const input = field.element;
            const formGroup = input.closest('.mb-3, .form-group, .form-control');
            
            // Удаляем классы
            input.classList.remove(this.options.validationClasses.error);
            input.classList.add(this.options.validationClasses.success);

            // Удаляем сообщение об ошибке
            const errorElement = this.errors.get(fieldName)?.element;
            if (errorElement) {
                errorElement.style.opacity = '0';
                setTimeout(() => errorElement.remove(), 300);
                this.errors.delete(fieldName);
            }
        }

        clearAllErrors() {
            this.fields.forEach((field, name) => {
                this.clearFieldError(name);
            });
            this.errors.clear();
        }

        formatMessage(template, rule) {
            return template.replace(/{(\w+)}/g, (match, key) => {
                return rule[key] !== undefined ? rule[key] : match;
            });
        }

        showSummary() {
            if (this.errors.size === 0) return;

            const errorCount = this.errors.size;
            const firstError = Array.from(this.errors.values())[0];
            
            if (window.showToast) {
                window.showToast(
                    `Исправьте ${errorCount} ${this.pluralize(errorCount, ['ошибку', 'ошибки', 'ошибок'])} в форме`,
                    'error'
                );
            }

            // Скролл к первой ошибке
            if (firstError?.element) {
                firstError.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        pluralize(count, forms) {
            const n = count % 100;
            const n1 = n % 10;
            
            if (n > 10 && n < 20) return forms[2];
            if (n1 > 1 && n1 < 5) return forms[1];
            if (n1 === 1) return forms[0];
            return forms[2];
        }

        // ====================================================================
        // AUTO-SAVE FUNCTIONALITY
        // ====================================================================
        saveDraft() {
            const draftData = {};
            
            this.fields.forEach((field, name) => {
                draftData[name] = field.element.value;
            });

            const draftKey = this.options.draftPrefix + (this.form.id || this.form.action);
            localStorage.setItem(draftKey, JSON.stringify({
                data: draftData,
                timestamp: Date.now()
            }));

            // Показываем уведомление
            if (window.showToast) {
                window.showToast('Черновик сохранён', 'success');
            }
        }

        loadDraft() {
            const draftKey = this.options.draftPrefix + (this.form.id || this.form.action);
            const draft = localStorage.getItem(draftKey);

            if (!draft) return;

            try {
                const { data, timestamp } = JSON.parse(draft);
                const age = Date.now() - timestamp;
                const maxAge = 24 * 60 * 60 * 1000; // 24 часа

                if (age > maxAge) {
                    this.clearDraft();
                    return;
                }

                // Восстанавливаем значения
                Object.keys(data).forEach(fieldName => {
                    const field = this.fields.get(fieldName);
                    if (field) {
                        field.element.value = data[fieldName];
                    }
                });

                // Показываем уведомление
                if (window.showToast && Object.keys(data).length > 0) {
                    window.showToast('Черновик загружен', 'info');
                }
            } catch (e) {
                this.clearDraft();
            }
        }

        clearDraft() {
            const draftKey = this.options.draftPrefix + (this.form.id || this.form.action);
            localStorage.removeItem(draftKey);
        }

        startAutoSave() {
            // Сохранение при изменениях
            let saveTimeout;
            this.form.addEventListener('input', () => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    this.saveDraft();
                }, this.options.autoSaveDelay);
            });

            // Периодическое сохранение
            setInterval(() => {
                this.saveDraft();
            }, this.options.autoSaveInterval);
        }

        // ====================================================================
        // UTILITY METHODS
        // ====================================================================
        getValues() {
            const values = {};
            this.fields.forEach((field, name) => {
                values[name] = field.element.value;
            });
            return values;
        }

        setValues(values) {
            Object.keys(values).forEach(name => {
                const field = this.fields.get(name);
                if (field) {
                    field.element.value = values[name];
                    this.validateField(name);
                }
            });
        }

        reset() {
            this.form.reset();
            this.clearAllErrors();
            this.clearDraft();
        }

        destroy() {
            this.clearAllErrors();
            this.fields.clear();
            this.errors.clear();
        }
    }

    // ========================================================================
    // GLOBAL INITIALIZATION
    // ========================================================================
    window.FormValidator = FormValidator;

    // Авто-инициализация форм с data-validate-form
    document.addEventListener('DOMContentLoaded', () => {
        const forms = document.querySelectorAll('[data-validate-form]');
        
        forms.forEach(form => {
            const options = form.dataset.validateOptions ? 
                JSON.parse(form.dataset.validateOptions) : {};
            
            const validator = new FormValidator(form, options);
            
            // Сохраняем экземпляр для доступа извне
            form.formValidator = validator;

            // Включаем авто-сохранение если указано
            if (form.dataset.autoSave !== 'false') {
                validator.startAutoSave();
            }
        });
    });

    if (window.logger) window.logger.log('[FormValidator] Initialized');
})();
