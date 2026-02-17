import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'input', 'priority', 'category', 'deadline', 'submitBtn'];
    static values = {
        url: String
    };

    connect() {
        console.log('Quick task controller connected');
    }

    async submit(event) {
        event.preventDefault();
        
        const title = this.inputTarget.value.trim();
        
        if (!title) {
            this.showError('Введите название задачи');
            return;
        }

        this.submitBtnTarget.disabled = true;
        this.submitBtnTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Создание...';

        const formData = new FormData();
        formData.append('title', title);
        formData.append('priority', this.priorityTarget.value);
        
        if (this.hasCategoryTarget && this.categoryTarget.value) {
            formData.append('category', this.categoryTarget.value);
        }
        
        if (this.hasDeadlineTarget && this.deadlineTarget.value) {
            formData.append('deadline', this.deadlineTarget.value);
        }

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Задача создана успешно!');
                this.resetForm();
                
                // Reload page after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showError(data.message || 'Ошибка при создании задачи');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Произошла ошибка при создании задачи');
        } finally {
            this.submitBtnTarget.disabled = false;
            this.submitBtnTarget.innerHTML = '<i class="fas fa-plus me-2"></i>Создать';
        }
    }

    resetForm() {
        this.inputTarget.value = '';
        this.priorityTarget.value = 'medium';
        
        if (this.hasCategoryTarget) {
            this.categoryTarget.value = '';
        }
        
        if (this.hasDeadlineTarget) {
            this.deadlineTarget.value = '';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'danger');
    }

    showNotification(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alert.style.zIndex = '9999';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    // Keyboard shortcuts
    handleKeydown(event) {
        // Ctrl/Cmd + Enter to submit
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            this.submit(event);
        }
        
        // Escape to clear
        if (event.key === 'Escape') {
            this.resetForm();
        }
    }

    // Priority quick select
    setPriority(event) {
        const priority = event.currentTarget.dataset.priority;
        this.priorityTarget.value = priority;
        
        // Visual feedback
        document.querySelectorAll('[data-priority]').forEach(btn => {
            btn.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
    }
}
