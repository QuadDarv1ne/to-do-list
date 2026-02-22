/**
 * Debounced Search for Task Filtering
 * Мгновенный поиск с задержкой для фильтрации задач
 * 
 * Usage: Include this script on pages with search inputs
 */

(function() {
    'use strict';

    /**
     * Debounce function - limits the rate of function execution
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    function debounce(func, wait) {
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

    /**
     * Update URL with search query without page reload
     * @param {string} query - Search query
     */
    function updateURL(query) {
        const url = new URL(window.location.href);
        
        if (query && query.trim() !== '') {
            url.searchParams.set('search', query.trim());
        } else {
            url.searchParams.delete('search');
        }
        
        // Update URL without reloading
        window.history.replaceState({}, '', url);
    }

    /**
     * Show loading indicator
     * @param {HTMLElement} container - Container to show loading in
     */
    function showLoading(container) {
        if (!container) return;
        
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        
        let loader = container.querySelector('.search-loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'search-loading-indicator';
            loader.innerHTML = `
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            `;
            loader.style.cssText = 'position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);';
            container.style.position = 'relative';
            container.appendChild(loader);
        }
    }

    /**
     * Hide loading indicator
     * @param {HTMLElement} container - Container to hide loading from
     */
    function hideLoading(container) {
        if (!container) return;
        
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
        
        const loader = container.querySelector('.search-loading-indicator');
        if (loader) {
            loader.remove();
        }
    }

    /**
     * Initialize debounced search on input element
     * @param {HTMLInputElement} input - Search input element
     * @param {Object} options - Configuration options
     */
    function initDebouncedSearch(input, options = {}) {
        const {
            debounceDelay = 300,
            autoSubmit = false,
            formSelector = null,
            onSearch = null,
            containerSelector = null
        } = options;

        if (!input) return;

        const container = containerSelector ? document.querySelector(containerSelector) : input.parentElement;
        let isComposing = false; // For IME input support (Chinese, Japanese, etc.)

        const performSearch = debounce((query) => {
            // Update URL
            updateURL(query);

            // Show loading state
            showLoading(container);

            if (autoSubmit && formSelector) {
                // Auto-submit form
                const form = document.querySelector(formSelector);
                if (form) {
                    form.requestSubmit();
                }
            } else if (onSearch && typeof onSearch === 'function') {
                // Custom search handler
                onSearch(query);
            } else {
                // Default: reload page with new query (for server-side filtering)
                // Hide loading after short delay for better UX
                setTimeout(() => {
                    hideLoading(container);
                }, 500);
            }

            // Dispatch custom event for other scripts to listen
            window.dispatchEvent(new CustomEvent('debounced-search', {
                detail: { query: query }
            }));
        }, debounceDelay);

        // Handle input event
        input.addEventListener('input', (e) => {
            if (!isComposing) {
                performSearch(e.target.value);
            }
        });

        // Handle IME composition events
        input.addEventListener('compositionstart', () => {
            isComposing = true;
        });

        input.addEventListener('compositionend', (e) => {
            isComposing = false;
            performSearch(e.target.value);
        });

        // Handle clear button if present
        const clearBtn = input.parentElement?.querySelector('.search-clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                performSearch('');
                input.focus();
            });
        }

        // Handle keyboard shortcuts
        input.addEventListener('keydown', (e) => {
            // Escape to clear
            if (e.key === 'Escape') {
                input.value = '';
                performSearch('');
                input.blur();
            }

            // Enter to force submit
            if (e.key === 'Enter') {
                performSearch.flush?.();
            }
        });
    }

    /**
     * Initialize search on page load
     */
    function initializeSearch() {
        // Find search inputs with data-debounce attribute
        const searchInputs = document.querySelectorAll('input[data-debounce-search="true"], input.debounce-search');
        
        searchInputs.forEach(input => {
            const delay = parseInt(input.dataset.debounceDelay) || 300;
            const autoSubmit = input.dataset.debounceAutoSubmit === 'true';
            const formSelector = input.dataset.debounceForm;
            
            initDebouncedSearch(input, {
                debounceDelay: delay,
                autoSubmit: autoSubmit,
                formSelector: formSelector
            });
        });

        // Initialize on specific known search fields
        const taskSearchInput = document.querySelector('input[name="search"]');
        if (taskSearchInput && !taskSearchInput.classList.contains('debounce-search-initialized')) {
            taskSearchInput.classList.add('debounce-search-initialized');
            
            initDebouncedSearch(taskSearchInput, {
                debounceDelay: 300,
                autoSubmit: true,
                formSelector: taskSearchInput.closest('form')?.querySelector('form') || taskSearchInput.closest('form'),
                containerSelector: taskSearchInput.closest('.card-body')
            });
        }

        // Quick search in header (if exists)
        const quickSearchInput = document.querySelector('input[name="quick_search"], input#quick-search');
        if (quickSearchInput && !quickSearchInput.classList.contains('debounce-search-initialized')) {
            quickSearchInput.classList.add('debounce-search-initialized');
            
            initDebouncedSearch(quickSearchInput, {
                debounceDelay: 200,
                onSearch: function(query) {
                    // Could implement AJAX search here
                    if (window.logger) window.logger.log('Quick search:', query);
                }
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSearch);
    } else {
        initializeSearch();
    }

    // Reinitialize after Turbo/Turbo Links navigation
    document.addEventListener('turbo:load', initializeSearch);
    document.addEventListener('turbo:frame-load', initializeSearch);

    // Expose to global scope for manual initialization
    window.DebouncedSearch = {
        init: initDebouncedSearch,
        debounce: debounce
    };

})();
