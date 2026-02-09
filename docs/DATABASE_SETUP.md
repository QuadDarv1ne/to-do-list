# Настройка базы данных

Документация по настройке различных типов баз данных для приложения To-Do List.

## Поддерживаемые базы данных

Приложение поддерживает следующие системы управления базами данных:

- SQLite (по умолчанию)
- MySQL
- PostgreSQL

## Общая конфигурация

Все настройки базы данных находятся в файлах окружения:

- `.env` - основной файл конфигурации
- `.env.local` - локальные переопределения (не коммитится в репозиторий)
- `.env.db` - переменные окружения для баз данных (шаблон)

## Настройка SQLite

SQLite используется по умолчанию. Никаких дополнительных действий не требуется.

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

## Настройка MySQL

### 1. Через Docker

Запустите MySQL-контейнер:

```bash
docker compose up database-mysql
```

### 2. Вручную

Убедитесь, что MySQL сервер запущен и доступен.

### 3. Настройка подключения

В файле `.env.local` установите:

```env
DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4"
```

### 4. Конфигурация Doctrine (опционально)

В файле `config/packages/doctrine.yaml` укажите версию сервера:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        server_version: '8.0.32'  # или другая версия MySQL
```

## Настройка PostgreSQL

### 1. Через Docker

Запустите PostgreSQL-контейнер:

```bash
docker compose up database-postgres
```

### 2. Вручную

Убедитесь, что PostgreSQL сервер запущен и доступен.

### 3. Настройка подключения

В файле `.env.local` установите:

```env
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

### 4. Конфигурация Doctrine (опционально)

В файле `config/packages/doctrine.yaml` укажите версию сервера:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        server_version: '16'  # или другая версия PostgreSQL
```

## Миграции

После изменения типа базы данных выполните миграции:

```bash
# Создание новых миграций
php bin/console make:migration

# Применение миграций
php bin/console doctrine:migrations:migrate
```

## Тестирование подключения

Проверьте подключение к базе данных:

```bash
# Проверка конфигурации
php bin/console doctrine:database:create --if-not-exists

# Проверка схемы
php bin/console doctrine:schema:validate
```

## Переменные окружения

Для удобства в файле `.env.db` содержатся шаблоны переменных:

```env
# PostgreSQL Configuration
POSTGRES_VERSION=16
POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=!ChangeMe!

# MySQL Configuration
MYSQL_VERSION=8.0
MYSQL_DB=app
MYSQL_USER=app
MYSQL_PASSWORD=!ChangeMe!
MYSQL_ROOT_PASSWORD=!ChangeMe!
```

## Особенности каждой СУБД

### MySQL

- Требует расширение `pdo_mysql`
- Использует `utf8mb4` кодировку по умолчанию
- Поддерживает AUTO_INCREMENT для первичных ключей

### PostgreSQL

- Требует расширение `pdo_pgsql`
- Использует `utf8` кодировку
- Поддерживает IDENTITY столбцы (указано в `doctrine.yaml`)

### SQLite

- Требует расширение `pdo_sqlite`
- Легковесное решение для разработки
- Не рекомендуется для продакшена

## Устранение неполадок

### Ошибки подключения

1. Проверьте, что служба базы данных запущена
2. Убедитесь в правильности хоста, порта и учетных данных
3. Проверьте, что соответствующее PHP-расширение установлено

### Ошибки миграции

1. Убедитесь, что вы используете правильную версию сервера в конфигурации
2. Проверьте права доступа к базе данных
3. Убедитесь, что база данных существует

## Безопасность

- Всегда используйте надежные пароли в продакшене
- Не коммитьте файлы `.env.local` в репозиторий
- Регулярно обновляйте версии баз данных
