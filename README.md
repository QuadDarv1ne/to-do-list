# 📋 To-Do List - Система управления задачами

Мощное веб-приложение для управления задачами с расширенными возможностями аналитики, коллаборации и автоматизации.

## 📊 О проекте

Современная система управления задачами, разработанная на Symfony 8.0, предоставляющая полный набор инструментов для эффективной организации работы команды.

### ✨ Ключевые возможности

- 📋 **Управление задачами** - создание, редактирование, назначение задач с приоритетами и дедлайнами
- 📅 **Календарь** - визуализация задач в календаре с поддержкой экспорта в iCal
- 🎯 **Канбан доска** - визуальное управление задачами с drag & drop
- 📊 **Аналитика и отчеты** - детальная статистика, тренды, прогнозирование
- 👥 **Ролевая система** - гибкое управление правами доступа (User, Analyst, Manager, Admin)
- 🔔 **Уведомления** - real-time уведомления о важных событиях
- ⏱️ **Учет времени** - отслеживание времени работы, Pomodoro таймер
- 🏆 **Геймификация** - достижения, уровни, таблица лидеров
- 🤖 **AI Ассистент** - умные подсказки и автоматизация
- 🔗 **Интеграции** - GitHub, Slack, Jira, Telegram
- 🌙 **Темная тема** - современный адаптивный дизайн
- 📱 **PWA** - работа в offline режиме

## 🎯 Целевая аудитория

- Менеджеры проектов
- Команды разработки
- Отделы продаж и маркетинга
- Любые команды, работающие с задачами

## 🚀 Технологии

- **Backend:** PHP 8.5, Symfony 8.0
- **Frontend:** Stimulus, Turbo, Bootstrap 5, FullCalendar
- **Database:** PostgreSQL / MySQL
- **Cache:** Redis (опционально)
- **Queue:** Symfony Messenger
- **Architecture:** DDD, CQRS, Event Sourcing

## 📦 Быстрый старт

### Требования

- PHP 8.5 или выше
- Composer 2.x
- Node.js 18+ и npm
- PostgreSQL 16+ или MySQL 8.0+

### Установка

```bash
# 1. Клонирование репозитория
git clone https://github.com/your-repo/to-do-list.git
cd to-do-list

# 2. Установка зависимостей
composer install
npm install

# 3. Настройка окружения
cp .env .env.local
# Отредактируйте .env.local с настройками БД

# 4. Создание базы данных и миграции
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Создание тестовых данных
php bin/console app:create-test-data

# 6. Сборка assets
npm run build

# 7. Запуск сервера разработки
php -S 127.0.0.1:8080 -t public
# или через Symfony CLI
symfony server:start
```

Откройте браузер: http://127.0.0.1:8080

## 🔐 Тестовые учетные записи

После выполнения команды `app:create-test-data` будут созданы следующие пользователи:

| Роль | Email | Пароль | Права доступа |
|------|-------|--------|---------------|
| Администратор | admin@example.com | admin123 | Полный доступ ко всему |
| Менеджер | manager@example.com | manager123 | Управление задачами, отчеты, бюджет |
| Аналитик | analyst@example.com | analyst123 | Просмотр отчетов и аналитики |
| Пользователь | user@example.com | user123 | Работа со своими задачами |

## 📖 Документация

- [Текущий статус](docs/CURRENT_STATUS.md) - полный список реализованных функций
- [Полезные команды](docs/USEFUL_COMMANDS.md) - команды для разработки
- [Документация](docs/DOCUMENTATION.md) - подробная документация проекта
- [Настройка БД](docs/DATABASE_SETUP.md) - инструкции по настройке базы данных
- [Тестовые данные](docs/TEST_CREDENTIALS.md) - учетные данные для тестирования

## ⌨️ Горячие клавиши

- `Ctrl + K` - Быстрый поиск
- `T` - Перейти к задачам
- `D` - Перейти к панели управления
- `C` - Перейти к категориям
- `P` - Перейти к профилю
- `?` - Показать справку

## 🎤 Голосовые команды

Нажмите на иконку микрофона и скажите:
- "Панель" / "Dashboard"
- "Задачи" / "Tasks"
- "Календарь" / "Calendar"
- "Новая задача" / "Create task"

## 📊 Статистика проекта

- **PHP файлов**: 240+
- **Сервисов**: 75+
- **Контроллеров**: 50+
- **Маршрутов**: 250+
- **Шаблонов**: 80+
- **Версия**: 3.2.0

## 🎓 Особенности проекта

Проект разработан в рамках производственной практики по теме:
**"Анализ и формализация требований к сайту 'CRM система: Анализ продаж'"**

