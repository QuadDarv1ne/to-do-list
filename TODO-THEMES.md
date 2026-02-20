# TODO: Создать 4 дополнительные темы

## Задача
Создать 4 отдельных CSS файла для тем, чтобы всего было 5 тем для переключателя.

## Текущее состояние
- ✅ Темная тема (dark) - уже создана в `dashboard-modern-dark.css`
- ⏳ Светлая тема (light) - нужно создать
- ⏳ Оранжевая тема (orange) - нужно создать
- ⏳ Фиолетовая тема (purple) - нужно создать
- ⏳ Кастомная тема (custom) - нужно создать

## Файлы для создания

### 1. Светлая тема
**Файл:** `public/css/theme-light.css`
**Цвета:**
- Фон: #ffffff, #f8f9fa
- Текст: #1a1a1a, #666666
- Акценты: синий, зеленый

### 2. Оранжевая тема
**Файл:** `public/css/theme-orange.css`
**Цвета:**
- Фон: #1a1a1a (темный)
- Акценты: #ff9f43, #ff6348, #ffa502
- Градиенты: оранжевые оттенки

### 3. Фиолетовая тема
**Файл:** `public/css/theme-purple.css`
**Цвета:**
- Фон: #1a1a1a (темный)
- Акценты: #8b7ff4, #5b9cff, #a29bfe
- Градиенты: фиолетово-синие

### 4. Кастомная тема (зеленая)
**Файл:** `public/css/theme-custom.css`
**Цвета:**
- Фон: #1a1a1a (темный)
- Акценты: #4dd4ac, #26de81, #20bf6b
- Градиенты: зелено-бирюзовые

## Что нужно сделать

1. Создать 4 CSS файла с переменными для каждой темы
2. Минифицировать каждый файл (создать .min.css версии)
3. Обновить JavaScript в `templates/dashboard/index_modern.html.twig`:
   - Добавить загрузку соответствующего CSS файла при переключении темы
   - Динамически подключать/отключать файлы тем

4. Обновить `templates/base_modern.html.twig`:
   - Добавить блок для динамической загрузки тем

## Пример структуры CSS файла темы

```css
:root {
    /* Background Colors */
    --bg-primary: #...;
    --bg-secondary: #...;
    --bg-card: #...;
    
    /* Text Colors */
    --text-primary: #...;
    --text-secondary: #...;
    
    /* Accent Colors */
    --accent-primary: #...;
    --accent-secondary: #...;
    
    /* Gradients */
    --gradient-1: linear-gradient(...);
    --gradient-2: linear-gradient(...);
}

body.theme-[name] {
    /* Применение переменных */
}
```

## Команды для минификации

```bash
# Светлая тема
php -r "file_put_contents('public/css/theme-light.min.css', preg_replace('/\s+/', ' ', preg_replace('/\/\*.*?\*\//s', '', file_get_contents('public/css/theme-light.css'))));"

# Оранжевая тема
php -r "file_put_contents('public/css/theme-orange.min.css', preg_replace('/\s+/', ' ', preg_replace('/\/\*.*?\*\//s', '', file_get_contents('public/css/theme-orange.css'))));"

# Фиолетовая тема
php -r "file_put_contents('public/css/theme-purple.min.css', preg_replace('/\s+/', ' ', preg_replace('/\/\*.*?\*\//s', '', file_get_contents('public/css/theme-purple.css'))));"

# Кастомная тема
php -r "file_put_contents('public/css/theme-custom.min.css', preg_replace('/\s+/', ' ', preg_replace('/\/\*.*?\*\//s', '', file_get_contents('public/css/theme-custom.css'))));"
```

## Дата создания
19 февраля 2026

## Напоминание
**ЗАВТРА:** Создать 4 дополнительные темы для переключателя тем в дашборде
