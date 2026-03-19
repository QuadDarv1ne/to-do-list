@echo off
REM ============================================
REM Скрипт автоматической очистки кеша и мусора
REM Для Windows Task Scheduler
REM ============================================

setlocal enabledelayedexpansion

REM Настройки
set PROJECT_DIR=%~dp0..
set PHP_BIN=php
set LOG_FILE=%PROJECT_DIR%\var\log\cleanup.log
set DAYS_TO_KEEP=7

REM Создаем директорию для логов если не существует
if not exist "%PROJECT_DIR%\var\log" (
    mkdir "%PROJECT_DIR%\var\log"
)

echo ============================================ >> %LOG_FILE%
echo [%date% %time%] Начало очистки >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%

REM Переходим в директорию проекта
cd /d "%PROJECT_DIR%"

REM Очищаем кэш
echo [%date% %time%] Очистка кэша... >> %LOG_FILE%
%PHP_BIN% bin/console app:cache-cleanup --expired --days=%DAYS_TO_KEEP% >> %LOG_FILE% 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [%date% %time%] ОШИБКА: Очистка кэша не удалась >> %LOG_FILE%
) else (
    echo [%date% %time%] Очистка кэша завершена успешно >> %LOG_FILE%
)

REM Очищаем мусор
echo [%date% %time%] Очистка мусора... >> %LOG_FILE%
%PHP_BIN% bin/console app:garbage-cleanup --all --days=%DAYS_TO_KEEP% >> %LOG_FILE% 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [%date% %time%] ОШИБКА: Очистка мусора не удалась >> %LOG_FILE%
) else (
    echo [%date% %time%] Очистка мусора завершена успешно >> %LOG_FILE%
)

REM Очищаем кэш Symfony стандартными средствами
echo [%date% %time%] Очистка кэша Symfony... >> %LOG_FILE%
%PHP_BIN% bin/console cache:clear --no-warmup >> %LOG_FILE% 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [%date% %time%] ОШИБКА: Очистка кэша Symfony не удалась >> %LOG_FILE%
) else (
    echo [%date% %time%] Очистка кэша Symfony завершена успешно >> %LOG_FILE%
)

echo [%date% %time%] Все задачи очистки завершены >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%

echo Очистка завершена! Лог: %LOG_FILE%
