# Настройка автоматической очистки кеша и мусора для Windows
# ============================================

## Вариант 1: Использование Windows Task Scheduler (GUI)

1. Откройте Task Scheduler (Планировщик заданий)
   - Нажмите Win + R
   - Введите `taskschd.msc`
   - Нажмите Enter

2. Создайте базовую задачу:
   - Действия → Создать простую задачу
   - Имя: "To-Do List Cleanup"
   - Описание: "Автоматическая очистка кеша и мусора"

3. Настройте триггер:
   - Выберите "Ежедневно"
   - Время: 03:00

4. Настройте действие:
   - Выберите "Запустить программу"
   - Программа: `C:\Windows\System32\cmd.exe`
   - Аргументы: `/c "cd /d C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list && scripts\cleanup.bat"`
   - Рабочая папка: `C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list`

5. Завершите мастер и проверьте задачу

## Вариант 2: Использование PowerShell

Откройте PowerShell от имени администратора и выполните:

```powershell
$taskName = "To-Do List Cleanup"
$scriptPath = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list\scripts\cleanup.bat"
$workingDir = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list"

# Создаем триггер на 3:00 AM ежедневно
$trigger = New-ScheduledTaskTrigger -Daily -At 3:00AM

# Создаем действие
$action = New-ScheduledTaskAction -Execute "cmd.exe" `
    -Argument "/c `"$scriptPath`"" `
    -WorkingDirectory $workingDir

# Создаем настройки
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable:$false

# Регистрируем задачу
Register-ScheduledTask `
    -TaskName $taskName `
    -Trigger $trigger `
    -Action $action `
    -Settings $settings `
    -Description "Автоматическая очистка кеша и мусора для To-Do List" `
    -Force
```

## Вариант 3: Использование команды schtasks

Откройте Command Prompt от имени администратора:

```cmd
schtasks /Create /TN "To-Do List Cleanup" /TR "cmd.exe /c \"cd /d C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list && scripts\cleanup.bat\"" /SC DAILY /ST 03:00 /RL HIGHEST /F
```

## Проверка задачи

### Просмотр задач:
```powershell
Get-ScheduledTask -TaskName "To-Do List Cleanup"
```

### Запуск вручную:
```powershell
Start-ScheduledTask -TaskName "To-Do List Cleanup"
```

### Просмотр истории:
```powershell
Get-ScheduledTaskInfo -TaskName "To-Do List Cleanup"
```

## Удаление задачи

```powershell
Unregister-ScheduledTask -TaskName "To-Do List Cleanup" -Confirm:$false
```

## Логи

Логи очистки сохраняются в:
- `var/log/cleanup.log` - логи скрипта очистки
- `var/log/cron.log` - логи cron задач

Для просмотра логов в реальном времени:
```powershell
Get-Content -Path "var/log/cleanup.log" -Wait -Tail 50
```
