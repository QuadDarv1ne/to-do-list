/**
 * Settings Enhanced
 * Modern settings page with theme support
 */

document.addEventListener('DOMContentLoaded', function() {
    initSettingsPage();
});

function initSettingsPage() {
    initSettingsTabs();
    initFormValidation();
    initPasswordToggle();
    initAvatarUpload();
    initNotificationSettings();
    initThemeSettings();
    initShortcutSettings();
}

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
            if (this.checked && window.themeSwitcher) {
                window.themeSwitcher.applyTheme(this.value, true);
            }
        });
    });
    
    // Set current theme
    if (window.themeSwitcher) {
        const currentTheme = window.themeSwitcher.getTheme();
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

/**
 * Helper functions
 */
function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}

// Export for use in other scripts
window.SettingsEnhanced = {
    saveNotificationSetting,
    saveShortcuts
};
