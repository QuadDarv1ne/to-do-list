/**
 * Table Enhancements
 * Advanced table functionality with sorting, filtering, and export
 */

(function() {
    'use strict';

    class TableEnhancer {
        constructor(table) {
            this.table = table;
            this.tbody = table.querySelector('tbody');
            this.thead = table.querySelector('thead');
            this.rows = Array.from(this.tbody?.querySelectorAll('tr') || []);
            this.currentSort = { column: null, direction: 'asc' };
            this.init();
        }

        init() {
            this.setupSorting();
            this.setupRowSelection();
            this.setupRowHover();
            this.addTableControls();
        }

        setupSorting() {
            if (!this.thead) return;

            const headers = this.thead.querySelectorAll('th[data-sortable]');
            headers.forEach((header, index) => {
                header.style.cursor = 'pointer';
                header.style.userSelect = 'none';
                header.classList.add('sortable-header');
                
                // Add sort icon
                const icon = document.createElement('i');
                icon.className = 'fas fa-sort ms-2 sort-icon';
                header.appendChild(icon);

                header.addEventListener('click', () => {
                    this.sortByColumn(index, header);
                });
            });
        }

        sortByColumn(columnIndex, header) {
            const direction = this.currentSort.column === columnIndex && this.currentSort.direction === 'asc' 
                ? 'desc' 
                : 'asc';

            // Update sort icons
            this.thead.querySelectorAll('.sort-icon').forEach(icon => {
                icon.className = 'fas fa-sort ms-2 sort-icon';
            });

            const icon = header.querySelector('.sort-icon');
            icon.className = `fas fa-sort-${direction === 'asc' ? 'up' : 'down'} ms-2 sort-icon`;

            // Sort rows
            this.rows.sort((a, b) => {
                const aValue = a.cells[columnIndex]?.textContent.trim() || '';
                const bValue = b.cells[columnIndex]?.textContent.trim() || '';

                // Try to parse as numbers
                const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
                const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }

                // String comparison
                return direction === 'asc' 
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });

            // Re-append rows
            this.rows.forEach(row => this.tbody.appendChild(row));

            this.currentSort = { column: columnIndex, direction };
        }

        setupRowSelection() {
            const selectAllCheckbox = this.table.querySelector('thead input[type="checkbox"]');
            const rowCheckboxes = this.table.querySelectorAll('tbody input[type="checkbox"]');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', (e) => {
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = e.target.checked;
                        this.toggleRowHighlight(checkbox.closest('tr'), e.target.checked);
                    });
                    this.updateSelectionCount();
                });
            }

            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    this.toggleRowHighlight(e.target.closest('tr'), e.target.checked);
                    this.updateSelectionCount();
                    
                    // Update select all checkbox
                    if (selectAllCheckbox) {
                        const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        }

        toggleRowHighlight(row, selected) {
            if (selected) {
                row.classList.add('table-row-selected');
            } else {
                row.classList.remove('table-row-selected');
            }
        }

        updateSelectionCount() {
            const selected = this.table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
            const event = new CustomEvent('selectionChange', { 
                detail: { count: selected } 
            });
            this.table.dispatchEvent(event);
        }

        setupRowHover() {
            this.rows.forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.transform = 'scale(1.01)';
                    row.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                });

                row.addEventListener('mouseleave', () => {
                    row.style.transform = 'scale(1)';
                    row.style.boxShadow = 'none';
                });
            });
        }

        addTableControls() {
            if (this.table.hasAttribute('data-no-controls')) return;

            const controls = document.createElement('div');
            controls.className = 'table-controls mb-3 d-flex justify-content-between align-items-center';
            controls.innerHTML = `
                <div class="table-search">
                    <input type="text" class="form-control" placeholder="Поиск в таблице..." style="width: 300px;">
                </div>
                <div class="table-actions">
                    <button class="btn btn-sm btn-outline-primary" data-action="export-csv">
                        <i class="fas fa-download me-1"></i>Экспорт CSV
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-action="print">
                        <i class="fas fa-print me-1"></i>Печать
                    </button>
                </div>
            `;

            this.table.parentNode.insertBefore(controls, this.table);

            // Setup search
            const searchInput = controls.querySelector('input');
            searchInput.addEventListener('input', (e) => {
                this.filterRows(e.target.value);
            });

            // Setup export
            controls.querySelector('[data-action="export-csv"]').addEventListener('click', () => {
                this.exportToCSV();
            });

            // Setup print
            controls.querySelector('[data-action="print"]').addEventListener('click', () => {
                this.printTable();
            });
        }

        filterRows(searchTerm) {
            const term = searchTerm.toLowerCase();
            
            this.rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        exportToCSV() {
            const rows = [];
            
            // Add headers
            const headers = Array.from(this.thead.querySelectorAll('th'))
                .map(th => th.textContent.trim())
                .filter(text => text && !text.includes('☐')); // Remove checkbox column
            rows.push(headers);

            // Add data rows
            this.rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = Array.from(row.querySelectorAll('td'))
                        .map(td => {
                            // Skip checkbox cells
                            if (td.querySelector('input[type="checkbox"]')) return null;
                            return td.textContent.trim();
                        })
                        .filter(cell => cell !== null);
                    rows.push(cells);
                }
            });

            // Create CSV
            const csv = rows.map(row => 
                row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',')
            ).join('\n');

            // Download
            const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `table-export-${Date.now()}.csv`;
            link.click();

            window.notify?.success('Таблица экспортирована в CSV');
        }

        printTable() {
            const printWindow = window.open('', '', 'width=800,height=600');
            const tableClone = this.table.cloneNode(true);
            
            // Remove checkboxes and action columns
            tableClone.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.remove());
            tableClone.querySelectorAll('.dropdown, .btn-group').forEach(el => el.remove());

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Печать таблицы</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; font-weight: bold; }
                        @media print {
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h2>Таблица данных</h2>
                    ${tableClone.outerHTML}
                    <button onclick="window.print()" style="margin-top: 20px; padding: 10px 20px;">Печать</button>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    }

    // Auto-initialize tables
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('table[data-enhanced]').forEach(table => {
            new TableEnhancer(table);
        });
    });

    // Expose globally
    window.TableEnhancer = TableEnhancer;

})();

// Add CSS for table enhancements
const style = document.createElement('style');
style.textContent = `
    .sortable-header {
        position: relative;
        transition: background-color 0.2s;
    }

    .sortable-header:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .sort-icon {
        font-size: 0.75rem;
        opacity: 0.5;
        transition: opacity 0.2s;
    }

    .sortable-header:hover .sort-icon {
        opacity: 1;
    }

    .table-row-selected {
        background-color: rgba(13, 110, 253, 0.1) !important;
    }

    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table-controls {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }

    [data-theme='dark'] .table-controls {
        background: #2d3748;
    }

    [data-theme='dark'] .sortable-header:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    [data-theme='dark'] .table-row-selected {
        background-color: rgba(66, 153, 225, 0.2) !important;
    }
`;
document.head.appendChild(style);
