/**
 * Theme Switcher - Advanced Theme Management System
 * Supports: Light, Dark, Orange, Purple, Custom themes
 */

class ThemeSwitcher {
    constructor() {
        this.currentTheme = this.getStoredTheme() || 'light';
        this.themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        this.customColors = this.getStoredCustomColors();
        
        this.init();
    }
    
    init() {
        // Apply stored theme immediately
        this.applyTheme(this.currentTheme);
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Update UI
        this.updateThemeUI();
        
        // Listen for system theme changes
        this.watchSystemTheme();
    }
    
    setupEventListeners() {
        // Theme selector cards
        document.querySelectorAll('[data-theme-select]').forEach(card => {
            card.addEventListener('click', (e) => {
                const theme = e.currentTarget.dataset.themeSelect;
                
                // Show custom controls if custom theme selected
                if (theme === 'custom') {
                    const controls = document.getElementById('customThemeControls');
                    if (controls) {
                        controls.style.display = 'block';
                    }
                } else {
                    this.setTheme(theme);
                    // Close modal after selection
                    const modal = bootstrap.Modal.getInstance(document.getElementById('themeModal'));
                    if (modal) {
                        setTimeout(() => modal.hide(), 300);
                    }
                }
            });
        });
        
        // Custom theme color pickers
        const customPickers = {
            'customPrimary': 'custom-primary',
            'customBgBody': 'custom-bg-body',
            'customBgCard': 'custom-bg-card',
            'customTextPrimary': 'custom-text-primary',
            'customSuccess': 'custom-success',
            'customDanger': 'custom-danger'
        };
        
        Object.entries(customPickers).forEach(([id, varName]) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', (e) => {
                    this.setCustomColor(varName, e.target.value);
                });
            }
        });
        
        // Apply custom theme button
        const applyBtn = document.getElementById('applyCustomTheme');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                this.setTheme('custom');
                const modal = bootstrap.Modal.getInstance(document.getElementById('themeModal'));
                if (modal) {
                    setTimeout(() => modal.hide(), 300);
                }
            });
        }
        
        // Reset custom theme button
        const resetBtn = document.getElementById('resetCustomTheme');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetCustomColors();
            });
        }
        
        // Auto theme switch checkbox
        const autoSwitch = document.getElementById('autoThemeSwitch');
        if (autoSwitch) {
            autoSwitch.checked = this.getAutoSwitchPreference();
            autoSwitch.addEventListener('change', (e) => {
                this.setAutoSwitch(e.target.checked);
            });
        }
    }
    
    setTheme(theme) {
        if (!this.themes.includes(theme)) {
            console.error(`Invalid theme: ${theme}`);
            return;
        }
        
        // Add transition class
        document.body.classList.add('theme-switching');
        
        // Apply theme
        this.currentTheme = theme;
        this.applyTheme(theme);
        
        // Store preference
        this.storeTheme(theme);
        
        // Update UI
        this.updateThemeUI();
        
        // Remove transition class after animation
        setTimeout(() => {
            document.body.classList.remove('theme-switching');
        }, 300);
        
        // Dispatch custom event
        this.dispatchThemeChange(theme);
    }
    
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Apply custom colors if custom theme
        if (theme === 'custom' && this.customColors) {
            this.applyCustomColors();
        }
    }
    
    cycleTheme() {
        const currentIndex = this.themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % this.themes.length;
        this.setTheme(this.themes[nextIndex]);
    }
    
    setCustomColor(colorVar, colorValue) {
        if (!this.customColors) {
            this.customColors = {};
        }
        
        this.customColors[colorVar] = colorValue;
        this.storeCustomColors();
        
        if (this.currentTheme === 'custom') {
            this.applyCustomColors();
        }
    }
    
    applyCustomColors() {
        if (!this.customColors) return;
        
        const root = document.documentElement;
        Object.entries(this.customColors).forEach(([key, value]) => {
            root.style.setProperty(`--${key}`, value);
        });
    }
    
    updateThemeUI() {
        // Update theme selector cards
        document.querySelectorAll('[data-theme-select]').forEach(card => {
            const theme = card.dataset.themeSelect;
            if (theme === this.currentTheme) {
                card.classList.add('active');
                card.setAttribute('aria-pressed', 'true');
            } else {
                card.classList.remove('active');
                card.setAttribute('aria-pressed', 'false');
            }
        });
        
        // Update custom color inputs
        if (this.customColors) {
            const inputMap = {
                'custom-primary': 'customPrimary',
                'custom-bg-body': 'customBgBody',
                'custom-bg-card': 'customBgCard',
                'custom-text-primary': 'customTextPrimary',
                'custom-success': 'customSuccess',
                'custom-danger': 'customDanger'
            };
            
            Object.entries(this.customColors).forEach(([key, value]) => {
                const inputId = inputMap[key];
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = value;
                }
            });
        }
    }
    
    getThemeIcon(theme) {
        const icons = {
            light: 'fas fa-sun',
            dark: 'fas fa-moon',
            orange: 'fas fa-fire',
            purple: 'fas fa-star',
            custom: 'fas fa-palette'
        };
        return icons[theme] || 'fas fa-adjust';
    }
    
    watchSystemTheme() {
        if (!window.matchMedia) return;
        
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        darkModeQuery.addEventListener('change', (e) => {
            // Only auto-switch if user hasn't set a preference
            if (!this.getStoredTheme()) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
    
    storeTheme(theme) {
        try {
            localStorage.setItem('app-theme', theme);
        } catch (e) {
            console.error('Failed to store theme preference:', e);
        }
    }
    
    getStoredTheme() {
        try {
            return localStorage.getItem('app-theme');
        } catch (e) {
            console.error('Failed to retrieve theme preference:', e);
            return null;
        }
    }
    
    storeCustomColors() {
        try {
            localStorage.setItem('app-custom-colors', JSON.stringify(this.customColors));
        } catch (e) {
            console.error('Failed to store custom colors:', e);
        }
    }
    
    getStoredCustomColors() {
        try {
            const stored = localStorage.getItem('app-custom-colors');
            return stored ? JSON.parse(stored) : null;
        } catch (e) {
            console.error('Failed to retrieve custom colors:', e);
            return null;
        }
    }
    
    dispatchThemeChange(theme) {
        const event = new CustomEvent('themechange', {
            detail: { theme, customColors: this.customColors }
        });
        window.dispatchEvent(event);
    }
    
    // Public API
    getCurrentTheme() {
        return this.currentTheme;
    }
    
    getAvailableThemes() {
        return [...this.themes];
    }
    
    resetToDefault() {
        this.setTheme('light');
        this.customColors = null;
        try {
            localStorage.removeItem('app-custom-colors');
        } catch (e) {
            console.error('Failed to clear custom colors:', e);
        }
    }
    
    resetCustomColors() {
        // Reset to default values
        const defaults = {
            'custom-primary': '#667eea',
            'custom-bg-body': '#f9fafb',
            'custom-bg-card': '#ffffff',
            'custom-text-primary': '#1f2937',
            'custom-success': '#10b981',
            'custom-danger': '#ef4444'
        };
        
        this.customColors = defaults;
        this.storeCustomColors();
        this.updateThemeUI();
        
        if (this.currentTheme === 'custom') {
            this.applyCustomColors();
        }
    }
    
    setAutoSwitch(enabled) {
        try {
            localStorage.setItem('app-auto-theme-switch', enabled ? 'true' : 'false');
            if (enabled) {
                this.enableAutoSwitch();
            } else {
                this.disableAutoSwitch();
            }
        } catch (e) {
            console.error('Failed to store auto-switch preference:', e);
        }
    }
    
    getAutoSwitchPreference() {
        try {
            return localStorage.getItem('app-auto-theme-switch') === 'true';
        } catch (e) {
            return false;
        }
    }
    
    enableAutoSwitch() {
        const hour = new Date().getHours();
        const isDayTime = hour >= 6 && hour < 18;
        this.setTheme(isDayTime ? 'light' : 'dark');
        
        // Check every hour
        if (this.autoSwitchInterval) {
            clearInterval(this.autoSwitchInterval);
        }
        this.autoSwitchInterval = setInterval(() => {
            const hour = new Date().getHours();
            const isDayTime = hour >= 6 && hour < 18;
            const targetTheme = isDayTime ? 'light' : 'dark';
            if (this.currentTheme !== targetTheme) {
                this.setTheme(targetTheme);
            }
        }, 60000); // Check every minute
    }
    
    disableAutoSwitch() {
        if (this.autoSwitchInterval) {
            clearInterval(this.autoSwitchInterval);
            this.autoSwitchInterval = null;
        }
    }
}

// Initialize theme switcher when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.themeSwitcher = new ThemeSwitcher();
    });
} else {
    window.themeSwitcher = new ThemeSwitcher();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeSwitcher;
}
