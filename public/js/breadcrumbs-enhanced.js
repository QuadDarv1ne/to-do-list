/**
 * Enhanced Breadcrumbs
 * Улучшенные хлебные крошки с историей навигации
 */

class EnhancedBreadcrumbs {
    constructor() {
        this.history = [];
        this.maxHistory = 10;
        this.container = null;
        this.init();
    }

    init() {
        this.loadHistory();
        this.trackNavigation();
        this.createContainer();
    }

    trackNavigation() {
        // Добавляем текущую страницу в историю
        this.addToHistory({
            url: window.location.pathname,
            title: document.title,
            timestamp: Date.now()
        });

        // Отслеживаем переходы
        window.addEventListener('popstate', () => {
            this.addToHistory({
                url: window.location.pathname,
                title: document.title,
                timestamp: Date.now()
            });
            this.updateBreadcrumbs();
        });
    }

    addToHistory(page) {
        // Не добавляем дубликаты подряд
        if (this.history.length > 0) {
            const last = this.history[this.history.length - 1];
            if (last.url === page.url) return;
        }

        this.history.push(page);

        // Ограничиваем размер истории
        if (this.history.length > this.maxHistory) {
            this.history.shift();
        }

        this.saveHistory();
        this.updateBreadcrumbs();
    }

    createContainer() {
        // Ищем существующий контейнер breadcrumbs
        let existing = document.querySelector('.breadcrumb, nav[aria-label="breadcrumb"]');
        
        if (!existing) {
            this.container = document.createElement('nav');
            this.container.className = 'breadcrumbs-enhanced';
            this.container.setAttribute('aria-label', 'breadcrumb');
            
            const main = document.querySelector('main');
            if (main) {
                main.insertBefore(this.container, main.firstChild);
            }
        } else {
            this.container = existing;
        }

        this.updateBreadcrumbs();
        this.addStyles();
    }

    updateBreadcrumbs() {
        if (!this.container || this.history.length === 0) return;

        const items = this.history.slice(-3).map((page, index, arr) => {
            const isLast = index === arr.length - 1;
            const title = this.formatTitle(page.title);
            
            if (isLast) {
                return `<span class="breadcrumb-item active">${title}</span>`;
            } else {
                return `<a href="${page.url}" class="breadcrumb-item">${title}</a>`;
            }
        });

        this.container.innerHTML = `
            <div class="breadcrumb-list">
                <a href="/" class="breadcrumb-item">
                    <i class="fas fa-home"></i>
                </a>
                ${items.join('<span class="breadcrumb-separator">/</span>')}
            </div>
        `;
    }

    formatTitle(title) {
        // Убираем название сайта из заголовка
        return title.split('|')[0].split('-')[0].trim();
    }

    saveHistory() {
        try {
            localStorage.setItem('navigationHistory', JSON.stringify(this.history));
        } catch (e) {
            console.error('Failed to save navigation history:', e);
        }
    }

    loadHistory() {
        try {
            const saved = localStorage.getItem('navigationHistory');
            if (saved) {
                this.history = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load navigation history:', e);
            this.history = [];
        }
    }

    addStyles() {
        if (document.getElementById('breadcrumbsEnhancedStyles')) return;

        const style = document.createElement('style');
        style.id = 'breadcrumbsEnhancedStyles';
        style.textContent = `
            .breadcrumbs-enhanced {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }

            .breadcrumb-list {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.5rem;
                font-size: 0.875rem;
            }

            .breadcrumb-item {
                color: var(--text-secondary);
                text-decoration: none;
                transition: color 0.2s ease;
                display: flex;
                align-items: center;
            }

            .breadcrumb-item:hover {
                color: var(--primary);
            }

            .breadcrumb-item.active {
                color: var(--text-primary);
                font-weight: 600;
            }

            .breadcrumb-separator {
                color: var(--text-secondary);
                opacity: 0.5;
            }

            body.focus-mode-active .breadcrumbs-enhanced {
                display: none;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.enhancedBreadcrumbs = new EnhancedBreadcrumbs();
});

window.EnhancedBreadcrumbs = EnhancedBreadcrumbs;
