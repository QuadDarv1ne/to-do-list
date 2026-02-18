/**
 * CRM Utilities
 * Вспомогательные функции для CRM системы
 */

class CRMUtilities {
    constructor() {
        this.init();
    }

    init() {
        this.initExportFunctions();
        this.initBulkActions();
        this.initAdvancedFilters();
        this.initDataVisualization();
    }

    /**
     * Экспорт данных
     */
    initExportFunctions() {
        // Экспорт в CSV
        window.exportToCSV = (data, filename = 'export.csv') => {
            const csv = this.convertToCSV(data);
            this.downloadFile(csv, filename, 'text/csv');
        };

        // Экспорт в JSON
        window.exportToJSON = (data, filename = 'export.json') => {
            const json = JSON.stringify(data, null, 2);
            this.downloadFile(json, filename, 'application/json');
        };

        // Экспорт в Excel (простой формат)
        window.exportToExcel = (data, filename = 'export.xlsx') => {
            const csv = this.convertToCSV(data);
            this.downloadFile(csv, filename, 'application/vnd.ms-excel');
        };
    }

    /**
     * Конвертация данных в CSV
     */
    convertToCSV(data) {
        if (!data || data.length === 0) return '';

        const headers = Object.keys(data[0]);
        const csvRows = [];

        // Добавляем заголовки
        csvRows.push(headers.join(','));

        // Добавляем данные
        for (const row of data) {
            const values = headers.map(header => {
                const value = row[header];
                const escaped = ('' + value).replace(/"/g, '\\"');
                return `"${escaped}"`;
            });
            csvRows.push(values.join(','));
        }

        return csvRows.join('\n');
    }

    /**
     * Скачивание файла
     */
    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    /**
     * Массовые операции
     */
    initBulkActions() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bulk-action]')) {
                const action = e.target.dataset.bulkAction;
                const selected = this.getSelectedItems();
                
                if (selected.length === 0) {
                    this.showToast('Выберите элементы для выполнения действия', 'warning');
                    return;
                }

