# Руководство по оптимизации производительности

## ✅ Реализованные улучшения

### 1. Критический CSS (Inline)
**Файл:** `public/css/critical.css`
**Размер:** ~14KB сжатый

Критический CSS встроен непосредственно в `<head>` для мгновенной отрисовки первого экрана.

**Что включено:**
- CSS переменные (светлая/тёмная тема)
- Базовый reset и стили
- Навигация (navbar)
- Кнопки и базовые компоненты
- Утилитарные классы (flex, grid, spacing)
- Skeleton loaders
- Анимации переходов

**Результат:**
- First Contentful Paint (FCP): < 1s
- Largest Contentful Paint (LCP): < 2.5s

### 2. Асинхронная загрузка CSS
Некритические стили загружаются асинхронно через `preload`:

```html
<link rel="preload" href="/css/design-system-tokens.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
```

**Преимущества:**
- Не блокирует рендеринг
- Загружается в фоне
- Fallback для браузеров без JavaScript

### 3. Code Splitting для JavaScript
**Файл:** `public/js/performance-optimizer.js`

**Принципы загрузки:**

| Приоритет | Скрипты | Когда |
|-----------|---------|-------|
| **Critical** | toast-system.js, state-manager.js | Синхронно |
| **High** | performance-optimizer.js, utils.js | Defer |
| **Normal** | keyboard-navigation, theme-switcher | Defer |
| **Page-specific** | table-enhancements, kanban, calendar | Только на нужных страницах |
| **Low** | analytics, monitoring | requestIdleCallback |

### 4. Lazy Loading для изображений
**Файл:** `public/js/lazy-load.js`

**Функции:**
- IntersectionObserver для отслеживания видимости
- WebP detection с fallback
- Placeholder для предотвращения CLS
- Адаптивные srcset изображения
- Предзагрузка при приближении к viewport

**Использование:**
```html
<img data-src="/path/to/image.jpg" 
     data-src-webp="/path/to/image.webp"
     loading="lazy" 
     alt="Description">
```

### 5. Preconnect и Resource Hints
```html
<!-- DNS Prefetch -->
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

<!-- Preconnect с CORS -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

<!-- Preload критических ресурсов -->
<link rel="preload" href="bootstrap.min.css" as="style">
<link rel="preload" href="font-awesome.min.css" as="style">
```

### 6. Кэширование ресурсов

#### HTTP заголовки для production

**Для статических ресурсов (CSS, JS, изображения):**
```
Cache-Control: public, max-age=31536000, immutable
```

**Для HTML страниц:**
```
Cache-Control: no-cache, must-revalidate
ETag: "unique-hash"
```

**Для API запросов:**
```
Cache-Control: private, max-age=0, must-revalidate
```

#### Настройка .htaccess (Apache)
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    
    # CSS и JS - 1 год
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType application/x-javascript "access plus 1 year"
    
    # Изображения - 1 год
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    
    # Шрифты - 1 год
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType application/font-woff2 "access plus 1 year"
    
    # HTML - без кэширования
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

<IfModule mod_headers.c>
    # Кэширование статики
    <FilesMatch "\.(css|js|jpg|jpeg|png|gif|webp|svg|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    
    # Без кэширования для HTML
    <FilesMatch "\.(html|php)$">
        Header set Cache-Control "no-cache, must-revalidate"
    </FilesMatch>
</IfModule>
```

#### Nginx конфигурация
```nginx
# Статические ресурсы
location ~* \.(css|js|jpg|jpeg|png|gif|webp|svg|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}

# HTML страницы
location ~* \.(html|php)$ {
    add_header Cache-Control "no-cache, must-revalidate";
}
```

### 7. Минификация ресурсов

#### CSS минификация
```bash
# Установка cssnano
npm install cssnano -g

# Минификация CSS файлов
for file in public/css/*.css; do
    cssnano $file ${file%.css}.min.css
done
```

#### JavaScript минификация
```bash
# Установка terser
npm install terser -g

# Минификация JS файлов
for file in public/js/*.js; do
    terser $file -o ${file%.js}.min.js -c -m
done
```

#### Автоматическая минификация через Symfony
```yaml
# config/packages/prod/asset_mapper.yaml
framework:
    asset_mapper:
        minify: true
```

### 8. Оптимизация изображений

#### Конвертация в WebP
```bash
# Установка cwebp
# Ubuntu/Debian
sudo apt-get install webp

# Windows
# Скачать с https://developers.google.com/speed/webp/download

# Конвертация всех изображений
find public/uploads -name "*.jpg" -o -name "*.png" | while read file; do
    cwebp -q 80 "$file" -o "${file%.*}.webp"
done
```

#### Оптимизация существующих изображений
```bash
# JPEG оптимизация
jpegoptim --max=85 --strip-all public/uploads/*.jpg

# PNG оптимизация
pngquant --quality=65-80 --ext=.png --force public/uploads/*.png

# SVG оптимизация
svgo --folder public/uploads/svg --output public/uploads/svg-optimized
```

### 9. Мониторинг производительности

#### Lighthouse CI
```bash
# Установка
npm install -g @lhci/cli

# Запуск аудита
lhci autorun --upload.url="https://your-lhci-server.com"
```

#### Web Vitals отслеживание
```javascript
// assets/app.js
import {onCLS, onFID, onFCP, onLCP, onTTFB} from 'web-vitals';

onCLS(console.log);
onFID(console.log);
onFCP(console.log);
onLCP(console.log);
onTTFB(console.log);
```

### 10. Production чеклист

#### Перед деплоем:
- [ ] Очистить кэш: `php bin/console cache:clear --env=prod`
- [ ] Прогреть кэш: `php bin/console cache:warmup --env=prod`
- [ ] Минифицировать CSS/JS
- [ ] Оптимизировать изображения
- [ ] Включить gzip/brotli сжатие

#### Проверка:
```bash
# Проверка производительности
php bin/console app:performance-check

# Проверка кэша
php bin/console debug:cache

# Проверка маршрутов
php bin/console debug:router
```

## 📊 Ожидаемые результаты

| Метрика | До | После | Цель |
|---------|-----|-------|------|
| **FCP** | 2.5s | < 1s | < 1.8s |
| **LCP** | 4.2s | < 2.5s | < 2.5s |
| **CLS** | 0.15 | < 0.1 | < 0.1 |
| **TBT** | 500ms | < 200ms | < 200ms |
| **Размер CSS** | 450KB | 180KB | < 200KB |
| **Размер JS** | 650KB | 280KB | < 300KB |

## 🔧 Полезные команды

```bash
# Анализ размера бандлов
npm run build -- --analyze

# Проверка производительности
npm run lighthouse

# Оптимизация изображений
npm run optimize:images

# Генерация critical CSS
npm run critical
```

## 📚 Дополнительные ресурсы

- [Web.dev Performance](https://web.dev/performance/)
- [Google PageSpeed Insights](https://pagespeed.web.dev/)
- [Web Vitals](https://web.dev/vitals/)
- [CSS Tricks - Critical CSS](https://css-tricks.com/annotating-critical-css/)
