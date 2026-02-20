/**
 * Enhanced Theme Manager
 * Улучшенная система управления темами с плавными переходами
 */

class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.currentMode = localStorage.getItem('mode') || 'light';
        this.init();
    }

    init() {
        // Применяем сохраненную тему сразу
        this.applyTheme(this.currentTheme, this.currentMode, false);
        
        // Инициализируем обработчики событий
        this.initEventListeners();
        
        // Обновляем активные кнопки
        this.updateActiveButtons();
        
        // Слушаем системные изменения темы
        this.watchSystemTheme();
    }

    initEventListeners() {
        // Кнопки переключения тем в sidebar
        document.querySelectorAll('[data-theme-option]').forEach(button => {
            button.addEventListener('click', (e) => {
                const theme = e.currentTarget.dataset.themeOption;
                this.setTheme(theme);
            });
        });

        // Кнопки переключения режима (light/dark)
        document.querySelectorAll('[data-mode-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                this.toggleMode();
            });
        });

        // FAB кнопка смены темы
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                this.cycleTheme();
            });
        });
    }

    setTheme(theme, mode = null) {
        const targetMode = mode || this.currentMode;
        
        // Добавляем класс для отключения transitions
        document.documentElement.classList.add('theme-transitioning');
        
        // Применяем тему
        this.applyTheme(theme, targetMode);
        
        // Сохраняем в localStorage
        localStorage.setItem('theme', theme);
        localStorage.setItem('mode', targetMode);
        
        // Обновляем текущие значения
        this.currentTheme = theme;
        this.currentMode = targetMode;
        
        // Обновляем UI
        this.updateActiveButtons();
        
        // Убираем класс через небольшую задержку
        setTimeout(() => {
            document.documentElement.classList.remove('theme-transitioning');
        }, 50);
        
        // Отправляем событие для других компонентов
        this.dispatchThemeChange();
    }

    toggleMode() {
        const newMode = this.currentMode === 'light' ? 'dark' : 'light';
        this.setTheme(this.currentTheme, newMode);
    }

    cycleTheme() {
        const themes = ['light', 'dark', 'orange', 'purple'];
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        this.setTheme(themes[nextIndex]);
    }

    applyTheme(theme, mode = 'light', animate = true) {
        const root = document.documentElement;
        
        // Устанавливаем data-атрибуты
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-mode', mode);
        
        // Обновляем класс body для совместимости
        document.body.className = document.body.className
            .replace(/theme-\w+/g, '')
            .replace(/mode-\w+/g, '');
        document.body.classList.add(`theme-${theme}`, `mode-${mode}`);
        
        // Динамически загружаем CSS файл темы
        this.loadThemeCSS(theme, mode);
        
        // Обновляем meta theme-color для PWA
        this.updateMetaThemeColor(theme, mode);
        
        // Анимация при смене темы
        if (animate) {
            this.animateThemeChange();
        }
    }

    loadThemeCSS(theme, mode) {
        // Определяем тип layout (sidebar или topbar)
        const isSidebar = document.querySelector('.sidebar') !== null;
        const layoutType = isSidebar ? 'sidebar' : 'topbar';
        
        // Удаляем старые темы
        const oldThemeLinks = document.querySelectorAll('link[data-theme-css]');
        oldThemeLinks.forEach(link => link.remove());
        
        // Создаем новый link для темы
        const themeLink = document.createElement('link');
        themeLink.rel = 'stylesheet';
        themeLink.href = `/css/themes/${layoutType}-theme-${theme}.css`;
        themeLink.setAttribute('data-theme-css', 'true');
        
        // Добавляем в head
        document.head.appendChild(themeLink);
    }

    updateMetaThemeColor(theme, mode) {
        let themeColor = '#667eea'; // default
        
        if (mode === 'dark') {
            themeColor = '#1e293b';
        } else {
            switch(theme) {
                case 'orange':
                    themeColor = '#f97316';
                    break;
                case 'purple':
                    themeColor = '#a855f7';
                    break;
                case 'dark':
                    themeColor = '#1e293b';
                    break;
            }
        }
        
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }
        metaTheme.content = themeColor;
    }

    updateActiveButtons() {
        // Обновляем кнопки тем
        document.querySelectorAll('[data-theme-option]').forEach(button => {
            const theme = button.dataset.themeOption;
            if (theme === this.currentTheme) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
        
        // Обновляем иконки режима
        document.querySelectorAll('[data-mode-toggle] i').forEach(icon => {
            if (this.currentMode === 'dark') {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        });
    }

    animateThemeChange() {
        // Создаем ripple эффект при смене темы
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: var(--color-primary);
            opacity: 0.3;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 9999;
            transition: width 0.6s ease-out, height 0.6s ease-out, opacity 0.6s ease-out;
        `;
        
        document.body.appendChild(ripple);
        
        // Запускаем анимацию
        requestAnimationFrame(() => {
            const size = Math.max(window.innerWidth, window.innerHeight) * 2;
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.opacity = '0';
        });
        
        // Удаляем элемент после анимации
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    watchSystemTheme() {
        // Следим за системными изменениями темы
        if (window.matchMedia) {
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            darkModeQuery.addEventListener('change', (e) => {
                // Только если пользователь не установил тему вручную
                if (!localStorage.getItem('theme')) {
                    const mode = e.matches ? 'dark' : 'light';
                    this.setTheme(this.currentTheme, mode);
                }
            });
        }
    }

    dispatchThemeChange() {
        // Отправляем кастомное событие для других компонентов
        const event = new CustomEvent('themechange', {
            detail: {
                theme: this.currentTheme,
                mode: this.currentMode
            }
        });
        window.dispatchEvent(event);
    }

    // Публичные методы для внешнего использования
    getTheme() {
        return this.currentTheme;
    }

    getMode() {
        return this.currentMode;
    }

    isDark() {
        return this.currentMode === 'dark';
    }
}

// Инициализируем менеджер тем при загрузке
let themeManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        themeManager = new ThemeManager();
        window.themeManager = themeManager;
    });
} else {
    themeManager = new ThemeManager();
    window.themeManager = themeManager;
}

// Экспортируем для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
