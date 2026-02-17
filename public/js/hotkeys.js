/**
 * Global Hotkeys System
 * Provides keyboard shortcuts for quick navigation and actions
 */

class HotkeyManager {
    constructor() {
        this.shortcuts = new Map();
        this.sequenceKeys = [];
        this.sequenceTimeout = null;
        this.isModalOpen = false;
        
        this.init();
    }
    
    init() {
        // Register default shortcuts
        this.registerDefaults();
        
        // Listen for keydown events
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        
        // Track modal state
        this.trackModals();
    }
    
    registerDefaults() {
        // Global shortcuts
        this.register('ctrl+k', () => this.openQuickSearch(), 'Быстрый поиск');
        this.register('ctrl+n', () => this.createNewTask(), 'Новая задача');
        this.register('ctrl+/', () => this.showShortcuts(), 'Показать горячие клавиши');
        this.register('escape', () => this.closeModal(), 'Закрыть модальное окно');
        
        // Navigation shortcuts (sequence)
        this.register('g d', () => this.navigate('/'), 'Перейти к дашборду');
        this.register('g t', () => this.navigate('/tasks'), 'Перейти к задачам');
        this.register('g k', () => this.navigate('/kanban'), 'Перейти к канбану');
        this.register('g r', () => this.navigate('/reports'), 'Перейти к отчетам');
        this.register('g a', () => this.navigate('/analytics/advanced'), 'Перейти к аналитике');
        this.register('g s', () => this.navigate('/settings'), 'Перейти к настройкам');
        
        // Task actions (when on task page)
        this.register('e', () => this.editCurrentTask(), 'Редактировать задачу');
        this.register('d', () => this.deleteCurrentTask(), 'Удалить задачу');
        this.register('c', () => this.completeCurrentTask(), 'Отметить как завершенную');
        this.register('a', () => this.assignCurrentTask(), 'Назначить пользователю');
    }
    
    register(keys, callback, description = '') {
        const normalizedKeys = this.normalizeKeys(keys);
        this.shortcuts.set(normalizedKeys, { callback, description });
    }
    
    normalizeKeys(keys) {
        return keys.toLowerCase()
            .replace(/\s+/g, ' ')
            .split(' ')
            .map(key => key.split('+').sort().join('+'))
            .join(' ');
    }
    
    handleKeyDown(event) {
        // Ignore if typing in input/textarea
        if (this.isTyping(event.target)) {
            return;
        }
        
        const key = this.getKeyString(event);
        
        // Handle sequence keys (like "g d")
        if (this.sequenceKeys.length > 0 || this.isSequenceStarter(key)) {
            this.handleSequence(key, event);
            return;
        }
        
        // Handle single key shortcuts
        const shortcut = this.shortcuts.get(key);
        if (shortcut) {
            event.preventDefault();
            shortcut.callback();
        }
    }
    
    handleSequence(key, event) {
        this.sequenceKeys.push(key);
        
        // Clear previous timeout
        if (this.sequenceTimeout) {
            clearTimeout(this.sequenceTimeout);
        }
        
        // Check if we have a matching shortcut
        const sequenceString = this.sequenceKeys.join(' ');
        const shortcut = this.shortcuts.get(sequenceString);
        
        if (shortcut) {
            event.preventDefault();
            shortcut.callback();
            this.sequenceKeys = [];
        } else {
            // Wait for next key (1 second timeout)
            this.sequenceTimeout = setTimeout(() => {
                this.sequenceKeys = [];
            }, 1000);
        }
    }
    
    isSequenceStarter(key) {
        // Keys that start sequences (like 'g' for navigation)
        return key === 'g';
    }
    
    getKeyString(event) {
        const parts = [];
        
        if (event.ctrlKey) parts.push('ctrl');
        if (event.altKey) parts.push('alt');
        if (event.shiftKey && event.key.length > 1) parts.push('shift');
        if (event.metaKey) parts.push('meta');
        
        const key = event.key.toLowerCase();
        if (key !== 'control' && key !== 'alt' && key !== 'shift' && key !== 'meta') {
            parts.push(key);
        }
        
        return parts.sort().join('+');
    }
    
    isTyping(element) {
        const tagName = element.tagName.toLowerCase();
        return (
            tagName === 'input' ||
            tagName === 'textarea' ||
            tagName === 'select' ||
            element.isContentEditable
        );
    }
    
    trackModals() {
        // Track Bootstrap modals
        document.addEventListener('shown.bs.modal', () => {
            this.isModalOpen = true;
        });
        
        document.addEventListener('hidden.bs.modal', () => {
            this.isModalOpen = false;
        });
    }
    
    // Action methods
    
    openQuickSearch() {
        console.log('Opening quick search...');
        if (window.quickSearch) {
            window.quickSearch.open();
        } else {
            // Fallback if quick search not loaded
            console.warn('Quick search not initialized');
        }
    }
    
    createNewTask() {
        console.log('Creating new task...');
        window.location.href = '/tasks/new';
    }
    
    showShortcuts() {
        console.log('Showing shortcuts...');
        window.location.href = '/settings/shortcuts';
    }
    
    closeModal() {
        if (this.isModalOpen) {
            // Close Bootstrap modal
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        }
    }
    
    navigate(path) {
        console.log('Navigating to:', path);
        window.location.href = path;
    }
    
    editCurrentTask() {
        const taskId = this.getCurrentTaskId();
        if (taskId) {
            window.location.href = `/tasks/${taskId}/edit`;
        }
    }
    
    deleteCurrentTask() {
        const taskId = this.getCurrentTaskId();
        if (taskId && confirm('Вы уверены, что хотите удалить эту задачу?')) {
            // TODO: Implement delete via AJAX
            console.log('Deleting task:', taskId);
        }
    }
    
    completeCurrentTask() {
        const taskId = this.getCurrentTaskId();
        if (taskId) {
            // TODO: Implement complete via AJAX
            console.log('Completing task:', taskId);
        }
    }
    
    assignCurrentTask() {
        const taskId = this.getCurrentTaskId();
        if (taskId) {
            // TODO: Open assign modal
            console.log('Assigning task:', taskId);
        }
    }
    
    getCurrentTaskId() {
        // Try to get task ID from URL
        const match = window.location.pathname.match(/\/tasks\/(\d+)/);
        return match ? match[1] : null;
    }
    
    // Public API
    
    getShortcuts() {
        return Array.from(this.shortcuts.entries()).map(([keys, data]) => ({
            keys,
            description: data.description
        }));
    }
    
    disable() {
        document.removeEventListener('keydown', this.handleKeyDown);
    }
}

// Initialize hotkey manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.hotkeyManager = new HotkeyManager();
    
    // Add visual indicator for sequence keys
    document.addEventListener('keydown', (e) => {
        if (window.hotkeyManager.sequenceKeys.length > 0) {
            showSequenceIndicator(window.hotkeyManager.sequenceKeys);
        }
    });
});

// Show sequence indicator
function showSequenceIndicator(keys) {
    let indicator = document.getElementById('sequence-indicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'sequence-indicator';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            z-index: 9999;
            animation: fadeIn 0.2s;
        `;
        document.body.appendChild(indicator);
    }
    
    indicator.textContent = keys.join(' ') + ' ...';
    
    // Remove after 1 second
    setTimeout(() => {
        if (indicator.parentNode) {
            indicator.remove();
        }
    }, 1000);
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HotkeyManager;
}
