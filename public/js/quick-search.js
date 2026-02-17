/**
 * Quick Search Modal
 * Ctrl+K to open
 */

class QuickSearch {
    constructor() {
        this.modal = null;
        this.input = null;
        this.results = null;
        this.selectedIndex = 0;
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        this.createModal();
        this.attachEventListeners();
    }
    
    createModal() {
        const modalHTML = `
            <div class="modal fade" id="quickSearchModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <div class="search-header p-3 border-bottom">
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-0">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-0 shadow-none" 
                                           id="quickSearchInput"
                                           placeholder="Поиск задач, пользователей, команд..."
                                           autocomplete="off">
                                    <span class="input-group-text bg-transparent border-0 text-muted">
                                        <kbd>Esc</kbd> закрыть
                                    </span>
                                </div>
                            </div>
                            <div class="search-results" id="quickSearchResults" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-search fa-2x mb-2"></i>
                                    <p>Начните вводить для поиска</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        this.modal = new bootstrap.Modal(document.getElementById('quickSearchModal'));
        this.input = document.getElementById('quickSearchInput');
        this.results = document.getElementById('quickSearchResults');
    }
    
    attachEventListeners() {
        // Input event
        this.input.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.search(e.target.value);
            }, 300);
        });
        
        // Keyboard navigation
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateResults(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.selectResult();
            }
        });
        
        // Clear on modal hide
        document.getElementById('quickSearchModal').addEventListener('hidden.bs.modal', () => {
            this.input.value = '';
            this.results.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-search fa-2x mb-2"></i><p>Начните вводить для поиска</p></div>';
            this.selectedIndex = 0;
        });
        
        // Focus input on modal show
        document.getElementById('quickSearchModal').addEventListener('shown.bs.modal', () => {
            this.input.focus();
        });
    }
    
    async search(query) {
        if (query.length < 2) {
            this.results.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-search fa-2x mb-2"></i><p>Введите минимум 2 символа</p></div>';
            return;
        }
        
        try {
            const response = await fetch(`/search/quick?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            this.renderResults(data.results);
        } catch (error) {
            console.error('Search error:', error);
            this.results.innerHTML = '<div class="alert alert-danger m-3">Ошибка поиска</div>';
        }
    }
    
    renderResults(results) {
        let html = '';
        
        // Commands
        if (results.commands && results.commands.length > 0) {
            html += '<div class="result-section p-3 border-bottom">';
            html += '<h6 class="text-muted mb-2">Команды</h6>';
            results.commands.forEach((cmd, index) => {
                html += `
                    <div class="result-item p-2 rounded ${index === this.selectedIndex ? 'bg-light' : ''}" 
                         data-url="${cmd.url}" 
                         data-index="${index}">
                        <i class="fas ${cmd.icon} me-2"></i>
                        ${cmd.name}
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // Tasks
        if (results.tasks && results.tasks.length > 0) {
            html += '<div class="result-section p-3 border-bottom">';
            html += '<h6 class="text-muted mb-2">Задачи</h6>';
            results.tasks.forEach((task, index) => {
                const idx = (results.commands?.length || 0) + index;
                html += `
                    <div class="result-item p-2 rounded ${idx === this.selectedIndex ? 'bg-light' : ''}" 
                         data-url="${task.url}" 
                         data-index="${idx}">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>${task.title}</span>
                            <span class="badge bg-${this.getPriorityColor(task.priority)}">${task.priority}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // Users
        if (results.users && results.users.length > 0) {
            html += '<div class="result-section p-3 border-bottom">';
            html += '<h6 class="text-muted mb-2">Пользователи</h6>';
            results.users.forEach((user, index) => {
                const idx = (results.commands?.length || 0) + (results.tasks?.length || 0) + index;
                html += `
                    <div class="result-item p-2 rounded ${idx === this.selectedIndex ? 'bg-light' : ''}" 
                         data-index="${idx}">
                        <div class="d-flex align-items-center">
                            <img src="${user.avatar}" class="rounded-circle me-2" width="32" height="32">
                            <div>
                                <div>${user.name}</div>
                                <small class="text-muted">${user.email}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        if (!html) {
            html = '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2"></i><p>Ничего не найдено</p></div>';
        }
        
        this.results.innerHTML = html;
        
        // Add click handlers
        this.results.querySelectorAll('.result-item').forEach(item => {
            item.addEventListener('click', () => {
                const url = item.dataset.url;
                if (url) {
                    window.location.href = url;
                }
            });
        });
    }
    
    navigateResults(direction) {
        const items = this.results.querySelectorAll('.result-item');
        if (items.length === 0) return;
        
        this.selectedIndex += direction;
        
        if (this.selectedIndex < 0) {
            this.selectedIndex = items.length - 1;
        } else if (this.selectedIndex >= items.length) {
            this.selectedIndex = 0;
        }
        
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('bg-light');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('bg-light');
            }
        });
    }
    
    selectResult() {
        const items = this.results.querySelectorAll('.result-item');
        if (items[this.selectedIndex]) {
            items[this.selectedIndex].click();
        }
    }
    
    getPriorityColor(priority) {
        const colors = {
            'urgent': 'danger',
            'high': 'warning',
            'medium': 'info',
            'low': 'secondary'
        };
        return colors[priority] || 'secondary';
    }
    
    open() {
        this.modal.show();
    }
}

// Initialize
let quickSearch;
document.addEventListener('DOMContentLoaded', () => {
    quickSearch = new QuickSearch();
});

// Export for use in hotkeys
if (typeof window !== 'undefined') {
    window.quickSearch = quickSearch;
}
