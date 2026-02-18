/**
 * Advanced Search with Autocomplete
 * Расширенный поиск с автодополнением и фильтрами
 */

class AdvancedSearch {
    constructor() {
        this.searchInput = null;
        this.resultsContainer = null;
        this.searchTimeout = null;
        this.minChars = 2;
        this.cache = new Map();
        this.recentSearches = this.loadRecentSearches();
        this.init();
    }

    init() {
        this.createSearchInterface();
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
    }

    /**
     * Создать интерфейс поиска
     */
    createSearchInterface() {
        // Проверить существующий поиск
        const existingSearch = document.getElementById('advanced-search-container');
        if (existingSearch) return;

        const container = document.createElement('div');
        container.id = 'advanced-search-container';
        container.className = 'advanced-search-container';
        container.innerHTML = `
            <div class="advanced-search-overlay"></div>
            <div class="advanced-search-modal">
                <div class="advanced-search-header">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               id="advanced-search-input" 
                               class="advanced-search-input" 
                               placeholder="Поиск задач, проектов, пользователей..."
                               autocomplete="off">
                        <button class="search-close-btn" id="close-advanced-search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="search-filters">
                        <button class="filter-chip active" data-type="all">
                            <i class="fas fa-globe"></i> Все
                        </button>
                        <button class="filter-chip" data-type="tasks">
                            <i class="fas fa-tasks"></i> Задачи
                        </button>
                        <button class="filter-chip" data-type="projects">
                            <i class="fas fa-folder"></i> Проекты
                        </button>
                        <button class="filter-chip" data-type="users">
                            <i class="fas fa-users"></i> Пользователи
                        </button>
                    </div>
                </div>
                <div class="advanced-search-body">
                    <div id="search-results" class="search-results"></div>
                    <div id="search-suggestions" class="search-suggestions"></div>
                </div>
                <div class="advanced-search-footer">
                    <div class="search-shortcuts">
                        <kbd>↑↓</kbd> навигация
                        <kbd>Enter</kbd> выбрать
                        <kbd>Esc</kbd> закрыть
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(container);
        
        this.searchInput = document.getElementById('advanced-search-input');
        this.resultsContainer = document.getElementById('search-results');
        
        this.addStyles();
    }

    /**
     * Добавить стили
     */
    addStyles() {
        if (document.getElementById('advancedSearchStyles')) return;

        const style = document.createElement('style');
        style.id = 'advancedSearchStyles';
        style.textContent = `
            .advanced-search-container {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                display: none;
                align-items: flex-start;
                justify-content: center;
                padding-top: 10vh;
            }

            .advanced-search-container.show {
                display: flex;
            }

            .advanced-search-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                animation: fadeIn 0.2s ease;
            }

            .advanced-search-modal {
                position: relative;
                width: 90%;
                max-width: 700px;
                background: var(--bg-card);
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideDown 0.3s ease;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .advanced-search-header {
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .search-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
                margin-bottom: 1rem;
            }

            .search-icon {
                position: absolute;
                left: 1rem;
                color: var(--text-muted);
                font-size: 1.25rem;
            }

            .advanced-search-input {
                width: 100%;
                padding: 1rem 3rem 1rem 3.5rem;
                border: 2px solid var(--border);
                border-radius: 8px;
                font-size: 1.125rem;
                background: var(--bg-body);
                color: var(--text-primary);
                transition: all 0.2s ease;
            }

            .advanced-search-input:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            }

