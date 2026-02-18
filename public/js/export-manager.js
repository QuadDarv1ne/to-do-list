/**
 * Export Manager
 * Экспорт данных в различные форматы
 */

class ExportManager {
    constructor() {
        this.formats = ['csv', 'json', 'excel', 'pdf'];
        this.init();
    }

    init() {
        this.setupExportButtons();
    }

    setupExportButtons() {
        // Добавляем кнопки экспорта к таблицам
        const tables = document.querySelectorAll('table');
        
        tables.forEach(table => {
            if (!table.dataset.exportEnabled) {
                this.addExportButton(table);
                table.dataset.exportEnabled = 'true';
            }
        });
    }

    addExportButton(table) {
        const container = table.parentElement;
        
        // Создаем контейнер для кнопок экспорта
        const exportContainer = document.createElement('div');
        exportContainer.className = 'export-container';
        exportContainer.innerHTML = `
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Экспорт
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" data-format="csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-format="json">
                        <i class="fas fa-file-code"></i> JSON
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-format="excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-format="pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a></li>
                </ul>
            </div>
        `;

        // Вставляем перед таблицей
        container.insertBefore(exportContainer, table);

        // Обработчики кликов
        exportContainer.querySelectorAll('[data-format]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const format = btn.dataset.format;
                this.exportTable(table, format);
            });
        });
    }

    exportTable(table, format) {
        const data = this.extractTableData(table);
        
        switch(format) {
            case 'csv':
                this.exportToCSV(data);
                break;
            case 'json':
                this.exportToJSON(data);
                break;
            case 'excel':
                this.exportToExcel(data);
                break;
            case 'pdf':
                this.exportToPDF(data, table);
                break;
        }
    }

    extractTableData(table) {
        const data = [];
        const headers = [];

        // Извлекаем заголовки
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            headers.push(cell.textContent.trim());
        });

        // Извлекаем данные
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const rowData = {};
            const cells = row.querySelectorAll('td');
            
            cells.forEach((cell, index) => {
                const header = headers[index] || `Column ${index + 1}`;
                rowData[header] = cell.textContent.trim();
            });

            data.push(rowData);
        });

        return { headers, data };
    }

    exportToCSV(tableData) {
        const { headers, data } = tableData;
        
        // Создаем CSV
        let csv = headers.join(',') + '\n';
        
        data.forEach(row => {
            const values = headers.map(header => {
                const value = row[header] || '';
                // Экранируем запятые и кавычки
                return `"${value.replace(/"/g, '""')}"`;
            });
            csv += values.join(',') + '\n';
        });

        // Скачиваем файл
        this.downloadFile(csv, 'export.csv', 'text/csv');
    }

    exportToJSON(tableData) {
        const { data } = tableData;
        const json = JSON.stringify(data, null, 2);
        this.downloadFile(json, 'export.json', 'application/json');
    }

    exportToExcel(tableData) {
        const { headers, data } = tableData;
        
        // Создаем HTML таблицу для Excel
        let html = '<table>';
        html += '<thead><tr>';
        headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        html += '</tr></thead>';
        
        html += '<tbody>';
        data.forEach(row => {
            html += '<tr>';
            headers.forEach(header => {
                html += `<td>${row[header] || ''}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';

        // Скачиваем как Excel файл
        this.downloadFile(html, 'export.xls', 'application/vnd.ms-excel');
    }

    exportToPDF(tableData, table) {
        // Для PDF используем window.print с настройками
        const printWindow = window.open('', '_blank');
        
        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Экспорт в PDF</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    @media print {
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                <h1>Экспорт данных</h1>
                <p>Дата: ${new Date().toLocaleString('ru-RU')}</p>
                ${table.outerHTML}
                <br>
                <button onclick="window.print()">Печать / Сохранить как PDF</button>
            </body>
            </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();
    }

    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        
        // Очистка
        setTimeout(() => {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 100);
    }

    // Экспорт произвольных данных
    exportData(data, filename, format = 'json') {
        switch(format) {
            case 'json':
                const json = JSON.stringify(data, null, 2);
                this.downloadFile(json, filename, 'application/json');
                break;
            case 'csv':
                // Конвертируем объект в CSV
                if (Array.isArray(data) && data.length > 0) {
                    const headers = Object.keys(data[0]);
                    const tableData = { headers, data };
                    this.exportToCSV(tableData);
                }
                break;
        }
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.exportManager = new ExportManager();
    
    // Добавляем глобальную функцию экспорта
    window.exportData = (data, filename, format) => {
        window.exportManager.exportData(data, filename, format);
    };
});

// Экспорт
window.ExportManager = ExportManager;
