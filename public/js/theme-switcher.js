/**
 * Theme Switcher
 * Multi-theme support: Light, Dark, Orange, Purple, Custom
 */

class ThemeSwitcher {
    constructor() {
        this.themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        this.currentTheme = this.getStoredTheme() || this.getPreferredTheme();
        this.customColors = this.getStoredCustomColors();
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme, false);
        this.createThemeSelector();
        this.setupEventListeners();
        this.watchSystemTheme();
    }

    /**
     * Get stored theme from localStorage
     */
    getStoredTheme() {
        return localStorage.getItem('theme');
    }

    /**
     * Get stored custom colors
     */
    getStoredCustomColors() {
        const stored = localStorage.getItem('customColors');
        return stored ? JSON.parse(stored) : {
            primary: '#667eea',
            bgBody: '#f5f5f5',
            bgCard: '#ffffff',
            textPrimary: '#212529'
        };
    }

    /**
     * Get system preferred theme
     */
    getPreferredTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    /**
     * Apply theme to document
     */
    applyTheme(theme, animate = true) {
        if (animate) {
            document.documentElement.classList.add('theme-transitioning');
        }

        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;
        localStorage.setItem('theme', theme);

        // Apply custom colors if custom theme
        if (theme === 'custom') {
            this.applyCustomColors(this.customColors);
        }

        // Update meta theme-color
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            const themeColors = {
                light: '#ffffff',
                dark: '#111827',
                orange: '#fef3f2',
                purple: '#faf5ff',
                custom: this.customColors.bgCard
            };
            metaThemeColor.setAttribute('content', themeColors[theme] || '#ffffff');
        }

        // Update active theme option
        this.updateActiveTheme();

        if (animate) {
            setTimeout(() => {
                document.documentElement.classList.remove('theme-transitioning');
            }, 300);
        }

        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }

    /**
     * Apply custom colors
     */
    applyCustomColors(colors) {
        const root = document.documentElement;
        root.style.setProperty('--custom-primary', colors.primary);
        root.style.setProperty('--custom-bg-body', colors.bgBody);
        root.style.setProperty('--custom-bg-card', colors.bgCard);
        root.style.setProperty('--custom-text-primary', colors.textPrimary);
        
        // Calculate darker/lighter variants
        root.style.setProperty('--custom-primary-dark', this.adjustColor(colors.primary, -20));
        root.style.setProperty('--custom-primary-light', this.adjustColor(colors.primary, 20));
    }

    /**
     * Adjust color brightness
     */
    adjustColor(color, amount) {
        const num = parseInt(color.replace('#', ''), 16);
        const r = Math.max(0, Math.min(255, (num >> 16) + amount));
        const g = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amount));
        const b = Math.max(0, Math.min(255, (num & 0x0000FF) + amount));
        return '#' + ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0');
    }

    /**
     * Create theme selector dropdown
     */
    createThemeSelector() {
        // Check if selector already exists
        const existingSelector = document.getElementById('theme-selector');
        if (existingSelector) {
            // Update existing selector instead of creating new one
            this.updateActiveTheme();
            return;
        }

        const selector = document.createElement('div');
        selector.id = 'theme-selector';
        selector.className = 'theme-selector';
        
        const button = document.createElement('button');
        button.className = 'theme-toggle-btn';
        button.setAttribute('aria-label', 'Выбрать тему');
        button.setAttribute('type', 'button');
        button.innerHTML = '<i class="fas fa-palette"></i>';

        const dropdown = document.createElement('div');
        dropdown.className = 'theme-selector-dropdown';
        dropdown.innerHTML = `
            <div class="theme-selector-title">Выберите тему</div>
            <div class="theme-options">
                <div class="theme-option" data-theme="light">
                    <div class="theme-option-preview">
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                    </div>
                    <div class="theme-option-name">Светлая</div>
                    <div class="theme-option-check"><i class="fas fa-check"></i></div>
                </div>
                <div class="theme-option" data-theme="dark">
                    <div class="theme-option-preview">
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                    </div>
                    <div class="theme-option-name">Тёмная</div>
                    <div class="theme-option-check"><i class="fas fa-check"></i></div>
                </div>
                <div class="theme-option" data-theme="orange">
                    <div class="theme-option-preview">
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                    </div>
                    <div class="theme-option-name">Оранжевая</div>
                    <div class="theme-option-check"><i class="fas fa-check"></i></div>
                </div>
                <div class="theme-option" data-theme="purple">
                    <div class="theme-option-preview">
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                    </div>
                    <div class="theme-option-name">Фиолетовая</div>
                    <div class="theme-option-check"><i class="fas fa-check"></i></div>
                </div>
                <div class="theme-option" data-theme="custom">
                    <div class="theme-option-preview">
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                        <div class="theme-option-color"></div>
                    </div>
                    <div class="theme-option-name">Настраиваемая</div>
                    <div class="theme-option-check"><i class="fas fa-check"></i></div>
                </div>
            </div>
            <div class="custom-theme-editor">
                <div class="color-picker-group">
                    <label class="color-picker-label">Основной цвет</label>
                    <div class="color-picker-input">
                        <input type="color" id="custom-primary" value="${this.customColors.primary}">
                        <input type="text" id="custom-primary-text" value="${this.customColors.primary}">
                    </div>
                </div>
                <div class="color-picker-group">
                    <label class="color-picker-label">Фон страницы</label>
                    <div class="color-picker-input">
                        <input type="color" id="custom-bg-body" value="${this.customColors.bgBody}">
                        <input type="text" id="custom-bg-body-text" value="${this.customColors.bgBody}">
                    </div>
                </div>
                <div class="color-picker-group">
                    <label class="color-picker-label">Фон карточек</label>
                    <div class="color-picker-input">
                        <input type="color" id="custom-bg-card" value="${this.customColors.bgCard}">
                        <input type="text" id="custom-bg-card-text" value="${this.customColors.bgCard}">
                    </div>
                </div>
                <div class="color-picker-group">
                    <label class="color-picker-label">Цвет текста</label>
                    <div class="color-picker-input">
                        <input type="color" id="custom-text-primary" value="${this.customColors.textPrimary}">
                        <input type="text" id="custom-text-primary-text" value="${this.customColors.textPrimary}">
                    </div>
                </div>
                <div class="theme-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="reset-custom-theme">Сбросить</button>
                    <button type="button" class="btn btn-sm btn-primary" id="apply-custom-theme">Применить</button>
                </div>
            </div>
            <div class="theme-reset-section">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" id="reset-to-default-theme">
                    <i class="fas fa-undo"></i> Сбросить тему по умолчанию
                </button>
            </div>
        `;

        selector.appendChild(button);
        selector.appendChild(dropdown);

        // Add to navbar - find the correct location
        const navbar = document.querySelector('.navbar .navbar-nav.ms-auto');
        if (navbar) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(selector);
            navbar.insertBefore(li, navbar.firstChild);
        } else {
            // Fallback: add to body if navbar not found
            console.warn('Navbar not found, theme selector not added to page');
        }

        this.updateActiveTheme();
    }

    /**
     * Update active theme option
     */
    updateActiveTheme() {
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.toggle('active', option.dataset.theme === this.currentTheme);
        });

        // Show/hide custom editor
        const editor = document.querySelector('.custom-theme-editor');
        if (editor) {
            editor.classList.toggle('show', this.currentTheme === 'custom');
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Prevent duplicate event listeners
        if (this.listenersSetup) return;
        this.listenersSetup = true;

        // Toggle dropdown
        document.addEventListener('click', (e) => {
            const button = e.target.closest('.theme-toggle-btn');
            const dropdown = document.querySelector('.theme-selector-dropdown');
            
            if (button) {
                e.stopPropagation();
                dropdown?.classList.toggle('show');
            } else if (!e.target.closest('.theme-selector-dropdown')) {
                dropdown?.classList.remove('show');
            }
        });

        // Theme option click
        document.addEventListener('click', (e) => {
            const option = e.target.closest('.theme-option');
            if (option) {
                const theme = option.dataset.theme;
                this.applyTheme(theme, true);
            }
        });

        // Setup custom theme color pickers after a short delay to ensure DOM is ready
        setTimeout(() => {
            this.setupColorPickers();
        }, 100);

        // Apply custom theme
        document.addEventListener('click', (e) => {
            if (e.target.closest('#apply-custom-theme')) {
                this.saveCustomColors();
                this.applyTheme('custom', true);
                this.showToast('Настраиваемая тема применена', 'success');
            }
        });

        // Reset custom theme
        document.addEventListener('click', (e) => {
            if (e.target.closest('#reset-custom-theme')) {
                this.resetCustomColors();
            }
        });

        // Reset to default theme
        document.addEventListener('click', (e) => {
            if (e.target.closest('#reset-to-default-theme')) {
                this.resetToDefaultTheme();
            }
        });

        // Keyboard shortcut (Ctrl/Cmd + Shift + T)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                document.querySelector('.theme-toggle-btn')?.click();
            }
        });
    }

    /**
     * Setup color pickers
     */
    setupColorPickers() {
        ['primary', 'bg-body', 'bg-card', 'text-primary'].forEach(key => {
            const colorInput = document.getElementById(`custom-${key}`);
            const textInput = document.getElementById(`custom-${key}-text`);
            
            if (colorInput && textInput) {
                // Remove old listeners by cloning
                const newColorInput = colorInput.cloneNode(true);
                const newTextInput = textInput.cloneNode(true);
                colorInput.parentNode.replaceChild(newColorInput, colorInput);
                textInput.parentNode.replaceChild(newTextInput, textInput);

                newColorInput.addEventListener('input', (e) => {
                    newTextInput.value = e.target.value;
                    this.updateCustomColor(key, e.target.value);
                });
                
                newTextInput.addEventListener('input', (e) => {
                    if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                        newColorInput.value = e.target.value;
                        this.updateCustomColor(key, e.target.value);
                    }
                });
            }
        });
    }

    /**
     * Update custom color
     */
    updateCustomColor(key, value) {
        const keyMap = {
            'primary': 'primary',
            'bg-body': 'bgBody',
            'bg-card': 'bgCard',
            'text-primary': 'textPrimary'
        };
        this.customColors[keyMap[key]] = value;
    }

    /**
     * Save custom colors
     */
    saveCustomColors() {
        localStorage.setItem('customColors', JSON.stringify(this.customColors));
    }

    /**
     * Reset custom colors
     */
    resetCustomColors() {
        this.customColors = {
            primary: '#667eea',
            bgBody: '#f5f5f5',
            bgCard: '#ffffff',
            textPrimary: '#212529'
        };
        
        // Update inputs
        document.getElementById('custom-primary').value = this.customColors.primary;
        document.getElementById('custom-primary-text').value = this.customColors.primary;
        document.getElementById('custom-bg-body').value = this.customColors.bgBody;
        document.getElementById('custom-bg-body-text').value = this.customColors.bgBody;
        document.getElementById('custom-bg-card').value = this.customColors.bgCard;
        document.getElementById('custom-bg-card-text').value = this.customColors.bgCard;
        document.getElementById('custom-text-primary').value = this.customColors.textPrimary;
        document.getElementById('custom-text-primary-text').value = this.customColors.textPrimary;
        
        this.saveCustomColors();
        this.applyTheme('custom', true);
        this.showToast('Настройки сброшены', 'info');
    }

    /**
     * Reset to default theme
     */
    resetToDefaultTheme() {
        // Clear localStorage
        localStorage.removeItem('theme');
        localStorage.removeItem('customColors');
        
        // Apply light theme
        this.applyTheme('light', true);
        
        this.showToast('Тема сброшена на светлую по умолчанию', 'success');
        
        // Close dropdown
        document.querySelector('.theme-selector-dropdown')?.classList.remove('show');
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

    /**
     * Watch for system theme changes
     */
    watchSystemTheme() {
        if (!window.matchMedia) return;

        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        darkModeQuery.addEventListener('change', (e) => {
            // Only auto-switch if user hasn't manually set a preference
            if (!localStorage.getItem('theme')) {
                this.applyTheme(e.matches ? 'dark' : 'light', true);
            }
        });
    }

    /**
     * Get current theme
     */
    getTheme() {
        return this.currentTheme;
    }
}

