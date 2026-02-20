/**
 * Settings Enhanced
 * Modern settings page with theme support
 */

document.addEventListener('DOMContentLoaded', function() {
    initSettingsPage();
});

function initSettingsPage() {
    // Apply current theme immediately
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    initSettingsNavigation();
    initThemeSelection();
    initNotificationToggles();
}

/**
 * Settings Navigation
 */
function initSettingsNavigation() {
    const navItems = document.querySelectorAll('.settings-nav-item');
    const sections = document.querySelectorAll('.settings-section');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active state
            navItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            // Show section
            const section = this.dataset.section;
            sections.forEach(s => {
                s.style.display = s.id === section ? 'block' : 'none';
            });
            
            // Update URL hash
            window.location.hash = section;
            
            // Save active section
            localStorage.setItem('activeSettingsSection', section);
        });
    });
    
    // Restore active section from hash or localStorage
    const hash = window.location.hash.substring(1);
    const savedSection = hash || localStorage.getItem('activeSettingsSection');
    
    if (savedSection) {
        const item = document.querySelector(`[data-section="${savedSection}"]`);
        if (item) {
            item.click();
        }
    }
}

/**
 * Theme Selection
 */
function initThemeSelection() {
    const themeOptions = document.querySelectorAll('.theme-option');
    
    // Get current theme
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Mark current theme as selected
    themeOptions.forEach(option => {
        if (option.dataset.theme === currentTheme) {
            option.classList.add('selected');
        }
    });
    
    // Handle theme selection
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.dataset.theme;
            
            // Update selected state
            themeOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Apply theme using theme manager or fallback
            if (window.themeManager) {
                window.themeManager.setTheme(theme);
            } else {
                applyTheme(theme);
                localStorage.setItem('theme', theme);
            }
            
            // Show success message
            showNotification('Тема изменена', 'success');
        });
    });
}

/**
 * Apply theme
 */
function applyTheme(theme) {
    // Use theme manager if available
    if (window.themeManager) {
        window.themeManager.setTheme(theme);
    } else {
        // Fallback: set data-theme attribute directly
        document.documentElement.setAttribute('data-theme', theme);
    }
}

/**
 * Notification Toggles
 */
function initNotificationToggles() {
    const toggles = document.querySelectorAll('.switch input[type="checkbox"]');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const setting = this.closest('.settings-option')?.querySelector('.settings-option-title')?.textContent;
            const enabled = this.checked;
            
            // Save setting (можно добавить AJAX запрос)
            console.log(`Setting "${setting}" changed to:`, enabled);
            
            // Show notification
            showNotification(
                enabled ? 'Уведомления включены' : 'Уведомления выключены',
                'info'
            );
        });
    });
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--bg-primary);
        color: var(--text-primary);
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 4px solid var(--color-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'});
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Export for use in other scripts
window.SettingsEnhanced = {
    applyTheme,
    showNotification
};

/**
 * Settings Tabs
 */
function initSettingsTabs() {
    const tabs = document.querySelectorAll('.settings-tab');
    const panels = document.querySelectorAll('.settings-panel');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.target;
            
            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update active panel
            panels.forEach(p => {
                p.classList.toggle('active', p.id === target);
            });
            
            // Save active tab to localStorage
            localStorage.setItem('activeSettingsTab', target);
        });
    });
    
    // Restore active tab
    const savedTab = localStorage.getItem('activeSettingsTab');
    if (savedTab) {
        const tab = document.querySelector(`[data-target="${savedTab}"]`);
        if (tab) tab.click();
    }
}

/**
 * Form Validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
}

/**
 * Password Toggle
 */
function initPasswordToggle() {
    const toggleButtons = document.querySelectorAll('.password-toggle');
    
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
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
 * Avatar Upload
 */
function initAvatarUpload() {
    const avatarInput = document.getElementById('avatar-upload');
    const avatarPreview = document.getElementById('avatar-preview');
    
    if (!avatarInput || !avatarPreview) return;
    
    avatarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            if (!file.type.startsWith('image/')) {
                showToast('Пожалуйста, выберите изображение', 'error');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                showToast('Размер файла не должен превышать 5 МБ', 'error');
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
                avatarPreview.style.display = 'block';
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Drag and drop
    const dropZone = document.getElementById('avatar-drop-zone');
    if (dropZone) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const file = e.dataTransfer.files[0];
            if (file) {
                avatarInput.files = e.dataTransfer.files;
                avatarInput.dispatchEvent(new Event('change'));
            }
        });
    }
}

/**
 * Notification Settings
 */
function initNotificationSettings() {
    const notificationToggles = document.querySelectorAll('.notification-toggle');
    
    notificationToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const setting = this.dataset.setting;
            const enabled = this.checked;
            
            saveNotificationSetting(setting, enabled);
        });
    });
}

/**
 * Save notification setting
 */
async function saveNotificationSetting(setting, enabled) {
    try {
        const response = await fetch('/api/settings/notifications', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ [setting]: enabled })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Настройки сохранены', 'success');
        } else {
            showToast('Ошибка сохранения настроек', 'error');
        }
    } catch (error) {
        console.error('Error saving notification setting:', error);
        showToast('Ошибка сохранения настроек', 'error');
    }
}

/**
 * Theme Settings
 */
function initThemeSettings() {
    const themeRadios = document.querySelectorAll('input[name="theme"]');
    
    themeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked && window.themeLoader) {
                window.themeLoader.setTheme(this.value);
            }
        });
    });
    
    // Set current theme
    if (window.themeLoader) {
        const currentTheme = window.themeLoader.getCurrentTheme();
        const radio = document.querySelector(`input[name="theme"][value="${currentTheme}"]`);
        if (radio) radio.checked = true;
    }
}

/**
 * Shortcut Settings
 */
function initShortcutSettings() {
    const shortcutInputs = document.querySelectorAll('.shortcut-input');
    
    shortcutInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            e.preventDefault();
            
            const keys = [];
            if (e.ctrlKey) keys.push('Ctrl');
            if (e.altKey) keys.push('Alt');
            if (e.shiftKey) keys.push('Shift');
            if (e.metaKey) keys.push('Cmd');
            
            if (e.key && !['Control', 'Alt', 'Shift', 'Meta'].includes(e.key)) {
                keys.push(e.key.toUpperCase());
            }
            
            this.value = keys.join(' + ');
        });
    });
    
    // Save shortcuts
    const saveShortcutsBtn = document.getElementById('save-shortcuts');
    if (saveShortcutsBtn) {
        saveShortcutsBtn.addEventListener('click', function() {
            saveShortcuts();
        });
    }
}

/**
 * Save shortcuts
 */
async function saveShortcuts() {
    const shortcuts = {};
    const inputs = document.querySelectorAll('.shortcut-input');
    
    inputs.forEach(input => {
        const action = input.dataset.action;
        shortcuts[action] = input.value;
    });
    
    try {
        const response = await fetch('/api/settings/shortcuts', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ shortcuts })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Горячие клавиши сохранены', 'success');
        } else {
            showToast('Ошибка сохранения горячих клавиш', 'error');
        }
    } catch (error) {
        console.error('Error saving shortcuts:', error);
        showToast('Ошибка сохранения горячих клавиш', 'error');
    }
}

// showToast теперь в utils.js

// Export for use in other scripts
window.SettingsEnhanced = {
    saveNotificationSetting,
    saveShortcuts
};
