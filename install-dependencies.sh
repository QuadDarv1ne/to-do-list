#!/bin/bash
# Скрипт автоматической установки всех зависимостей проекта

echo "Установка зависимостей для CRM Task Management System..."

# Production зависимости
composer require symfony/framework-bundle:8.0.*
composer require symfony/console:8.0.*
composer require symfony/dotenv:8.0.*
composer require symfony/flex:^2.10
composer require symfony/runtime:8.0.*
composer require symfony/yaml:8.0.*

# Database
composer require doctrine/orm:^3.6
composer require doctrine/doctrine-bundle:^3.2
composer require doctrine/doctrine-migrations-bundle:^4.0

# Security
composer require symfony/security-bundle:8.0.*
composer require scheb/2fa-bundle:^8.3
composer require scheb/2fa-google-authenticator:^8.3
composer require endroid/qr-code:^6.1
composer require nelmio/security-bundle:^3.8

# Forms & Validation
composer require symfony/form:8.0.*
composer require symfony/validator:8.0.*
composer require symfony/property-access:8.0.*
composer require symfony/property-info:8.0.*

# Templating
composer require symfony/twig-bundle:8.0.*
composer require twig/extra-bundle:^3.23

# HTTP & API
composer require symfony/http-client:8.0.*
composer require guzzlehttp/guzzle:^7.10
composer require symfony/serializer:8.0.*
composer require symfony/rate-limiter:8.0.*

# Mailer & Notifications
composer require symfony/mailer:8.0.*
composer require symfony/notifier:8.0.*
composer require symfony/mime:8.0.*

# Messaging
composer require symfony/messenger:8.0.*
composer require symfony/doctrine-messenger:8.0.*

# Frontend
composer require symfony/asset:8.0.*
composer require symfony/asset-mapper:8.0.*
composer require symfony/stimulus-bundle:^2.32
composer require symfony/ux-turbo:^2.32

# Authentication helpers
composer require symfonycasts/reset-password-bundle:^1.24
composer require symfonycasts/verify-email-bundle:^1.18

# Export & Reports
composer require dompdf/dompdf:^3.1
composer require phpoffice/phpspreadsheet:^5.4

# Integrations
composer require league/oauth2-client:^2.9

# Caching & Performance
composer require predis/predis:^3.4

# Monitoring & Logging
composer require symfony/monolog-bundle:^3.0
composer require sentry/sentry-symfony:^5.8

# API Documentation
composer require nelmio/api-doc-bundle:^5.9

# Image Processing
composer require intervention/image:^3.11

# Utilities
composer require symfony/string:8.0.*
composer require symfony/translation:8.0.*
composer require symfony/intl:8.0.*
composer require symfony/expression-language:8.0.*
composer require symfony/process:8.0.*
composer require symfony/web-link:8.0.*
composer require phpdocumentor/reflection-docblock:^5.6
composer require phpstan/phpdoc-parser:^2.3

# Development зависимости
composer require --dev symfony/maker-bundle:^1.66
composer require --dev symfony/debug-bundle:8.0.*
composer require --dev symfony/web-profiler-bundle:8.0.*
composer require --dev symfony/stopwatch:8.0.*
composer require --dev symfony/browser-kit:8.0.*
composer require --dev symfony/css-selector:8.0.*

# Testing
composer require --dev phpunit/phpunit:^12.5
composer require --dev doctrine/doctrine-fixtures-bundle:^4.3

# Code Quality
composer require --dev phpstan/phpstan:^2.1
composer require --dev phpstan/phpstan-doctrine:^2.0
composer require --dev phpstan/phpstan-phpunit:^2.0
composer require --dev phpstan/phpstan-symfony:^2.0
composer require --dev friendsofphp/php-cs-fixer:^3.94

echo "Установка завершена!"
echo "Запустите 'composer install' для установки всех зависимостей"
