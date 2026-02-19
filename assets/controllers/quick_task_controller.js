import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'input', 'priority', 'category', 'deadline', 'submitBtn', 'suggestions'];
    static values = {
        url: String,
        suggestionsUrl: String
    };

    connect() {
        this.setupAutoComplete();
        this.setupKeyboardShortcuts();
        this.loadRecentSuggestions();
    }

    setupAutoComplete() {
        if (this.hasInputTarget) {
            this.inputTarget.addEventListener('input', this.debounce(this.handleInput.bind(this), 300));
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            // Ctrl/Cmd + Shift + N для быстрого создания задачи
            if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'N') {
                event.preventDefault();
                this.focusInput();
            }
        });
    }

    async loadRecentSuggestions() {
        if (!this.hasSuggestionsTarget || !this.suggestionsUrlValue) return;

        try {
            const response = await fetch(this.suggestionsUrlValue);
            const suggestions = await response.json();
            this.displaySuggestions(suggestions);
        } catch (error) {
            console.error('Failed to load suggestions:', error);
        }
    }

    displaySuggestions(suggestions) {
        if (!this.hasSuggestionsTarget) return;

        this.suggestionsTarget.innerHTML = '';
        
        if (suggestions.length === 0) return;

        const container = document.createElement('div');
        container.className = 'quick-suggestions mb-3';
        container.innerHTML = '<small class="text-muted">Недавние задачи:</small>';

        const list = document.createElement('div');
        list.className = 'suggestion-list mt-1';

        suggestions.slice(0, 5).forEach(suggestion => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'btn btn-sm btn-outline-secondary me-2 mb-1';
            item.textContent = suggestion.title;
            item.addEventListener('click', () => this.applySuggestion(suggestion));
            list.appendChild(item);
        });

        container.appendChild(list);
        this.suggestionsTarget.appendChild(container);
    }

    applySuggestion(suggestion) {
        this.inputTarget.value = suggestion.title;
        if (suggestion.priority && this.hasPriorityTarget) {
            this.priorityTarget.value = suggestion.priority;
        }
        if (suggestion.category && this.hasCategoryTarget) {
            this.categoryTarget.value = suggestion.category;
        }
        this.inputTarget.focus();
    }

    async handleInput(event) {
        const query = event.target.value.trim();
        
        if (query.length < 2) {
            this.hideSuggestions();
            return;
        }

        try {
            const response = await fetch(`${this.suggestionsUrlValue}?q=${encodeURIComponent(query)}`);
            const suggestions = await response.json();
            this.showAutoComplete(suggestions);
        } catch (error) {
            console.error('Autocomplete error:', error);
        }
    }

    showAutoComplete(suggestions) {
        this.hideAutoComplete();

        if (suggestions.length === 0) return;

        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown position-absolute bg-white border rounded shadow-sm';
        dropdown.style.cssText = `
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        `;

        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item px-3 py-2 cursor-pointer';
            item.textContent = suggestion.title;
            
            item.addEventListener('click', () => {
                this.applySuggestion(suggestion);
                this.hideAutoComplete();
            });
            
            item.addEventListener('mouseenter', () => {
                document.querySelectorAll('.autocomplete-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });

            dropdown.appendChild(item);
        });

        this.inputTarget.parentElement.style.position = 'relative';
        this.inputTarget.parentElement.appendChild(dropdown);
        this.currentDropdown = dropdown;
    }

    hideAutoComplete() {
        if (this.currentDropdown) {
            this.currentDropdown.remove();
            this.currentDropdown = null;
        }
    }

    hideSuggestions() {
        if (this.hasSuggestionsTarget) {
            this.suggestionsTarget.innerHTML = '';
        }
    }

    async submit(event) {
        event.preventDefault();
        
        const title = this.inputTarget.value.trim();
        
        if (!title) {
            this.showError('Введите название задачи');
            this.inputTarget.focus();
            return;
        }

        // Проверка на дублирование
        if (await this.checkDuplicate(title)) {
            if (!confirm('Похожая задача уже существует. Создать новую?')) {
                return;
            }
        }

        this.setLoading(true);

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
                
                // Обновляем список задач без перезагрузки страницы
                this.dispatch('taskCreated', { detail: data.task });
                
                // Фокус обратно на поле ввода для быстрого создания следующей задачи
                setTimeout(() => {
                    this.inputTarget.focus();
                }, 500);
                
            } else {
                this.showError(data.message || 'Ошибка при создании задачи');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Произошла ошибка при создании задачи');
        } finally {
            this.setLoading(false);
        }
    }

    async checkDuplicate(title) {
        try {
            const response = await fetch(`/api/tasks/check-duplicate?title=${encodeURIComponent(title)}`);
            const data = await response.json();
            return data.exists;
        } catch (error) {
            return false;
        }
    }

    setLoading(loading) {
        this.submitBtnTarget.disabled = loading;
        this.submitBtnTarget.innerHTML = loading 
            ? '<span class="spinner-border spinner-border-sm me-2"></span>Создание...'
            : '<i class="fas fa-plus me-2"></i>Создать';
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

        this.hideAutoComplete();
        this.loadRecentSuggestions();
    }

    focusInput() {
        this.inputTarget.focus();
        this.inputTarget.select();
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

        // Arrow keys for autocomplete navigation
        if (this.currentDropdown) {
            const items = this.currentDropdown.querySelectorAll('.autocomplete-item');
            const activeItem = this.currentDropdown.querySelector('.autocomplete-item.active');
            
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                const nextItem = activeItem ? activeItem.nextElementSibling : items[0];
                if (nextItem) {
                    items.forEach(i => i.classList.remove('active'));
                    nextItem.classList.add('active');
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                const prevItem = activeItem ? activeItem.previousElementSibling : items[items.length - 1];
                if (prevItem) {
                    items.forEach(i => i.classList.remove('active'));
                    prevItem.classList.add('active');
                }
            } else if (event.key === 'Enter' && activeItem) {
                event.preventDefault();
                activeItem.click();
            }
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

    // Template methods
    applyTemplate(event) {
        const template = JSON.parse(event.currentTarget.dataset.template);
        
        this.inputTarget.value = template.title;
        if (template.priority) this.priorityTarget.value = template.priority;
        if (template.category && this.hasCategoryTarget) this.categoryTarget.value = template.category;
        
        this.inputTarget.focus();
    }

    // Utility methods
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    disconnect() {
        this.hideAutoComplete();
    }
}
