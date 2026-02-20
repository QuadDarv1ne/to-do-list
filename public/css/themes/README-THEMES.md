# Система тем оформления

## Структура файлов

### Для страниц с боковым меню (sidebar)
- `sidebar-layout.css` - базовые стили layout (независимо от темы)
- `sidebar-theme-light.css` - светлая тема
- `sidebar-theme-dark.css` - тёмная тема
- `sidebar-theme-orange.css` - оранжевая тема
- `sidebar-theme-purple.css` - фиолетовая тема
- `sidebar-theme-custom.css` - настраиваемая (зелёная) тема

### Для страниц с верхним меню (topbar)
- `topbar-theme-light.css` - светлая тема
- `topbar-theme-dark.css` - тёмная тема
- `topbar-theme-orange.css` - оранжевая тема
- `topbar-theme-purple.css` - фиолетовая тема
- `topbar-theme-custom.css` - настраиваемая (зелёная) тема

### Общие файлы
- `themes-unified.css` - CSS переменные для всех тем
- `theme-loader.js` - динамическое подключение тем

## Как это работает

1. При загрузке страницы `theme-loader.js` определяет тип layout (sidebar или topbar)
2. Читает выбранную тему из localStorage (по умолчанию 'light')
3. Динамически подключает соответствующий CSS файл
4. Применяет классы темы к body и html

## Переключение тем

### Через кнопки в sidebar/topbar
```html
<button data-theme-option="light">Светлая</button>
<button data-theme-option="dark">Тёмная</button>
<button data-theme-option="orange">Оранжевая</button>
<button data-theme-option="purple">Фиолетовая</button>
<button data-theme-option="custom">Настраиваемая</button>
```

### Программно через JavaScript
```javascript
// Установить тему
window.themeLoader.setTheme('dark');

// Получить текущую тему
const currentTheme = window.themeLoader.getCurrentTheme();

// Слушать изменения темы
window.addEventListener('themeChanged', (e) => {
    console.log('Новая тема:', e.detail.theme);
});
```

## Добавление новой темы

1. Создайте файл `sidebar-theme-{name}.css` и/или `topbar-theme-{name}.css`
2. Скопируйте структуру из существующей темы
3. Измените цвета под свои нужды
4. Добавьте кнопку переключения с `data-theme-option="{name}"`
5. Обновите массив тем в `theme-loader.js` если используете FAB кнопку

## Оптимизация

- Темы загружаются динамически - только одна тема активна в момент времени
- Нет дублирования CSS кода
- Быстрое переключение без перезагрузки страницы
- Сохранение выбора в localStorage