                this.executeBulkAction(action, selected);
            }
        });

        // Выбор всех элементов
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-select-all]')) {
                const checkboxes = document.querySelectorAll('[data-item-checkbox]');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                this.updateBulkActionsUI();
            }

            if (e.target.matches('[data-item-checkbox]')) {
                this.updateBulkActionsUI();
            }
        });
    }

    /**
     * Получить выбранные элементы
     */
    getSelectedItems() {
        const checkboxes = document.querySelectorAll('[data-item-checkbox]:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    /**
     * Обновить UI массовых операций
     */
    updateBulkActionsUI() {
        const selected = this.getSelectedItems();
        const bulkActionsBar = document.querySelector('[data-bulk-actions-bar]');
        const selectedCount = document.querySelector('[data-selected-count]');

        if (bulkActionsBar) {
            bulkActionsBar.classList.toggle('show', selected.length > 0);
        }

        if (selectedCount) {
            selectedCount.textContent = selected.length;
        }
    }

    /**
     * Выполнить массовое действие
     */
    async executeBulkAction(action, items) {
        const confirmMessage = this.getBulkActionConfirmMessage(action, items.length);
        
        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }

        try {
            const response = await fetch(`/api/bulk/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ items })
            });

            if (response.ok) {
                this.showToast(`Действие "${action}" выполнено для ${items.length} элементов`, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Ошибка выполнения действия');
            }
        } catch (error) {
            console.error('Bulk action error:', error);
            this.showToast('Ошибка выполнения массового действия', 'error');
        }
    }

    /**
     * Получить сообщение подтверждения для массового действия
     */
    getBulkActionConfirmMessage(action, count) {
        const messages = {
            'delete': `Вы уверены, что хотите удалить ${count} элементов?`,
            'archive': `Архивировать ${count} элементов?`,
            'complete': `Отметить ${count} задач как выполненные?`
        };
        return messages[action] || null;
    }

    /**
     * Расширенные фильтры
     */
    initAdvancedFilters() {
        const filterForm = document.querySelector('[data-filter-form]');
        if (!filterForm) return;

        // Применение фильтров
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters(new FormData(filterForm));
        });

        // Сброс фильтров
        const resetBtn = filterForm.querySelector('[data-reset-filters]');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                filterForm.reset();
                this.applyFilters(new FormData(filterForm));
            });
        }

        // Сохранение фильтров
        const saveBtn = filterForm.querySelector('[data-save-filters]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveFilters(new FormData(filterForm));
            });
        }
    }

    /**
     * Применить фильтры
     */
    applyFilters(formData) {
        const params = new URLSearchParams(formData);
        const url = new URL(window.location);
        
        // Очищаем старые параметры фильтрации
        for (const key of url.searchParams.keys()) {
            if (key.startsWith('filter_')) {
                url.searchParams.delete(key);
            }
        }

        // Добавляем новые параметры
        for (const [key, value] of params.entries()) {
            if (value) {
                url.searchParams.set(key, value);
            }
        }

        window.location.href = url.toString();
    }

    /**
     * Сохранить фильтры
     */
    saveFilters(formData) {
        const filters = {};
        for (const [key, value] of formData.entries()) {
            if (value) filters[key] = value;
        }

        const name = prompt('Введите название для сохранения фильтров:');
        if (!name) return;

        const savedFilters = JSON.parse(localStorage.getItem('savedFilters') || '{}');
        savedFilters[name] = filters;
        localStorage.setItem('savedFilters', JSON.stringify(savedFilters));

        this.showToast(`Фильтры сохранены как "${name}"`, 'success');
        this.updateSavedFiltersList();
    }

    /**
     * Обновить список сохраненных фильтров
     */
    updateSavedFiltersList() {
        const container = document.querySelector('[data-saved-filters]');
        if (!container) return;

        const savedFilters = JSON.parse(localStorage.getItem('savedFilters') || '{}');
        const names = Object.keys(savedFilters);

        if (names.length === 0) {
            container.innerHTML = '<p class="text-muted small">Нет сохраненных фильтров</p>';
            return;
        }

        container.innerHTML = names.map(name => `
            <button class="btn btn-sm btn-outline-primary me-2 mb-2" 
                    data-load-filter="${name}">
                ${name}
            </button>
        `).join('');

        // Загрузка сохраненных фильтров
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-load-filter]');
            if (btn) {
                const name = btn.dataset.loadFilter;
                this.loadSavedFilters(name);
            }
        });
    }

    /**
     * Загрузить сохраненные фильтры
     */
    loadSavedFilters(name) {
        const savedFilters = JSON.parse(localStorage.getItem('savedFilters') || '{}');
        const filters = savedFilters[name];

        if (!filters) return;

        const filterForm = document.querySelector('[data-filter-form]');
        if (!filterForm) return;

        // Заполняем форму
        for (const [key, value] of Object.entries(filters)) {
            const input = filterForm.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value;
            }
        }

        this.applyFilters(new FormData(filterForm));
    }

    /**
     * Визуализация данных
     */
    initDataVisualization() {
        // Инициализация графиков
        this.initCharts();
        
        // Инициализация статистических карточек
        this.initStatCards();
    }

    /**
     * Инициализация графиков
     */
    initCharts() {
        const chartContainers = document.querySelectorAll('[data-chart]');
        
        chartContainers.forEach(container => {
            const type = container.dataset.chart;
            const dataUrl = container.dataset.chartData;

            if (dataUrl) {
                fetch(dataUrl)
                    .then(res => res.json())
                    .then(data => this.renderChart(container, type, data))
                    .catch(err => console.error('Chart data error:', err));
            }
        });
    }

    /**
     * Отрисовка графика
     */
    renderChart(container, type, data) {
        // Простая реализация без внешних библиотек
        // Для production рекомендуется использовать Chart.js или подобные
        
        if (type === 'bar') {
            this.renderBarChart(container, data);
        } else if (type === 'line') {
            this.renderLineChart(container, data);
        } else if (type === 'pie') {
            this.renderPieChart(container, data);
        }
    }

    /**
     * Столбчатая диаграмма
     */
    renderBarChart(container, data) {
        const max = Math.max(...data.values);
        const html = data.labels.map((label, i) => {
            const value = data.values[i];
            const height = (value / max) * 100;
            return `
                <div class="chart-bar" style="flex: 1; text-align: center;">
                    <div class="bar" style="height: ${height}%; background: var(--primary); margin: 0 5px; border-radius: 4px 4px 0 0;"></div>
                    <div class="label" style="font-size: 0.75rem; margin-top: 5px;">${label}</div>
                    <div class="value" style="font-size: 0.875rem; font-weight: 600;">${value}</div>
                </div>
            `;
        }).join('');

        container.innerHTML = `<div style="display: flex; align-items: flex-end; height: 200px;">${html}</div>`;
    }

    /**
     * Линейный график
     */
    renderLineChart(container, data) {
        // Упрощенная реализация
        const canvas = document.createElement('canvas');
        canvas.width = container.clientWidth;
        canvas.height = 200;
        container.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        const max = Math.max(...data.values);
        const step = canvas.width / (data.values.length - 1);

        ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--primary');
        ctx.lineWidth = 2;
        ctx.beginPath();

        data.values.forEach((value, i) => {
            const x = i * step;
            const y = canvas.height - (value / max) * canvas.height;
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        ctx.stroke();
    }

    /**
     * Круговая диаграмма
     */
    renderPieChart(container, data) {
        const total = data.values.reduce((sum, val) => sum + val, 0);
        const colors = ['#667eea', '#f97316', '#a855f7', '#22c55e', '#ef4444'];

        const html = data.labels.map((label, i) => {
            const value = data.values[i];
            const percentage = ((value / total) * 100).toFixed(1);
            const color = colors[i % colors.length];

            return `
                <div class="pie-item" style="display: flex; align-items: center; margin-bottom: 10px;">
                    <div class="pie-color" style="width: 20px; height: 20px; background: ${color}; border-radius: 4px; margin-right: 10px;"></div>
                    <div class="pie-label" style="flex: 1;">${label}</div>
                    <div class="pie-value" style="font-weight: 600;">${percentage}%</div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
    }

    /**
     * Инициализация статистических карточек
     */
    initStatCards() {
        const statCards = document.querySelectorAll('[data-stat-card]');
        
        statCards.forEach(card => {
            const dataUrl = card.dataset.statCard;
            
            if (dataUrl) {
                fetch(dataUrl)
                    .then(res => res.json())
                    .then(data => this.updateStatCard(card, data))
                    .catch(err => console.error('Stat card error:', err));
            }
        });
    }

    /**
     * Обновить статистическую карточку
     */
    updateStatCard(card, data) {
        const valueEl = card.querySelector('[data-stat-value]');
        const changeEl = card.querySelector('[data-stat-change]');

        if (valueEl) {
            this.animateValue(valueEl, 0, data.value, 1000);
        }

        if (changeEl && data.change !== undefined) {
            const isPositive = data.change >= 0;
            changeEl.textContent = `${isPositive ? '+' : ''}${data.change}%`;
            changeEl.className = `stat-change ${isPositive ? 'text-success' : 'text-danger'}`;
        }
    }

    /**
     * Анимация значения
     */
    animateValue(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }

            element.textContent = Math.round(current);
        }, 16);
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.crmUtilities = new CRMUtilities();
    });
} else {
    window.crmUtilities = new CRMUtilities();
}

// Экспорт
window.CRMUtilities = CRMUtilities;
