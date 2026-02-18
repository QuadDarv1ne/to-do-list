/**
 * Quick Search Functionality
 * Provides instant search across the application with keyboard shortcuts
 */

class QuickSearch {
    constructor() {
        this.searchModal = null;
        this.searchInput = null;
        this.resultsContainer = null;
        this.searchTimeout = null;
        this.currentIndex = -1;
        this.results = [];
        
        this.init();
    }
    
    init() {
        this.createSearchModal();
        this.bindKeyboardShortcuts();
        this.bindEvents();
    }
    
    createSearchModal() {
        const modalHTML = `
            <div class="modal fade" id="quickSearchModal" tabindex="-1" aria-labelledby="quickSearchLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 pb-0">
                            <div class="w-100">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-transparent border-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-0 shadow-none" 
                                           id="quickSearchInput" 
                                           placeholder="Поиск задач, пользователей, проектов..."
                                           autocomplete="off">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body pt-2">
                            <div id="quickSearchResults" class="quick-search-results">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                    <p>Начните вводить для поиска...</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                                        <kbd>↑↓</kbd> навигация
                                        <kbd>Enter</kbd> открыть
                                        <kbd>Esc</kbd> закрыть
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <small class="text-muted">
                                <kbd>Ctrl</kbd> + <kbd>K</kbd> для быстрого поиска
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        this.searchModal = new bootstrap.Modal(document.getElementById('quickSearchModal'));
        this.searchInput = document.getElementById('quickSearchInput');
        this.resultsContainer = document.getElementById('quickSearchResults');
    }
    
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K or Cmd+K to open search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.open();
            }
            
            // Slash key to open search (when not in input)
            if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                e.preventDefault();
                this.open();
            }
        });
    }
    
    bindEvents() {
        // Search input
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                this.showEmptyState();
                return;
            }
            
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.navigateResults(1);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.navigateResults(-1);
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectResult();
                    break;
                case 'Escape':
                    this.close();
                    break;
            }
        });
        
        // Reset on modal hide
        document.getElementById('quickSearchModal').addEventListener('hidden.bs.modal', () => {
            this.searchInput.value = '';
            this.showEmptyState();
            this.currentIndex = -1;
        });
        
        // Focus input on modal show
        document.getElementById('quickSearchModal').addEventListener('shown.bs.modal', () => {
            this.searchInput.focus();
        });
    }
    
    async performSearch(query) {
        this.showLoading();
        
        try {
            const response = await fetch(`/search/api/quick?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            this.results = data.results || [];
            this.displayResults(this.results, query);
        } catch (error) {
            console.error('Search error:', error);
            this.showError();
        }
    }
    
    displayResults(results, query) {
        if (results.length === 0) {
            this.showNoResults(query);
            return;
        }
        
        const groupedResults = this.groupResults(results);
        let html = '';
        
        for (const [type, items] of Object.entries(groupedResults)) {
            html += `
                <div class="result-group mb-3">
                    <div class="result-group-header">
                        <i class="fas ${this.getTypeIcon(type)} me-2"></i>
                        ${this.getTypeLabel(type)}
                        <span class="badge bg-secondary ms-2">${items.length}</span>
                    </div>
                    <div class="result-group-items">
                        ${items.map((item, index) => this.renderResultItem(item, index)).join('')}
                    </div>
                </div>
            `;
        }
        
        this.resultsContainer.innerHTML = html;
        this.bindResultClicks();
    }
    
    renderResultItem(item, index) {
        const highlightedTitle = this.highlightMatch(item.title, this.searchInput.value);
        const highlightedDesc = item.description ? this.highlightMatch(item.description, this.searchInput.value) : '';
        
        return `
            <div class="result-item" data-index="${index}" data-url="${item.url}">
                <div class="result-item-icon">
                    <i class="fas ${this.getTypeIcon(item.type)}"></i>
                </div>
                <div class="result-item-content">
                    <div class="result-item-title">${highlightedTitle}</div>
                    ${highlightedDesc ? `<div class="result-item-description">${highlightedDesc}</div>` : ''}
                    <div class="result-item-meta">
                        ${item.meta || ''}
                    </div>
                </div>
                <div class="result-item-action">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        `;
    }
    
    groupResults(results) {
        return results.reduce((groups, item) => {
            const type = item.type || 'other';
            if (!groups[type]) {
                groups[type] = [];
            }
            groups[type].push(item);
            return groups;
        }, {});
    }
    
    getTypeIcon(type) {
        const icons = {
            task: 'fa-tasks',
            user: 'fa-user',
            project: 'fa-folder',
            comment: 'fa-comment',
            category: 'fa-tag',
            other: 'fa-file'
        };
        return icons[type] || icons.other;
    }
    
    getTypeLabel(type) {
        const labels = {
            task: 'Задачи',
            user: 'Пользователи',
            project: 'Проекты',
            comment: 'Комментарии',
            category: 'Категории',
            other: 'Другое'
        };
        return labels[type] || labels.other;
    }
    
    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    bindResultClicks() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        items.forEach((item, index) => {
            item.addEventListener('click', () => {
                this.currentIndex = index;
                this.selectResult();
            });
            
            item.addEventListener('mouseenter', () => {
                this.currentIndex = index;
                this.updateActiveResult();
            });
        });
    }
    
    navigateResults(direction) {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        if (items.length === 0) return;
        
        this.currentIndex += direction;
        
        if (this.currentIndex < 0) {
            this.currentIndex = items.length - 1;
        } else if (this.currentIndex >= items.length) {
            this.currentIndex = 0;
        }
        
        this.updateActiveResult();
    }
    
    updateActiveResult() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        items.forEach((item, index) => {
            if (index === this.currentIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                item.classList.remove('active');
            }
        });
    }
    
    selectResult() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        if (this.currentIndex >= 0 && this.currentIndex < items.length) {
            const url = items[this.currentIndex].dataset.url;
            if (url) {
                window.location.href = url;
            }
        }
    }
    
    showLoading() {
        this.resultsContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p class="mt-3 text-muted">Поиск...</p>
            </div>
        `;
    }
    
    showEmptyState() {
        this.resultsContainer.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                <p>Начните вводить для поиска...</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                    <kbd>↑↓</kbd> навигация
                    <kbd>Enter</kbd> открыть
                    <kbd>Esc</kbd> закрыть
                </div>
            </div>
        `;
    }
    
    showNoResults(query) {
        this.resultsContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-search-minus fa-3x mb-3 text-muted"></i>
                <h5>Ничего не найдено</h5>
                <p class="text-muted">По запросу "${query}" результатов не найдено</p>
            </div>
        `;
    }
    
    showError() {
        this.resultsContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i>
                <h5>Ошибка поиска</h5>
                <p class="text-muted">Попробуйте еще раз</p>
            </div>
        `;
    }
    
    open() {
        this.searchModal.show();
    }
    
    close() {
        this.searchModal.hide();
    }
}

// Styles for quick search
const quickSearchStyles = `
<style>
.quick-search-results {
    max-height: 60vh;
    overflow-y: auto;
}

.result-group {
    margin-bottom: 1.5rem;
}

.result-group-header {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    color: #6c757d;
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.result-group-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.result-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.result-item:hover,
.result-item.active {
    background: #f8f9fa;
    border-color: #667eea;
    transform: translateX(4px);
}

.result-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.result-item-content {
    flex: 1;
    min-width: 0;
}

.result-item-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-item-description {
    font-size: 0.875rem;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-item-meta {
    font-size: 0.75rem;
    color: #adb5bd;
    margin-top: 0.25rem;
}

.result-item-action {
    color: #adb5bd;
    flex-shrink: 0;
}

.result-item:hover .result-item-action,
.result-item.active .result-item-action {
    color: #667eea;
}

mark {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.3) 0%, rgba(255, 152, 0, 0.3) 100%);
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

kbd {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.75rem;
    font-family: monospace;
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', quickSearchStyles);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.quickSearch = new QuickSearch();
});
