/**
 * Table Enhancements
 * Улучшения для таблиц: сортировка, фильтрация, пагинация
 */

class TableEnhancements {
    constructor() {
        this.tables = [];
        this.init();
    }

    init() {
        this.enhanceTables();
        this.initColumnResizing();
        this.initRowSelection();
        this.initInlineEditing();
    }

    /**
     * Улучшение таблиц
     */
    enhanceTables() {
        const tables = document.querySelectorAll('[data-enhanced-table]');
        
        tables.forEach(table => {
            const config = {
                sortable: table.dataset.sortable !== 'false',
                filterable: table.dataset.filterable !== 'false',
                resizable: table.dataset.resizable !== 'false',
                selectable: table.dataset.selectable !== 'false'
            };

            this.enhanceTable(table, config);
        });
    }

    /**
     * Улучшить таблицу
     */
    enhanceTable(table, config) {
        const tableData = {
            element: table,
            config: config,
            data: this.extractTableData(table),
            sortColumn: null,
            sortDirection: 'asc',
            filters: {}
        };

        this.tables.push(tableData);

        if (config.sortable) {
            this.makeSortable(tableData);
        }

        if (config.filterable) {
            this.makeFilterable(tableData);
        }

        if (config.resizable) {
            this.makeResizable(tableData);
        }

        if (config.selectable) {
            this.makeSelectable(tableData);
        }

        // Добавляем поиск по таблице
        this.addTableSearch(tableData);
    }

    /**
     * Извлечь данные из таблицы
     */
    extractTableData(table) {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        return rows.map(row => {
            const cells = Array.from(row.querySelectorAll('td'));
            return {
                element: row,
                values: cells.map(cell => cell.textContent.trim()),
                data: cells.map(cell => cell.dataset.value || cell.textContent.trim())
            };
        });
    }

    /**
     * Сделать таблицу сортируемой
     */
    makeSortable(tableData) {
        const headers = tableData.element.querySelectorAll('thead th');
        
        headers.forEach((header, index) => {
            if (header.dataset.sortable === 'false') return;

            header.style.cursor = 'pointer';
            header.classList.add('sortable');
            
            // Добавляем иконку сортировки
            const icon = document.createElement('i');
            icon.className = 'fas fa-sort ms-2 sort-icon';
            header.appendChild(icon);

            header.addEventListener('click', () => {
                this.sortTable(tableData, index);
            });
        });
    }

    /**
     * Сортировать таблицу
     */
    sortTable(tableData, columnIndex) {
        const { element, data, sortColumn, sortDirection } = tableData;
        
        // Определяем направление сортировки
        let newDirection = 'asc';
        if (sortColumn === columnIndex) {
            newDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        }

        // Сортируем данные
        const sortedData = [...data].sort((a, b) => {
            const aVal = a.data[columnIndex];
            const bVal = b.data[columnIndex];

            // Пытаемся сравнить как числа
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return newDirection === 'asc' ? aNum - bNum : bNum - aNum;
            }

            // Сравниваем как строки
            const comparison = aVal.localeCompare(bVal);
            return newDirection === 'asc' ? comparison : -comparison;
        });

        // Обновляем таблицу
        const tbody = element.querySelector('tbody');
        tbody.innerHTML = '';
        sortedData.forEach(row => tbody.appendChild(row.element));

        // Обновляем иконки сортировки
        const headers = element.querySelectorAll('thead th');
        headers.forEach((header, i) => {
            const icon = header.querySelector('.sort-icon');
            if (!icon) return;

            if (i === columnIndex) {
                icon.className = `fas fa-sort-${newDirection === 'asc' ? 'up' : 'down'} ms-2 sort-icon`;
            } else {
                icon.className = 'fas fa-sort ms-2 sort-icon';
            }
        });

