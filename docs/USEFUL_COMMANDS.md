# Полезные команды для разработки

## Запуск проекта

```bash
# Запуск встроенного PHP сервера
php -S 127.0.0.1:8080 -t public

# Запуск через Symfony CLI (если установлен)
symfony server:start

# Запуск в фоновом режиме
symfony server:start -d
```

## Работа с базой данных

```bash
# Создание базы данных
php bin/console doctrine:database:create

# Выполнение миграций
php bin/console doctrine:migrations:migrate

# Создание новой миграции
php bin/console make:migration

# Обновление схемы БД (только для разработки!)
php bin/console doctrine:schema:update --force

# Просмотр SQL для обновления схемы
php bin/console doctrine:schema:update --dump-sql

# Выполнение DQL запроса
php bin/console doctrine:query:dql "SELECT u FROM App\Entity\User u"

# Проверка валидности схемы
php bin/console doctrine:schema:validate
```

## Работа с кешем

```bash
# Очистка всего кеша
php bin/console cache:clear

# Очистка кеша для production
php bin/console cache:clear --env=prod

# Прогрев кеша
php bin/console cache:warmup

# Очистка пула кеша Doctrine
php bin/console cache:pool:clear doctrine.system_cache_pool
```

## Отладка и информация

```bash
# Список всех маршрутов
php bin/console debug:router

# Информация о конкретном маршруте
php bin/console debug:router app_task_index

# Список всех сервисов
php bin/console debug:container

# Информация о конкретном сервисе
php bin/console debug:container App\Service\TaskService

# Список всех событий
php bin/console debug:event-dispatcher

# Конфигурация приложения
php bin/console debug:config

# Список всех команд
php bin/console list
```

## Создание сущностей и контроллеров

```bash
# Создание новой сущности
php bin/console make:entity

# Создание контроллера
php bin/console make:controller

# Создание формы
php bin/console make:form

# Создание команды
php bin/console make:command

# Создание сервиса
php bin/console make:service

# Создание voter (для авторизации)
php bin/console make:voter
```

## Пользовательские команды проекта

```bash
# Создание тестовых данных
php bin/console app:create-test-data

# Очистка старых данных (если есть)
php bin/console app:cleanup-old-data

# Отправка напоминаний о дедлайнах
php bin/console app:send-deadline-reminders
```

## Работа с зависимостями

```bash
# Установка зависимостей Composer
composer install

# Обновление зависимостей
composer update

# Установка зависимостей для production
composer install --no-dev --optimize-autoloader

# Установка npm зависимостей
npm install

# Сборка assets
npm run build

# Режим разработки с watch
npm run watch
```

## Тестирование

```bash
# Запуск всех тестов
php bin/phpunit

# Запуск конкретного теста
php bin/phpunit tests/Controller/TaskControllerTest.php

# Запуск с покрытием кода
php bin/phpunit --coverage-html coverage
```

## Проверка кода

```bash
# PHP CS Fixer (если установлен)
vendor/bin/php-cs-fixer fix src

# PHPStan (если установлен)
vendor/bin/phpstan analyse src

# Psalm (если установлен)
vendor/bin/psalm
```

## Работа с assets

```bash
# Компиляция assets
php bin/console asset-map:compile

# Очистка скомпилированных assets
php bin/console cache:pool:clear cache.asset_mapper
```

## Безопасность

```bash
# Проверка уязвимостей в зависимостях
composer audit

# Обновление security checker
symfony security:check
```

## Production команды

```bash
# Подготовка к деплою
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console doctrine:migrations:migrate --no-interaction

# Оптимизация autoloader
composer dump-autoload --optimize --classmap-authoritative
```

## Полезные алиасы (добавьте в .bashrc или .zshrc)

```bash
alias sf='php bin/console'
alias sfcc='php bin/console cache:clear'
alias sfrouter='php bin/console debug:router'
alias sfmigrate='php bin/console doctrine:migrations:migrate'
alias sfserver='php -S 127.0.0.1:8080 -t public'
```

## Горячие клавиши в приложении

- `Ctrl + K` - Быстрый поиск
- `T` - Перейти к задачам
- `D` - Перейти к панели управления
- `C` - Перейти к категориям
- `P` - Перейти к профилю
- `?` - Показать справку по горячим клавишам

## Голосовые команды

- "Панель" / "Dashboard" - Переход на панель управления
- "Задачи" / "Tasks" - Переход к списку задач
- "Категории" / "Categories" - Переход к категориям
- "Профиль" / "Profile" - Переход к профилю
- "Календарь" / "Calendar" - Переход к календарю
- "Новая задача" / "Create task" - Создание новой задачи
- "Быстрая задача" / "Quick task" - Быстрое создание задачи

## Переменные окружения (.env)

```env
# Database
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/todo_db"

# Mailer
MAILER_DSN=smtp://localhost:1025

# App
APP_ENV=dev
APP_SECRET=your-secret-key

# Redis (опционально)
REDIS_URL=redis://localhost:6379
```

## Troubleshooting

### Проблема: Ошибка подключения к БД
```bash
# Проверьте DATABASE_URL в .env
# Убедитесь, что БД создана
php bin/console doctrine:database:create
```

### Проблема: Маршрут не найден
```bash
# Очистите кеш
php bin/console cache:clear
# Проверьте список маршрутов
php bin/console debug:router
```

### Проблема: Ошибка миграции
```bash
# Откатите последнюю миграцию
php bin/console doctrine:migrations:migrate prev
# Или выполните заново
php bin/console doctrine:migrations:migrate --no-interaction
```

### Проблема: Медленная работа
```bash
# Включите кеширование
# Оптимизируйте запросы
# Проверьте производительность
php bin/console app:performance:check
```
