# 🧹 Быстрый старт: Очистка кеша и мусора

## ✅ Проверка работоспособности

Система автоматической очистки кеша и мусора **настроена и работает**!

### Созданные компоненты

| Файл | Назначение |
|------|------------|
| `src/Command/CacheCleanupCommand.php` | Команда для очистки кэша |
| `src/Command/GarbageCleanupCommand.php` | Команда для очистки мусора |
| `scripts/cleanup.bat` | Скрипт для Windows |
| `scripts/cleanup.sh` | Скрипт для Linux |
| `docs/CLEANUP_AUTOMATION.md` | Полная документация |
| `docs/cleanup-automation-windows.md` | Инструкция для Windows |

## 🚀 Быстрый старт

### Ручная очистка (прямо сейчас)

```bash
# Очистка кэша со статистикой
php bin/console app:cache-cleanup --stats

# Очистка мусора (файлы старше 7 дней)
php bin/console app:garbage-cleanup --all --stats --days=7

# Полная очистка всего
php bin/console app:cache-cleanup --all
php bin/console app:garbage-cleanup --all
```

### Через Makefile (если установлен make)

```bash
make cache-cleanup          # Умная очистка кэша
make garbage-cleanup        # Очистка мусора
make cleanup-all            # Полная очистка
```

## ⏰ Настройка автоматической очистки

### Windows - PowerShell (от администратора)

```powershell
$taskName = "To-Do List Cleanup"
$scriptPath = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list\scripts\cleanup.bat"
$workingDir = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list"

$trigger = New-ScheduledTaskTrigger -Daily -At 3:00AM
$action = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$scriptPath`"" -WorkingDirectory $workingDir
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName $taskName -Trigger $trigger -Action $action -Settings $settings -Description "Автоматическая очистка кеша" -Force
```

**Готово!** Очистка будет запускаться ежедневно в 3:00 AM.

### Linux - cron

```bash
crontab -e
```

Добавьте:
```bash
# Ежедневная очистка в 3:00 AM
0 3 * * * cd /path/to/project && php bin/console app:cache-cleanup --expired >> var/log/cron.log 2>&1
30 3 * * * cd /path/to/project && php bin/console app:garbage-cleanup --all --days=7 >> var/log/cron.log 2>&1
```

## 📊 Просмотр логов

```bash
# В реальном времени
tail -f var/log/cleanup.log

# Последние записи
tail -n 50 var/log/cleanup.log
```

## 📝 Доступные опции

### app:cache-cleanup

```bash
php bin/console app:cache-cleanup --expired     # Истёкший кэш (по умолчанию)
php bin/console app:cache-cleanup --all         # Весь кэш
php bin/console app:cache-cleanup --stats       # Со статистикой
php bin/console app:cache-cleanup --dry-run     # Проверка без удаления
```

### app:garbage-cleanup

```bash
php bin/console app:garbage-cleanup --all       # Весь мусор
php bin/console app:garbage-cleanup --temp      # Только временные файлы
php bin/console app:garbage-cleanup --logs      # Только старые логи
php bin/console app:garbage-cleanup --sessions  # Только сессии
php bin/console app:garbage-cleanup --days=14   # Хранить 14 дней
php bin/console app:garbage-cleanup --stats     # Со статистикой
```

## 🔍 Проверка что будет удалено

```bash
# Dry-run режим (без фактического удаления)
php bin/console app:garbage-cleanup --dry-run --stats
```

## 📄 Документация

- **Полная**: `docs/CLEANUP_AUTOMATION.md`
- **Для Windows**: `docs/cleanup-automation-windows.md`
- **Пример crontab**: `crontab.example`

---

**Система готова к использованию!** 🎉
