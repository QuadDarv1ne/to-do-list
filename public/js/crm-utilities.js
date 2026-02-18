/**
 * CRM Utilities
 * Common utilities and helpers for the entire CRM system
 */

class CRMUtilities {
    constructor() {
        this.init();
    }

    init() {
        this.setupThemeToggle();
        this.setupSearchShortcut();
        this.setupTooltips();
        this.setupConfirmDialogs();
        this.setupAutoSave();
        this.setupOfflineDetection();
        this.setupPerformanceMonitoring();
    }

    /**
     * Theme Toggle
     */
    setupThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        if (!themeToggle) return;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        this.updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            this.updateThemeIcon(newTheme);
            
            // Animate transition
            document.body.style.transition = 'background-color 0.3s ease';
        });
    }

    updateThemeIcon(theme) {
        const icon = document.querySelector('#themeToggle i');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    /**
     * Global Search Shortcut (Ctrl/Cmd + K)
     */
    setupSearchShortcut() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openGlobalSearch();
            }
        });
    }

    openGlobalSearch() {
        const searchInput = document.querySelector('#globalSearch');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        } else {
            // Create search modal if doesn't exist
            this.createSearchModal();
        }
    }

    createSearchModal() {
        const modal = document.createElement('div');
        modal.id = 'searchModal';
        modal.className = 'search-modal';
        modal.innerHTML = `
            <div class="search-modal-content">
                <div class="search-modal-header">
                    <input type="text" 
                           id="globalSearch" 
                           class="search-modal-input" 
                           placeholder="Поиск задач, проектов, людей..."
                           autofocus>
                    <button class="search-modal-close" onclick="this.closest('.search-modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-modal-results" id="searchResults">
                    <div class="search-modal-empty">
                        <i class="fas fa-search"></i>
                        <p>Начните вводить для поиска</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup search
        const input = modal.querySelector('#globalSearch');
        let searchTimeout;
        
        input.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.performSearch(e.target.value);
            }, 300);
        });
        
        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modal.remove();
            }
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    async performSearch(query) {
        if (!query || query.length < 2) {
            document.getElementById('searchResults').innerHTML = `
                <div class="search-modal-empty">
                    <i class="fas fa-search"></i>
                    <p>Начните вводить для поиска</p>
                </div>
            `;
            return;
        }

        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            this.displaySearchResults(data.results);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    displaySearchResults(results) {
        const container = document.getElementById('searchResults');
        
        if (!results || results.length === 0) {
            container.innerHTML = `
                <div class="search-modal-empty">
                    <i class="fas fa-inbox"></i>
                    <p>Ничего не найдено</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = results.map(result => `
            <a href="${result.url}" class="search-result-item">
                <div class="search-result-icon">
                    <i class="fas fa-${result.icon}"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${result.title}</div>
                    <div class="search-result-description">${result.description}</div>
                </div>
                <div class="search-result-type">${result.type}</div>
            </a>
        `).join('');
    }

    /**
     * Tooltips
     */
    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element) {
        const text = element.dataset.tooltip;
        const position = element.dataset.tooltipPosition || 'top';
        
        const tooltip = document.createElement('div');
        tooltip.className = `crm-tooltip crm-tooltip-${position}`;
        tooltip.textContent = text;
        tooltip.id = 'activeTooltip';
        
        document.body.appendChild(tooltip);
        
        // Position tooltip
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top, left;
        
        switch(position) {
            case 'top':
                top = rect.top - tooltipRect.height - 8;
                left = rect.left + (rect.width - tooltipRect.width) / 2;
                break;
            case 'bottom':
                top = rect.bottom + 8;
                left = rect.left + (rect.width - tooltipRect.width) / 2;
                break;
            case 'left':
                top = rect.top + (rect.height - tooltipRect.height) / 2;
                left = rect.left - tooltipRect.width - 8;
                break;
            case 'right':
                top = rect.top + (rect.height - tooltipRect.height) / 2;
                left = rect.right + 8;
                break;
        }
        
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
        
        setTimeout(() => tooltip.classList.add('show'), 10);
    }

    hideTooltip() {
        const tooltip = document.getElementById('activeTooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
            setTimeout(() => tooltip.remove(), 200);
        }
    }

    /**
     * Confirm Dialogs
     */
    setupConfirmDialogs() {
        document.addEventListener('click', (e) => {
            const confirmBtn = e.target.closest('[data-confirm]');
            if (!confirmBtn) return;
            
            const message = confirmBtn.dataset.confirm;
            const confirmed = confirm(message);
            
            if (!confirmed) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }

    /**
     * Auto-save functionality
     */
    setupAutoSave() {
        const autoSaveForms = document.querySelectorAll('[data-autosave]');
        
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            let saveTimeout;
            
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        this.autoSaveForm(form);
                    }, 2000);
                });
            });
        });
    }

    async autoSaveForm(form) {
        const formData = new FormData(form);
        const url = form.dataset.autosaveUrl || form.action;
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                this.showAutoSaveIndicator('Сохранено');
            }
        } catch (error) {
            console.error('Auto-save error:', error);
        }
    }

    showAutoSaveIndicator(message) {
        let indicator = document.getElementById('autoSaveIndicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autoSaveIndicator';
            indicator.className = 'auto-save-indicator';
            document.body.appendChild(indicator);
        }
        
        indicator.textContent = message;
        indicator.classList.add('show');
        
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }

    /**
     * Offline Detection
     */
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.showConnectionStatus('Соединение восстановлено', 'success');
        });
        
        window.addEventListener('offline', () => {
            this.showConnectionStatus('Нет подключения к интернету', 'error');
        });
    }

    showConnectionStatus(message, type) {
        const notification = document.createElement('div');
        notification.className = `connection-status connection-status-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'wifi' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Performance Monitoring
     */
    setupPerformanceMonitoring() {
        // Monitor page load time
        window.addEventListener('load', () => {
            const perfData = performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            
            console.log(`Page load time: ${pageLoadTime}ms`);
            
            // Send to analytics if needed
            if (pageLoadTime > 3000) {
                console.warn('Slow page load detected');
            }
        });
    }

    /**
     * Format date
     */
    static formatDate(date, format = 'short') {
        const d = new Date(date);
        
        const formats = {
            short: d.toLocaleDateString('ru-RU'),
            long: d.toLocaleDateString('ru-RU', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            }),
            time: d.toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit' 
            }),
            full: d.toLocaleString('ru-RU')
        };
        
        return formats[format] || formats.short;
    }

    /**
     * Format number
     */
    static formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    /**
     * Debounce function
     */
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Copy to clipboard
     */
    static async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (error) {
            console.error('Copy failed:', error);
            return false;
        }
    }
}

// Initialize utilities
document.addEventListener('DOMContentLoaded', () => {
    window.crmUtils = new CRMUtilities();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CRMUtilities;
}

// Add required CSS
const style = document.createElement('style');
style.textContent = `
    /* Search Modal */
    .search-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 10vh;
        z-index: 9999;
        animation: fadeIn 0.2s ease;
    }

    .search-modal-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideDown 0.3s ease;
    }

    .search-modal-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        border-bottom: 2px solid #e9ecef;
    }

    .search-modal-input {
        flex: 1;
        border: none;
        font-size: 1.125rem;
        outline: none;
    }

    .search-modal-close {
        width: 36px;
        height: 36px;
        border: none;
        background: #f8f9fa;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .search-modal-close:hover {
        background: #e9ecef;
    }

    .search-modal-results {
        max-height: 400px;
        overflow-y: auto;
    }

    .search-modal-empty {
        padding: 3rem;
        text-align: center;
        color: #6c757d;
    }

    .search-modal-empty i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    .search-result-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .search-result-item:hover {
        background: #f8f9fa;
    }

    .search-result-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .search-result-content {
        flex: 1;
    }

    .search-result-title {
        font-weight: 600;
        color: #212529;
    }

    .search-result-description {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .search-result-type {
        padding: 0.25rem 0.75rem;
        background: #e9ecef;
        border-radius: 12px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #495057;
    }

    /* Tooltip */
    .crm-tooltip {
        position: fixed;
        background: rgba(0,0,0,0.9);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.875rem;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.2s ease;
        pointer-events: none;
    }

    .crm-tooltip.show {
        opacity: 1;
    }

    /* Auto-save indicator */
    .auto-save-indicator {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 600;
        color: #28a745;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        z-index: 9999;
    }

    .auto-save-indicator.show {
        opacity: 1;
        transform: translateY(0);
    }

    /* Connection status */
    .connection-status {
        position: fixed;
        top: 2rem;
        left: 50%;
        transform: translateX(-50%) translateY(-100px);
        background: white;
        padding: 1rem 2rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        transition: all 0.3s ease;
    }

    .connection-status.show {
        transform: translateX(-50%) translateY(0);
    }

    .connection-status-success {
        border-left: 4px solid #28a745;
    }

    .connection-status-error {
        border-left: 4px solid #dc3545;
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Dark theme */
    [data-theme='dark'] .search-modal-content {
        background: #2d3748;
    }

    [data-theme='dark'] .search-modal-input {
        color: #e2e8f0;
    }

    [data-theme='dark'] .search-result-item {
        border-bottom-color: #4a5568;
    }

    [data-theme='dark'] .search-result-item:hover {
        background: #374151;
    }

    [data-theme='dark'] .search-result-title {
        color: #e2e8f0;
    }

    [data-theme='dark'] .search-result-description {
        color: #a0aec0;
    }

    [data-theme='dark'] .auto-save-indicator,
    [data-theme='dark'] .connection-status {
        background: #2d3748;
    }
`;
document.head.appendChild(style);