            .search-close-btn {
                position: absolute;
                right: 1rem;
                background: transparent;
                border: none;
                color: var(--text-muted);
                font-size: 1.25rem;
                cursor: pointer;
                padding: 0.5rem;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .search-close-btn:hover {
                background: var(--bg-body);
                color: var(--text-primary);
            }

            .search-filters {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            .filter-chip {
                padding: 0.5rem 1rem;
                border: 1px solid var(--border);
                border-radius: 20px;
                background: transparent;
                color: var(--text-primary);
                font-size: 0.875rem;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .filter-chip:hover {
                background: var(--bg-body);
            }

            .filter-chip.active {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }

            .advanced-search-body {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
                max-height: 50vh;
            }

            .search-results {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .search-result-item {
                padding: 1rem;
                border-radius: 8px;
                background: var(--bg-body);
                border: 1px solid var(--border);
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: start;
                gap: 1rem;
            }

            .search-result-item:hover,
            .search-result-item.selected {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
                transform: translateX(4px);
            }

            .search-result-icon {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                font-size: 1.25rem;
            }

            .search-result-item:hover .search-result-icon,
            .search-result-item.selected .search-result-icon {
                background: rgba(255, 255, 255, 0.2);
                color: white;
            }

            .search-result-icon.task {
                background: rgba(102, 126, 234, 0.1);
                color: #667eea;
            }

            .search-result-icon.project {
                background: rgba(255, 193, 7, 0.1);
                color: #ffc107;
            }

            .search-result-icon.user {
                background: rgba(40, 167, 69, 0.1);
                color: #28a745;
            }

            .search-result-content {
                flex: 1;
            }

            .search-result-title {
                font-weight: 600;
                margin-bottom: 0.25rem;
            }

            .search-result-meta {
                font-size: 0.875rem;
                opacity: 0.8;
            }

            .search-result-item:hover .search-result-meta,
            .search-result-item.selected .search-result-meta {
                opacity: 1;
            }

            .search-suggestions {
                padding: 1rem 0;
            }

            .search-suggestions-title {
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-muted);
                margin-bottom: 0.75rem;
                text-transform: uppercase;
            }

            .search-suggestion-item {
                padding: 0.75rem 1rem;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .search-suggestion-item:hover {
                background: var(--bg-body);
            }

            .search-suggestion-icon {
                color: var(--text-muted);
            }

            .search-empty {
                text-align: center;
                padding: 3rem 1rem;
                color: var(--text-muted);
            }

            .search-empty i {
                font-size: 3rem;
                margin-bottom: 1rem;
                opacity: 0.3;
            }

            .search-loading {
                text-align: center;
                padding: 2rem;
            }

            .advanced-search-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid var(--border);
                background: var(--bg-body);
                border-radius: 0 0 12px 12px;
            }

            .search-shortcuts {
                display: flex;
                gap: 1rem;
                font-size: 0.875rem;
                color: var(--text-muted);
                align-items: center;
            }

            .search-shortcuts kbd {
                padding: 0.25rem 0.5rem;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 4px;
                font-family: monospace;
                font-size: 0.75rem;
            }

            @media (max-width: 768px) {
                .advanced-search-modal {
                    width: 95%;
                    max-height: 90vh;
                }

                .advanced-search-container {
                    padding-top: 5vh;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Ввод в поиск
        this.searchInput?.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Закрытие
        document.getElementById('close-advanced-search')?.addEventListener('click', () => {
            this.close();
        });

        // Закрытие по клику на overlay
        document.querySelector('.advanced-search-overlay')?.addEventListener('click', () => {
            this.close();
        });

        // Фильтры
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                e.target.classList.add('active');
                this.handleSearch(this.searchInput.value);
            });
        });

        // Навигация клавиатурой
        this.searchInput?.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });
    }

    /**
     * Настроить горячие клавиши
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K или Cmd+K для открытия поиска
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.open();
            }

            // Esc для закрытия
            if (e.key === 'Escape') {
                this.close();
            }
        });
    }

    /**
     * Обработать поиск
     */
    async handleSearch(query) {
        clearTimeout(this.searchTimeout);

        if (query.length < this.minChars) {
            this.showSuggestions();
            return;
        }

        this.showLoading();

        this.searchTimeout = setTimeout(async () => {
            try {
                const results = await this.performSearch(query);
                this.renderResults(results);
                this.saveRecentSearch(query);
            } catch (error) {
                console.error('Search error:', error);
                this.showError();
            }
        }, 300);
    }

    /**
     * Выполнить поиск
     */
    async performSearch(query) {
        // Проверить кэш
        const cacheKey = `${query}_${this.getActiveFilter()}`;
        if (this.cache.has(cacheKey)) {
            return this.cache.get(cacheKey);
        }

        const filter = this.getActiveFilter();
        const response = await fetch(`/api/search?q=${encodeURIComponent(query)}&type=${filter}`);
        
        if (!response.ok) throw new Error('Search failed');

        const results = await response.json();
        
        // Сохранить в кэш
        this.cache.set(cacheKey, results);
        
        return results;
    }

    /**
     * Отрисовать результаты
     */
    renderResults(results) {
        if (results.length === 0) {
            this.showEmpty();
            return;
        }

        const html = results.map(result => this.createResultHTML(result)).join('');
        this.resultsContainer.innerHTML = html;

        // Добавить обработчики
        this.resultsContainer.querySelectorAll('.search-result-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.selectResult(results[index]);
            });
        });
    }

    /**
     * Создать HTML для результата
     */
    createResultHTML(result) {
        const iconClass = result.type === 'task' ? 'task' : result.type === 'project' ? 'project' : 'user';
        const icon = result.type === 'task' ? 'tasks' : result.type === 'project' ? 'folder' : 'user';

        return `
            <div class="search-result-item" data-id="${result.id}" data-type="${result.type}">
                <div class="search-result-icon ${iconClass}">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${this.highlightQuery(result.title)}</div>
                    <div class="search-result-meta">${result.meta || ''}</div>
                </div>
            </div>
        `;
    }

    /**
     * Показать подсказки
     */
    showSuggestions() {
        const suggestions = document.getElementById('search-suggestions');
        if (!suggestions) return;

        if (this.recentSearches.length === 0) {
            suggestions.innerHTML = '';
            return;
        }

        const html = `
            <div class="search-suggestions-title">Недавние поиски</div>
            ${this.recentSearches.map(search => `
                <div class="search-suggestion-item" data-query="${search}">
                    <i class="fas fa-history search-suggestion-icon"></i>
                    <span>${search}</span>
                </div>
            `).join('')}
        `;

        suggestions.innerHTML = html;
        this.resultsContainer.innerHTML = '';

        // Добавить обработчики
        suggestions.querySelectorAll('.search-suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.searchInput.value = item.dataset.query;
                this.handleSearch(item.dataset.query);
            });
        });
    }

    /**
     * Показать загрузку
     */
    showLoading() {
        this.resultsContainer.innerHTML = `
            <div class="search-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Поиск...</span>
                </div>
            </div>
        `;
    }

    /**
     * Показать пустой результат
     */
    showEmpty() {
        this.resultsContainer.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-search"></i>
                <p class="mb-0">Ничего не найдено</p>
            </div>
        `;
    }

    /**
     * Показать ошибку
     */
    showError() {
        this.resultsContainer.innerHTML = `
            <div class="search-empty">
                <i class="fas fa-exclamation-triangle"></i>
                <p class="mb-0">Ошибка поиска</p>
            </div>
        `;
    }

    /**
     * Обработать навигацию клавиатурой
     */
    handleKeyboardNavigation(e) {
        const items = this.resultsContainer.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        const selected = this.resultsContainer.querySelector('.search-result-item.selected');
        let index = selected ? Array.from(items).indexOf(selected) : -1;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = Math.min(index + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = Math.max(index - 1, 0);
        } else if (e.key === 'Enter' && selected) {
            e.preventDefault();
            selected.click();
            return;
        } else {
            return;
        }

        items.forEach(item => item.classList.remove('selected'));
        items[index]?.classList.add('selected');
        items[index]?.scrollIntoView({ block: 'nearest' });
    }

    /**
     * Выбрать результат
     */
    selectResult(result) {
        const urls = {
            'task': `/tasks/${result.id}`,
            'project': `/projects/${result.id}`,
            'user': `/users/${result.id}`
        };

        const url = urls[result.type];
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Получить активный фильтр
     */
    getActiveFilter() {
        const active = document.querySelector('.filter-chip.active');
        return active?.dataset.type || 'all';
    }

    /**
     * Подсветить запрос
     */
    highlightQuery(text) {
        const query = this.searchInput?.value || '';
        if (!query) return text;

        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    /**
     * Сохранить недавний поиск
     */
    saveRecentSearch(query) {
        this.recentSearches = this.recentSearches.filter(s => s !== query);
        this.recentSearches.unshift(query);
        this.recentSearches = this.recentSearches.slice(0, 5);
        localStorage.setItem('recentSearches', JSON.stringify(this.recentSearches));
    }

    /**
     * Загрузить недавние поиски
     */
    loadRecentSearches() {
        try {
            return JSON.parse(localStorage.getItem('recentSearches') || '[]');
        } catch {
            return [];
        }
    }

    /**
     * Открыть поиск
     */
    open() {
        const container = document.getElementById('advanced-search-container');
        if (container) {
            container.classList.add('show');
            this.searchInput?.focus();
            this.showSuggestions();
        }
    }

    /**
     * Закрыть поиск
     */
    close() {
        const container = document.getElementById('advanced-search-container');
        if (container) {
            container.classList.remove('show');
            this.searchInput.value = '';
            this.resultsContainer.innerHTML = '';
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.advancedSearch = new AdvancedSearch();
    });
} else {
    window.advancedSearch = new AdvancedSearch();
}

// Экспорт
window.AdvancedSearch = AdvancedSearch;
