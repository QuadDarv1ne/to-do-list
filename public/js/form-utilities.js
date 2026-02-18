/**
 * Form Utilities
 * Helper functions for form handling and validation
 */

(function() {
    'use strict';

    class FormUtilities {
        constructor() {
            this.init();
        }

        init() {
            this.setupFormSubmitHandlers();
            this.setupDynamicValidation();
            this.setupFileUploadPreviews();
            this.setupSelectEnhancements();
        }

        /**
         * Setup form submit handlers with loading states
         */
        setupFormSubmitHandlers() {
            document.querySelectorAll('form[data-async]').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn?.innerHTML;
                    
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Отправка...';
                    }

                    try {
                        const formData = new FormData(form);
                        const response = await fetch(form.action, {
                            method: form.method || 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            window.notify?.success(data.message || 'Успешно сохранено');
                            
                            if (data.redirect) {
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1000);
                            } else {
                                form.reset();
                            }
                        } else {
                            window.notify?.error(data.message || 'Произошла ошибка');
                            
                            if (data.errors) {
                                this.displayFormErrors(form, data.errors);
                            }
                        }
                    } catch (error) {
                        console.error('Form submission error:', error);
                        window.notify?.error('Ошибка отправки формы');
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }
                });
            });
        }

        /**
         * Display form validation errors
         */
        displayFormErrors(form, errors) {
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.invalid-feedback').forEach(el => {
                el.remove();
            });

            // Display new errors
            Object.keys(errors).forEach(fieldName => {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.classList.add('is-invalid');
                    
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = errors[fieldName];
                    field.parentNode.appendChild(feedback);
                }
            });
        }

        /**
         * Setup dynamic validation
         */
        setupDynamicValidation() {
            document.querySelectorAll('input[required], textarea[required], select[required]').forEach(field => {
                field.addEventListener('blur', () => {
                    this.validateField(field);
                });

                field.addEventListener('input', () => {
                    if (field.classList.contains('is-invalid')) {
                        this.validateField(field);
                    }
                });
            });
        }

        /**
         * Validate single field
         */
        validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            let message = '';

            // Required validation
            if (field.hasAttribute('required') && !value) {
                isValid = false;
                message = 'Это поле обязательно для заполнения';
            }

            // Email validation
            if (field.type === 'email' && value && !this.isValidEmail(value)) {
                isValid = false;
                message = 'Введите корректный email адрес';
            }

            // Min length validation
            if (field.hasAttribute('minlength')) {
                const minLength = parseInt(field.getAttribute('minlength'));
                if (value.length < minLength) {
                    isValid = false;
                    message = `Минимальная длина: ${minLength} символов`;
                }
            }

            // Max length validation
            if (field.hasAttribute('maxlength')) {
                const maxLength = parseInt(field.getAttribute('maxlength'));
                if (value.length > maxLength) {
                    isValid = false;
                    message = `Максимальная длина: ${maxLength} символов`;
                }
            }

            // Pattern validation
            if (field.hasAttribute('pattern') && value) {
                const pattern = new RegExp(field.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    isValid = false;
                    message = field.getAttribute('title') || 'Неверный формат';
                }
            }

            // Update field state
            if (isValid) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                this.removeFeedback(field);
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                this.showFeedback(field, message);
            }

            return isValid;
        }

        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        showFeedback(field, message) {
            this.removeFeedback(field);
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            field.parentNode.appendChild(feedback);
        }

        removeFeedback(field) {
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            if (feedback) feedback.remove();
        }

        /**
         * Setup file upload previews
         */
        setupFileUploadPreviews() {
            document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
                input.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (!file) return;

                    const previewId = input.getAttribute('data-preview');
                    const preview = document.getElementById(previewId);
                    if (!preview) return;

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 100%; border-radius: 8px;">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.innerHTML = `
                            <div class="alert alert-info">
                                <i class="fas fa-file me-2"></i>
                                ${file.name} (${this.formatFileSize(file.size)})
                            </div>
                        `;
                    }
                });
            });
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        /**
         * Setup select enhancements
         */
        setupSelectEnhancements() {
            document.querySelectorAll('select[data-search]').forEach(select => {
                // Add search functionality to select
                const wrapper = document.createElement('div');
                wrapper.className = 'select-search-wrapper';
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);

                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.className = 'form-control form-control-sm mb-2';
                searchInput.placeholder = 'Поиск...';
                wrapper.insertBefore(searchInput, select);

                searchInput.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    Array.from(select.options).forEach(option => {
                        const text = option.textContent.toLowerCase();
                        option.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            });
        }

        /**
         * Serialize form data to object
         */
        serializeForm(form) {
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (data[key]) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            }
            return data;
        }

        /**
         * Reset form with animation
         */
        resetForm(form) {
            form.reset();
            form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
            form.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => {
                el.remove();
            });
        }
    }

    // Initialize
    const formUtilities = new FormUtilities();

    // Expose globally
    window.FormUtils = {
        validate: (field) => formUtilities.validateField(field),
        serialize: (form) => formUtilities.serializeForm(form),
        reset: (form) => formUtilities.resetForm(form),
        displayErrors: (form, errors) => formUtilities.displayFormErrors(form, errors)
    };

})();
