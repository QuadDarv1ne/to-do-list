@echo off
chcp 65001 >nul 2>&1
REM Production Readiness Check Script
REM Проверяет готовность проекта к развертыванию в продакшене

echo Проверка готовности к продакшену...
echo ======================================
echo.

set ERRORS=0
set WARNINGS=0

echo 1. Проверка окружения...
echo ------------------------

REM Проверка APP_ENV
findstr /C:"APP_ENV=prod" .env >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] APP_ENV установлен в prod
) else (
    echo [WARNING] APP_ENV не установлен в prod
    set /a WARNINGS+=1
)

REM Проверка APP_DEBUG
findstr /C:"APP_DEBUG=0" .env >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] APP_DEBUG отключен
) else (
    findstr /C:"APP_DEBUG=false" .env >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] APP_DEBUG отключен
    ) else (
        echo [ERROR] APP_DEBUG должен быть отключен в продакшене
        set /a ERRORS+=1
    )
)

REM Проверка APP_SECRET
findstr /C:"APP_SECRET=123e4567" .env >nul 2>&1
if %errorlevel% equ 0 (
    echo [ERROR] APP_SECRET использует значение по умолчанию!
    set /a ERRORS+=1
) else (
    echo [OK] APP_SECRET настроен
)

echo.
echo 2. Проверка базы данных...
echo --------------------------

REM Проверка DATABASE_URL
findstr /C:"sqlite" .env >nul 2>&1
if %errorlevel% equ 0 (
    echo [WARNING] Используется SQLite. Для продакшена рекомендуется MySQL или PostgreSQL
    set /a WARNINGS+=1
) else (
    echo [OK] Используется продакшенная СУБД
)

REM Проверка миграций
php bin/console doctrine:migrations:status --no-interaction >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Миграции в порядке
) else (
    echo [ERROR] Проблемы с миграциями
    set /a ERRORS+=1
)

echo.
echo 3. Проверка зависимостей...
echo ---------------------------

if exist composer.lock (
    echo [OK] composer.lock найден
) else (
    echo [ERROR] composer.lock не найден
    set /a ERRORS+=1
)

echo.
echo 4. Проверка кэша...
echo -------------------

if exist var\cache\prod (
    echo [OK] Продакшен кэш существует
) else (
    echo [WARNING] Продакшен кэш не найден
    set /a WARNINGS+=1
)

echo.
echo 5. Проверка безопасности...
echo ---------------------------

findstr /C:".env" .gitignore >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] .env в .gitignore
) else (
    echo [ERROR] .env должен быть в .gitignore
    set /a ERRORS+=1
)

findstr /C:"/var/" .gitignore >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] var/ в .gitignore
) else (
    echo [WARNING] var/ должен быть в .gitignore
    set /a WARNINGS+=1
)

findstr /C:"/vendor/" .gitignore >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] vendor/ в .gitignore
) else (
    echo [ERROR] vendor/ должен быть в .gitignore
    set /a ERRORS+=1
)

echo.
echo 6. Проверка файлов...
echo ---------------------

if exist public\index.php (
    echo [OK] public/index.php существует
) else (
    echo [ERROR] public/index.php не найден
    set /a ERRORS+=1
)

if exist config\services.yaml (
    echo [OK] config/services.yaml существует
) else (
    echo [ERROR] config/services.yaml не найден
    set /a ERRORS+=1
)

if exist composer.json (
    echo [OK] composer.json существует
) else (
    echo [ERROR] composer.json не найден
    set /a ERRORS+=1
)

if exist .env (
    echo [OK] .env существует
) else (
    echo [ERROR] .env не найден
    set /a ERRORS+=1
)

echo.
echo 7. Проверка конфигурации...
echo ----------------------------

php bin/console debug:router --env=prod >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Маршруты настроены корректно
) else (
    echo [ERROR] Проблемы с маршрутами
    set /a ERRORS+=1
)

php bin/console debug:container --env=prod >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Контейнер настроен корректно
) else (
    echo [ERROR] Проблемы с контейнером
    set /a ERRORS+=1
)

echo.
echo ======================================
echo Результаты проверки:
echo ======================================

if %ERRORS% equ 0 if %WARNINGS% equ 0 (
    echo [SUCCESS] Отлично! Проект готов к продакшену!
    exit /b 0
) else if %ERRORS% equ 0 (
    echo [WARNING] Найдено предупреждений: %WARNINGS%
    echo Рекомендуется исправить перед деплоем
    exit /b 0
) else (
    echo [ERROR] Найдено ошибок: %ERRORS%
    echo [WARNING] Найдено предупреждений: %WARNINGS%
    echo.
    echo Исправьте ошибки перед деплоем в продакшен!
    exit /b 1
)
