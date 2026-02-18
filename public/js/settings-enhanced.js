/**
 * Enhanced Settings Page Functionality
 * Provides interactive features for settings management
 */

class SettingsManager {
    constructor() {
        this.unsavedChanges = false;
        this.init();
    }

    init() {
        this.setupFormTracking();
        this.setupToggleSwitches();
        this.setupPasswordVisibility();
        this.setupConfirmations();
        this.setupAutoSave();
        this.setupThemePreview();
        this.setupNotificationTest();
    }

    /**
     * Track form changes
     */
    setupFormTracking() {
        const forms = document.querySelectorAll('.settings-form');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.unsavedChanges = true;
                    this.showUnsavedIndicator();
                });
            });
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    }

    showUnsavedIndicator() {
        let indicator = document.getElementById('unsaved-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'unsaved-indicator';
            indicator.className = 'unsaved-indicator';
            indicator.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>Есть несохраненные изменения</span>
            `;
            document.body.appendChild(indicator);
        }
        
        indicator.classList.add('show');
    }

    hideUnsavedIndicator() {
        const indicator = document.getElementById('unsaved-indicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
        this.unsavedChanges = false;
    }

    /**
     * Toggle switches functionality
     */
    setupToggleSwitches() {
        const toggles = document.querySelectorAll('.settings-toggle-switch input');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const toggleContainer = e.target.closest('.settings-toggle');
                const title = toggleContainer.querySelector('.settings-toggle-title').textContent;
                
                // Animate the change
                toggleContainer.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    toggleContainer.style.transform = 'scale(1)';
                }, 200);
                
                // Show notification
                this.showNotification(
                    `${title}: ${e.target.checked ? 'Включено' : 'Выключено'}`,
                    'info'
                );
                
                // Auto-save if enabled
                if (this.autoSaveEnabled) {
                    this.saveSettings();
                }
            });
        });
    }

    /**
     * Password visibility toggle
     */
    setupPasswordVisibility() {
        const passwordToggles = document.querySelectorAll('.password-toggle');
        
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const input = toggle.previousElementSibling;
                const icon = toggle.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }

    /**
     * Confirmation dialogs for dangerous actions
     */
    setupConfirmations() {
        const dangerButtons = document.querySelectorAll('[data-confirm]');
        
        dangerButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const message = button.dataset.confirm;
                
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }

    /**
     * Auto-save functionality
     */
    setupAutoSave() {
        this.autoSaveEnabled = localStorage.getItem('autoSaveSettings') === 'true';
        const autoSaveToggle = document.getElementById('autoSaveToggle');
        
        if (autoSaveToggle) {
            autoSaveToggle.checked = this.autoSaveEnabled;
            
            autoSaveToggle.addEventListener('change', (e) => {
                this.autoSaveEnabled = e.target.checked;
                localStorage.setItem('autoSaveSettings', e.target.checked);
                
                this.showNotification(
                    `Автосохранение ${e.target.checked ? 'включено' : 'выключено'}`,
                    'success'
                );
            });
        }
    }

    /**
     * Save settings
     */
    async saveSettings() {
        const forms = document.querySelectorAll('.settings-form');
        
        for (const form of forms) {
            const formData = new FormData(form);
            
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    this.hideUnsavedIndicator();
                    this.showNotification('Настройки сохранены', 'success');
                } else {
                    throw new Error('Failed to save settings');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                this.showNotification('Ошибка при сохранении настроек', 'error');
            }
        }
    }

    /**
     * Theme preview
     */
    setupThemePreview() {
        const themeRadios = document.querySelectorAll('input[name="theme"]');
        
        themeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                const theme = e.target.value;
                this.previewTheme(theme);
            });
        });
    }

    previewTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        this.showNotification(
            `Предпросмотр темы: ${theme === 'dark' ? 'Темная' : 'Светлая'}`,
            'info'
        );
    }

    /**
     * Test notification
     */
    setupNotificationTest() {
        const testButton = document.getElementById('testNotification');
        
        if (testButton) {
            testButton.addEventListener('click', () => {
                this.showNotification(
                    'Это тестовое уведомление! 🎉',
                    'success'
                );
            });
        }
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const container = this.getNotificationContainer();
        
        const notification = document.createElement('div');
        notification.className = `settings-notification settings-notification-${type}`;
        notification.innerHTML = `
            <div class="settings-notification-icon">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            </div>
            <div class="settings-notification-message">${message}</div>
            <button class="settings-notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    getNotificationContainer() {
        let container = document.getElementById('settings-notification-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'settings-notification-container';
            document.body.appendChild(container);
        }
        
        return container;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new SettingsManager();
});

// Add required CSS
const style = document.createElement('style');
style.textContent = `
    /* Unsaved changes indicator */
    .unsaved-indicator {
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: white;
        padding: 1rem 2rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .unsaved-indicator.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    .unsaved-indicator i {
        color: #ffc107;
        font-size: 1.25rem;
    }

    .unsaved-indicator span {
        font-weight: 600;
        color: #495057;
    }

    /* Notification container */
    #settings-notification-container {
        position: fixed;
        top: 2rem;
        right: 2rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* Notification styles */
    .settings-notification {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        min-width: 320px;
        max-width: 400px;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .settings-notification.show {
        opacity: 1;
        transform: translateX(0);
    }

    .settings-notification-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .settings-notification-success {
        border-left: 4px solid #28a745;
    }

    .settings-notification-success .settings-notification-icon {
        color: #28a745;
    }

    .settings-notification-error {
        border-left: 4px solid #dc3545;
    }

    .settings-notification-error .settings-notification-icon {
        color: #dc3545;
    }

    .settings-notification-warning {
        border-left: 4px solid #ffc107;
    }

    .settings-notification-warning .settings-notification-icon {
        color: #ffc107;
    }

    .settings-notification-info {
        border-left: 4px solid #17a2b8;
    }

    .settings-notification-info .settings-notification-icon {
        color: #17a2b8;
    }

    .settings-notification-message {
        flex: 1;
        font-weight: 500;
        color: #212529;
    }

    .settings-notification-close {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .settings-notification-close:hover {
        background: #f8f9fa;
        color: #212529;
    }

    /* Password toggle button */
    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0.5rem;
        transition: color 0.2s ease;
    }

    .password-toggle:hover {
        color: #495057;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #settings-notification-container {
            left: 1rem;
            right: 1rem;
            top: 1rem;
        }

        .settings-notification {
            min-width: auto;
            max-width: none;
        }

        .unsaved-indicator {
            left: 1rem;
            right: 1rem;
            transform: translateX(0) translateY(100px);
        }

        .unsaved-indicator.show {
            transform: translateX(0) translateY(0);
        }
    }

    /* Dark theme */
    [data-theme='dark'] .unsaved-indicator,
    [data-theme='dark'] .settings-notification {
        background: #2d3748;
        color: #e2e8f0;
    }

    [data-theme='dark'] .settings-notification-message {
        color: #e2e8f0;
    }

    [data-theme='dark'] .settings-notification-close {
        color: #a0aec0;
    }

    [data-theme='dark'] .settings-notification-close:hover {
        background: #374151;
        color: #e2e8f0;
    }
`;
document.head.appendChild(style);
