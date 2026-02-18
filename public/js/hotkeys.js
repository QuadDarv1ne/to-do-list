/**
 * Global Hotkeys Manager
 * Centralized keyboard shortcuts management
 */

class HotkeysManager {
    constructor() {
        this.shortcuts = new Map();
        this.enabled = true;
        this.init();
    }
    
    init() {
        this.registerDefaultShortcuts();
        this.bindEvents();
        this.createHelpModal();
    }
    
    registerDefaultShortcuts() {
        // Navigation shortcuts
        this.register('g d', () => this.navigate('/dashboard'), 'Перейти на панель управления');
        this.register('g t', () => this.navigate('/task'), 'Перейти к задачам');
        this.register('g k', () => this.navigate('/kanban'), 'Перейти к канбан-доске');
        this.register('g c', () => this.navigate('/calendar'), 'Перейти к календарю');
        this.register('g u', () => this.navigate('/user'), 'Перейти к пользователям');
        this.register('g p', () => this.navigate('/profile'), 'Перейти к профилю');
        this.register('g s', () => this.navigate('/settings'), 'Перейти к настройкам');
        
        // Action shortcuts
        this.register('n', () => this.createTask(), 'Создать новую задачу');
        this.register('/', () => this.openSearch(), 'Открыть поиск');
        this.register('ctrl+k', () => this.openQuickSearch(), 'Быстрый поиск');
        this.register('?', () => this.showHelp(), 'Показать справку по горячим клавишам');
        this.register('r', () => this.refresh(), 'Обновить страницу');
        this.register('esc', () => this.closeModals(), 'Закрыть модальные окна');
        
        // Theme toggle
        this.register('t', () => this.toggleTheme(), 'Переключить тему');
        
        // Bulk actions (only on list pages)
        this.register('ctrl+a', () => this.selectAll(), 'Выбрать все');
        this.register('ctrl+shift+a', () => this.deselectAll(), 'Снять выделение');
    }
    
    register(keys, callback, description = '') {
        const normalizedKeys = this.normalizeKeys(keys);
        this.shortcuts.set(normalizedKeys, {
            callback,
            description,
            keys: keys
        });
    }
    
    unregister(keys) {
        const normalizedKeys = this.normalizeKeys(keys);
        this.shortcuts.delete(normalizedKeys);
    }
    
    normalizeKeys(keys) {
        return keys.toLowerCase()
            .replace(/\s+/g, ' ')
            .split(' ')
            .map(key => key.trim())
            .filter(key => key.length > 0)
            .join(' ');
    }
    
    bindEvents() {
        let keySequence = [];
        let sequenceTimeout = null;
        
        document.addEventListener('keydown', (e) => {
            // Skip if typing in input/textarea or shortcuts disabled
            if (!this.enabled || ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                return;
            }
            
            // Skip if contenteditable
            if (e.target.isContentEditable) {
                return;
            }
            
            // Build key combination
            const key = this.getKeyString(e);
            
            // Clear previous sequence timeout
            clearTimeout(sequenceTimeout);
            
            // Add to sequence
            keySequence.push(key);
            
            // Try to match shortcut
            const matched = this.matchShortcut(keySequence);
            
            if (matched) {
                e.preventDefault();
                keySequence = [];
                matched.callback();
            } else {
                // Set timeout to reset sequence
                sequenceTimeout = setTimeout(() => {
                    keySequence = [];
                }, 1000);
            }
        });
    }
    
    getKeyString(e) {
        const parts = [];
        
        if (e.ctrlKey) parts.push('ctrl');
        if (e.altKey) parts.push('alt');
        if (e.shiftKey) parts.push('shift');
        if (e.metaKey) parts.push('meta');
        
        // Add the actual key
        const key = e.key.toLowerCase();
        if (!['control', 'alt', 'shift', 'meta'].includes(key)) {
            parts.push(key);
        }
        
        return parts.join('+');
    }
    
    matchShortcut(sequence) {
        const sequenceString = sequence.join(' ');
        
        for (const [keys, shortcut] of this.shortcuts) {
            if (keys === sequenceString) {
                return shortcut;
            }
        }
        
        return null;
    }
    
