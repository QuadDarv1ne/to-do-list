/**
 * Theme Switcher v2.0
 * Enhanced theme management with localStorage persistence
 */

class ThemeSwitcher {
    constructor() {
        this.THEME_KEY = 'app_theme';
        this.themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        this.currentTheme = this.getStoredTheme();
        
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.bindEvents();
        this.updateActiveState();
    }

    getStoredTheme() {
        const stored = localStorage.getItem(this.THEME_KEY);
        if (stored && this.themes.includes(stored)) {
            return stored;
        }
        
        // Check system preference
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        
        return 'light';
    }

    applyTheme(theme) {
        const html = document.documentElement;
        
        if (theme === 'light') {
            html.removeAttribute('data-theme');
        } else {
            html.setAttribute('data-theme', theme);
        }
        
        this.currentTheme = theme;
        localStorage.setItem(this.THEME_KEY, theme);
        this.updateActiveState();
        
        // Dispatch custom event for other components
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }

    toggle() {
        const currentIndex = this.themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % this.themes.length;
        this.applyTheme(this.themes[nextIndex]);
    }

    setTheme(theme) {
        if (this.themes.includes(theme)) {
            this.applyTheme(theme);
        }
    }

    getTheme() {
        return this.currentTheme;
    }

    getNextTheme() {
        const currentIndex = this.themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % this.themes.length;
        return this.themes[nextIndex];
    }

    updateActiveState() {
        // Update theme switcher buttons
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = this.getThemeIcon(this.currentTheme);
            }
            
            const tooltip = btn.querySelector('[data-tooltip]');
            if (tooltip) {
                tooltip.setAttribute('data-tooltip', this.getThemeName(this.currentTheme));
            }
        });

        // Update active state in dropdown
        document.querySelectorAll('[data-theme-value]').forEach(item => {
            const isActive = item.getAttribute('data-theme-value') === this.currentTheme;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
    }

    getThemeIcon(theme) {
        const icons = {
            light: 'fas fa-sun',
            dark: 'fas fa-moon',
            orange: 'fas fa-orange',
            purple: 'fas fa-palette',
            custom: 'fas fa-sliders-h'
        };
        return icons[theme] || icons.light;
    }

    getThemeName(theme) {
        const names = {
            light: 'Светлая',
            dark: 'Тёмная',
            orange: 'Оранжевая',
            purple: 'Фиолетовая',
            custom: 'Пользовательская'
        };
        return names[theme] || 'Светлая';
    }

    bindEvents() {
        // Theme toggle button
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        });

        // Theme selection in dropdown
        document.querySelectorAll('[data-theme-value]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const theme = item.getAttribute('data-theme-value');
                this.applyTheme(theme);
            });
        });

        // System theme change
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(this.THEME_KEY)) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Keyboard shortcut (Alt + T)
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                this.toggle();
            }
        });
    }
}

/**
 * Toast Notifications v2.0
 * Modern toast notification system
 */

