/**
 * Продвинутая система фильтрации
 * Паттерны из Notion, Airtable, Linear
 */

class AdvancedFilters {
    constructor() {
        this.filters = [];
        this.savedFilters = this.loadSavedFilters();
        this.init();
    }

    init() {
        this.initFilterBuilder();
        this.initSavedFilters();
        this.initQuickFilters();
        this.initSearchWithFilters();
    }

    /**
     * Конструктор фильтров
     */
    initFilterBuilder() {
        const builderBtn = document.querySelector('[data-filter-builder]');
        if (!builderBtn) return;

        builderBtn.addEventListener('click', () => {
            this.openFilterBuilder();
        });
    }

    openFilterBuilder() {
        const modal = document.createElement('div');
        modal.className = 'filter-builder-modal';
        modal.innerHTML = `
            <div class="filter-builder-backdrop" onclick="this.parentElement.remove()"></div>
            <div class="filter-builder-content">
                <div class="filter-builder-header">
                    <h3>Настройка фильтров</h3>
                    <button onclick="this.closest('.filter-builder-modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="filter-builder-body">
                    <div class="filter-rules" id="filterRules"></div>
                    <button class="add-filter-rule" onclick="window.advancedFilters.addFilterRule()">
                        <i class="fas fa-plus"></i> Добавить условие
                    </button>
                </div>
                <div class="filter-builder-footer">
                    <button class="btn-secondary" onclick="this.closest('.filter-builder-modal').remove()">
                        Отмена
                    </button>
                    <button class="btn-primary" onclick="window.advancedFilters.applyFilters()">
                        Применить
                    </button>
                    <button class="btn-success" onclick="window.advancedFilters.saveFilter()">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.addFilterRule();
    }

    addFilterRule() {
        const rulesContainer = document.getElementById('filterRules');
        const ruleId = Date.now();
        
        const rule = document.createElement('div');
        rule.className = 'filter-rule';
        rule.setAttribute('data-rule-id', ruleId);
        rule.innerHTML = `
            <select class="filter-field">
                <option value="status">Статус</option>
                <option value="priority">Приоритет</option>
                <option value="assignee">Исполнитель</option>
                <option value="date">Дата</option>
                <option value="value">Сумма</option>
            </select>
            <select class="filter-operator">
                <option value="equals">равно</option>
                <option value="not_equals">не равно</option>
                <option value="contains">содержит</option>
                <option value="greater">больше</option>
                <option value="less">меньше</option>
            </select>
            <input type="text" class="filter-value" placeholder="Значение">
            <button class="remove-filter-rule" onclick="this.parentElement.remove()">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        rulesContainer.appendChild(rule);
    }

    applyFilters() {
        const rules = document.querySelectorAll('.filter-rule');
        this.filters = [];
        
        rules.forEach(rule => {
            const field = rule.querySelector('.filter-field').value;
            const operator = rule.querySelector('.filter-operator').value;
            const value = rule.querySelector('.filter-value').value;
            
            if (value) {
                this.filters.push({ field, operator, value });
            }
        });
        
        this.filterData();
        document.querySelector('.filter-builder-modal')?.remove();
        
        // Показываем активные фильтры
        this.displayActiveFilters();
    }

    filterData() {
        const items = document.querySelectorAll('[data-filterable-item]');
        
        items.forEach(item => {
            let show = true;
            
            this.filters.forEach(filter => {
                const itemValue = item.getAttribute(`data-${filter.field}`);
                
                switch(filter.operator) {
                    case 'equals':
                        if (itemValue !== filter.value) show = false;
                        break;
                    case 'not_equals':
                        if (itemValue === filter.value) show = false;
                        break;
                    case 'contains':
                        if (!itemValue.toLowerCase().includes(filter.value.toLowerCase())) show = false;
                        break;
                    case 'greater':
                        if (parseFloat(itemValue) <= parseFloat(filter.value)) show = false;
                        break;
                    case 'less':
                        if (parseFloat(itemValue) >= parseFloat(filter.value)) show = false;
                        break;
                }
            });
            
            item.style.display = show ? '' : 'none';
        });
    }

