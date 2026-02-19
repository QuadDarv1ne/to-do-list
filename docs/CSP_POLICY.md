# Content Security Policy (CSP) в Symfony и Laravel

## Содержание

1. [Введение](#введение)
2. [Что такое CSP](#что-такое-csp)
3. [CSP в Symfony](#csp-в-symfony)
4. [CSP в Laravel](#csp-в-laravel)
5. [Директивы CSP](#директивы-csp)
6. [Лучшие практики](#лучшие-практики)
7. [Отладка и тестирование](#отладка-и-тестирование)
8. [Реализация в проекте To-Do List](#реализация-в-проекте-to-do-list)

---

## Введение

Content Security Policy (CSP) — это механизм безопасности, который помогает защитить веб-приложения от XSS-атак, инъекций контента и других уязвимостей, контролируя, какие ресурсы браузер может загружать и выполнять.

---

## Что такое CSP

CSP работает через HTTP-заголовок `Content-Security-Policy`, который указывает браузеру, какие источники контента считаются доверенными.

### Основные преимущества

- **Защита от XSS** — блокирует выполнение вредоносных скриптов
- **Контроль ресурсов** — управляет загрузкой скриптов, стилей, изображений
- **Отчётность** — позволяет получать уведомления о нарушениях политики
- **Гибкость** — тонкая настройка для разных сред (dev, prod)

### Формат заголовка

```
Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.example.com; style-src 'self' 'unsafe-inline'
```

---

## CSP в Symfony

### Установка пакета NelmioSecurityBundle

```bash
composer require nelmio/security-bundle
```

### Конфигурация

**Файл:** `config/packages/nelmio_security.yaml`

```yaml
nelmio_security:
    # Content Security Policy
    csp:
        enabled: true
        
        # Режим отладки (только сбор отчётов, без блокировки)
        report_only: false
        
        # Хосты, для которых применяется CSP
        hosts: []
        
        # Источники контента по умолчанию
        default:
            - 'self'
        
        # Уровни CSP (2 или 3)
        level: 2
        
        # Сообщение для browser console при нарушении
        message: 'Нарушение Content Security Policy'
        
        # Настройки отчётности
        report_endpoint: /csp-report
        report_logger_service: logger
        
        # Директивы CSP
        directives:
            # Источники для скриптов
            script-src:
                - 'self'
                - 'unsafe-inline'  # Только для dev, удалите в prod
                - https://www.google-analytics.com
            
            # Источники для стилей
            style-src:
                - 'self'
                - 'unsafe-inline'  # Требуется для многих CSS-фреймворков
            
            # Источники для изображений
            img-src:
                - 'self'
                - data:
                - https://www.google-analytics.com
            
            # Источники для шрифтов
            font-src:
                - 'self'
                - https://fonts.gstatic.com
            
            # Источники для подключений (AJAX, WebSocket)
            connect-src:
                - 'self'
            
            # Источники для фреймов
            frame-src:
                - 'self'
            
            # Источники для объектов (embed, object)
            object-src:
                - 'none'
            
            # Базовый URI для относительных ссылок
            base-uri:
                - 'self'
            
            # Запрет на загрузку через <meta http-equiv="refresh">
            form-action:
                - 'self'
            
            # Политика для вложенных контекстов
            frame-ancestors:
                - 'self'
            
            # Отчёт о нарушениях
            report-uri:
                - /csp-report

        # Нарушения, которые нужно игнорировать
        compatibility:
            script:
                - 'unsafe-eval'  # Только для dev
```

### Конфигурация для разных окружений

**Файл:** `config/packages/dev/nelmio_security.yaml`

```yaml
nelmio_security:
    csp:
        report_only: true  # Только отчёты, без блокировки
        directives:
            script-src:
                - 'self'
                - 'unsafe-inline'
                - 'unsafe-eval'  # Для Hot Module Replacement
```

**Файл:** `config/packages/prod/nelmio_security.yaml`

```yaml
nelmio_security:
    csp:
        report_only: false
        directives:
            script-src:
                - 'self'
                # Добавьте хеши для инлайн-скриптов
                # 'sha256-...'
            style-src:
                - 'self'
                # 'sha256-...'
```

### Обработчик отчётов о нарушениях

**Контроллер:** `src/Controller/CspReportController.php`

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class CspReportController extends AbstractController
{
    public function report(Request $request, LoggerInterface $logger): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);
        
        if ($data && isset($data['csp-report'])) {
            $report = $data['csp-report'];
            
            $logger->warning('CSP Violation', [
                'blocked_uri' => $report['blocked-uri'] ?? null,
                'document_uri' => $report['document-uri'] ?? null,
                'effective_directive' => $report['effective-directive'] ?? null,
                'original_policy' => $report['original-policy'] ?? null,
                'referrer' => $report['referrer'] ?? null,
                'script_sample' => $report['script-sample'] ?? null,
                'source_file' => $report['source-file'] ?? null,
                'status_code' => $report['status-code'] ?? null,
                'violated_directive' => $report['violated-directive'] ?? null,
            ]);
        }
        
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
```

**Маршрут:** `config/routes.yaml`

```yaml
csp_report:
    path: /csp-report
    controller: App\Controller\CspReportController::report
    methods: [POST]
```

### Генерация nonce для скриптов

**Твиг:** `templates/base.html.twig`

```twig
<!DOCTYPE html>
<html>
<head>
    {# ... другие мета-теги ... #}
    
    {% block stylesheets %}
        <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    {% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    
    {% block javascripts %}
        <script nonce="{{ csp_nonce() }}" src="{{ asset('build/app.js') }}"></script>
    {% endblock %}
</body>
</html>
```

---

## CSP в Laravel

### Установка пакета spatie/laravel-csp

```bash
composer require spatie/laravel-csp
```

### Публикация конфигурации

```bash
php artisan vendor:publish --provider="Spatie\Csp\CspServiceProvider" --tag="csp-config"
```

### Конфигурация

**Файл:** `config/csp.php`

```php
<?php

return [
    /*
     * Включить/выключить CSP
     */
    'enabled' => env('CSP_ENABLED', true),

    /*
     * Только отчёты (без блокировки)
     */
    'report_only' => env('CSP_REPORT_ONLY', false),

    /*
     * Класс политики по умолчанию
     */
    'default' => Spatie\Csp\Policies\Basic::class,

    /*
     * Политики для разных путей
     */
    'paths' => [
        'api/*' => Spatie\Csp\Policies\Api::class,
        'admin/*' => App\Csp\Policies\AdminPolicy::class,
    ],

    /*
     * Сервис для отправки отчётов
     */
    'report_uri' => env('CSP_REPORT_URI', '/csp-report'),
];
```

### Создание кастомной политики

**Файл:** `app/Csp/Policies/AppPolicy.php`

```php
<?php

namespace App\Csp\Policies;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Policy;
use Spatie\Csp\Value;

class AppPolicy extends Policy
{
    public function configure(): void
    {
        $this
            // Политика по умолчанию для всех ресурсов
            ->addDirective(Directive::DEFAULT, [
                Keyword::SELF,
            ])
            
            // Скрипты
            ->addDirective(Directive::SCRIPT, [
                Keyword::SELF,
                // 'unsafe-inline' и 'unsafe-eval' только для dev
                config('app.env') === 'local' ? Value::UNSAFE_INLINE : null,
                config('app.env') === 'local' ? Value::UNSAFE_EVAL : null,
                'https://www.google-analytics.com',
                'https://js.stripe.com',
            ])
            
            // Стили
            ->addDirective(Directive::STYLE, [
                Keyword::SELF,
                Value::UNSAFE_INLINE, // Часто требуется для CSS-фреймворков
                'https://fonts.googleapis.com',
            ])
            
            // Изображения
            ->addDirective(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'blob:',
                'https://www.google-analytics.com',
                'https://stats.g.doubleclick.net',
            ])
            
            // Шрифты
            ->addDirective(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.gstatic.com',
            ])
            
            // Подключения (AJAX, Fetch, WebSocket)
            ->addDirective(Directive::CONNECT, [
                Keyword::SELF,
                'https://api.stripe.com',
            ])
            
            // Фреймы
            ->addDirective(Directive::FRAME, [
                Keyword::SELF,
                'https://js.stripe.com',
            ])
            
            // Объекты (embed, object)
            ->addDirective(Directive::OBJECT, [
                Keyword::NONE,
            ])
            
            // Базовый URI
            ->addDirective(Directive::BASE, [
                Keyword::SELF,
            ])
            
            // Отправка форм
            ->addDirective(Directive::FORM, [
                Keyword::SELF,
            ])
            
            // Родительские фреймы
            ->addDirective(Directive::FRAME_ANCESTORS, [
                Keyword::SELF,
            ]);
    }
}
```

### Политика для API

**Файл:** `app/Csp/Policies/ApiPolicy.php`

```php
<?php

namespace App\Csp\Policies;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Policy;

class ApiPolicy extends Policy
{
    public function configure(): void
    {
        $this
            ->addDirective(Directive::DEFAULT, [Keyword::SELF])
            ->addDirective(Directive::SCRIPT, [Keyword::NONE])
            ->addDirective(Directive::STYLE, [Keyword::NONE])
            ->addDirective(Directive::IMG, [Keyword::SELF, 'data:'])
            ->addDirective(Directive::CONNECT, [Keyword::SELF]);
    }
}
```

### Добавление CSP к ответу

**Глобально через middleware:** `app/Http/Kernel.php`

```php
protected $middlewareGroups = [
    'web' => [
        \Spatie\Csp\AddCspHeaders::class,
        // ... другие middleware
    ],
];
```

**В контроллере:**

```php
use Spatie\Csp\AddCspHeaders;
use Spatie\Csp\Csp;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(AddCspHeaders::class);
    }
    
    public function index()
    {
        // Или программно добавить директивы
        Csp::addDirective(Directive::SCRIPT, 'https://example.com');
        
        return view('dashboard');
    }
}
```

### Обработчик отчётов

**Контроллер:** `app/Http/Controllers/CspReportController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CspReportController extends Controller
{
    public function report(Request $request)
    {
        $data = $request->json()->all();
        
        if (isset($data['csp-report'])) {
            $report = $data['csp-report'];
            
            Log::warning('CSP Violation', [
                'blocked_uri' => $report['blocked-uri'] ?? null,
                'document_uri' => $report['document-uri'] ?? null,
                'effective_directive' => $report['effective-directive'] ?? null,
                'original_policy' => $report['original-policy'] ?? null,
                'source_file' => $report['source-file'] ?? null,
                'violated_directive' => $report['violated-directive'] ?? null,
            ]);
        }
        
        return response('', 204);
    }
}
```

**Маршрут:** `routes/web.php`

```php
Route::post('/csp-report', [CspReportController::class, 'report']);
```

### Использование nonce в Blade

**Файл:** `resources/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    
    @vite(['resources/css/app.css'])
</head>
<body>
    @yield('content')
    
    <script nonce="{{ csp_nonce() }}">
        // Ваш инлайн-скрипт
    </script>
    
    @vite(['resources/js/app.js'])
</body>
</html>
```

**Хелпер для nonce:** `app/Helpers/CspHelper.php`

```php
<?php

namespace App\Helpers;

use Spatie\Csp\Csp;

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return Csp::nonce();
    }
}
```

---

## Директивы CSP

### Основные директивы

| Директива | Описание | Пример |
|-----------|----------|--------|
| `default-src` | Политика по умолчанию для всех ресурсов | `default-src 'self'` |
| `script-src` | Источники для JavaScript | `script-src 'self' https://cdn.example.com` |
| `style-src` | Источники для CSS | `style-src 'self' 'unsafe-inline'` |
| `img-src` | Источники для изображений | `img-src 'self' data: https:` |
| `font-src` | Источники для шрифтов | `font-src 'self' https://fonts.gstatic.com` |
| `connect-src` | Источники для AJAX/Fetch/WebSocket | `connect-src 'self' https://api.example.com` |
| `frame-src` | Источники для фреймов | `frame-src 'self' https://www.youtube.com` |
| `object-src` | Источники для `<object>`, `<embed>` | `object-src 'none'` |
| `base-uri` | Разрешённые базовые URI | `base-uri 'self'` |
| `form-action` | Разрешённые цели для форм | `form-action 'self'` |
| `frame-ancestors` | Кто может встраивать страницу | `frame-ancestors 'self'` |
| `media-src` | Источники для медиа (audio, video) | `media-src 'self'` |
| `worker-src` | Источники для Web Workers | `worker-src 'self' blob:` |

### Ключевые слова

| Ключевое слово | Описание |
|----------------|----------|
| `'self'` | Разрешить только с текущего домена |
| `'unsafe-inline'` | Разрешить инлайн-скрипты/стили |
| `'unsafe-eval'` | Разрешить `eval()` и подобные конструкции |
| `'none'` | Запретить все источники |
| `'strict-dynamic'` | Доверять скриптам, загруженным доверенными |
| `'report-sample'` | Включать образец кода в отчёты |
| `data:` | Разрешить data: URI |
| `blob:` | Разрешить blob: URI |
| `https:` | Разрешить все HTTPS-ресурсы |

### Хеш-суммы для инлайн-скриптов

Вместо `'unsafe-inline'` можно использовать хеши:

```bash
# Генерация хеша для скрипта
echo -n "console.log('Hello');" | openssl dgst -sha256 -binary | base64
# Результат: p7XC4ZfE5V+K8R3qN2mJ5L8wQ9xY6tU4oI1cA3bD7fE=
```

```yaml
script-src:
    - 'self'
    - 'sha256-p7XC4ZfE5V+K8R3qN2mJ5L8wQ9xY6tU4oI1cA3bD7fE='
```

---

## Лучшие практики

### 1. Начинайте с режима отчётов

```yaml
# Symfony (dev)
nelmio_security:
    csp:
        report_only: true

# Laravel (.env)
CSP_REPORT_ONLY=true
```

### 2. Постепенное ужесточение

1. **Этап 1:** Только отчёты, широкие правила
2. **Этап 2:** Анализ отчётов, выявление проблем
3. **Этап 3:** Добавление конкретных источников
4. **Этап 4:** Включение блокирующего режима
5. **Этап 5:** Удаление `'unsafe-inline'` и `'unsafe-eval'`

### 3. Разные политики для разных окружений

```yaml
# dev -宽松ная политика
script-src: ['self', 'unsafe-inline', 'unsafe-eval']

# prod - строгая политика
script-src: ['self', 'sha256-...']
```

### 4. Используйте nonce для инлайн-скриптов

Вместо `'unsafe-inline'`:

```twig
<script nonce="{{ csp_nonce() }}">
    // Ваш код
</script>
```

### 5. Запретите опасные ресурсы

```yaml
object-src: ['none']  # Отключает Flash и другие плагины
base-uri: ['self']    # Предотвращает атаки через <base>
```

### 6. Ограничьте frame-ancestors

```yaml
frame-ancestors: ['self']  # Запрет clickjacking
```

### 7. Мониторинг отчётов

Настройте логирование и алерты для нарушений CSP.

---

## Отладка и тестирование

### Инструменты разработчика браузера

Откройте DevTools → Console для просмотра ошибок CSP:

```
Refused to load the script 'https://evil.com/malicious.js' because it violates 
the following Content Security Policy directive: "script-src 'self'".
```

### Расширения браузера

- **CSP Evaluator** (Chrome) — анализ политики
- **CSP Inspector** — отладка нарушений

### Онлайн-инструменты

- [CSP Generator](https://report-uri.com/home/generate/)
- [CSP Validator](https://cspvalidator.org/)

### Тестовый заголовок для проверки

Добавьте временно строгую политику для тестирования:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'
```

### Логирование нарушений

**Symfony:** Отчёты сохраняются в лог через `report_logger_service`

**Laravel:** Отчёты в `storage/logs/laravel.log`

### Пример отчёта о нарушении

```json
{
    "csp-report": {
        "blocked-uri": "https://evil.com/malicious.js",
        "document-uri": "https://yoursite.com/page",
        "effective-directive": "script-src",
        "original-policy": "default-src 'self'; script-src 'self'",
        "source-file": "https://yoursite.com/page",
        "status-code": 200,
        "violated-directive": "script-src 'self'"
    }
}
```

### Автоматическое тестирование

**PHPUnit тест для Symfony:**

```php
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CspTest extends WebTestCase
{
    public function testCspHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $this->assertResponseHeaderExists('Content-Security-Policy');
        $this->assertResponseHeaderSame('Content-Security-Policy', 
            "default-src 'self'");
    }
}
```

**PHPUnit тест для Laravel:**

```php
class CspTest extends TestCase
{
    public function testCspHeaderIsPresent(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Content-Security-Policy');
    }
}
```

---

## Полезные команды

### Symfony

```bash
# Очистка кеша после изменений
php bin/console cache:clear

# Проверка конфигурации
php bin/console debug:config nelmio_security
```

### Laravel

```bash
# Очистка кеша
php artisan config:clear
php artisan cache:clear

# Проверка middleware
php artisan route:list
```

---

## Дополнительные ресурсы

- [MDN CSP Documentation](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [CSP Specification W3C](https://www.w3.org/TR/CSP3/)
- [NelmioSecurityBundle Docs](https://github.com/nelmio/NelmioSecurityBundle)
- [spatie/laravel-csp Docs](https://github.com/spatie/laravel-csp)
- [CSP Cheat Sheet](https://content-security-policy.com/)

---

## Реализация в проекте To-Do List

### Текущая архитектура CSP

В проекте To-Do List (Symfony 8.0) CSP реализована через **NelmioSecurityBundle** с разделением на dev и prod окружения.

### Структура конфигурации

```
config/packages/
├── nelmio_security.yaml          # Базовый конфиг
├── dev/
│   └── nelmio_security.yaml      #宽松ная политика для разработки
└── prod/
    └── nelmio_security.yaml      # Строгая политика для продакшена
```

### Dev окружение

**Файл:** `config/packages/dev/nelmio_security.yaml`

Особенности:
- `report_only: true` — нарушения только логируются, не блокируются
- `'unsafe-inline'` и `'unsafe-eval'` разрешены для HMR и отладки
- Разрешены WebSocket подключения к localhost

```yaml
nelmio_security:
    csp:
        report_only: true
        level: 2
        directives:
            script-src:
                - 'self'
                - 'unsafe-inline'
                - 'unsafe-eval'
                - https://cdn.jsdelivr.net
                - https://cdnjs.cloudflare.com
            connect-src:
                - 'self'
                - ws://localhost:*
                - http://localhost:*
```

### Prod окружение

**Файл:** `config/packages/prod/nelmio_security.yaml`

Особенности:
- `report_only: false` — нарушения блокируются
- CSP Level 3 с поддержкой nonce
- `upgrade-insecure-requests` для принудительного HTTPS
- `frame-ancestors: 'none'` для защиты от clickjacking

```yaml
nelmio_security:
    csp:
        report_only: false
        level: 3
        directives:
            script-src:
                - 'self'
                - 'strict-dynamic'
                - 'nonce-{{nonce}}'
            upgrade-insecure-requests: ~
            frame-ancestors:
                - 'none'
```

### Компоненты безопасности

#### 1. EventSubscriber для заголовков

**Файл:** `src/EventSubscriber/SecurityHeadersSubscriber.php`

Добавляет дополнительные заголовки безопасности в prod:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `Strict-Transport-Security` (HSTS)
- `Cross-Origin-*` политики

#### 2. Middleware для валидации

**Файл:** `src/Middleware/SecurityValidationMiddleware.php`

Валидирует и санирует входные данные запроса.

#### 3. Контроллер отчётов CSP

**Файл:** `src/Controller/CspReportController.php`

Обрабатывает POST-запросы от браузера с отчётами о нарушениях.

**Маршрут:** `config/routes.yaml`
```yaml
csp_report:
    path: /csp-report
    controller: App\Controller\CspReportController::report
    methods: [POST]
```

### Twig шаблоны с nonce

**Файл:** `templates/base.html.twig`

```twig
<!-- Meta-тег с nonce для prod -->
{% if app.environment == 'prod' %}
<meta name="csp-nonce" content="{{ csp_nonce() }}">
{% endif %}

<!-- Инлайн-скрипты с nonce -->
<script{% if app.environment == 'prod' %} nonce="{{ csp_nonce() }}"{% endif %}>
    // Ваш код
</script>
```

### Поэтапный план ужесточения CSP

#### Этап 1: Текущее состояние (dev)

- [x] NelmioSecurityBundle установлен
- [x] CSP в режиме отчётов (report_only)
- [x] `'unsafe-inline'` и `'unsafe-eval'` разрешены
- [x] Отчёты логируются

#### Этап 2: Подготовка к prod

- [ ] Проанализировать логи нарушений CSP
- [ ] Выявить все инлайн-скрипты
- [ ] Добавить nonce к критическим инлайн-скриптам
- [ ] Протестировать с `report_only: false` локально

#### Этап 3: Production-ready CSP

- [ ] Удалить `'unsafe-inline'` из `script-src`
- [ ] Удалить `'unsafe-eval'` (заменить на явные функции)
- [ ] Включить `strict-dynamic`
- [ ] Настроить мониторинг нарушений

### Мониторинг нарушений CSP

#### Просмотр логов

```bash
# Symfony dev
tail -f var/log/dev.log | grep "CSP Violation"

# Production
tail -f var/log/prod.log | grep "CSP"
```

#### Пример отчёта о нарушении

```json
{
    "csp-report": {
        "blocked-uri": "https://evil.com/malicious.js",
        "document-uri": "https://yoursite.com/task/123",
        "effective-directive": "script-src",
        "original-policy": "default-src 'self'; script-src 'self' 'nonce-abc123'",
        "violated-directive": "script-src 'self' 'nonce-abc123'"
    }
}
```

#### Логирование в Symfony

Отчёты CSP автоматически логируются через `report_logger_service`:

```php
// src/Controller/CspReportController.php
$this->logger->warning('CSP Violation', [
    'blocked_uri' => $report['blocked-uri'],
    'violated_directive' => $report['violated-directive'],
    // ...
]);
```

### Тестирование CSP

#### PHPUnit тест

**Файл:** `tests/Controller/CspHeadersTest.php`

```php
<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CspHeadersTest extends WebTestCase
{
    public function testCspHeaderPresentInProduction(): void
    {
        // Создаём клиент для prod окружения
        $client = static::createClient([], [
            'HTTP_HOST' => 'example.com',
        ]);
        
        $client->request('GET', '/');
        
        // Проверяем наличие CSP заголовка
        $this->assertResponseHeaderExists('Content-Security-Policy');
        
        // Проверяем директивы
        $csp = $client->getResponse()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }
    
    public function testSecurityHeadersPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $this->assertResponseHeaderExists('X-Content-Type-Options');
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        
        $this->assertResponseHeaderExists('X-Frame-Options');
        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
        
        $this->assertResponseHeaderExists('Referrer-Policy');
    }
}
```

#### Браузерные тесты

1. Откройте DevTools → Console
2. Проверьте на наличие CSP violations
3. Используйте вкладку Network для проверки заголовков

### Команды для разработки

```bash
# Проверка текущей CSP конфигурации
php bin/console debug:config nelmio_security

# Очистка кеша после изменений
php bin/console cache:clear

# Просмотр логов CSP в реальном времени
tail -f var/log/dev.log | grep -i csp

# Тестирование CSP в prod режиме (локально)
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod symfony serve
```

### Чеклист перед деплоем

- [ ] CSP работает в blocking mode (`report_only: false`)
- [ ] Все инлайн-скрипты используют nonce
- [ ] Нет нарушений CSP в логах
- [ ] `'unsafe-inline'` удалён из `script-src`
- [ ] `'unsafe-eval'` удалён (если возможно)
- [ ] `frame-ancestors` настроен для защиты от clickjacking
- [ ] HSTS включён для HTTPS
- [ ] Отчёты CSP логируются и мониторятся

---

*Последнее обновление: 19 февраля 2026*
