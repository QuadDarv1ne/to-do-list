/**
 * Advanced Filters
 * Расширенная система фильтрации и сортировки
 */

class AdvancedFilters {
    constructor() {
        this.filters = new Map();
        this.activeFilters = new Map();
        this.sortBy = null;
        this.sortOrder = 'asc';
        this.init();
    }

    init() {
        this.setupFilterPanel();
        this.bindEvents();
    }

    setupFilterPanel() {
        // Ищем таблицы и списки для добавления фильтров
        const tables = document.querySelectorAll('table');
        const lists = document.querySelectorAll('.task-list, .item-list');

        tables.forEach(table => {
            if (!table.dataset.filtersEnabled) {
                this.addFilterPanel(table);
                table.dataset.filtersEnabled = 'true';
            }
        });

        lists.forEach(list => {
            if (!list.dataset.filtersEnabled) {
                this.addFilterPanel(list);
                list.dataset.filtersEnabled = 'true';
            }
        });
    }

    addFilterPanel(container) {
        const panel = document.createElement('div');
        panel.className = 'advanced-filters-panel';
        panel.innerHTML = `
            <div class="filters-header">
                <h5><i class="fas fa-filter"></i> Фильтры</h5>
                <button class="btn btn-sm btn-link filters-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="filters-body">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Поиск</label>
                        <input type="text" class="form-control form-control-sm filter-search" placeholder="Поиск...">
                    </div>
                    <div class="filter-group">
                        <label>Статус</label>
                        <select class="form-select form-select-sm filter-status">
                            <option value="">Все</option>
                            <option value="new">Новая</option>
                            <option value="in_progress">В работе</option>
                            <option value="completed">Завершена</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Приоритет</label>
                        <select class="form-select form-select-sm filter-priority">
                            <option value="">Все</option>
                            <option value="low">Низкий</option>
                            <option value="medium">Средний</option>
                            <option value="high">Высокий</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Сортировка</label>
                        <select class="form-select form-select-sm filter-sort">
                            <option value="">По умолчанию</option>
                            <option value="title">По названию</option>
                            <option value="date">По дате</option>
                            <option value="priority">По приоритету</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Порядок</label>
                        <select class="form-select form-select-sm filter-order">
                            <option value="asc">По возрастанию</option>
                            <option value="desc">По убыванию</option>
                        </select>
                    </div>
                </div>
                <div class="filters-actions">
                    <button class="btn btn-sm btn-primary filter-apply">
                        <i class="fas fa-check"></i> Применить
                    </button>
                    <button class="btn btn-sm btn-secondary filter-reset">
                        <i class="fas fa-undo"></i> Сбросить
                    </button>
                </div>
                <div class="active-filters"></div>
            </div>
        `;

        container.parentElement.insertBefore(panel, container);
        this.addStyles();
    }

