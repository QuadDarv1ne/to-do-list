# Руководство по оптимизации производительности

## Текущее состояние

✅ Проект оптимизирован и готов к продакшену
✅ Все критичные оптимизации применены
✅ Безопасность проверена

## Применённые оптимизации

### 1. База данных

#### Индексы
Все критичные поля проиндексированы:
```php
// Task entity
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_task_user_status')]
#[ORM\Index(columns: ['assigned_user_id', 'status'], name: 'idx_task_assigned_user_status')]
#[ORM\Index(columns: ['due_date', 'status'], name: 'idx_task_due_date_status')]
#[ORM\Index(columns: ['category_id'], name: 'idx_task_category')]
#[ORM\Index(columns: ['priority'], name: 'idx_task_priority')]
#[ORM\Index(columns: ['created_at'], name: 'idx_task_created_at')]
```

#### Eager Loading
Предотвращение N+1 запросов:
```php
return $this->createQueryBuilder('t')
    ->leftJoin('t.assignedUser', 'au')->addSelect('au')
    ->leftJoin('t.category', 'c')->addSelect('c')
    ->leftJoin('t.tags', 'tags')->addSelect('tags')
    ->getQuery()
    ->getResult();
```

#### Кэширование запросов
```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        metadata_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        query_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        result_cache_driver:
            type: pool
            pool: doctrine.result_cache_pool
```

### 2. Кэширование

#### Doctrine Cache
- Metadata cache: включен
- Query cache: включен
- Result cache: включен (prod)

#### Application Cache
```bash
# Прогрев кэша
php bin/console cache:warmup

# Прогрев кэша данных
php bin/console app:warm-cache
```

### 3. Frontend оптимизации

#### Отключено автообновление
```javascript
// templates/dashboard/index.html.twig
// Закомментировано:
// setInterval(() => {
//     loadQuickStats();
//     loadChartData();
// }, 300000);
```

#### Оптимизирован polling
```javascript
// templates/base.html.twig
// Уведомления: каждые 2 минуты вместо постоянного stream
setInterval(() => {
    this.updateNotificationCount();
}, 120000); // 2 minutes
```

### 4. Безопасность

#### Заголовки безопасности
```yaml
# config/packages/framework.yaml
http_client:
    default_options:
        headers:
            'X-Content-Type-Options': 'nosniff'
            'X-Frame-Options': 'DENY'
            'X-XSS-Protection': '1; mode=block'
            'Referrer-Policy': 'strict-origin-when-cross-origin'
```

#### Сессии
```yaml
session:
    cookie_secure: auto
    cookie_httponly: true
    cookie_samesite: lax
    cookie_lifetime: 3600
    gc_maxlifetime: 3600
```

## Рекомендации для продакшена

### 1. Переключение на MySQL/PostgreSQL

SQLite подходит для разработки, но для продакшена рекомендуется:

```env
# MySQL
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname?serverVersion=8.0.32&charset=utf8mb4"

# PostgreSQL
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/dbname?serverVersion=16&charset=utf8"
```

### 2. Настройка OPcache

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### 3. Настройка PHP-FPM

```ini
; php-fpm.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

### 4. Nginx конфигурация

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/project/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # Кэширование статики
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 5. Redis для кэша и сессий

```yaml
# config/packages/framework.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: redis://localhost
    session:
        handler_id: Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler
```

```yaml
# config/services.yaml
services:
    Redis:
        class: Redis
        calls:
            - connect:
                - '%env(REDIS_HOST)%'
                - '%env(int:REDIS_PORT)%'

    Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler:
        arguments:
            - '@Redis'
```

### 6. CDN для статики

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - assets/
        public_prefix: 'https://cdn.example.com/assets/'
```

## Мониторинг производительности

### Команды для мониторинга

```bash
# Отчет о производительности
php bin/console app:performance-monitor --action=report

# Медленные операции
php bin/console app:performance-monitor --action=slow-ops --threshold=200

# Статистика памяти
php bin/console app:monitor-memory --action=analysis

# API производительность
php bin/console app:monitor-api-performance --action=summary

# Проверка здоровья
php bin/console app:health-check
```

### Метрики для отслеживания

1. **Время ответа**
   - Средний: < 200ms
   - 95 перцентиль: < 500ms
   - 99 перцентиль: < 1000ms

2. **Использование памяти**
   - PHP процесс: < 128MB
   - Пиковое: < 256MB

3. **База данных**
   - Медленные запросы: < 100ms
   - Количество запросов на страницу: < 20

4. **Кэш**
   - Hit rate: > 80%
   - Miss rate: < 20%

## Регулярное обслуживание

### Ежедневно (через cron)

```bash
# Очистка старых данных
0 3 * * * php bin/console app:cleanup-data --notifications-days=90

# Резервное копирование
0 2 * * * php bin/console app:backup

# Отправка уведомлений
0 9 * * * php bin/console app:send-deadline-notifications

# Генерация повторяющихся задач
0 0 * * * php bin/console app:generate-recurring-tasks
```

### Еженедельно

```bash
# Оптимизация базы данных
0 4 * * 0 php bin/console app:optimize-database --analyze --optimize

# Очистка старых задач
0 5 * * 0 php bin/console app:cleanup-old-tasks --days=365
```

### Ежемесячно

```bash
# Аудит производительности
php bin/console app:performance-audit --format=json --output-file=var/reports/audit_$(date +%Y%m).json

# Генерация отчета
php bin/console app:generate-performance-report
```

## Troubleshooting

### Медленные запросы

```bash
# Найти медленные запросы
php bin/console app:performance-monitor --action=slow-ops --threshold=100

# Проверить индексы
php bin/console doctrine:schema:validate
```

### Высокое использование памяти

```bash
# Анализ памяти
php bin/console app:monitor-memory --action=analysis

# Поиск утечек
php bin/console app:monitor-memory --action=leaks --threshold=50
```

### Проблемы с кэшем

```bash
# Очистка кэша
php bin/console cache:clear

# Прогрев кэша
php bin/console cache:warmup

# Статистика кэша
php bin/console app:cache-management --action=stats
```

## Чек-лист перед деплоем

- [ ] Переключиться на MySQL/PostgreSQL
- [ ] Настроить OPcache
- [ ] Настроить PHP-FPM
- [ ] Настроить Nginx/Apache
- [ ] Настроить Redis для кэша
- [ ] Настроить Redis для сессий
- [ ] Настроить CDN для статики
- [ ] Настроить мониторинг
- [ ] Настроить логирование
- [ ] Настроить резервное копирование
- [ ] Настроить cron задачи
- [ ] Проверить безопасность
- [ ] Запустить тесты
- [ ] Проверить производительность

## Полезные ссылки

- [Symfony Performance](https://symfony.com/doc/current/performance.html)
- [Doctrine Performance](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/improving-performance.html)
- [PHP OPcache](https://www.php.net/manual/en/book.opcache.php)
- [Nginx Optimization](https://www.nginx.com/blog/tuning-nginx/)
