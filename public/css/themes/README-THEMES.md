# Система тем оформления

## Структура файлов

### Layout файлы (общие стили структуры)
- `sidebar-layout.css` - базовые стили для страниц с боковым меню (структура, размеры, позиционирование)
- `topbar-layout.css` - базовые стили для страниц с верхним меню (структура, размеры, позиционирование)

### Файлы тем для Sidebar Layout (цветовые схемы)
- `sidebar-theme-light.css` - светлая тема
- `sidebar-theme-dark.css` - тёмная тема
- `sidebar-theme-orange.css` - оранжевая тема
- `sidebar-theme-purple.css` - фиолетовая тема
- `sidebar-theme-custom.css` - настраиваемая пользователем (зелёная по умолчанию)

### Файлы тем для Topbar Layout (цветовые схемы)
- `topbar-theme-light.css` - светлая тема
- `topbar-theme-dark.css` - тёмная тема
- `topbar-theme-orange.css` - оранжевая тема
- `topbar-theme-purple.css` - фиолетовая тема
- `topbar-theme-custom.css` - настраиваемая пользователем (зелёная по умолчанию)

### JavaScript
- `theme-manager-enhanced.js` - управление темами с плавными переходами

## Как это работает

1. Layout файлы (`sidebar-layout.css` или `topbar-layout.css`) загружаются всегда - они содержат структуру
2. При загрузке страницы `theme-manager-enhanced.js` читает выбранную тему из localStorage (по умолчанию 'light')
3. Динамически подключает соответствующий файл темы (например, `sidebar-theme-dark.css`)
4. Применяет data-атрибуты к html элементу: `data-theme="dark"` и `data-mode="dark"`
5. Обновляет meta theme-color для PWA

## Переключение тем

### Через кнопки в sidebar footer
```html
<button class="theme-option" data-theme-option="light">
    <i class="fas fa-sun"></i>
</button>
<button class="theme-option" data-theme-option="dark">
    <i class="fas fa-moon"></i>
</button>
<button class="theme-option" data-theme-option="orange">
    <i class="fas fa-fire"></i>
</button>
<button class="theme-option" data-theme-option="purple">
    <i class="fas fa-palette"></i>
</button>
<button class="theme-option" data-theme-option="custom">
    <i class="fas fa-sliders-h"></i>
</button>
```

### Программно через JavaScript
```javascript
// Установить тему
window.themeManager.setTheme('dark');

// Переключить режим (light/dark)
window.themeManager.toggleMode();

// Циклическое переключение тем
window.themeManager.cycleTheme();

// Получить текущую тему
const currentTheme = window.themeManager.getTheme();

// Проверить тёмный режим
const isDark = window.themeManager.isDark();

// Слушать изменения темы
window.addEventListener('themechange', (e) => {
    console.log('Новая тема:', e.detail.theme);
    console.log('Режим:', e.detail.mode);
});
```

## Добавление новой темы

1. Создайте файлы `sidebar-theme-{name}.css` и `topbar-theme-{name}.css`
2. Скопируйте структуру из существующей темы (например, из light)
3. Измените только цвета - структура уже в layout файлах
4. Добавьте кнопку переключения с `data-theme-option="{name}"`
5. Обновите массив тем в `theme-manager-enhanced.js` в методе `cycleTheme()` если нужно

## Структура темы

### Layout файлы содержат:
- Размеры элементов (width, height, padding, margin)
- Позиционирование (position, display, flex)
- Структуру grid/flexbox
- Transitions и animations
- Responsive breakpoints
- Z-index слои

### Файлы тем содержат:
- Цвета фона (background)
- Цвета текста (color)
- Цвета границ (border-color)
- Цвета теней (box-shadow)
- Градиенты
- Hover/active состояния цветов

## Оптимизация

- Layout файлы загружаются один раз и кэшируются
- Файлы тем загружаются динамически - только одна тема активна
- Нет дублирования структурных стилей между темами
- Быстрое переключение с плавной анимацией
- Сохранение выбора в localStorage
- Поддержка системных настроек (prefers-color-scheme)

## Использование в шаблонах

### Sidebar Layout (base_sidebar.html.twig)
```html
<link rel="stylesheet" href="{{ asset('css/themes/sidebar-layout.css') }}">
<script src="{{ asset('js/core/theme-manager-enhanced.js') }}"></script>
```

### Topbar Layout (base.html.twig)
```html
<link rel="stylesheet" href="{{ asset('css/themes/topbar-layout.css') }}">
<script src="{{ asset('js/core/theme-manager-enhanced.js') }}"></script>
```

Файлы тем подключаются автоматически через JavaScript.
