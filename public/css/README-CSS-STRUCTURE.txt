CSS ФАЙЛЫ - СТРУКТУРА И НАЗНАЧЕНИЕ
=====================================

БАЗОВЫЕ СТИЛИ (загружаются всегда):
-----------------------------------
1. design-system.css - Основная дизайн-система (переменные, типография, базовые компоненты)
2. theme-system.css - Система тем (светлая/темная)
3. animations-enhanced.css - Анимации

LAYOUT СТИЛИ (выбирается один):
-------------------------------
4. base-layout.css - Классический layout (горизонтальный navbar + bottom nav)
5. base-modern.css - Современный layout (sidebar + top bar) - ИСПОЛЬЗУЕТ УНИКАЛЬНЫЕ КЛАССЫ

КОМПОНЕНТЫ (загружаются по необходимости):
------------------------------------------
6. components-bundle.css - Общие компоненты (карточки, модалы, формы)
7. navbar-enhanced.css - Расширенный navbar (только для base-layout.css)
8. mobile-enhanced.css - Мобильные улучшения

СТРАНИЧНЫЕ СТИЛИ (загружаются на конкретных страницах):
-------------------------------------------------------
9. dashboard-enhanced.css - Стили дашборда
10. tasks-enhanced.css - Стили задач
11. kanban-enhanced.css - Стили канбан доски
12. calendar-enhanced.css - Стили календаря
13. users-enhanced.css - Стили пользователей

УСТАРЕВШИЕ/ДУБЛИРУЮЩИЕ (можно удалить):
---------------------------------------
- app-bundle.css - дублирует design-system.css
- themes-bundle.css - дублирует theme-system.css
- navbar-design-system.css - дублирует navbar-enhanced.css
- dashboard-design-system.css - дублирует dashboard-enhanced.css
- task-design-enhancements.css - дублирует tasks-enhanced.css
- app-enhanced.css - дублирует design-system.css
- ui-enhancements.css - дублирует components-bundle.css
- form-validation-styles.css - включен в components-bundle.css

ПРАВИЛА ИСПОЛЬЗОВАНИЯ:
---------------------
1. base.html.twig использует: design-system + theme-system + animations + base-layout + navbar-enhanced
2. base_modern.html.twig использует: design-system + theme-system + animations + base-modern
3. Страничные стили загружаются условно через {% if %}

КОНФЛИКТЫ КЛАССОВ:
-----------------
- .navbar, .btn, .card определены в нескольких файлах
- .sidebar конфликтует между base-layout и base-modern
- Решение: base-modern использует .sidebar-modern, .top-bar-modern, .main-content-modern
