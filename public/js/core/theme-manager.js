/**
 * Unified Theme Manager
 * Оптимизированная система управления темами
 */

class ThemeManager {
    constructor() {
        this.THEME_KEY = 'app_theme';
        this.themes = {
            light: {
                name: 'Светлая',
                icon: 'fas fa-sun',
                class: 'theme-light'
            },
            dark: {
                name: 'Тёмная',
                icon: 'fas fa-moon',
                class: 'theme-dark'
            },
            orange: {
                name: 'Оранжевая',
                icon: 'fas fa-fire',
                class: 'theme-orange'
            },
            purple: {
                name: 'Фиолетовая',
                icon: 'fas fa-palette',
                class: 'theme-purple'
            },
            custom: {
                name: 'Настраиваемая',
                icon: 'fas fa-sliders-h',
                class: 'theme-custom'
            }
        };
        
        this.currentTheme = this.getStoredTheme();
        this.init();
    }

    init() {
        // Применяем тему до загрузки DOM для избежания мерцания
        this.applyTheme(this.currentTheme, false);
        
        // Помечаем что тема готова
        document.documentElement.classList.add('theme-ready');
        
        // Инициализация после загрузки DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeUI());
        } else {
            this.initializeUI();
        }
    }

    initializeUI() {
        this.bindEvents();
        this.updateUI();
        this.loadThemeCSS();
    }

    getStoredTheme() {
        const stored = localStorage.getItem(this.THEME_KEY);
        if (stored && this.themes[stored]) {
            return stored;
        }
        
        // Определяем тему по системным настройкам
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        
        return 'light';
    }

    applyTheme(themeName, animate = true) {
        if (!this.themes[themeName]) {
            console.warn(`Theme "${themeName}" not found`);
            return;
        }

        const html = document.documentElement;
        const body = document.body;
        
        // Добавляем класс анимации если нужно
        if (animate) {
            body.classList.add('theme-transitioning');
        }

        // Удаляем все классы тем
        Object.keys(this.themes).forEach(key => {
            html.classList.remove(`theme-${key}`);
            body.classList.remove(`theme-${key}`);
        });

        // Применяем новую тему
        html.classList.add(`theme-${themeName}`);
        body.classList.add(`theme-${themeName}`);

        // Устанавливаем data-theme атрибут
        html.setAttribute('data-theme', themeName);
        body.setAttribute('data-theme', themeName);

        // Сохраняем выбор
        this.currentTheme = themeName;
        localStorage.setItem(this.THEME_KEY, themeName);

        // Обновляем UI
        this.updateUI();

        // Отправляем событие
        window.dispatchEvent(new CustomEvent('themechange', {
            detail: { theme: themeName, themeData: this.themes[themeName] }
        }));

        // Убираем класс анимации
        if (animate) {
            setTimeout(() => {
                body.classList.remove('theme-transitioning');
            }, 300);
        }
        
        console.log(`✓ Theme changed to: ${this.themes[themeName].name}`);
    }

    loadThemeCSS() {
        // CSS переменные загружаются через themes-unified.css
        // Этот метод оставлен для совместимости
        return;
    }

    toggle() {
        const themeKeys = Object.keys(this.themes);
        const currentIndex = themeKeys.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themeKeys.length;
        this.applyTheme(themeKeys[nextIndex]);
    }

    setTheme(themeName) {
        if (this.themes[themeName]) {
            this.applyTheme(themeName);
        }
    }

    getTheme() {
        return this.currentTheme;
    }

    getThemeData() {
        return this.themes[this.currentTheme];
    }

    updateUI() {
        const themeData = this.themes[this.currentTheme];

        // Обновляем кнопки переключения
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = themeData.icon;
            }

            const tooltip = btn.getAttribute('data-tooltip');
            if (tooltip !== null) {
                btn.setAttribute('data-tooltip', themeData.name);
            }

            const text = btn.querySelector('[data-theme-text]');
            if (text) {
                text.textContent = themeData.name;
            }
        });

        // Обновляем селекторы тем
        document.querySelectorAll('[data-theme-option]').forEach(option => {
            const optionTheme = option.getAttribute('data-theme-option');
            const isActive = optionTheme === this.currentTheme;
            
            option.classList.toggle('active', isActive);
            option.setAttribute('aria-current', isActive ? 'true' : 'false');
            
            if (isActive) {
                option.setAttribute('aria-label', `Текущая тема: ${this.themes[optionTheme].name}`);
            } else {
                option.setAttribute('aria-label', `Переключить на тему: ${this.themes[optionTheme].name}`);
            }
        });
    }

    bindEvents() {
        // Кнопки переключения темы
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        });

        // Селекторы конкретной темы
        document.querySelectorAll('[data-theme-option]').forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const themeName = option.getAttribute('data-theme-option');
                this.applyTheme(themeName);
            });
        });

        // Горячая клавиша Alt+T
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                this.toggle();
            }
        });

        // Отслеживание системных настроек
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Применяем только если пользователь не выбрал тему вручную
            if (!localStorage.getItem(this.THEME_KEY)) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
}

// Инициализация при загрузке
if (typeof window !== 'undefined') {
    // Создаем экземпляр сразу для быстрого применения темы
    window.themeManager = new ThemeManager();
}

// Экспорт для модулей
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