    bindEvents() {
        document.addEventListener('click', (e) => {
            // Переключение панели фильтров
            if (e.target.closest('.filters-toggle')) {
                const panel = e.target.closest('.advanced-filters-panel');
                panel.classList.toggle('collapsed');
            }

            // Применение фильтров
            if (e.target.closest('.filter-apply')) {
                const panel = e.target.closest('.advanced-filters-panel');
                this.applyFilters(panel);
            }

            // Сброс фильтров
            if (e.target.closest('.filter-reset')) {
                const panel = e.target.closest('.advanced-filters-panel');
                this.resetFilters(panel);
            }

            // Удаление активного фильтра
            if (e.target.closest('.active-filter-remove')) {
                const filterKey = e.target.closest('.active-filter-tag').dataset.filter;
                this.removeFilter(filterKey);
            }
        });

        // Применение фильтров по Enter
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.closest('.advanced-filters-panel')) {
                const panel = e.target.closest('.advanced-filters-panel');
                this.applyFilters(panel);
            }
        });
    }

    applyFilters(panel) {
        const search = panel.querySelector('.filter-search').value;
        const status = panel.querySelector('.filter-status').value;
        const priority = panel.querySelector('.filter-priority').value;
        const sort = panel.querySelector('.filter-sort').value;
        const order = panel.querySelector('.filter-order').value;

        // Сохраняем активные фильтры
        this.activeFilters.clear();
        if (search) this.activeFilters.set('search', search);
        if (status) this.activeFilters.set('status', status);
        if (priority) this.activeFilters.set('priority', priority);
        if (sort) {
            this.sortBy = sort;
            this.sortOrder = order;
        }

        // Применяем фильтры
        const container = panel.nextElementSibling;
        this.filterItems(container);
        this.updateActiveFiltersDisplay(panel);
    }

    filterItems(container) {
        let items;
        
        if (container.tagName === 'TABLE') {
            items = Array.from(container.querySelectorAll('tbody tr'));
        } else {
            items = Array.from(container.querySelectorAll('.task-item, .item'));
        }

        items.forEach(item => {
            let visible = true;

            // Применяем каждый фильтр
            for (const [key, value] of this.activeFilters.entries()) {
                if (!this.matchesFilter(item, key, value)) {
                    visible = false;
                    break;
                }
            }

            item.style.display = visible ? '' : 'none';
        });

        // Применяем сортировку
        if (this.sortBy) {
            this.sortItems(items, container);
        }

        this.updateResultsCount(container, items);
    }

    matchesFilter(item, key, value) {
        const text = item.textContent.toLowerCase();
        const lowerValue = value.toLowerCase();

        switch(key) {
            case 'search':
                return text.includes(lowerValue);
            case 'status':
                return item.dataset.status === value || text.includes(lowerValue);
            case 'priority':
                return item.dataset.priority === value || text.includes(lowerValue);
            default:
                return true;
        }
    }

    sortItems(items, container) {
        const sorted = items.sort((a, b) => {
            let aValue, bValue;

            switch(this.sortBy) {
                case 'title':
                    aValue = a.textContent.trim();
                    bValue = b.textContent.trim();
                    break;
                case 'date':
                    aValue = a.dataset.date || '';
                    bValue = b.dataset.date || '';
                    break;
                case 'priority':
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    aValue = priorityOrder[a.dataset.priority] || 0;
                    bValue = priorityOrder[b.dataset.priority] || 0;
                    break;
                default:
                    return 0;
            }

            if (this.sortOrder === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });

        // Перестраиваем DOM
        const parent = container.tagName === 'TABLE' ? container.querySelector('tbody') : container;
        sorted.forEach(item => parent.appendChild(item));
    }

    updateActiveFiltersDisplay(panel) {
        const display = panel.querySelector('.active-filters');
        
        if (this.activeFilters.size === 0) {
            display.innerHTML = '';
            return;
        }

        const tags = Array.from(this.activeFilters.entries()).map(([key, value]) => {
            return `
                <span class="active-filter-tag" data-filter="${key}">
                    ${this.getFilterLabel(key)}: ${value}
                    <button class="active-filter-remove" aria-label="Удалить фильтр">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            `;
        }).join('');

        display.innerHTML = `
            <div class="active-filters-label">Активные фильтры:</div>
            ${tags}
        `;
    }

    getFilterLabel(key) {
        const labels = {
            search: 'Поиск',
            status: 'Статус',
            priority: 'Приоритет'
        };
        return labels[key] || key;
    }

    updateResultsCount(container, items) {
        const visible = items.filter(item => item.style.display !== 'none').length;
        const total = items.length;

        // Добавляем счетчик результатов
        let counter = container.parentElement.querySelector('.results-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'results-counter';
            container.parentElement.insertBefore(counter, container);
        }

        counter.textContent = `Показано: ${visible} из ${total}`;
    }

    removeFilter(key) {
        this.activeFilters.delete(key);
        
        // Находим панель и применяем фильтры заново
        const panel = document.querySelector('.advanced-filters-panel');
        if (panel) {
            // Очищаем соответствующее поле
            const input = panel.querySelector(`.filter-${key}`);
            if (input) {
                input.value = '';
            }
            this.applyFilters(panel);
        }
    }

    resetFilters(panel) {
        this.activeFilters.clear();
        this.sortBy = null;
        this.sortOrder = 'asc';

        // Очищаем все поля
        panel.querySelectorAll('input, select').forEach(input => {
            input.value = '';
        });

        // Показываем все элементы
        const container = panel.nextElementSibling;
        const items = container.querySelectorAll('tbody tr, .task-item, .item');
        items.forEach(item => {
            item.style.display = '';
        });

        this.updateActiveFiltersDisplay(panel);
        this.updateResultsCount(container, Array.from(items));
    }

    addStyles() {
        if (document.getElementById('advancedFiltersStyles')) return;

        const style = document.createElement('style');
        style.id = 'advancedFiltersStyles';
        style.textContent = `
            .advanced-filters-panel {
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                margin-bottom: 1rem;
                overflow: hidden;
            }

            .filters-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.25rem;
                background: var(--bg-body);
                cursor: pointer;
            }

            .filters-header h5 {
                margin: 0;
                font-size: 1rem;
                color: var(--text-primary);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .filters-toggle {
                padding: 0;
                color: var(--text-secondary);
                transition: transform 0.3s ease;
            }

            .advanced-filters-panel.collapsed .filters-toggle {
                transform: rotate(-90deg);
            }

            .advanced-filters-panel.collapsed .filters-body {
                display: none;
            }

            .filters-body {
                padding: 1.25rem;
            }

            .filters-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .filter-group label {
                display: block;
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-secondary);
                margin-bottom: 0.375rem;
            }

            .filters-actions {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }

            .active-filters {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid var(--border);
            }

            .active-filters:empty {
                display: none;
            }

            .active-filters-label {
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
            }

            .active-filter-tag {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: var(--primary);
                color: white;
                padding: 0.375rem 0.75rem;
                border-radius: 20px;
                font-size: 0.875rem;
                margin-right: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .active-filter-remove {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0;
                font-size: 0.875rem;
                opacity: 0.8;
                transition: opacity 0.2s ease;
            }

            .active-filter-remove:hover {
                opacity: 1;
            }

            .results-counter {
                font-size: 0.875rem;
                color: var(--text-secondary);
                margin-bottom: 0.75rem;
                padding: 0.5rem 0;
            }

            @media (max-width: 768px) {
                .filters-row {
                    grid-template-columns: 1fr;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.advancedFilters = new AdvancedFilters();
});

window.AdvancedFilters = AdvancedFilters;
