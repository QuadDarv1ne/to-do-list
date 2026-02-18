/**
 * Global UX/UI Interactions
 * Enhanced user experience features for Task Management System
 */

(function() {
    'use strict';

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initTooltips();
        initPopovers();
        initFormEnhancements();
        initTableEnhancements();
        initCardAnimations();
        initBulkActions();
        initSearchEnhancements();
        initLoadingStates();
        initConfirmDialogs();
        initAutoSave();
        initKeyboardShortcuts();
    });

    /**
     * Initialize Bootstrap tooltips
     */
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                delay: { show: 500, hide: 100 }
            });
        });
    }

    /**
     * Initialize Bootstrap popovers
     */
    function initPopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }

    /**
     * Form enhancements
     */
    function initFormEnhancements() {
        // Real-time validation
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });
        });

        // Character counter for textareas
        const textareas = document.querySelectorAll('textarea[maxlength]');
        textareas.forEach(textarea => {
            const maxLength = textarea.getAttribute('maxlength');
            const counter = document.createElement('small');
            counter.className = 'form-text text-muted';
            counter.textContent = `0 / ${maxLength}`;
            textarea.parentNode.appendChild(counter);

            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length} / ${maxLength}`;
                counter.className = length > maxLength * 0.9 ? 'form-text text-warning' : 'form-text text-muted';
            });
        });

        // Auto-resize textareas
        const autoResizeTextareas = document.querySelectorAll('textarea[data-auto-resize]');
        autoResizeTextareas.forEach(textarea => {
            textarea.style.overflow = 'hidden';
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });

        // Password strength indicator
        const passwordInputs = document.querySelectorAll('input[type="password"][data-strength]');
        passwordInputs.forEach(input => {
            const strengthBar = document.createElement('div');
            strengthBar.className = 'password-strength-bar mt-2';
            strengthBar.innerHTML = '<div class="password-strength-progress"></div>';
            input.parentNode.appendChild(strengthBar);

            input.addEventListener('input', function() {
                const strength = calculatePasswordStrength(this.value);
                updatePasswordStrength(strengthBar, strength);
            });
        });
    }

    /**
     * Validate form field
     */
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'Это поле обязательно для заполнения';
        } else if (field.type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            message = 'Введите корректный email адрес';
        } else if (field.type === 'url' && value && !isValidUrl(value)) {
            isValid = false;
            message = 'Введите корректный URL';
        } else if (field.hasAttribute('minlength') && value.length < field.getAttribute('minlength')) {
            isValid = false;
            message = `Минимальная длина: ${field.getAttribute('minlength')} символов`;
        }

        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            removeFeedback(field);
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            showFeedback(field, message);
        }

        return isValid;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    function showFeedback(field, message) {
        removeFeedback(field);
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }

    function removeFeedback(field) {
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    }

    function calculatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        return strength;
    }

    function updatePasswordStrength(bar, strength) {
        const progress = bar.querySelector('.password-strength-progress');
        const width = (strength / 5) * 100;
        progress.style.width = width + '%';
        
        progress.className = 'password-strength-progress';
        if (strength <= 2) progress.classList.add('bg-danger');
        else if (strength <= 3) progress.classList.add('bg-warning');
        else progress.classList.add('bg-success');
    }

    /**
     * Table enhancements
     */
    function initTableEnhancements() {
        // Sortable tables
        const sortableTables = document.querySelectorAll('table[data-sortable]');
        sortableTables.forEach(table => {
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    sortTable(table, this);
                });
            });
        });

        // Row hover effects
        const tables = document.querySelectorAll('table tbody tr');
        tables.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    }

    /**
     * Card animations
     */
    function initCardAnimations() {
        const cards = document.querySelectorAll('.card');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('fade-in-up');
                    }, index * 100);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(card => {
            observer.observe(card);
        });
    }

    /**
     * Bulk actions
     */
    function initBulkActions() {
        const selectAllCheckbox = document.getElementById('select-all-tasks');
        const taskCheckboxes = document.querySelectorAll('.task-checkbox');
        const bulkActionsContainer = document.getElementById('bulk-actions-container');
        const selectedCountSpan = document.getElementById('selected-count');
        const cancelButton = document.getElementById('cancel-bulk-selection');
        const bulkActionSelect = document.getElementById('bulk-action-select');
        const tagSelectionContainer = document.getElementById('tag-selection-container');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                taskCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActionsUI();
            });
        }

        taskCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsUI);
        });

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                taskCheckboxes.forEach(checkbox => checkbox.checked = false);
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateBulkActionsUI();
            });
        }

        if (bulkActionSelect) {
            bulkActionSelect.addEventListener('change', function() {
                if (this.value === 'assign_tag' && tagSelectionContainer) {
                    tagSelectionContainer.style.display = 'block';
                } else if (tagSelectionContainer) {
                    tagSelectionContainer.style.display = 'none';
                }
            });
        }

        function updateBulkActionsUI() {
            const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
            
            if (checkedCount > 0) {
                bulkActionsContainer?.classList.remove('d-none');
                if (selectedCountSpan) {
                    selectedCountSpan.textContent = `Выбрано: ${checkedCount}`;
                }
            } else {
                bulkActionsContainer?.classList.add('d-none');
            }
        }
    }

    /**
     * Search enhancements
     */
    function initSearchEnhancements() {
        const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');
        
        searchInputs.forEach(input => {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                const searchIcon = this.parentNode.querySelector('i');
                
                if (searchIcon) {
                    searchIcon.className = 'fas fa-spinner fa-spin';
                }
                
                timeout = setTimeout(() => {
                    if (searchIcon) {
                        searchIcon.className = 'fas fa-search';
                    }
                }, 500);
            });
        });
    }

    /**
     * Loading states
     */
    function initLoadingStates() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton && !submitButton.disabled) {
                    submitButton.disabled = true;
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Загрузка...';
                    
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                    }, 5000);
                }
            });
        });

        // AJAX loading indicator
        document.addEventListener('ajaxStart', function() {
            showLoadingIndicator();
        });
        
        document.addEventListener('ajaxComplete', function() {
            hideLoadingIndicator();
        });
    }

    function showLoadingIndicator() {
        let indicator = document.getElementById('global-loading-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'global-loading-indicator';
            indicator.className = 'position-fixed top-0 start-0 w-100 bg-primary';
            indicator.style.height = '3px';
            indicator.style.zIndex = '9999';
            indicator.innerHTML = '<div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>';
            document.body.appendChild(indicator);
        }
        indicator.style.display = 'block';
    }

    function hideLoadingIndicator() {
        const indicator = document.getElementById('global-loading-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    /**
     * Confirm dialogs
     */
    function initConfirmDialogs() {
        const deleteLinks = document.querySelectorAll('a[data-confirm], button[data-confirm]');
        
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm') || 'Вы уверены?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }

    /**
     * Auto-save functionality
     */
    function initAutoSave() {
        const autoSaveForms = document.querySelectorAll('form[data-auto-save]');
        
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            let timeout;
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    showAutoSaveIndicator('Сохранение...');
                    
                    timeout = setTimeout(() => {
                        saveFormData(form);
                    }, 2000);
                });
            });
        });
    }

    function saveFormData(form) {
        const formData = new FormData(form);
        const url = form.getAttribute('data-auto-save-url') || form.action;
        
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAutoSaveIndicator('Сохранено', 'success');
            } else {
                showAutoSaveIndicator('Ошибка сохранения', 'error');
            }
        })
        .catch(() => {
            showAutoSaveIndicator('Ошибка сохранения', 'error');
        });
    }

    function showAutoSaveIndicator(message, type = 'info') {
        let indicator = document.getElementById('auto-save-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'auto-save-indicator';
            indicator.className = 'position-fixed bottom-0 end-0 m-3 p-3 rounded shadow';
            indicator.style.zIndex = '9999';
            document.body.appendChild(indicator);
        }
        
        indicator.className = `position-fixed bottom-0 end-0 m-3 p-3 rounded shadow bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} text-white`;
        indicator.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'spinner fa-spin'} me-2"></i>${message}`;
        indicator.style.display = 'block';
        
        if (type !== 'info') {
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 3000);
        }
    }

    /**
     * Keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Ctrl/Cmd + S for save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const form = document.querySelector('form');
                if (form) {
                    e.preventDefault();
                    form.requestSubmit();
                }
            }
        });
    }

    // Expose utility functions globally
    window.TaskManagerUI = {
        showToast: function(message, type = 'info') {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
            }
        },
        showLoading: showLoadingIndicator,
        hideLoading: hideLoadingIndicator,
        validateField: validateField
    };

})();

// Add CSS for password strength bar
const style = document.createElement('style');
style.textContent = `
    .password-strength-bar {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
    }
    
    .password-strength-progress {
        height: 100%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
    
    #auto-save-indicator {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