// Add theme transition styles
if (!document.getElementById('themeTransitionStyles')) {
    const style = document.createElement('style');
    style.id = 'themeTransitionStyles';
    style.textContent = `
    .theme-transitioning,
    .theme-transitioning *,
    .theme-transitioning *::before,
    .theme-transitioning *::after {
        transition: background-color 0.3s ease, 
                    color 0.3s ease, 
                    border-color 0.3s ease,
                    box-shadow 0.3s ease !important;
    }

    .theme-toggle-btn {
        width: 40px;
        height: 40px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .theme-toggle-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: scale(1.05);
    }

    .theme-toggle-btn:active {
        transform: scale(0.95);
    }

    .theme-toggle-btn i {
        font-size: 1rem;
        transition: transform 0.3s ease;
    }

    .theme-toggle-btn:hover i {
        transform: rotate(20deg);
    }

    /* Theme-specific toggle button colors */
    [data-theme='dark'] .theme-toggle-btn {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
    }

    [data-theme='dark'] .theme-toggle-btn:hover {
        background: #3b82f6;
        border-color: #3b82f6;
    }

    [data-theme='orange'] .theme-toggle-btn {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }

    [data-theme='orange'] .theme-toggle-btn:hover {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.3);
    }

    [data-theme='purple'] .theme-toggle-btn {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }

    [data-theme='purple'] .theme-toggle-btn:hover {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.3);
    }

    [data-theme='light'] .theme-toggle-btn {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }

    [data-theme='light'] .theme-toggle-btn:hover {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.3);
    }

    .theme-icon-dark,
    .theme-icon-light {
        position: absolute;
    }
`;
    document.head.appendChild(style);
}

// Initialize theme IMMEDIATELY (before DOMContentLoaded to prevent flash)
(function() {
    const storedTheme = localStorage.getItem('theme');
    const preferredTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const theme = storedTheme || preferredTheme;
    
    document.documentElement.setAttribute('data-theme', theme);
    
    // Apply custom colors if custom theme
    if (theme === 'custom') {
        const stored = localStorage.getItem('customColors');
        if (stored) {
            try {
                const colors = JSON.parse(stored);
                const root = document.documentElement;
                root.style.setProperty('--custom-primary', colors.primary);
                root.style.setProperty('--custom-bg-body', colors.bgBody);
                root.style.setProperty('--custom-bg-card', colors.bgCard);
                root.style.setProperty('--custom-text-primary', colors.textPrimary);
            } catch (e) {
                console.error('Error parsing custom colors:', e);
            }
        }
    }
})();

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.themeSwitcher = new ThemeSwitcher();
    });
} else {
    window.themeSwitcher = new ThemeSwitcher();
}

// Export for use in other scripts
window.ThemeSwitcher = ThemeSwitcher;
