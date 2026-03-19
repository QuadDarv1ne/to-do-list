# Скрипт удаления задачи автоматической очистки
# Запускать от имени администратора!

$taskName = "To-Do List Cleanup"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Удаление задачи автоматической очистки" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Проверяем существует ли задача
$task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if ($task) {
    Write-Host "Задача найдена: $taskName" -ForegroundColor Yellow
    Write-Host ""
    
    $confirmation = Read-Host "Вы уверены, что хотите удалить задачу? (y/n)"
    
    if ($confirmation -eq 'y' -or $confirmation -eq 'Y') {
        Write-Host "Удаление задачи..." -ForegroundColor Yellow
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Host "Задача успешно удалена!" -ForegroundColor Green
    } else {
        Write-Host "Отменено." -ForegroundColor Gray
    }
} else {
    Write-Host "Задача '$taskName' не найдена." -ForegroundColor Red
    Write-Host "Возможно, она уже была удалена или никогда не создавалась." -ForegroundColor Gray
}

Write-Host ""
