# Улучшения UX/UI и CSP - Февраль 2026

## Внесённые изменения

### 1. Content Security Policy (CSP)

#### Установленные компоненты
- **NelmioSecurityBundle** - профессиональное CSP решение для Symfony
- **CspReportController** - обработка отчётов о нарушениях
- **Маршрут `/csp-report`** - endpoint для browser CSP reports

#### Конфигурация для DEV
**Файл:** `config/packages/dev/nelmio_security.yaml`

```yaml
nelmio_security:
    csp:
        enabled: true
        report_only: true  # Только логи, без блокировок
        script-src:
            - 'self'
            - 'unsafe-inline'
            - 'unsafe-eval'
            - https://cdn.jsdelivr.net
            - https://cdnjs.cloudflare.com
```

#### Конфигурация для PROD
**Файл:** `config/packages/prod/nelmio_security.yaml`

```yaml
nelmio_security:
    csp:
        enabled: true
        report_only: false  # Блокирующий режим
        script-src:
            - 'self'
            - 'strict-dynamic'
            # 'unsafe-inline' и 'unsafe-eval' запрещены
```

### 2. Улучшения Base Layout

#### Новый `templates/base.html.twig`

**Основные изменения:**

1. **Page Loader**
   - Красивый градиентный лоадер при загрузке страницы
   - Плавное исчезновение после загрузки
   - Анимация spinner с тенью

2. **Skip Link**
   - Улучшенная доступность
   - Градиентный фон
   - Плавная анимация появления

3. **Навигация**
   - Glassmorphism эффект для navbar
   - Улучшенные hover эффекты
   - Градиентные акценты
   - Анимированные иконки

4. **Quick Actions**
   - Кнопки с градиентным фоном
   - Анимация при наведении
   - Тени и масштабирование

5. **Notification Badge**
   - Pulse анимация для новых уведомлений
   - Градиентный фон
   - Улучшенная видимость

6. **User Avatar**
   - Градиентный фон
   - Анимация при наведении
   - Закруглённые углы

7. **Dropdown Menus**
   - Увеличенные border-radius (16px)
   - Улучшенные тени
   - Плавные переходы
   - Transform эффекты для items

8. **Mobile Bottom Navigation**
   - Фиксированная навигация внизу
   - Активная кнопка с градиентом
   - Quick action кнопка по центру
   - Анимированные иконки

9. **FAB Button (Floating Action Button)**
   - Градиентный фон
   - Анимация вращения при hover
   - Выпадающее меню
   - Backdrop для затемнения фона

10. **CSP Nonce Support**
    - Все скрипты получают nonce атрибут в prod
    - Корректная работа с CSP
    - Безопасная загрузка

### 3. Новый CSS файл `base-layout.css`

**Основные стили:**

```css
/* Page Loader */
.page-loader {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Skip Link */
.skip-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Navbar */
.navbar-enhanced {
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
}

/* Quick Actions */
.quick-action-btn-enhanced {
    background: rgba(102, 126, 234, 0.1);
}

/* Bottom Navigation */
.bottom-nav-item.quick-action {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* FAB Button */
.bottom-nav-fab {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

### 4. Критические JS функции

**Новый файл:** `public/js/critical-functions.js`

Содержит:
- `showToast()` - функция уведомлений
- Console wrapper для production
- Загружается без `defer` для мгновенной доступности

### 5. Порядок загрузки скриптов

```html
<!-- 1. Critical functions (без defer) -->
<script src="/js/critical-functions.js"></script>

<!-- 2. Utility scripts -->
<script src="/js/logger.js" defer></script>
<script src="/js/timer-manager.js" defer></script>
<script src="/js/event-manager.js" defer></script>

<!-- 3. Core bundle -->
<script src="/js/core-bundle.min.js" defer></script>

<!-- 4. Page-specific scripts -->
<script src="/js/table-enhancements.js" defer></script>
```

## Цветовая схема

### Основные цвета
```css
--primary: #0d6efd
--primary-dark: #0a58ca
--gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%)
```

### Состояния
```css
--success: #198754
--warning: #ffc107
--danger: #dc3545
--info: #0dcaf0
```

## Анимации

### Page Load
1. Page loader показывается с градиентным фоном
2. Spinner вращается
3. После загрузки body получает класс `loaded`
4. Page loader плавно исчезает

### Hover Effects
- **Lift**: `transform: translateY(-4px)`
- **Scale**: `transform: scale(1.05)`
- **Glow**: `box-shadow: 0 0 20px rgba(102, 126, 234, 0.5)`

### Transitions
- Все переходы: `0.3s ease`
- Плавные изменения цвета и трансформации

## Доступность (A11y)

1. **Skip Link** - переход к основному контенту
2. **ARIA labels** - для кнопок и навигации
3. **Focus visible** - контур при фокусе
4. **Keyboard navigation** - все элементы доступны с клавиатуры
5. **Screen reader support** - семантическая разметка

## Производительность

### Оптимизации
1. **Critical CSS inline** - мгновенная отрисовка
2. **Defer для скриптов** - не блокируют парсинг
3. **Preconnect к CDN** - быстрая загрузка ресурсов
4. **DNS Prefetch** - предварительное разрешение имён
5. **Lazy loading** - загрузка по требованию

### CSP Performance
1. **Report only mode в dev** - сбор статистики без блокировок
2. **Логирование нарушений** - через Monolog
3. **Адаптивная политика** - для разных окружений

## Мониторинг CSP нарушений

### Просмотр логов
```bash
# Dev environment
tail -f var/log/dev.log | grep -i csp

# Prod environment
tail -f var/log/prod.log | grep -i csp
```

### Пример нарушения в логе
```
[2026-02-19 12:34:56] security.WARNING: CSP Violation 
{"blocked-uri":"https://evil.com/script.js",
"violated_directive":"script-src 'self'"}
```

## Команды для разработки

```bash
# Проверка CSP конфигурации
php bin/console debug:config nelmio_security

# Очистка кеша
php bin/console cache:clear

# Тестирование CSP в prod режиме
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod symfony serve

# Просмотр логов в реальном времени
tail -f var/log/dev.log | grep -i csp
```

## Чеклист перед деплоем

- [ ] CSP работает в blocking mode (`report_only: false`)
- [ ] Все инлайн-скрипты используют nonce
- [ ] Нет нарушений CSP в логах за неделю
- [ ] `'unsafe-inline'` удалён из `script-src`
- [ ] `'unsafe-eval'` удалён (если возможно)
- [ ] `frame-ancestors: 'none'` для защиты от clickjacking
- [ ] HSTS включён для HTTPS
- [ ] Отчёты CSP логируются и мониторятся
- [ ] Все UI компоненты работают корректно
- [ ] Mobile navigation отображается правильно
- [ ] FAB button функционирует
- [ ] Toast уведомления показываются

## Известные проблемы и решения

### Проблема: Скрипты не загружаются в prod
**Решение:** Добавить nonce атрибут ко всем инлайн-скриптам

### Проблема: Стили не применяются
**Решение:** Проверить `style-src` директиву в CSP

### Проблема: Toast не показывается
**Решение:** `critical-functions.js` должен загружаться без `defer`

## Будущие улучшения

1. **Service Worker** - офлайн режим
2. **Lazy loading изображений** - оптимизация загрузки
3. **Code splitting** - разделение JS на чанки
4. **CSS Modules** - изоляция стилей
5. **Web Vitals мониторинг** - метрики производительности

---

**Дата обновления:** 19 февраля 2026
**Версия:** 2.0
