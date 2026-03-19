# Автоматическая очистка кеша и мусора

## 📋 Обзор

Настроена система автоматической очистки кеша и мусора для приложения To-Do List.

## 🛠️ Новые команды

### Console команды

| Команда | Описание |
|---------|----------|
| `app:cache-cleanup` | Умная очистка кэша с различными стратегиями |
| `app:garbage-cleanup` | Очистка мусора: временные файлы, логи, сессии |

### Makefile команды

| Команда | Описание |
|---------|----------|
| `make cache-cleanup` | Умная очистка кэша (истёкший) |
| `make cache-cleanup-all` | Полная очистка кэша |
| `make garbage-cleanup` | Очистка мусора (временные файлы, логи) |
| `make garbage-cleanup-dry` | Проверка что будет очищено (dry-run) |
| `make cleanup-all` | Полная очистка кэша и мусора |

## 📝 Использование

### Ручная очистка

```bash
# Очистка кэша (только истёкший)
php bin/console app:cache-cleanup --stats

# Полная очистка кэша
php bin/console app:cache-cleanup --all

# Очистка мусора
php bin/console app:garbage-cleanup --all --days=7

# Проверка (dry-run)
php bin/console app:garbage-cleanup --dry-run --stats

# Через Makefile
make cache-cleanup
make garbage-cleanup
make cleanup-all
```

### Опции для cache-cleanup

| Опция | Описание |
|-------|----------|
| `--all` | Очистить весь кэш |
| `--expired` | Очистить только истёкший кэш (по умолчанию) |
| `--pool=NAME` | Очистить конкретный пул кэша |
| `--dry-run` | Показать что будет очищено |
| `--stats` | Показать статистику кэша |

### Опции для garbage-cleanup

| Опция | Описание |
|-------|----------|
| `--all` | Очистить весь мусор (по умолчанию) |
| `--temp` | Очистить временные файлы |
| `--logs` | Очистить старые логи |
| `--sessions` | Очистить старые сессии |
| `--uploads` | Очистить неиспользуемые загрузки |
| `--days=N` | Количество дней для хранения (по умолчанию: 7) |
| `--dry-run` | Показать что будет очищено |
| `--stats` | Показать статистику перед очисткой |

## ⏰ Автоматизация

### Linux (cron)

1. Откройте crontab:
```bash
crontab -e
```

2. Добавьте задачи (из `crontab.example`):
```bash
# Ежедневная очистка кэша в 3:00 AM
0 3 * * * cd /path/to/project && php bin/console app:cache-cleanup --expired >> var/log/cron.log 2>&1

# Ежедневная очистка мусора в 3:30 AM
30 3 * * * cd /path/to/project && php bin/console app:garbage-cleanup --all --days=7 >> var/log/cron.log 2>&1

# Еженедельная полная очистка в воскресенье в 4:00 AM
0 4 * * 0 cd /path/to/project && php bin/console cache:clear --no-warmup >> var/log/cron.log 2>&1
```

### Windows (Task Scheduler)

#### Быстрая настройка через PowerShell

Откройте PowerShell от имени администратора:

```powershell
$taskName = "To-Do List Cleanup"
$scriptPath = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list\scripts\cleanup.bat"
$workingDir = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list"

$trigger = New-ScheduledTaskTrigger -Daily -At 3:00AM
$action = New-ScheduledTaskAction -Execute "cmd.exe" `
    -Argument "/c `"$scriptPath`"" `
    -WorkingDirectory $workingDir
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable

Register-ScheduledTask `
    -TaskName $taskName `
    -Trigger $trigger `
    -Action $action `
    -Settings $settings `
    -Description "Автоматическая очистка кеша и мусора" `
    -Force
```

#### Через командную строку

```cmd
schtasks /Create /TN "To-Do List Cleanup" /TR "cmd.exe /c \"cd /d C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list && scripts\cleanup.bat\"" /SC DAILY /ST 03:00 /RL HIGHEST /F
```

#### Проверка и управление

```powershell
# Просмотр задачи
Get-ScheduledTask -TaskName "To-Do List Cleanup"

# Запуск вручную
Start-ScheduledTask -TaskName "To-Do List Cleanup"

# Удаление задачи
Unregister-ScheduledTask -TaskName "To-Do List Cleanup" -Confirm:$false
```

## 📂 Скрипты

### scripts/cleanup.bat (Windows)
Автоматический скрипт для Windows:
- Очищает кэш
- Очищает мусор
- Очищает кэш Symfony
- Ведёт логирование

### scripts/cleanup.sh (Linux)
Автоматический скрипт для Linux:
- Очищает кэш
- Очищает мусор
- Очищает кэш Symfony
- Ведёт логирование

## 📊 Логирование

Логи сохраняются в:
- `var/log/cleanup.log` - логи скрипта очистки
- `var/log/cron.log` - логи cron задач

Просмотр логов:
```bash
# В реальном времени
tail -f var/log/cleanup.log

# Последние 50 строк
tail -n 50 var/log/cleanup.log
```

## 🔧 Настройка

### Изменение периода хранения

В crontab или скриптах измените параметр `--days`:
```bash
# Хранить файлы 14 дней вместо 7
php bin/console app:garbage-cleanup --all --days=14
```

### Изменение времени выполнения

Отредактируйте `crontab.example` или задачу в Task Scheduler.

## ✅ Проверка работоспособности

```bash
# Проверка команд
php bin/console app:cache-cleanup --stats
php bin/console app:garbage-cleanup --dry-run --stats

# Проверка через Makefile
make cache-cleanup
make garbage-cleanup-dry
```

## 📌 Рекомендации

1. **Разработка**: Используйте `--dry-run` для проверки перед фактической очисткой
2. **Production**: Настройте автоматическую очистку на ночное время
3. **Мониторинг**: Регулярно проверяйте логи очистки
4. **Резервные копии**: Перед полной очисткой создавайте резервные копии

## 📄 Дополнительные файлы

- `crontab.example` - Пример конфигурации cron
- `docs/cleanup-automation-windows.md` - Подробная инструкция для Windows
- `scripts/cleanup.bat` - Скрипт для Windows
- `scripts/cleanup.sh` - Скрипт для Linux