class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.defaultDuration = 5000;
        this.maxToasts = 5;
        
        this.init();
    }

    init() {
        this.createContainer();
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: var(--z-toast, 1080);
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 420px;
        `;
        document.body.appendChild(this.container);
    }

    show(message, options = {}) {
        const {
            type = 'info',
            title = null,
            duration = this.defaultDuration,
            icon = null,
            closable = true,
            action = null
        } = options;

        // Remove oldest toast if max reached
        if (this.toasts.length >= this.maxToasts) {
            this.remove(this.toasts[0]);
        }

        const toast = this.createToast(message, type, title, icon, closable, action);
        this.container.appendChild(toast);
        this.toasts.push(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Auto remove
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }

        return toast;
    }

    createToast(message, type, title, icon, closable, action) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        toast.innerHTML = `
            ${icon ? `<div class="toast-icon"><i class="${icon}"></i></div>` : 
                   `<div class="toast-icon"><i class="${icons[type] || icons.info}"></i></div>`}
            <div class="toast-content">
                ${title ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            ${closable ? `<button class="toast-close" aria-label="Close"><i class="fas fa-times"></i></button>` : ''}
            ${action ? `<div class="toast-action">${action}</div>` : ''}
        `;

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.remove(toast));
        }

        return toast;
    }

    remove(toast) {
        if (!toast || !this.container.contains(toast)) return;

        toast.classList.remove('show');
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';

        setTimeout(() => {
            if (this.container.contains(toast)) {
                this.container.removeChild(toast);
                this.toasts = this.toasts.filter(t => t !== toast);
            }
        }, 300);
    }

    success(message, options = {}) {
        return this.show(message, { ...options, type: 'success' });
    }

    error(message, options = {}) {
        return this.show(message, { ...options, type: 'error' });
    }

    warning(message, options = {}) {
        return this.show(message, { ...options, type: 'warning' });
    }

    info(message, options = {}) {
        return this.show(message, { ...options, type: 'info' });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

/**
 * Page Loader v2.0
 * Enhanced page loading experience
 */

class PageLoader {
    constructor() {
        this.loader = null;
        this.minDisplayTime = 300;
        this.startTime = null;
        
        this.init();
    }

    init() {
        this.createLoader();
        this.bindEvents();
        this.startTime = Date.now();
    }

    createLoader() {
        this.loader = document.createElement('div');
        this.loader.className = 'page-loader';
        this.loader.id = 'pageLoader';
        this.loader.innerHTML = `
            <div class="spinner"></div>
        `;
        document.body.appendChild(this.loader);
    }

    hide() {
        const elapsed = Date.now() - this.startTime;
        const delay = Math.max(0, this.minDisplayTime - elapsed);

        setTimeout(() => {
            this.loader.classList.add('hidden');
            document.body.classList.add('loaded');
            
            // Remove from DOM after transition
            setTimeout(() => {
                if (this.loader && this.loader.parentNode) {
                    this.loader.parentNode.removeChild(this.loader);
                }
            }, 500);
        }, delay);
    }

    bindEvents() {
        window.addEventListener('load', () => this.hide());
        
        // Also hide on DOMContentLoaded as fallback
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => this.hide(), 100);
        });

        // Handle AJAX page loads (Turbo, etc.)
        document.addEventListener('turbo:load', () => this.hide());
        document.addEventListener('turbo:frame-load', () => this.hide());
    }
}

/**
 * Scroll Manager v2.0
 * Smooth scrolling and scroll position management
 */

class ScrollManager {
    constructor() {
        this.scrollThreshold = 100;
        this.lastScrollY = 0;
        this.ticking = false;
        
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        window.addEventListener('scroll', () => this.onScroll(), { passive: true });
    }

    onScroll() {
        this.lastScrollY = window.scrollY;

        if (!this.ticking) {
            window.requestAnimationFrame(() => {
                this.update();
                this.ticking = false;
            });
            this.ticking = true;
        }
    }

    update() {
        // Navbar shadow on scroll
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            if (this.lastScrollY > this.scrollThreshold) {
                navbar.style.boxShadow = 'var(--shadow-md)';
            } else {
                navbar.style.boxShadow = 'var(--shadow-sm)';
            }
        }

        // Back to top button visibility
        const backToTop = document.querySelector('[data-back-to-top]');
        if (backToTop) {
            if (this.lastScrollY > this.scrollThreshold * 3) {
                backToTop.style.opacity = '1';
                backToTop.style.pointerEvents = 'auto';
            } else {
                backToTop.style.opacity = '0';
                backToTop.style.pointerEvents = 'none';
            }
        }
    }

    scrollTo(element, options = {}) {
        const {
            offset = 0,
            duration = 500,
            easing = 'easeInOutQuart'
        } = options;

        const target = typeof element === 'string' 
            ? document.querySelector(element) 
            : element;

        if (!target) return;

        const targetPosition = target.getBoundingClientRect().top + window.scrollY - offset;
        const startPosition = window.scrollY;
        const distance = targetPosition - startPosition;
        let startTime = null;

        const animation = (currentTime) => {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);
            const ease = this.easingFunctions[easing](progress);

            window.scrollTo(0, startPosition + distance * ease);

            if (timeElapsed < duration) {
                requestAnimationFrame(animation);
            }
        };

        requestAnimationFrame(animation);
    }

    easingFunctions = {
        easeInOutQuart: (t) => t < 0.5 
            ? 8 * t * t * t * t 
            : 1 - 8 * (--t) * t * t * t
    };
}

/**
 * Initialize all components on DOM ready
 */

document.addEventListener('DOMContentLoaded', () => {
    // Theme management handled by core/theme-manager-enhanced.js
    
    // Initialize toast manager
    window.toastManager = new ToastManager();
    
    // Initialize page loader (will auto-hide on load)
    window.pageLoader = new PageLoader();
    
    // Initialize scroll manager
    window.scrollManager = new ScrollManager();
    
    // Global toast function for backward compatibility
    window.showToast = (message, type = 'info') => {
        window.toastManager[type](message);
    };
    
    // Console welcome message
    console.log('%c🎨 Design System v2.0 initialized', 'color: #6366f1; font-weight: bold; font-size: 14px;');
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ToastManager, PageLoader, ScrollManager };
}
