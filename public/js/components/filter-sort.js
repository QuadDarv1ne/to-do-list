/**
 * Filter & Sort System
 * Продвинутая система фильтрации и сортировки
 */

class FilterSort {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            items: options.items || [],
            filters: options.filters || {},
            sortBy: options.sortBy || null,
            sortOrder: options.sortOrder || 'asc',
            onUpdate: options.onUpdate || null,
            searchFields: options.searchFields || [],
            ...options
        };
        
        this.filteredItems = [...this.options.items];
        this.activeFilters = {};
        this.searchQuery = '';
        
        this.init();
    }

    init() {
        this.createFilterUI();
        this.createSortUI();
        this.createSearchUI();
        this.applyFilters();
    }

    createFilterUI() {
        const filterContainer = document.createElement('div');
        filterContainer.className = 'filter-container';
        
        Object.entries(this.options.filters).forEach(([key, config]) => {
            const filterGroup = this.createFilterGroup(key, config);
            filterContainer.appendChild(filterGroup);
        });
        
        this.container.prepend(filterContainer);
    }

    createFilterGroup(key, config) {
        const group = document.createElement('div');
        group.className = 'filter-group';
        
        const label = document.createElement('label');
        label.textContent = config.label;
        label.className = 'filter-label';
        
        let input;
        
        switch (config.type) {
            case 'select':
                input = this.createSelectFilter(key, config);
                break;
            case 'range':
                input = this.createRangeFilter(key, config);
                break;
            case 'checkbox':
                input = this.createCheckboxFilter(key, config);
                break;
            case 'date':
                input = this.createDateFilter(key, config);
                break;
            default:
                input = this.createTextFilter(key, config);
        }
        
        group.appendChild(label);
        group.appendChild(input);
        
        return group;
    }

    createSelectFilter(key, config) {
        const select = document.createElement('select');
        select.className = 'filter-select';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Все';
        select.appendChild(defaultOption);
        
        config.options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.label;
            select.appendChild(opt);
        });
        
        select.addEventListener('change', (e) => {
            this.setFilter(key, e.target.value);
        });
        
        return select;
    }

    createRangeFilter(key, config) {
        const container = document.createElement('div');
        container.className = 'filter-range';
        
        const minInput = document.createElement('input');
        minInput.type = 'number';
        minInput.placeholder = config.minLabel || 'Мин';
        minInput.className = 'filter-input';
        
        const maxInput = document.createElement('input');
        maxInput.type = 'number';
        maxInput.placeholder = config.maxLabel || 'Макс';
        maxInput.className = 'filter-input';
        
        const updateRange = () => {
            this.setFilter(key, {
                min: minInput.value ? parseFloat(minInput.value) : null,
                max: maxInput.value ? parseFloat(maxInput.value) : null
            });
        };
        
        minInput.addEventListener('input', updateRange);
        maxInput.addEventListener('input', updateRange);
        
        container.appendChild(minInput);
        container.appendChild(maxInput);
        
        return container;
    }

    createCheckboxFilter(key, config) {
        const container = document.createElement('div');
        container.className = 'filter-checkbox-group';
        
        config.options.forEach(option => {
            const label = document.createElement('label');
            label.className = 'filter-checkbox-label';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option.value;
            checkbox.className = 'filter-checkbox';
            
            checkbox.addEventListener('change', () => {
                const checked = Array.from(container.querySelectorAll('input:checked'))
                    .map(cb => cb.value);
                this.setFilter(key, checked.length > 0 ? checked : null);
            });
            
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(option.label));
            container.appendChild(label);
        });
        
        return container;
    }

    createDateFilter(key, config) {
        const input = document.createElement('input');
        input.type = 'date';
        input.className = 'filter-input';
        
        input.addEventListener('change', (e) => {
            this.setFilter(key, e.target.value);
        });
        
        return input;
    }

    createTextFilter(key, config) {
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = config.placeholder || '';
        input.className = 'filter-input';
        
        let debounceTimer;
        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                this.setFilter(key, e.target.value);
            }, 300);
        });
        
        return input;
    }

    createSortUI() {
        const sortContainer = document.createElement('div');
        sortContainer.className = 'sort-container';
        
        const sortSelect = document.createElement('select');
        sortSelect.className = 'sort-select';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Сортировка';
        sortSelect.appendChild(defaultOption);
        
        if (this.options.sortOptions) {
            this.options.sortOptions.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.label;
                sortSelect.appendChild(opt);
            });
        }
        
        sortSelect.addEventListener('change', (e) => {
            this.setSortBy(e.target.value);
        });
        
        const orderButton = document.createElement('button');
        orderButton.className = 'sort-order-btn';
        orderButton.innerHTML = '<i class="fas fa-sort-amount-down"></i>';
        orderButton.addEventListener('click', () => {
            this.toggleSortOrder();
            orderButton.innerHTML = this.options.sortOrder === 'asc' 
                ? '<i class="fas fa-sort-amount-down"></i>'
                : '<i class="fas fa-sort-amount-up"></i>';
        });
        
        sortContainer.appendChild(sortSelect);
        sortContainer.appendChild(orderButton);
        
        this.container.prepend(sortContainer);
    }

    createSearchUI() {
        if (this.options.searchFields.length === 0) return;
        
        const searchContainer = document.createElement('div');
        searchContainer.className = 'search-container';
        
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Поиск...';
        searchInput.className = 'search-input-filter';
        
        const searchIcon = document.createElement('i');
        searchIcon.className = 'fas fa-search search-icon-filter';
        
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                this.setSearch(e.target.value);
            }, 300);
        });
        
        searchContainer.appendChild(searchIcon);
        searchContainer.appendChild(searchInput);
        
        this.container.prepend(searchContainer);
    }

    setFilter(key, value) {
        if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
            delete this.activeFilters[key];
        } else {
            this.activeFilters[key] = value;
        }
        this.applyFilters();
    }

    setSearch(query) {
        this.searchQuery = query.toLowerCase();
        this.applyFilters();
    }

    setSortBy(field) {
        this.options.sortBy = field;
        this.applyFilters();
    }

    toggleSortOrder() {
        this.options.sortOrder = this.options.sortOrder === 'asc' ? 'desc' : 'asc';
        this.applyFilters();
    }

    applyFilters() {
        let items = [...this.options.items];
        
        // Применяем фильтры
        Object.entries(this.activeFilters).forEach(([key, value]) => {
            items = items.filter(item => this.matchFilter(item, key, value));
        });
        
        // Применяем поиск
        if (this.searchQuery) {
            items = items.filter(item => this.matchSearch(item));
        }
        
        // Применяем сортировку
        if (this.options.sortBy) {
            items = this.sortItems(items);
        }
        
        this.filteredItems = items;
        
        if (this.options.onUpdate) {
            this.options.onUpdate(this.filteredItems);
        }
    }

    matchFilter(item, key, value) {
        const filterConfig = this.options.filters[key];
        const itemValue = item[key];
        
        if (filterConfig.type === 'range') {
            if (value.min !== null && itemValue < value.min) return false;
            if (value.max !== null && itemValue > value.max) return false;
            return true;
        }
        
        if (Array.isArray(value)) {
            return value.includes(itemValue);
        }
        
        return itemValue === value || itemValue?.toString().toLowerCase().includes(value.toLowerCase());
    }

    matchSearch(item) {
        return this.options.searchFields.some(field => {
            const value = item[field];
            return value?.toString().toLowerCase().includes(this.searchQuery);
        });
    }

    sortItems(items) {
        return items.sort((a, b) => {
            const aVal = a[this.options.sortBy];
            const bVal = b[this.options.sortBy];
            
            let comparison = 0;
            if (aVal > bVal) comparison = 1;
            if (aVal < bVal) comparison = -1;
            
            return this.options.sortOrder === 'asc' ? comparison : -comparison;
        });
    }

    getFilteredItems() {
        return this.filteredItems;
    }

    reset() {
        this.activeFilters = {};
        this.searchQuery = '';
        this.container.querySelectorAll('input, select').forEach(el => {
            if (el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
        this.applyFilters();
    }
}

// Стили
const styles = `
    .filter-container,
    .sort-container,
    .search-container {
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .filter-group {
        margin-bottom: 1rem;
    }

    .filter-label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #374151;
    }

    .filter-input,
    .filter-select,
    .sort-select,
    .search-input-filter {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9375rem;
        transition: all 0.2s ease;
    }

    .filter-input:focus,
    .filter-select:focus,
    .sort-select:focus,
    .search-input-filter:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .filter-range {
        display: flex;
        gap: 0.5rem;
    }

    .filter-checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .sort-container {
        display: flex;
        gap: 0.75rem;
    }

    .sort-order-btn {
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .sort-order-btn:hover {
        background: #f9fafb;
        border-color: #6366f1;
    }

    .search-container {
        position: relative;
    }

    .search-icon-filter {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .search-input-filter {
        padding-left: 2.75rem;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);

// Экспорт
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FilterSort;
}
