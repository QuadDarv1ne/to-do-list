# Руководство по обслуживанию проекта

## 🧹 Очистка проекта

### Очистка кэша
```bash
# Очистка кэша (dev окружение)
php bin/console cache:clear

# Очистка кэша (prod окружение)
php bin/console cache:clear --env=prod

# Прогрев кэша после очистки
php bin/console cache:warmup --env=prod

# Очистка всех кэшей
rm -rf var/cache/*
php bin/console cache:clear
```

### Очистка логов
```bash
# Удаление старых логов
rm -rf var/log/*.log

# Очистка логов старше 7 дней
find var/log -name "*.log" -mtime +7 -delete
```

### Очистка временных файлов
```bash
# Удаление сессий
rm -rf var/sessions/*

# Полная очистка var директории
rm -rf var/cache/* var/log/* var/sessions/*
```

## 📦 Обновление зависимостей

### Composer

```bash
# Проверка устаревших пакетов
composer outdated

# Показать только прямые зависимости
composer outdated --direct

# Обновление всех пакетов
composer update

# Обновление конкретного пакета
composer update symfony/framework-bundle

# Обновление с проверкой (dry-run)
composer update --dry-run

# Обновление без dev-зависимостей
composer update --no-dev

# Обновление автозагрузчика
composer dump-autoload --optimize
```

### NPM (для frontend зависимостей)

```bash
# Проверка устаревших пакетов
npm outdated

# Обновление всех пакетов
npm update

# Обновление конкретного пакета
npm update package-name

# Установка последних версий
npm install package-name@latest
```

## 🚀 Запуск проекта

### Локальная разработка

```bash
# Запуск встроенного сервера Symfony
symfony server:start

# Или через PHP
php -S localhost:8000 -t public

# Запуск в фоновом режиме
symfony server:start -d

# Остановка сервера
symfony server:stop
```

### База данных

```bash
# Создание базы данных
php bin/console doctrine:database:create

# Применение миграций
php bin/console doctrine:migrations:migrate

# Откат последней миграции
php bin/console doctrine:migrations:migrate prev

# Проверка схемы БД
php bin/console doctrine:schema:validate

# Загрузка тестовых данных
php bin/console doctrine:fixtures:load
```

### Messenger (очереди)

```bash
# Обработка очереди async
php bin/console messenger:consume async

# Обработка с лимитом
php bin/console messenger:consume async --limit=10

# Обработка с таймаутом
php bin/console messenger:consume async --time-limit=3600

# Просмотр сообщений в очереди
php bin/console messenger:stats
```

## 🔧 Регулярное обслуживание

### Ежедневно

```bash
# Очистка кэша
php bin/console cache:clear

# Проверка логов на ошибки
tail -f var/log/dev.log

# Проверка очереди сообщений
php bin/console messenger:stats
```

### Еженедельно

```bash
# Обновление зависимостей
composer update
npm update

# Очистка старых логов
find var/log -name "*.log" -mtime +7 -delete

# Проверка безопасности
symfony security:check

# Оптимизация автозагрузчика
composer dump-autoload --optimize
```

### Ежемесячно

```bash
# Проверка устаревших пакетов
composer outdated
npm outdated

# Анализ производительности
php bin/console debug:container --env=prod

# Проверка схемы БД
php bin/console doctrine:schema:validate

# Очистка неиспользуемых зависимостей
composer remove --unused
```

## 🐛 Диагностика проблем

### Проверка конфигурации

```bash
# Проверка синтаксиса YAML
php bin/console lint:yaml config/

# Проверка синтаксиса Twig
php bin/console lint:twig templates/

# Проверка контейнера
php bin/console lint:container

# Список всех сервисов
php bin/console debug:container

# Список всех маршрутов
php bin/console debug:router
```

### Проверка прав доступа

```bash
# Установка правильных прав
chmod -R 755 var/
chmod -R 755 public/

# Для Linux/Mac
chown -R www-data:www-data var/
chown -R www-data:www-data public/
```

### Режим отладки

```bash
# Включить режим отладки
export APP_ENV=dev
export APP_DEBUG=1

# Выключить режим отладки
export APP_ENV=prod
export APP_DEBUG=0
```

## 🔒 Безопасность

### Проверка уязвимостей

```bash
# Проверка безопасности Composer
composer audit

# Проверка безопасности Symfony
symfony security:check

# Проверка безопасности NPM
npm audit

# Исправление уязвимостей NPM
npm audit fix
```

### Обновление секретов

```bash
# Генерация нового APP_SECRET
php bin/console secrets:generate-keys

# Установка секрета
php bin/console secrets:set SECRET_NAME
```

## 📊 Мониторинг производительности

### Профилирование

```bash
# Включить профайлер (только dev)
# В .env установить APP_ENV=dev

# Анализ производительности запросов
php bin/console debug:event-dispatcher

# Проверка использования памяти
php -d memory_limit=-1 bin/console your:command
```

### Оптимизация

```bash
# Оптимизация автозагрузчика
composer dump-autoload --optimize --classmap-authoritative

# Прогрев кэша
php bin/console cache:warmup --env=prod

# Компиляция контейнера
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

## 🐳 Docker (если используется)

```bash
# Запуск контейнеров
docker-compose up -d

# Остановка контейнеров
docker-compose down

# Пересборка контейнеров
docker-compose up -d --build

# Просмотр логов
docker-compose logs -f

# Выполнение команд в контейнере
docker-compose exec php php bin/console cache:clear
```

## 📝 Резервное копирование

### База данных

```bash
# Создание дампа БД
pg_dump -U username dbname > backup_$(date +%Y%m%d).sql

# Восстановление из дампа
psql -U username dbname < backup_20240220.sql
```

### Файлы

```bash
# Создание архива проекта
tar -czf project_backup_$(date +%Y%m%d).tar.gz \
  --exclude='var/cache' \
  --exclude='var/log' \
  --exclude='node_modules' \
  --exclude='vendor' \
  .

# Восстановление из архива
tar -xzf project_backup_20240220.tar.gz
```

## ⚡ Быстрые команды

```bash
# Полная переустановка проекта
composer install
npm install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console cache:clear

# Быстрая очистка и перезапуск
rm -rf var/cache/* var/log/*
php bin/console cache:clear
symfony server:stop
symfony server:start

# Обновление всего
composer update
npm update
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
```

## 🆘 Решение частых проблем

### "Class not found"
```bash
composer dump-autoload
php bin/console cache:clear
```

### "Permission denied"
```bash
chmod -R 755 var/
chmod -R 755 public/
```

### "Database connection failed"
```bash
# Проверь .env файл
# Проверь что БД запущена
php bin/console doctrine:database:create
```

### "Out of memory"
```bash
php -d memory_limit=-1 bin/console your:command
```

### Медленная работа
```bash
composer dump-autoload --optimize
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```
