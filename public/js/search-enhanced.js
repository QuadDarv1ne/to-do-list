/**
 * Search Enhanced
 * Advanced search with autocomplete and filters
 */

class SearchSystem {
    constructor() {
        this.searchInput = null;
        this.searchResults = null;
        this.searchTimeout = null;
        this.currentQuery = '';
        this.isSearching = false;
        this.cache = new Map();
        this.init();
    }

    init() {
        this.createSearchUI();
        this.setupEventListeners();
        this.initKeyboardShortcuts();
        this.initThemeSupport();
    }

    /**
     * Create search UI
     */
    createSearchUI() {
        // Check if already exists
        if (document.getElementById('global-search')) return;

        const searchContainer = document.createElement('div');
        searchContainer.id = 'global-search';
        searchContainer.className = 'global-search';
        searchContainer.innerHTML = `
            <div class="search-backdrop"></div>
            <div class="search-modal">
                <div class="search-header">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               class="search-input" 
                               placeholder="Поиск задач, проектов, пользователей..."
                               autocomplete="off"
                               id="global-search-input">
                        <button class="search-close" id="close-search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="search-filters">
                        <button class="search-filter active" data-filter="all">Все</button>
                        <button class="search-filter" data-filter="tasks">Задачи</button>
                        <button class="search-filter" data-filter="projects">Проекты</button>
                        <button class="search-filter" data-filter="users">Пользователи</button>
                        <button class="search-filter" data-filter="categories">Категории</button>
                    </div>
                </div>
                <div class="search-body">
                    <div class="search-results" id="search-results">
                        <div class="search-empty">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <p class="mb-0">Начните вводить для поиска</p>
                            <div class="search-shortcuts mt-3">
                                <small class="text-muted">
                                    <kbd>Ctrl</kbd> + <kbd>K</kbd> для быстрого поиска
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="search-footer">
                    <div class="search-footer-shortcuts">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> навигация</span>
                        <span><kbd>Enter</kbd> выбрать</span>
                        <span><kbd>Esc</kbd> закрыть</span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(searchContainer);
        
        this.searchInput = document.getElementById('global-search-input');
        this.searchResults = document.getElementById('search-results');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Search input
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });

            this.searchInput.addEventListener('keydown', (e) => {
                this.handleKeyNavigation(e);
            });
        }

        // Close button
        document.addEventListener('click', (e) => {
            if (e.target.closest('#close-search')) {
                this.closeSearch();
            }
        });

        // Backdrop click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('search-backdrop')) {
                this.closeSearch();
            }
        });

        // Filter buttons
        document.addEventListener('click', (e) => {
            const filter = e.target.closest('.search-filter');
            if (filter) {
                this.switchFilter(filter.dataset.filter);
            }
        });

        // Result item click
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if (item) {
                this.handleResultClick(item);
            }
        });
    }

    /**
     * Keyboard shortcuts
     */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K to open search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openSearch();
            }

            // Escape to close
            if (e.key === 'Escape') {
                this.closeSearch();
            }
        });
    }

    /**
     * Open search
     */
    openSearch() {
        const search = document.getElementById('global-search');
        if (search) {
            search.classList.add('show');
            this.searchInput?.focus();
        }
    }

    /**
     * Close search
     */
    closeSearch() {
        const search = document.getElementById('global-search');
        if (search) {
            search.classList.remove('show');
            this.searchInput.value = '';
            this.currentQuery = '';
            this.showEmptyState();
        }
    }

    /**
     * Handle search
     */
    handleSearch(query) {
        clearTimeout(this.searchTimeout);
        
        this.currentQuery = query.trim();

        if (this.currentQuery.length === 0) {
            this.showEmptyState();
            return;
        }

        if (this.currentQuery.length < 2) {
            return;
        }

        // Show loading
        this.showLoading();

        // Debounce search
        this.searchTimeout = setTimeout(() => {
            this.performSearch(this.currentQuery);
        }, 300);
    }

    /**
     * Perform search
     */
    async performSearch(query) {
        // Check cache
        const cacheKey = this.getCacheKey(query);
        if (this.cache.has(cacheKey)) {
            this.displayResults(this.cache.get(cacheKey));
            return;
        }

        this.isSearching = true;

        try {
            const activeFilter = document.querySelector('.search-filter.active')?.dataset.filter || 'all';
            
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}&filter=${activeFilter}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Cache results
                this.cache.set(cacheKey, data.results);
                
                // Limit cache size
                if (this.cache.size > 50) {
                    const firstKey = this.cache.keys().next().value;
                    this.cache.delete(firstKey);
                }

                this.displayResults(data.results);
            } else {
                this.showError('Ошибка поиска');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Ошибка поиска');
        } finally {
            this.isSearching = false;
        }
    }

    /**
     * Get cache key
     */
    getCacheKey(query) {
        const filter = document.querySelector('.search-filter.active')?.dataset.filter || 'all';
        return `${filter}:${query.toLowerCase()}`;
    }

    /**
     * Display results
     */
    displayResults(results) {
        if (!results || results.length === 0) {
            this.showNoResults();
            return;
        }

        const groupedResults = this.groupResults(results);
        let html = '';

        for (const [type, items] of Object.entries(groupedResults)) {
            if (items.length === 0) continue;

            html += `
                <div class="search-result-group">
                    <div class="search-result-group-title">${this.getTypeLabel(type)}</div>
                    ${items.map(item => this.renderResultItem(item)).join('')}
                </div>
            `;
        }

        this.searchResults.innerHTML = html;
    }

    /**
     * Group results by type
     */
    groupResults(results) {
        const grouped = {
            tasks: [],
            projects: [],
            users: [],
            categories: []
        };

        results.forEach(result => {
            if (grouped[result.type]) {
                grouped[result.type].push(result);
            }
        });

        return grouped;
    }

    /**
     * Render result item
     */
    renderResultItem(item) {
        const icon = this.getTypeIcon(item.type);
        const badge = this.getTypeBadge(item);

        return `
            <div class="search-result-item" data-url="${item.url}" data-id="${item.id}">
                <div class="search-result-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${this.highlightQuery(item.title)}</div>
                    ${item.description ? `<div class="search-result-description">${this.highlightQuery(item.description)}</div>` : ''}
                    ${item.meta ? `<div class="search-result-meta">${item.meta}</div>` : ''}
                </div>
                ${badge ? `<div class="search-result-badge">${badge}</div>` : ''}
            </div>
        `;
    }

    /**
     * Highlight query in text
     */
    highlightQuery(text) {
        if (!this.currentQuery || !text) return text;

        const regex = new RegExp(`(${this.escapeRegex(this.currentQuery)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    /**
     * Escape regex
     */
    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Get type icon
     */
    getTypeIcon(type) {
        const icons = {
            tasks: 'fas fa-tasks',
            projects: 'fas fa-folder',
            users: 'fas fa-user',
            categories: 'fas fa-tags'
        };
        return icons[type] || 'fas fa-file';
    }

    /**
     * Get type label
     */
    getTypeLabel(type) {
        const labels = {
            tasks: 'Задачи',
            projects: 'Проекты',
            users: 'Пользователи',
            categories: 'Категории'
        };
        return labels[type] || type;
    }

    /**
     * Get type badge
     */
    getTypeBadge(item) {
        if (item.status) {
            return `<span class="badge badge-${item.status}">${item.status}</span>`;
        }
        if (item.priority) {
            return `<span class="badge badge-priority-${item.priority}">${item.priority}</span>`;
        }
        return '';
    }

    /**
     * Show loading
     */
    showLoading() {
        this.searchResults.innerHTML = `
            <div class="search-loading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span class="ms-2">Поиск...</span>
            </div>
        `;
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        this.searchResults.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p class="mb-0">Начните вводить для поиска</p>
                <div class="search-shortcuts mt-3">
                    <small class="text-muted">
                        <kbd>Ctrl</kbd> + <kbd>K</kbd> для быстрого поиска
                    </small>
                </div>
            </div>
        `;
    }

    /**
     * Show no results
     */
    showNoResults() {
        this.searchResults.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-search-minus fa-3x mb-3"></i>
                <p class="mb-0">Ничего не найдено</p>
                <small class="text-muted">Попробуйте изменить запрос</small>
            </div>
        `;
    }

    /**
     * Show error
     */
    showError(message) {
        this.searchResults.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i>
                <p class="mb-0">${message}</p>
            </div>
        `;
    }

    /**
     * Switch filter
     */
    switchFilter(filter) {
        document.querySelectorAll('.search-filter').forEach(f => {
            f.classList.toggle('active', f.dataset.filter === filter);
        });

        // Re-search with new filter
        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }

    /**
     * Handle key navigation
     */
    handleKeyNavigation(e) {
        const items = this.searchResults.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        const current = this.searchResults.querySelector('.search-result-item.active');
        let index = Array.from(items).indexOf(current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = (index + 1) % items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = index <= 0 ? items.length - 1 : index - 1;
        } else if (e.key === 'Enter' && current) {
            e.preventDefault();
            this.handleResultClick(current);
            return;
        } else {
            return;
        }

        items.forEach(item => item.classList.remove('active'));
        items[index].classList.add('active');
        items[index].scrollIntoView({ block: 'nearest' });
    }

    /**
     * Handle result click
     */
    handleResultClick(item) {
        const url = item.dataset.url;
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Theme support
     */
    initThemeSupport() {
        window.addEventListener('themechange', (e) => {
            console.log('Search: Theme changed to', e.detail.theme);
        });
    }
}

// Initialize search system
let searchSystem;

document.addEventListener('DOMContentLoaded', function() {
    searchSystem = new SearchSystem();
    window.searchSystem = searchSystem;
});

// Export
window.SearchSystem = SearchSystem;
