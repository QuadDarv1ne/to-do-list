/**
 * Form Validator - Улучшенная валидация форм
 */

class FormValidator {
    constructor(form, options = {}) {
        this.form = form;
        this.options = {
            realTime: true,
            showErrors: true,
            scrollToError: true,
            ...options
        };
        this.init();
    }

    init() {
        if (this.options.realTime) {
            this.initRealTimeValidation();
        }
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    initRealTimeValidation() {
        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });
    }

    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        let isValid = true;
        let errorMessage = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'Это поле обязательно для заполнения';
        } else if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Введите корректный email';
            }
        }

        this.updateFieldStatus(field, isValid, errorMessage);
        return isValid;
    }

    updateFieldStatus(field, isValid, errorMessage) {
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            this.removeError(field);
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            if (this.options.showErrors) {
                this.showError(field, errorMessage);
            }
        }
    }

    showError(field, message) {
        this.removeError(field);
        const error = document.createElement('div');
        error.className = 'invalid-feedback d-block';
        error.textContent = message;
        field.parentNode.appendChild(error);
    }

    removeError(field) {
        const error = field.parentNode.querySelector('.invalid-feedback');
        if (error) error.remove();
    }

    handleSubmit(e) {
        const inputs = this.form.querySelectorAll('input, textarea, select');
        let isFormValid = true;
        let firstInvalid = null;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isFormValid = false;
                if (!firstInvalid) firstInvalid = input;
            }
        });

        if (!isFormValid) {
            e.preventDefault();
            if (this.options.scrollToError && firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            window.notify?.error('Пожалуйста, исправьте ошибки в форме');
        }
    }
}

window.FormValidator = FormValidator;
console.log('✅ Form Validator загружен!');
