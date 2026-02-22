import { Controller } from '@hotwired/stimulus';
import { useTransition } from '@symfony/ux-stimulus-basis';

export class CommandPaletteController extends Controller {
    static targets = ['input', 'results', 'item'];
    static values = {
        searchUrl: { type: String, default: '/quick-search' },
        maxResults: { type: Number, default: 10 }
    };

    connect() {
        this.isOpen = false;
        this.selectedIndex = -1;
        this.results = [];
        this.debounceTimer = null;

        // Закрытие по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.toggle();
            }
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Закрытие при клике вне
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.element.contains(e.target)) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.element.classList.remove('command-palette-hidden');
        this.element.classList.add('command-palette-visible');
        this.inputTarget?.focus();
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.isOpen = false;
        this.element.classList.remove('command-palette-visible');
        this.element.classList.add('command-palette-hidden');
        document.body.style.overflow = '';
        this.clearResults();
    }

    search(event) {
        const query = event.target.value.trim();

        if (query.length < 2) {
            this.clearResults();
            return;
        }

        // Debounce
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    async performSearch(query) {
        this.query = query;
        try {
            const response = await fetch(
                `${this.searchUrlValue}?q=${encodeURIComponent(query)}`
            );
            const data = await response.json();
            this.renderResults(data.results || []);
        } catch (error) {
            console.error('Search error:', error);
            this.renderResults({ tasks: [], commands: [] });
        }
    }

    renderResults(results) {
        this.results = results;
        this.selectedIndex = -1;

        if (!results || (results.tasks?.length === 0 && results.commands?.length === 0)) {
            this.resultsTarget.innerHTML = `
                <div class="command-palette-empty">
                    <i class="fas fa-search"></i>
                    <span>Ничего не найдено</span>
                </div>
            `;
            return;
        }

        let html = '';

        // Tasks
        if (results.tasks?.length > 0) {
            html += '<div class="command-palette-section"><div class="command-palette-section-title">Задачи</div>';
            results.tasks.forEach((task, index) => {
                html += `
                    <div class="command-palette-item" data-index="task-${index}" data-url="${task.url}">
                        <div class="command-palette-item-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="command-palette-item-content">
                            <div class="command-palette-item-title">${this.highlightMatch(task.title, this.query)}</div>
                            <div class="command-palette-item-subtitle">${task.status || ''}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Commands
        if (results.commands?.length > 0) {
            html += '<div class="command-palette-section"><div class="command-palette-section-title">Действия</div>';
            results.commands.forEach((cmd, index) => {
                html += `
                    <div class="command-palette-item" data-index="cmd-${index}" data-url="${cmd.url}">
                        <div class="command-palette-item-icon">
                            <i class="fas ${cmd.icon || 'fa-bolt'}"></i>
                        </div>
                        <div class="command-palette-item-content">
                            <div class="command-palette-item-title">${cmd.name}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        this.resultsTarget.innerHTML = html;

        // Add click handlers
        this.resultsTarget.querySelectorAll('.command-palette-item').forEach(item => {
            item.addEventListener('click', () => {
                const url = item.dataset.url;
                if (url) window.location.href = url;
            });
        });
    }

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    clearResults() {
        this.results = [];
        this.resultsTarget.innerHTML = '';
    }

    navigate(event) {
        const { key } = event;

        if (key === 'ArrowDown') {
            event.preventDefault();
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
            this.updateSelection();
        } else if (key === 'ArrowUp') {
            event.preventDefault();
            this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
            this.updateSelection();
        } else if (key === 'Enter' && this.selectedIndex >= 0) {
            event.preventDefault();
            this.select(this.selectedIndex);
        }
    }

    updateSelection() {
        const items = this.resultsTarget.querySelectorAll('.command-palette-item');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === this.selectedIndex);
        });

        // Scroll into view
        const activeItem = items[this.selectedIndex];
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }

    select(index) {
        const result = this.results[index];
        if (result && result.url) {
            window.location.href = result.url;
        }
        this.close();
    }

    // Keyboard shortcuts для быстрых действий
    executeAction(event) {
        const action = event.target.dataset.action;
        switch (action) {
            case 'new-task':
                window.location.href = '/tasks/new';
                break;
            case 'new-deal':
                window.location.href = '/deals/new';
                break;
            case 'new-client':
                window.location.href = '/clients/new';
                break;
            case 'dashboard':
                window.location.href = '/dashboard';
                break;
            case 'settings':
                window.location.href = '/settings';
                break;
            default:
                break;
        }
        this.close();
    }
}
