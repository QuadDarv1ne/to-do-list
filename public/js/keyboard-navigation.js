/**
 * Keyboard Navigation & Shortcuts
 * Горячие клавиши и keyboard navigation
 */

(function() {
    'use strict';

    // Keyboard shortcuts configuration
    const SHORTCUTS = {
        'd': { action: 'navigate', url: '/dashboard', description: 'Дашборд' },
        't': { action: 'navigate', url: '/tasks', description: 'Задачи' },
        'k': { action: 'navigate', url: '/kanban', description: 'Канбан' },
        'c': { action: 'navigate', url: '/calendar', description: 'Календарь' },
        'p': { action: 'navigate', url: '/profile', description: 'Профиль' },
        'n': { action: 'navigate', url: '/task/new', description: 'Новая задача' },
        '?': { action: 'modal', target: 'keyboardShortcutsModal', description: 'Справка' }
    };

    // Track if user is using keyboard
    let isKeyboardMode = false;

    /**
     * Enable keyboard mode visual indicators
     */
    function enableKeyboardMode() {
        if (!isKeyboardMode) {
            isKeyboardMode = true;
            document.body.classList.add('keyboard-mode');
        }
    }

    /**
     * Disable keyboard mode visual indicators
     */
    function disableKeyboardMode() {
        isKeyboardMode = false;
        document.body.classList.remove('keyboard-mode');
    }

    /**
     * Navigate to URL
     * @param {string} url - URL to navigate to
     */
    function navigateTo(url) {
        // Check if Turbo is available
        if (window.Turbo) {
            Turbo.visit(url);
        } else {
            window.location.href = url;
        }
    }

    /**
     * Show keyboard shortcuts modal
     */
    function showShortcutsModal() {
        const modal = document.getElementById('keyboardShortcutsModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    /**
     * Handle keyboard shortcuts
     * @param {KeyboardEvent} event - Keyboard event
     */
    function handleKeyboardShortcut(event) {
        // Ignore if typing in input/textarea
        const activeElement = document.activeElement;
        if (activeElement.tagName === 'INPUT' || 
            activeElement.tagName === 'TEXTAREA' || 
            activeElement.isContentEditable) {
            return;
        }

        // Ignore if modifier keys are pressed (except for Ctrl+K)
        if (event.ctrlKey || event.altKey || event.metaKey) {
            // Ctrl+K for search
            if (event.ctrlKey && event.key === 'k') {
                event.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                return;
            }
            return;
        }

        // Ignore Shift alone
        if (event.key === 'Shift') {
            return;
        }

        const key = event.key.toLowerCase();

        // Check for shortcut match
        if (SHORTCUTS[key]) {
            event.preventDefault();
            const shortcut = SHORTCUTS[key];

            if (shortcut.action === 'navigate') {
                navigateTo(shortcut.url);
            } else if (shortcut.action === 'modal') {
                showShortcutsModal();
            }
        }

        // Escape to close modals
        if (event.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const bsModal = bootstrap.Modal.getInstance(openModal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        }
    }

    /**
     * Initialize keyboard navigation
     */
    function initializeKeyboardNavigation() {
        // Listen for keyboard events
        document.addEventListener('keydown', handleKeyboardShortcut);

        // Detect keyboard vs mouse navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                enableKeyboardMode();
            }
        });

        document.addEventListener('mousedown', () => {
            disableKeyboardMode();
        });

        // Initialize search with Ctrl+K
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    }

    /**
     * Initialize on DOM ready
     */
    function init() {
        initializeKeyboardNavigation();

        // Make shortcuts available globally
        window.KeyboardShortcuts = {
            enable: enableKeyboardMode,
            disable: disableKeyboardMode,
            navigate: navigateTo,
            showHelp: showShortcutsModal
        };
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Reinitialize after Turbo navigation
    document.addEventListener('turbo:load', init);

})();