    // Action handlers
    navigate(path) {
        window.location.href = path;
    }
    
    createTask() {
        const quickTaskBtn = document.getElementById('quick-task-fab');
        if (quickTaskBtn) {
            quickTaskBtn.click();
        } else {
            this.navigate('/task/new');
        }
    }
    
    openSearch() {
        const searchInput = document.querySelector('input[name="search"], input[type="search"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    openQuickSearch() {
        if (window.quickSearch) {
            window.quickSearch.open();
        } else {
            this.openSearch();
        }
    }
    
    refresh() {
        window.location.reload();
    }
    
    closeModals() {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
    }
    
    toggleTheme() {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.click();
        }
    }
    
    selectAll() {
        const selectAllCheckbox = document.getElementById('select-all-tasks');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.dispatchEvent(new Event('change'));
        }
    }
    
    deselectAll() {
        const selectAllCheckbox = document.getElementById('select-all-tasks');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.dispatchEvent(new Event('change'));
        }
    }
    
    showHelp() {
        const modal = document.getElementById('hotkeysHelpModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }
    
    createHelpModal() {
        const shortcuts = Array.from(this.shortcuts.entries());
        const grouped = this.groupShortcuts(shortcuts);
        
        let groupsHTML = '';
        for (const [group, items] of Object.entries(grouped)) {
            groupsHTML += `
                <div class="col-md-6 mb-4">
                    <h6 class="text-uppercase text-muted mb-3">${group}</h6>
                    <div class="list-group list-group-flush">
                        ${items.map(([keys, shortcut]) => `
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${shortcut.description}</span>
                                    <div class="hotkey-combo">
                                        ${this.renderKeys(shortcut.keys)}
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        const modalHTML = `
            <div class="modal fade" id="hotkeysHelpModal" tabindex="-1" aria-labelledby="hotkeysHelpLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="hotkeysHelpLabel">
                                <i class="fas fa-keyboard me-2"></i>
                                Горячие клавиши
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                ${groupsHTML}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    groupShortcuts(shortcuts) {
        const groups = {
            'Навигация': [],
            'Действия': [],
            'Прочее': []
        };
        
        shortcuts.forEach(([keys, shortcut]) => {
            if (keys.startsWith('g ')) {
                groups['Навигация'].push([keys, shortcut]);
            } else if (['n', 'ctrl+k', '/'].includes(keys)) {
                groups['Действия'].push([keys, shortcut]);
            } else {
                groups['Прочее'].push([keys, shortcut]);
            }
        });
        
        return groups;
    }
    
    renderKeys(keys) {
        return keys.split(' ')
            .map(combo => {
                const parts = combo.split('+');
                return parts.map(key => {
                    const displayKey = this.getKeyDisplay(key);
                    return `<kbd>${displayKey}</kbd>`;
                }).join('<span class="mx-1">+</span>');
            })
            .join('<span class="mx-2">затем</span>');
    }
    
    getKeyDisplay(key) {
        const displays = {
            'ctrl': 'Ctrl',
            'alt': 'Alt',
            'shift': 'Shift',
            'meta': 'Cmd',
            'escape': 'Esc',
            'arrowup': '↑',
            'arrowdown': '↓',
            'arrowleft': '←',
            'arrowright': '→',
            'enter': 'Enter',
            ' ': 'Space'
        };
        
        return displays[key.toLowerCase()] || key.toUpperCase();
    }
    
    enable() {
        this.enabled = true;
    }
    
    disable() {
        this.enabled = false;
    }
}

// Styles for hotkeys
const hotkeysStyles = `
<style>
.hotkey-combo {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.hotkey-combo kbd {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.75rem;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 24px;
    text-align: center;
}

[data-theme="dark"] .hotkey-combo kbd {
    background: linear-gradient(135deg, #343a40 0%, #495057 100%);
    border-color: #495057;
    color: #f8f9fa;
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', hotkeysStyles);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.hotkeysManager = new HotkeysManager();
});