### Цели практики

1. Изучение предметной области агробизнеса
2. Анализ текущих процессов получения аналитики
3. Сравнительный анализ существующих CRM-систем
4. Формализация бизнес и функциональных требований
5. Разработка прототипа системы

## 📊 Основные модули

### 1. Дашборды

- Главный дашборд с KPI
- Дашборд менеджера
- Аналитический дашборд

### 2. Управление задачами

- Создание и назначение задач
- Отслеживание статусов
- Календарь задач
- Уведомления о дедлайнах

### 3. Аналитика

- Отчёты по продажам
- Анализ клиентской базы
- Товарная аналитика
- Эффективность менеджеров

### 4. Уведомления

- Real-time уведомления
- Email-рассылки
- Push-уведомления
- Настраиваемые алерты

## 🔐 Безопасность

- Аутентификация и авторизация
- Двухфакторная аутентификация (2FA)
- Разграничение прав доступа (RBAC)
- Защита от CSRF, XSS, SQL Injection
- Content Security Policy (CSP)
- HTTPS/TLS шифрование

## 📈 Ожидаемые результаты

- Рост продаж на 15% в течение года
- Снижение оттока клиентов с 12% до 7%
- Сокращение времени на аналитику с 12 до 2 часов в неделю
- Сокращение времени реакции на изменения с 3 дней до 1 часа

## 👨‍💻 Разработка

```bash
# Режим разработки
symfony server:start
npm run watch

# Тестирование
composer test

# Проверка кода
composer check          # Все проверки (cs + phpstan + test)
composer cs             # Проверка стиля кода
composer cs:fix         # Исправление стиля кода
composer phpstan        # Статический анализ

# Очистка кеша
php bin/console cache:clear
```

### Стандарты кода

**Проект использует автоматизированные инструменты для поддержания качества кода:**

- **EditorConfig** — единообразие стиля в редакторе
- **PHP CS Fixer** — автоматическое форматирование (PSR-12)
- **PHPStan** — статический анализ (уровень 5, 0 ошибок ✅)
- **PHPUnit** — тестирование (46 тестов, 94 assertions ✅)

📖 **Подробная документация:**

- [Стандарты кода](docs/CODE_STANDARDS.md)
- [План улучшений](docs/IMPROVEMENT_PLAN.md)
- [Отчёт об улучшениях](docs/IMPROVEMENTS_REPORT.md)

### Настройка pre-commit hook

```bash
# Автоматическая проверка перед коммитом
git config core.hooksPath .githooks
```

### Статус качества