        // Сохраняем состояние
        tableData.sortColumn = columnIndex;
        tableData.sortDirection = newDirection;
    }

    /**
     * Сделать таблицу фильтруемой
     */
    makeFilterable(tableData) {
        const table = tableData.element;
        const headers = table.querySelectorAll('thead th');
        
        // Создаем строку с фильтрами
        const filterRow = document.createElement('tr');
        filterRow.className = 'filter-row';

        headers.forEach((header, index) => {
            const th = document.createElement('th');
            
            if (header.dataset.filterable !== 'false') {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.placeholder = 'Фильтр...';
                input.dataset.columnIndex = index;

                input.addEventListener('input', (e) => {
                    this.filterTable(tableData, index, e.target.value);
                });

                th.appendChild(input);
            }

            filterRow.appendChild(th);
        });

        table.querySelector('thead').appendChild(filterRow);
    }

    /**
     * Фильтровать таблицу
     */
    filterTable(tableData, columnIndex, filterValue) {
        tableData.filters[columnIndex] = filterValue.toLowerCase();

        const { element, data, filters } = tableData;
        const tbody = element.querySelector('tbody');

        data.forEach(row => {
            let visible = true;

            for (const [colIndex, filter] of Object.entries(filters)) {
                if (filter && !row.values[colIndex].toLowerCase().includes(filter)) {
                    visible = false;
                    break;
                }
            }

            row.element.style.display = visible ? '' : 'none';
        });

        this.updateTableInfo(tableData);
    }

    /**
     * Обновить информацию о таблице
     */
    updateTableInfo(tableData) {
        const visibleRows = tableData.data.filter(row => 
            row.element.style.display !== 'none'
        ).length;

        const info = tableData.element.parentElement.querySelector('[data-table-info]');
        if (info) {
            info.textContent = `Показано ${visibleRows} из ${tableData.data.length} записей`;
        }
    }

    /**
     * Добавить поиск по таблице
     */
    addTableSearch(tableData) {
        const searchContainer = tableData.element.parentElement.querySelector('[data-table-search]');
        if (!searchContainer) return;

        const input = document.createElement('input');
        input.type = 'search';
        input.className = 'form-control';
        input.placeholder = 'Поиск по таблице...';

        input.addEventListener('input', (e) => {
            this.searchTable(tableData, e.target.value);
        });

        searchContainer.appendChild(input);
    }

    /**
     * Поиск по таблице
     */
    searchTable(tableData, searchValue) {
        const search = searchValue.toLowerCase();
        const { data } = tableData;

        data.forEach(row => {
            const match = row.values.some(value => 
                value.toLowerCase().includes(search)
            );
            row.element.style.display = match ? '' : 'none';
        });

        this.updateTableInfo(tableData);
    }

    /**
     * Изменение размера колонок
     */
    initColumnResizing() {
        document.addEventListener('mousedown', (e) => {
            if (!e.target.matches('.column-resizer')) return;

            const th = e.target.parentElement;
            const startX = e.pageX;
            const startWidth = th.offsetWidth;

            const onMouseMove = (e) => {
                const width = startWidth + (e.pageX - startX);
                th.style.width = width + 'px';
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    }

    /**
     * Сделать таблицу с изменяемыми колонками
     */
    makeResizable(tableData) {
        const headers = tableData.element.querySelectorAll('thead th');
        
        headers.forEach(header => {
            const resizer = document.createElement('div');
            resizer.className = 'column-resizer';
            resizer.style.cssText = `
                position: absolute;
                right: 0;
                top: 0;
                width: 5px;
                height: 100%;
                cursor: col-resize;
                user-select: none;
            `;
            
            header.style.position = 'relative';
            header.appendChild(resizer);
        });
    }

    /**
     * Выбор строк
     */
    initRowSelection() {
        document.addEventListener('click', (e) => {
            const row = e.target.closest('tr[data-selectable]');
            if (!row) return;

            const checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox && e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }

            row.classList.toggle('selected', checkbox?.checked);
        });
    }

    /**
     * Сделать таблицу с выбором строк
     */
    makeSelectable(tableData) {
        const table = tableData.element;
        
        // Добавляем чекбокс в заголовок
        const headerRow = table.querySelector('thead tr:first-child');
        const selectAllTh = document.createElement('th');
        selectAllTh.style.width = '40px';
        
        const selectAllCheckbox = document.createElement('input');
        selectAllCheckbox.type = 'checkbox';
        selectAllCheckbox.className = 'form-check-input';
        selectAllCheckbox.dataset.selectAll = 'true';
        
        selectAllTh.appendChild(selectAllCheckbox);
        headerRow.insertBefore(selectAllTh, headerRow.firstChild);

        // Добавляем чекбоксы в строки
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const td = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.dataset.itemCheckbox = 'true';
            checkbox.value = row.dataset.id || '';
            
            td.appendChild(checkbox);
            row.insertBefore(td, row.firstChild);
            row.dataset.selectable = 'true';
        });
    }

    /**
     * Inline редактирование
     */
    initInlineEditing() {
        document.addEventListener('dblclick', (e) => {
            const cell = e.target.closest('td[data-editable]');
            if (!cell || cell.querySelector('input')) return;

            this.makeEditable(cell);
        });
    }

    /**
     * Сделать ячейку редактируемой
     */
    makeEditable(cell) {
        const originalValue = cell.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.value = originalValue;

        cell.textContent = '';
        cell.appendChild(input);
        input.focus();
        input.select();

        const save = () => {
            const newValue = input.value;
            cell.textContent = newValue;

            if (newValue !== originalValue) {
                this.saveEdit(cell, newValue);
            }
        };

        const cancel = () => {
            cell.textContent = originalValue;
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                save();
            } else if (e.key === 'Escape') {
                cancel();
            }
        });
    }

    /**
     * Сохранить изменение
     */
    async saveEdit(cell, value) {
        const row = cell.closest('tr');
        const field = cell.dataset.field;
        const id = row.dataset.id;

        if (!field || !id) return;

        try {
            const response = await fetch(`/api/v1/tasks/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ [field]: value })
            });

            if (response.ok) {
                cell.classList.add('table-success');
                setTimeout(() => cell.classList.remove('table-success'), 2000);
            } else {
                throw new Error('Save failed');
            }
        } catch (error) {
            console.error('Edit save error:', error);
            cell.classList.add('table-danger');
            setTimeout(() => cell.classList.remove('table-danger'), 2000);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.tableEnhancements = new TableEnhancements();
    });
} else {
    window.tableEnhancements = new TableEnhancements();
}

// Экспорт
window.TableEnhancements = TableEnhancements;
