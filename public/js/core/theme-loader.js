/**
 * Theme Loader
 * Динамическое подключение тем для sidebar и topbar
 */

(function() {
    'use strict';
    
    // Определяем тип layout (sidebar или topbar)
    const isSidebarLayout = document.querySelector('.sidebar') !== null;
    const layoutType = isSidebarLayout ? 'sidebar' : 'topbar';
    
    // Получаем текущую тему из localStorage или используем светлую по умолчанию
    const currentTheme = localStorage.getItem('app_theme') || 'light';
    
    // Функция для загрузки CSS файла темы
    function loadThemeCSS(theme) {
        // Удаляем предыдущий файл темы если есть
        const existingThemeLink = document.getElementById('dynamic-theme-css');
        if (existingThemeLink) {
            existingThemeLink.remove();
        }
        
        // Создаём новый link элемент
        const link = document.createElement('link');
        link.id = 'dynamic-theme-css';
        link.rel = 'stylesheet';
        link.href = `/css/themes/${layoutType}-theme-${theme}.css`;
        
        // Добавляем в head
        document.head.appendChild(link);
        
        // Применяем класс темы к body
        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        if (theme !== 'light') {
            document.body.classList.add(`theme-${theme}`);
        }
        document.body.setAttribute('data-theme', theme);
        
        // Применяем к html тоже
        document.documentElement.className = document.documentElement.className.replace(/theme-\w+/g, '');
        if (theme !== 'light') {
            document.documentElement.classList.add(`theme-${theme}`);
        }
        document.documentElement.setAttribute('data-theme', theme);
    }
    
    // Загружаем тему при загрузке страницы
    loadThemeCSS(currentTheme);
    
    // Обновляем активную кнопку темы
    function updateActiveThemeButton() {
        const themeButtons = document.querySelectorAll('[data-theme-option]');
        themeButtons.forEach(btn => {
            if (btn.getAttribute('data-theme-option') === currentTheme) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    // Слушаем изменения темы
    document.addEventListener('DOMContentLoaded', function() {
        updateActiveThemeButton();
        
        // Обработчики для кнопок переключения темы
        const themeButtons = document.querySelectorAll('[data-theme-option]');
        themeButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const newTheme = this.getAttribute('data-theme-option');
                
                // Сохраняем в localStorage
                localStorage.setItem('app_theme', newTheme);
                
                // Загружаем новую тему
                loadThemeCSS(newTheme);
                
                // Обновляем активную кнопку
                themeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Диспатчим событие для других скриптов
                window.dispatchEvent(new CustomEvent('themeChanged', { 
                    detail: { theme: newTheme } 
                }));
            });
        });
        
        // Поддержка FAB кнопки переключения темы
        const fabThemeToggle = document.querySelector('[data-theme-toggle]');
        if (fabThemeToggle) {
            fabThemeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Циклически переключаем темы
                const themes = ['light', 'dark', 'orange', 'purple', 'custom'];
                const currentIndex = themes.indexOf(currentTheme);
                const nextTheme = themes[(currentIndex + 1) % themes.length];
                
                localStorage.setItem('app_theme', nextTheme);
                loadThemeCSS(nextTheme);
                updateActiveThemeButton();
                
                window.dispatchEvent(new CustomEvent('themeChanged', { 
                    detail: { theme: nextTheme } 
                }));
            });
        }
    });
    
    // Экспортируем функции для использования в других скриптах
    window.themeLoader = {
        loadTheme: loadThemeCSS,
        getCurrentTheme: () => localStorage.getItem('app_theme') || 'light',
        setTheme: (theme) => {
            localStorage.setItem('app_theme', theme);
            loadThemeCSS(theme);
            updateActiveThemeButton();
        }
    };
})();