| Метрика | Значение |
|---------|----------|
| **PHPStan** | ![PHPStan](https://img.shields.io/badge/PHPStan-0%20errors-blue) |
| **Тесты** | 46 тестов, 94 assertions |
| **Покрытие** | ~8% (цель: 80%) |
| **Форматирование** | PSR-12 |

## 📝 Лицензия

Проект разработан для учебных целей в рамках производственной практики.

## 📧 Контакты

ООО «Дальневосточный фермер»
**Email:** maksimqwe42@mail.ru
**Сайт:** нет


## 🔧 Полезные команды

### Разработка

```bash
# Очистка кэша
php bin/console cache:clear

# Прогрев кэша
php bin/console cache:warmup

# Проверка маршрутов
php bin/console debug:router

# Проверка контейнера
php bin/console debug:container
```

### База данных

```bash
# Создание миграции
php bin/console doctrine:migrations:diff

# Применение миграций
php bin/console doctrine:migrations:migrate

# Откат миграции
php bin/console doctrine:migrations:migrate prev

# Статус миграций
php bin/console doctrine:migrations:status
```

### Тестовые данные

```bash
# Создание тестовых пользователей
php bin/console app:create-test-users

# Сброс паролей тестовых пользователей
php bin/console app:reset-test-passwords

# Создание администратора
php bin/console app:create-admin admin@example.com password123 Admin User
```

### Обслуживание

```bash
# Очистка старых данных
php bin/console app:cleanup-data --notifications-days=90

# Резервное копирование
php bin/console app:backup

# Оптимизация базы данных
php bin/console app:optimize-database --analyze --optimize

# Проверка здоровья системы
php bin/console app:health-check
```

### Мониторинг производительности

```bash
# Отчет о производительности
php bin/console app:performance-monitor --action=report

# Медленные операции
php bin/console app:performance-monitor --action=slow-ops --threshold=200

# Статистика памяти
php bin/console app:monitor-memory --action=analysis

# Аудит производительности
php bin/console app:performance-audit
```

## 📚 Документация

- [Руководство по очистке проекта](docs/CLEANUP_GUIDE.md)
- [Оптимизация производительности](docs/PERFORMANCE_OPTIMIZATION.md)
- [Миграции базы данных](migrations/README.md)
- [Настройка базы данных](docs/DATABASE_SETUP.md)
- [Полная документация](docs/DOCUMENTATION.md)

## 🔐 Тестовые учетные записи

После выполнения `php bin/console app:create-test-users`:

| Email | Пароль | Роль |
|-------|--------|------|
| admin@example.com | admin123 | Администратор |
| manager@example.com | manager123 | Менеджер |
| user@example.com | user123 | Пользователь |
| analyst@example.com | analyst123 | Аналитик |

⚠️ **Важно:** Измените пароли перед развертыванием в продакшене!

## Развертывание в продакшене

### Проверка готовности

```bash
# Windows
bin\production-check.bat

# Linux/Mac
chmod +x bin/production-check.sh
./bin/production-check.sh
```

### Подготовка

```bash
# 1. Установка зависимостей без dev-пакетов
composer install --no-dev --optimize-autoloader

# 2. Настройка окружения
# Отредактируйте .env:
# APP_ENV=prod
# APP_DEBUG=0
# DATABASE_URL=mysql://user:pass@host:3306/dbname

# 3. Очистка и прогрев кэша
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 4. Применение миграций
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Установка прав доступа
chmod -R 755 var/
chmod -R 755 public/
```

### Настройка cron задач

```bash
# Редактирование crontab
crontab -e

# Добавьте задачи из crontab.example
# Пример:
0 2 * * * cd /path/to/project && php bin/console app:backup
0 9 * * * cd /path/to/project && php bin/console app:send-deadline-notifications
```

## 📊 Статистика проекта

- **PHP файлов:** 146
- **Команд:** 21
- **Сервисов:** 30
- **Контроллеров:** 25
- **Маршрутов:** 101
- **Шаблонов:** 49
- **Миграций:** 35

## 🔄 Сброс миграций (только для разработки)

Если нужно начать с чистой базы данных:

```bash
# Windows
bin\reset-migrations.bat

# Linux/Mac
chmod +x bin/reset-migrations.sh
./bin/reset-migrations.sh
```

⚠️ **Внимание:** Это удалит все данные и создаст новую базу!

## 🛠️ Оптимизация

Проект уже оптимизирован:

✅ Индексы базы данных настроены
✅ Eager loading для предотвращения N+1 запросов
✅ Кэширование Doctrine включено
✅ Автообновление дашборда отключено
✅ Polling уведомлений оптимизирован (2 минуты)
✅ Безопасность настроена
✅ Готов к продакшену

Подробнее: [docs/PERFORMANCE_OPTIMIZATION.md](docs/PERFORMANCE_OPTIMIZATION.md)

## 🤝 Вклад в проект

1. Fork репозитория
2. Создайте ветку для новой функции (`git checkout -b feature/AmazingFeature`)
3. Commit изменений (`git commit -m 'Add some AmazingFeature'`)
4. Push в ветку (`git push origin feature/AmazingFeature`)
5. Откройте Pull Request

## 📝 Лицензия

Proprietary - ООО «Дальневосточный фермер»

## 📧 Контакты

- **Проект:** CRM система анализа продаж
- **Организация:** ООО «Дальневосточный фермер»
- **Email:** info@dvfarm.ru

---

## 🚀 Деплой

Проект поддерживает развертывание на следующих платформах:

| Платформа | Тип | Бесплатный тариф | Ссылка |
|-----------|-----|------------------|--------|
| **Amvera** ☁️ | PaaS (Россия) | ❌ | [amvera.ru](https://amvera.ru) |
| **Railway** 🚀 | PaaS | ✅ $5/мес | [railway.app](https://railway.app) |
| **Render** 🎨 | PaaS | ✅ | [render.com](https://render.com) |
| **Fly.io** 🪂 | IaaS | ✅ ~3 VM | [fly.io](https://fly.io) |
| **Heroku** 📮 | PaaS | ❌ | [heroku.com](https://heroku.com) |

### Быстрый старт

```bash
# Amvera
amvera login && amvera deploy

# Railway
railway login && railway up

# Fly.io
fly auth login && fly deploy --dockerfile Dockerfile.prod

# Render
# Подключите репозиторий в Render Dashboard
```

📖 **Подробная документация:** [DEPLOYMENT.md](DEPLOYMENT.md)

---

Сделано с ❤️ для ООО «Дальневосточный фермер»