    displayActiveFilters() {
        let container = document.querySelector('.active-filters');
        
        if (!container) {
            container = document.createElement('div');
            container.className = 'active-filters';
            const targetContainer = document.querySelector('[data-filters-container]');
            if (targetContainer) {
                targetContainer.appendChild(container);
            }
        }
        
        container.innerHTML = this.filters.map((filter, index) => `
            <div class="filter-chip">
                <span>${filter.field}: ${filter.value}</span>
                <button onclick="window.advancedFilters.removeFilter(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
        
        if (this.filters.length > 0) {
            container.innerHTML += `
                <button class="clear-all-filters" onclick="window.advancedFilters.clearAllFilters()">
                    Очистить все
                </button>
            `;
        }
    }

    removeFilter(index) {
        this.filters.splice(index, 1);
        this.filterData();
        this.displayActiveFilters();
    }

    clearAllFilters() {
        this.filters = [];
        this.filterData();
        this.displayActiveFilters();
    }

    /**
     * Сохранённые фильтры
     */
    saveFilter() {
        const name = prompt('Название фильтра:');
        if (!name) return;
        
        const filter = {
            id: Date.now(),
            name,
            rules: [...this.filters]
        };
        
        this.savedFilters.push(filter);
        localStorage.setItem('savedFilters', JSON.stringify(this.savedFilters));
        
        this.displaySavedFilters();
        
        if (window.showSmartNotification) {
            window.showSmartNotification('Фильтр сохранён', 'success');
        }
    }

    loadSavedFilters() {
        const saved = localStorage.getItem('savedFilters');
        return saved ? JSON.parse(saved) : [];
    }

    initSavedFilters() {
        this.displaySavedFilters();
    }

    displaySavedFilters() {
        const container = document.querySelector('[data-saved-filters]');
        if (!container) return;
        
        container.innerHTML = this.savedFilters.map(filter => `
            <div class="saved-filter-item">
                <button onclick="window.advancedFilters.applySavedFilter(${filter.id})">
                    <i class="fas fa-filter"></i>
                    ${filter.name}
                </button>
                <button class="delete-saved-filter" onclick="window.advancedFilters.deleteSavedFilter(${filter.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');
    }

    applySavedFilter(id) {
        const filter = this.savedFilters.find(f => f.id === id);
        if (!filter) return;
        
        this.filters = [...filter.rules];
        this.filterData();
        this.displayActiveFilters();
    }

    deleteSavedFilter(id) {
        if (!confirm('Удалить сохранённый фильтр?')) return;
        
        this.savedFilters = this.savedFilters.filter(f => f.id !== id);
        localStorage.setItem('savedFilters', JSON.stringify(this.savedFilters));
        this.displaySavedFilters();
    }

    /**
     * Быстрые фильтры
     */
    initQuickFilters() {
        const quickFilters = document.querySelectorAll('[data-quick-filter]');
        
        quickFilters.forEach(btn => {
            btn.addEventListener('click', () => {
                const filterType = btn.getAttribute('data-quick-filter');
                const filterValue = btn.getAttribute('data-filter-value');
                
                this.applyQuickFilter(filterType, filterValue);
                
                // Обновляем активную кнопку
                quickFilters.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    applyQuickFilter(type, value) {
        this.filters = [{ field: type, operator: 'equals', value }];
        this.filterData();
        this.displayActiveFilters();
    }

    /**
     * Поиск с фильтрами
     */
    initSearchWithFilters() {
        const searchInput = document.querySelector('[data-advanced-search]');
        if (!searchInput) return;

        let timeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                this.searchWithFilters(e.target.value);
            }, 300);
        });
    }

    searchWithFilters(query) {
        const items = document.querySelectorAll('[data-filterable-item]');
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            const matchesSearch = text.includes(query.toLowerCase());
            
            // Проверяем и поиск, и фильтры
            let matchesFilters = true;
            this.filters.forEach(filter => {
                const itemValue = item.getAttribute(`data-${filter.field}`);
                if (filter.operator === 'equals' && itemValue !== filter.value) {
                    matchesFilters = false;
                }
            });
            
            item.style.display = (matchesSearch && matchesFilters) ? '' : 'none';
        });
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.advancedFilters = new AdvancedFilters();
    });
} else {
    window.advancedFilters = new AdvancedFilters();
}

// CSS стили
const styles = document.createElement('style');
styles.textContent = `
    .filter-builder-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .filter-builder-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }
    
    .filter-builder-content {
        position: relative;
        background: var(--card-bg);
        border-radius: 16px;
        width: 90%;
        max-width: 700px;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
    }
    
    .filter-builder-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .filter-builder-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }
    
    .filter-builder-header button {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: var(--bg-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .filter-builder-header button:hover {
        background: var(--danger);
        color: white;
    }
    
    .filter-builder-body {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
    }
    
    .filter-rules {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .filter-rule {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 12px;
        background: var(--bg-secondary);
        border-radius: 8px;
    }
    
    .filter-rule select,
    .filter-rule input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: var(--card-bg);
        color: var(--text-primary);
        font-size: 14px;
    }
    
    .remove-filter-rule {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: var(--danger);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .remove-filter-rule:hover {
        background: var(--danger);
        color: white;
    }
    
    .add-filter-rule {
        width: 100%;
        padding: 12px;
        border: 2px dashed var(--border-color);
        border-radius: 8px;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .add-filter-rule:hover {
        background: var(--bg-secondary);
        color: var(--primary-color);
        border-color: var(--primary-color);
    }
    
    .filter-builder-footer {
        display: flex;
        gap: 12px;
        padding: 20px;
        border-top: 1px solid var(--border-color);
        justify-content: flex-end;
    }
    
    .filter-builder-footer button {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-secondary {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-success {
        background: var(--success);
        color: white;
    }
    
    .active-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 16px 0;
    }
    
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: var(--primary-color);
        color: white;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .filter-chip button {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }
    
    .filter-chip button:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    .clear-all-filters {
        padding: 6px 12px;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .clear-all-filters:hover {
        background: #dc2626;
    }
    
    .saved-filter-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border-radius: 8px;
        transition: background 0.2s;
    }
    
    .saved-filter-item:hover {
        background: var(--bg-secondary);
    }
    
    .saved-filter-item button {
        padding: 8px 12px;
        border: none;
        background: transparent;
        color: var(--text-primary);
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .saved-filter-item button:first-child {
        flex: 1;
        text-align: left;
    }
    
    .saved-filter-item button:hover {
        background: var(--primary-color);
        color: white;
    }
    
    .delete-saved-filter {
        color: var(--danger) !important;
    }
    
    .delete-saved-filter:hover {
        background: var(--danger) !important;
        color: white !important;
    }
`;
document.head.appendChild(styles);
