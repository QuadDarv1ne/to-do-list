/**
 * Unified Theme System
 * Работает с обоими layout: base.html.twig и base_sidebar.html.twig
 */

class UnifiedThemeSystem {
    constructor() {
        this.themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        this.currentTheme = this.getStoredTheme() || 'dark';
        this.init();
    }

    init() {
        // Применяем сохранённую тему сразу
        this.applyTheme(this.currentTheme, false);
        
        // Инициализируем обработчики событий
        this.setupEventListeners();
        
        // Обновляем UI
        this.updateThemeUI();
    }

    setupEventListeners() {
        // Обработчики для base_sidebar.html.twig
        const sidebarThemeBtn = document.querySelector('.sidebar .icon-btn .fa-moon, .sidebar .icon-btn .fa-sun');
        if (sidebarThemeBtn) {
            const btn = sidebarThemeBtn.closest('.icon-btn');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.cycleTheme();
                });
            }
        }

        // Обработчики для base.html.twig (modern header)
        const headerThemeBtn = document.querySelector('.modern-action-btn .fa-moon, .modern-action-btn .fa-sun');
        if (headerThemeBtn) {
            const btn = headerThemeBtn.closest('.modern-action-btn');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.cycleTheme();
                });
            }
        }

        // Обработчики для dashboard theme switcher
        const dashboardThemeOptions = document.querySelectorAll('.theme-option[onclick*="switchTheme"]');
        dashboardThemeOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const themeMatch = option.getAttribute('onclick').match(/switchTheme\('(\w+)'\)/);
                if (themeMatch) {
                    this.setTheme(themeMatch[1]);
                }
            });
        });

        // Горячая клавиша T для переключения темы
        document.addEventListener('keydown', (e) => {
            if (e.key === 't' && !e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
                const target = e.target;
                if (target.tagName !== 'INPUT' && target.tagName !== 'TEXTAREA' && !target.isContentEditable) {
                    e.preventDefault();
                    this.cycleTheme();
                }
            }
        });
    }

    cycleTheme() {
        const currentIndex = this.themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % this.themes.length;
        this.setTheme(this.themes[nextIndex]);
    }

    setTheme(theme) {
        if (!this.themes.includes(theme)) {
            console.warn(`Invalid theme: ${theme}`);
            return;
        }

        this.currentTheme = theme;
        this.applyTheme(theme, true);
        this.storeTheme(theme);
        this.updateThemeUI();
        
        // Показываем уведомление
        this.showThemeNotification(theme);
    }

    applyTheme(theme, animate = true) {
        const root = document.documentElement;
        const body = document.body;

        // Добавляем класс анимации если нужно
        if (animate) {
            body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        }

        // Устанавливаем атрибут темы
        root.setAttribute('data-theme', theme);
        
        // Применяем класс темы к body
        body.className = body.className.replace(/theme-\w+/g, '');
        body.classList.add(`theme-${theme}`);

        // Применяем CSS переменные в зависимости от темы
        const themeColors = this.getThemeColors(theme);
        Object.entries(themeColors).forEach(([key, value]) => {
            root.style.setProperty(`--${key}`, value);
        });

        // Обновляем иконки
        this.updateThemeIcons(theme);

        // Убираем анимацию после применения
        if (animate) {
            setTimeout(() => {
                body.style.transition = '';
            }, 300);
        }
    }

    getThemeColors(theme) {
        const themes = {
            light: {
                'bg-dark': '#f8fafc',
                'bg-darker': '#ffffff',
                'text-light': '#0f172a',
                'text-muted': '#64748b',
                'border-color': '#e2e8f0',
                'card-bg': '#ffffff',
                'primary-color': '#6366f1',
                'primary-hover': '#4f46e5'
            },
            dark: {
                'bg-dark': '#1a1d2e',
                'bg-darker': '#151824',
                'text-light': '#e2e8f0',
                'text-muted': '#94a3b8',
                'border-color': '#2d3548',
                'card-bg': '#1e2139',
                'primary-color': '#6366f1',
                'primary-hover': '#4f46e5'
            },
            orange: {
                'bg-dark': '#fff7ed',
                'bg-darker': '#ffedd5',
                'text-light': '#1c1917',
                'text-muted': '#78716c',
                'border-color': '#fed7aa',
                'card-bg': '#ffedd5',
                'primary-color': '#f97316',
                'primary-hover': '#ea580c'
            },
            purple: {
                'bg-dark': '#faf5ff',
                'bg-darker': '#f3e8ff',
                'text-light': '#1e1b4b',
                'text-muted': '#6b7280',
                'border-color': '#e9d5ff',
                'card-bg': '#f3e8ff',
                'primary-color': '#a855f7',
                'primary-hover': '#9333ea'
            },
            custom: {
                'bg-dark': '#f9fafb',
                'bg-darker': '#ffffff',
                'text-light': '#1f2937',
                'text-muted': '#6b7280',
                'border-color': '#e5e7eb',
                'card-bg': '#ffffff',
                'primary-color': '#667eea',
                'primary-hover': '#5568d3'
            }
        };

        return themes[theme] || themes.dark;
    }

    updateThemeIcons(theme) {
        // Обновляем иконки в sidebar
        const sidebarIcon = document.querySelector('.sidebar .icon-btn i');
        if (sidebarIcon) {
            sidebarIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Обновляем иконки в modern header
        const headerIcon = document.querySelector('.modern-action-btn i.fa-moon, .modern-action-btn i.fa-sun');
        if (headerIcon) {
            headerIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    updateThemeUI() {
        // Обновляем активные опции в dashboard theme switcher
        document.querySelectorAll('.theme-option').forEach(option => {
            const themeMatch = option.getAttribute('onclick')?.match(/switchTheme\('(\w+)'\)/);
            if (themeMatch) {
                const optionTheme = themeMatch[1];
                if (optionTheme === this.currentTheme) {
                    option.classList.add('active');
                } else {
                    option.classList.remove('active');
                }
            }
        });
    }

    showThemeNotification(theme) {
        const themeNames = {
            light: 'Светлая тема',
            dark: 'Тёмная тема',
            orange: 'Оранжевая тема',
            purple: 'Фиолетовая тема',
            custom: 'Настраиваемая тема'
        };

        const notification = document.createElement('div');
        notification.className = 'theme-notification';
        notification.textContent = themeNames[theme] || theme;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-weight: 600;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 2000);
    }

    storeTheme(theme) {
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            console.error('Failed to store theme:', e);
        }
    }

    getStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (e) {
            console.error('Failed to retrieve theme:', e);
            return null;
        }
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    getAvailableThemes() {
        return [...this.themes];
    }
}

// Добавляем анимации для уведомлений
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
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

// Инициализация при загрузке
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.unifiedThemeSystem = new UnifiedThemeSystem();
    });
} else {
    window.unifiedThemeSystem = new UnifiedThemeSystem();
}

// Экспорт для использования в других скриптах
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnifiedThemeSystem;
}
