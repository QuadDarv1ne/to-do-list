@echo off
REM Script to reset migrations for fresh installations
REM WARNING: This will delete all migration history and recreate from scratch
REM Only use this on development environments or fresh installations

echo WARNING: This will reset all migrations!
echo This should only be used for fresh installations.
set /p confirm="Are you sure you want to continue? (yes/no): "

if not "%confirm%"=="yes" (
    echo Aborted.
    exit /b 1
)

echo Backing up current database...
copy var\data.db var\data.db.backup.%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%%time:~6,2%

echo Dropping all tables...
php bin/console doctrine:schema:drop --force --full-database

echo Removing old migrations...
del /q migrations\Version*.php

echo Creating fresh migration...
php bin/console doctrine:migrations:diff

echo Running migration...
php bin/console doctrine:migrations:migrate --no-interaction

echo Migration reset complete!
echo Creating test data...
php bin/console app:create-test-users

echo Done! Database has been reset with a single migration.
pause
