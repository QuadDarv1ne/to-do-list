/**
 * Enhanced Search - улучшенный поиск с автодополнением
 */

class EnhancedSearch {
    constructor() {
        this.input = null;
        this.dropdown = null;
        this.results = [];
        this.selectedIndex = -1;
        this.debounceTimer = null;
        this.searchUrl = '/quick-search';
        this.init();
    }

    init() {
        // Находим все search inputs
        const searchInputs = document.querySelectorAll('[data-enhanced-search]');
        
        searchInputs.forEach(input => {
            this.setupSearchInput(input);
        });

        // Глобальный поиск
        this.setupGlobalSearch();
    }

    setupSearchInput(input) {
        this.input = input;
        
        // Создаём dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'enhanced-search-dropdown';
        this.dropdown.style.display = 'none';
        input.parentNode.appendChild(this.dropdown);

        // Event listeners
        input.addEventListener('input', (e) => this.handleInput(e));
        input.addEventListener('focus', () => this.showDropdown());
        input.addEventListener('blur', () => this.hideDropdownDelayed());
        input.addEventListener('keydown', (e) => this.handleKeydown(e));
    }

    setupGlobalSearch() {
        const globalSearch = document.getElementById('global-search') || 
                           document.querySelector('[data-global-search]');
        
        if (globalSearch && globalSearch !== this.input) {
            this.setupSearchInput(globalSearch);
        }
    }

    handleInput(e) {
        const query = e.target.value.trim();

        if (query.length < 2) {
            this.hideDropdown();
            return;
        }

        // Debounce
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.search(query);
        }, 300);
    }

    async search(query) {
        try {
            const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            this.results = data.results || [];
            this.selectedIndex = -1;
            this.renderResults();

            if (this.results.length > 0) {
                this.showDropdown();
            } else {
                this.showNoResults();
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    renderResults() {
        if (this.results.length === 0) {
            this.showNoResults();
            return;
        }

        let html = '';

        // Группируем по типам
        const tasks = this.results.filter(r => r.type === 'task');
        const clients = this.results.filter(r => r.type === 'client');
        const deals = this.results.filter(r => r.type === 'deal');
        const commands = this.results.filter(r => r.type === 'command');

        if (tasks.length > 0) {
            html += this.renderGroup('Задачи', tasks, 'tasks');
        }
        if (clients.length > 0) {
            html += this.renderGroup('Клиенты', clients, 'users');
        }
        if (deals.length > 0) {
            html += this.renderGroup('Сделки', deals, 'handshake');
        }
        if (commands.length > 0) {
            html += this.renderGroup('Действия', commands, 'bolt');
        }

        this.dropdown.innerHTML = html;
        this.dropdown.style.display = 'block';

        // Add click handlers
        this.dropdown.querySelectorAll('.search-result-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.selectResult(index);
            });
        });
    }

    renderGroup(title, items, icon) {
        const itemsHtml = items.map((item, idx) => {
            const globalIndex = this.results.indexOf(item);
            return `
                <div class="search-result-item" data-index="${globalIndex}">
                    <div class="search-result-icon">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">${this.highlightMatch(item.title, this.input.value)}</div>
                        ${item.subtitle ? `<div class="search-result-subtitle">${item.subtitle}</div>` : ''}
                    </div>
                    <div class="search-result-type">${item.type}</div>
                </div>
            `;
        }).join('');

        return `
            <div class="search-result-group">
                <div class="search-group-title">${title}</div>
                ${itemsHtml}
            </div>
        `;
    }

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    showNoResults() {
        this.dropdown.innerHTML = `
            <div class="search-no-results">
                <i class="fas fa-search"></i>
                <span>Ничего не найдено</span>
            </div>
        `;
        this.dropdown.style.display = 'block';
    }

    handleKeydown(e) {
        const items = this.dropdown?.querySelectorAll('.search-result-item');
        if (!items || items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelection(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.updateSelection(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectResult(this.selectedIndex);
                }
                break;
            case 'Escape':
                this.hideDropdown();
                break;
        }
    }

    updateSelection(items) {
        items.forEach((item, index) => {
            item.classList.toggle('active', index === this.selectedIndex);
        });

        // Scroll into view
        const activeItem = items[this.selectedIndex];
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }

    selectResult(index) {
        const result = this.results[index];
        if (!result) return;

        if (result.url) {
            window.location.href = result.url;
        } else if (result.action) {
            // Execute command
            this.executeCommand(result.action);
        }

        this.hideDropdown();
    }

    executeCommand(action) {
        switch (action) {
            case 'new-task':
                window.location.href = '/tasks/new';
                break;
            case 'new-deal':
                window.location.href = '/deals/new';
                break;
            case 'new-client':
                window.location.href = '/clients/new';
                break;
            case 'dashboard':
                window.location.href = '/dashboard';
                break;
            case 'settings':
                window.location.href = '/settings';
                break;
        }
    }

    showDropdown() {
        if (this.dropdown && this.results.length > 0) {
            this.dropdown.style.display = 'block';
        }
    }

    hideDropdown() {
        if (this.dropdown) {
            this.dropdown.style.display = 'none';
        }
    }

    hideDropdownDelayed() {
        setTimeout(() => this.hideDropdown(), 200);
    }
}

// CSS
const style = document.createElement('style');
style.textContent = `
    .enhanced-search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        max-height: 400px;
        overflow-y: auto;
        z-index: 9999;
        margin-top: 8px;
    }
    
    .search-result-group {
        padding: 8px 0;
    }
    
    .search-group-title {
        padding: 8px 16px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
    }
    
    .search-result-item {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        cursor: pointer;
        transition: background 0.15s;
    }
    
    .search-result-item:hover,
    .search-result-item.active {
        background: #f8f9fa;
    }
    
    .search-result-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e9ecef;
        border-radius: 8px;
        margin-right: 12px;
        color: #6c757d;
    }
    
    .search-result-content {
        flex: 1;
    }
    
    .search-result-title {
        font-size: 14px;
        font-weight: 500;
        color: #212529;
    }
    
    .search-result-title mark {
        background: rgba(102, 126, 234, 0.2);
        color: inherit;
        padding: 0 2px;
        border-radius: 2px;
    }
    
    .search-result-subtitle {
        font-size: 12px;
        color: #6c757d;
        margin-top: 2px;
    }
    
    .search-result-type {
        font-size: 11px;
        color: #adb5bd;
        text-transform: capitalize;
    }
    
    .search-no-results {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px;
        color: #6c757d;
    }
    
    .search-no-results i {
        font-size: 24px;
        margin-bottom: 8px;
        opacity: 0.5;
    }

    /* Dark theme */
    [data-theme="dark"] .enhanced-search-dropdown {
        background: #1e293b;
    }

    [data-theme="dark"] .search-result-item:hover,
    [data-theme="dark"] .search-result-item.active {
        background: #334155;
    }

    [data-theme="dark"] .search-result-icon {
        background: #334155;
        color: #94a3b8;
    }

    [data-theme="dark"] .search-result-title {
        color: #f1f5f9;
    }

    [data-theme="dark"] .search-result-subtitle {
        color: #94a3b8;
    }
`;
document.head.appendChild(style);

// Инициализация
const enhancedSearch = new EnhancedSearch();
window.enhancedSearch = enhancedSearch;
