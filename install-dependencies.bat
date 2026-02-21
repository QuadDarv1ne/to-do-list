@echo off
REM Скрипт автоматической установки всех зависимостей проекта для Windows

echo Установка зависимостей для CRM Task Management System...

REM Production зависимости
call composer require symfony/framework-bundle:8.0.*
call composer require symfony/console:8.0.*
call composer require symfony/dotenv:8.0.*
call composer require symfony/flex:^2.10
call composer require symfony/runtime:8.0.*
call composer require symfony/yaml:8.0.*

REM Database
call composer require doctrine/orm:^3.6
call composer require doctrine/doctrine-bundle:^3.2
call composer require doctrine/doctrine-migrations-bundle:^4.0

REM Security
call composer require symfony/security-bundle:8.0.*
call composer require scheb/2fa-bundle:^8.3
call composer require scheb/2fa-google-authenticator:^8.3
call composer require endroid/qr-code:^6.1
call composer require nelmio/security-bundle:^3.8

REM Forms & Validation
call composer require symfony/form:8.0.*
call composer require symfony/validator:8.0.*
call composer require symfony/property-access:8.0.*
call composer require symfony/property-info:8.0.*

REM Templating
call composer require symfony/twig-bundle:8.0.*
call composer require twig/extra-bundle:^3.23

REM HTTP & API
call composer require symfony/http-client:8.0.*
call composer require guzzlehttp/guzzle:^7.10
call composer require symfony/serializer:8.0.*
call composer require symfony/rate-limiter:8.0.*

REM Mailer & Notifications
call composer require symfony/mailer:8.0.*
call composer require symfony/notifier:8.0.*
call composer require symfony/mime:8.0.*

REM Messaging
call composer require symfony/messenger:8.0.*
call composer require symfony/doctrine-messenger:8.0.*

REM Frontend
call composer require symfony/asset:8.0.*
call composer require symfony/asset-mapper:8.0.*
call composer require symfony/stimulus-bundle:^2.32
call composer require symfony/ux-turbo:^2.32

REM Authentication helpers
call composer require symfonycasts/reset-password-bundle:^1.24
call composer require symfonycasts/verify-email-bundle:^1.18

REM Export & Reports
call composer require dompdf/dompdf:^3.1
call composer require phpoffice/phpspreadsheet:^5.4

REM Integrations
call composer require league/oauth2-client:^2.9

REM Caching & Performance
call composer require predis/predis:^3.4

REM Monitoring & Logging
call composer require symfony/monolog-bundle:^3.0
call composer require sentry/sentry-symfony:^5.8

REM API Documentation
call composer require nelmio/api-doc-bundle:^5.9

REM Image Processing
call composer require intervention/image:^3.11

REM Development зависимости
call composer require --dev symfony/maker-bundle:^1.66
call composer require --dev symfony/debug-bundle:8.0.*
call composer require --dev symfony/web-profiler-bundle:8.0.*
call composer require --dev phpunit/phpunit:^12.5
call composer require --dev doctrine/doctrine-fixtures-bundle:^4.3
call composer require --dev phpstan/phpstan:^2.1
call composer require --dev phpstan/phpstan-doctrine:^2.0
call composer require --dev phpstan/phpstan-phpunit:^2.0
call composer require --dev phpstan/phpstan-symfony:^2.0
call composer require --dev friendsofphp/php-cs-fixer:^3.94

echo Установка завершена!
echo Запустите 'composer install' для установки всех зависимостей
pause
