/**
 * Интеграция системы уведомлений с формами
 * Автоматические уведомления при отправке форм
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Обработка всех форм с data-notify атрибутом
    const forms = document.querySelectorAll('form[data-notify]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const notifyType = this.dataset.notify;
            
            if (notifyType === 'loading') {
                // Показываем loading индикатор
                const loadingNotification = window.notify?.loading('Отправка данных...');
                
                // Сохраняем для последующего удаления
                this.dataset.loadingNotificationId = loadingNotification?.dataset?.id || '';
            }
        });
    });
    
    // Обработка AJAX форм
    const ajaxForms = document.querySelectorAll('form[data-ajax]');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const url = this.action || window.location.href;
            const method = this.method || 'POST';
            
            try {
                const loadingNotification = window.notify?.loading('Отправка данных...');
                
                const response = await fetch(url, {
                    method: method,
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                // Удаляем loading
                if (loadingNotification) {
                    window.notificationSystem?.remove(loadingNotification);
                }
                
                if (response.ok) {
                    const data = await response.json();
                    
                    // Показываем success
                    window.notify?.success(
                        data.message || 'Данные успешно отправлены!',
                        5000
                    );
                    
                    // Вызываем callback если есть
                    if (this.dataset.onSuccess) {
                        const callback = new Function('data', this.dataset.onSuccess);
                        callback(data);
                    }
                    
                    // Очищаем форму если указано
                    if (this.dataset.clearOnSuccess === 'true') {
                        this.reset();
                    }
                    
                } else {
                    const error = await response.json();
                    window.notify?.error(
                        error.message || 'Ошибка при отправке данных',
                        7000
                    );
                }
                
            } catch (error) {
                console.error('Form submission error:', error);
                window.notify?.error(
                    'Произошла ошибка при отправке данных',
                    7000
                );
            }
        });
    });
    
    // Валидация форм с уведомлениями
    const validatedForms = document.querySelectorAll('form[data-validate-notify]');
    
    validatedForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const isValid = this.checkValidity();
            
            if (!isValid) {
                e.preventDefault();
                
                // Находим первое невалидное поле
                const firstInvalid = this.querySelector(':invalid');
                
                if (firstInvalid) {
                    const fieldName = firstInvalid.getAttribute('name') || 
                                     firstInvalid.getAttribute('placeholder') || 
                                     'поле';
                    
                    window.notify?.warning(
                        `Пожалуйста, заполните поле "${fieldName}" корректно`,
                        5000
                    );
                    
                    firstInvalid.focus();
                }
            }
        });
    });
    
    // Автосохранение с уведомлениями
    const autosaveForms = document.querySelectorAll('form[data-autosave]');
    
    autosaveForms.forEach(form => {
        let autosaveTimeout;
        
        form.addEventListener('input', function() {
            clearTimeout(autosaveTimeout);
            
            autosaveTimeout = setTimeout(async () => {
                const formData = new FormData(this);
                const url = this.dataset.autosaveUrl || this.action;
                
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (response.ok) {
                        window.notify?.info('Изменения сохранены', 2000);
                    }
                } catch (error) {
                    console.error('Autosave error:', error);
                }
            }, 2000);
        });
    });
    
    // Подтверждение перед отправкой
    const confirmForms = document.querySelectorAll('form[data-confirm]');
    
    confirmForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const confirmMessage = this.dataset.confirm || 
                                  'Вы уверены, что хотите отправить форму?';
            
            window.notify?.confirm(
                confirmMessage,
                () => {
                    // Подтверждено - отправляем форму
                    this.submit();
                },
                () => {
                    // Отменено
                    console.log('Form submission cancelled');
                }
            );
        });
    });
});

// Утилиты для работы с формами
window.formNotifications = {
    // Показать ошибки валидации
    showValidationErrors: (errors) => {
        if (Array.isArray(errors)) {
            errors.forEach((error, index) => {
                setTimeout(() => {
                    window.notify?.error(error, 5000);
                }, index * 300);
            });
        } else if (typeof errors === 'object') {
            Object.entries(errors).forEach(([field, messages], index) => {
                setTimeout(() => {
                    const errorText = Array.isArray(messages) ? messages.join(', ') : messages;
                    window.notify?.error(`${field}: ${errorText}`, 5000);
                }, index * 300);
            });
        }
    },
    
    // Показать успех с действиями
    showSuccessWithActions: (message, actions) => {
        window.notify?.success(message, 0, { actions });
    },
    
    // Обработка promise с формой
    handleFormPromise: (promise, messages = {}) => {
        return window.notify?.promise(promise, {
            loading: messages.loading || 'Отправка данных...',
            success: messages.success || 'Данные успешно отправлены!',
            error: messages.error || 'Ошибка при отправке данных'
        });
    }
};

console.log('📝 Form Notifications загружен!');
