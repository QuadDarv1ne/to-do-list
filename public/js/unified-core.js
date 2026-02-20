/**
 * Unified Core JavaScript v3.0
 * Единая система без дублирования функций
 */

(function() {
    'use strict';

    // Предотвращаем повторную инициализацию
    if (window.__unifiedCoreLoaded) {
        console.warn('Unified Core already loaded');
        return;
    }
    window.__unifiedCoreLoaded = true;

    // ============================================================================
    // TOAST NOTIFICATIONS (единственная реализация)
    // ============================================================================
    window.showToast = function(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
            danger: 'fa-times-circle'
        };

        const colors = {
            success: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            error: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            warning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            info: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
            danger: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
        };

        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.cssText = `
            display:flex;align-items:center;gap:12px;padding:14px 18px;
            margin-bottom:10px;border-radius:12px;color:#fff;
            background:${colors[type] || colors.info};
            box-shadow:0 8px 24px rgba(0,0,0,0.2);
            transform:translateX(400px);opacity:0;
            transition:all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        `;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info}" style="font-size:20px;"></i>
            <span style="flex:1;font-size:14px;font-weight:500;">${message}</span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;padding:0;opacity:0.7;transition:opacity 0.2s;" aria-label="Закрыть">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });

        setTimeout(() => {
            toast.style.transform = 'translateX(400px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    };

    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================

    // Debounce
    window.debounce = function(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                clearTimeout(timeout);
                func(...args);
            }, wait);
        };
    };

    // Throttle
    window.throttle = function(func, limit = 300) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };

    // Local Storage Helper
    window.storage = {
        set: (key, value) => {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error('Storage error:', error);
                return false;
            }
        },
        get: (key, defaultValue = null) => {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (error) {
                console.error('Storage error:', error);
                return defaultValue;
            }
        },
        remove: (key) => {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (error) {
                console.error('Storage error:', error);
                return false;
            }
        },
        clear: () => {
            try {
                localStorage.clear();
                return true;
            } catch (error) {
                console.error('Storage error:', error);
                return false;
            }
        }
    };

    // Fetch JSON Helper
    window.fetchJSON = async function(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    };

    // Form Validation
    window.validateForm = function(form) {
        const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return isValid;
    };

    // Date Formatting
    window.formatDate = function(date, format = 'short') {
        const d = new Date(date);
        
        if (format === 'short') {
            return d.toLocaleDateString('ru-RU');
        } else if (format === 'long') {
            return d.toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } else if (format === 'time') {
            return d.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            });
        } else if (format === 'datetime') {
            return d.toLocaleString('ru-RU');
        }
        
        return d.toLocaleDateString('ru-RU');
    };

    // Copy to Clipboard
    window.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                window.showToast('Скопировано в буфер обмена', 'success');
            }).catch(err => {
                console.error('Copy failed:', err);
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                window.showToast('Скопировано в буфер обмена', 'success');
            } catch (err) {
                console.error('Fallback copy failed:', err);
                window.showToast('Ошибка копирования', 'error');
            }
            document.body.removeChild(textarea);
        }
    };

    // Escape HTML
    window.escapeHtml = function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    // Query String Helper
    window.getQueryParam = function(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    };

    // Scroll to Element
    window.scrollToElement = function(element, offset = 0) {
        if (!element) return;
        const top = element.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    };

    // Check if Element is in Viewport
    window.isInViewport = function(element) {
        if (!element) return false;
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    };

    // Loading Indicator
    window.showLoading = function(element) {
        if (!element) return;
        element.classList.add('loading');
        element.setAttribute('disabled', 'disabled');
    };

    window.hideLoading = function(element) {
        if (!element) return;
        element.classList.remove('loading');
        element.removeAttribute('disabled');
    };

    // Confirm Dialog
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    // ============================================================================
    // PAGE LOADER
    // ============================================================================
    const pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        window.addEventListener('load', () => {
            setTimeout(() => {
                pageLoader.classList.add('hidden');
                document.body.classList.add('loaded');
            }, 300);
        });
    }

    // ============================================================================
    // BOOTSTRAP TOOLTIPS & POPOVERS
    // ============================================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
        }

        // Initialize popovers
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            [...popoverTriggerList].map(el => new bootstrap.Popover(el));
        }
    });

    // ============================================================================
    // LOGOUT MODAL
    // ============================================================================
    document.addEventListener('DOMContentLoaded', () => {
        const logoutModal = document.getElementById('logoutModal');
        if (logoutModal) {
            const confirmBtn = logoutModal.querySelector('#confirmLogout');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => {
                    window.location.href = '/logout';
                });
            }
        }
    });

    console.log('✨ Unified Core v3.0 initialized');
})();
