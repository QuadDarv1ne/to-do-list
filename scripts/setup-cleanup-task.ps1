# Скрипт настройки автоматической очистки кеша для Windows Task Scheduler
# Запускать от имени администратора!

$taskName = "To-Do List Cleanup"
$scriptPath = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list\scripts\cleanup.bat"
$workingDir = "C:\Users\maksi\OneDrive\Documents\GitHub\to-do-list"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Настройка автоматической очистки кеша" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Создаем триггер на 3:00 AM ежедневно
Write-Host "[1/4] Создание триггера (ежедневно в 3:00)..." -ForegroundColor Yellow
$trigger = New-ScheduledTaskTrigger -Daily -At 3:00AM
Write-Host "      Готово!" -ForegroundColor Green

# Создаем действие
Write-Host "[2/4] Создание действия..." -ForegroundColor Yellow
$action = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$scriptPath`"" -WorkingDirectory $workingDir
Write-Host "      Готово!" -ForegroundColor Green

# Создаем настройки
Write-Host "[3/4] Настройка параметров..." -ForegroundColor Yellow
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
Write-Host "      Готово!" -ForegroundColor Green

# Регистрируем задачу
Write-Host "[4/4] Регистрация задачи..." -ForegroundColor Yellow
Register-ScheduledTask -TaskName $taskName -Trigger $trigger -Action $action -Settings $settings -Description "Автоматическая очистка кеша и мусора для To-Do List" -Force
Write-Host "      Готово!" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Задача успешно создана!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Задача: $taskName" -ForegroundColor White
Write-Host "Время запуска: ежедневно в 3:00 AM" -ForegroundColor White
Write-Host "Скрипт: $scriptPath" -ForegroundColor White
Write-Host ""
Write-Host "Полезные команды:" -ForegroundColor Cyan
Write-Host "  Проверка задачи:           Get-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
Write-Host "  Запуск вручную (тест):     Start-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
Write-Host "  Просмотр истории:          Get-ScheduledTaskInfo -TaskName '$taskName'" -ForegroundColor Gray
Write-Host "  Удаление задачи:           Unregister-ScheduledTask -TaskName '$taskName' -Confirm:\$false" -ForegroundColor Gray
Write-Host ""
