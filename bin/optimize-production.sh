#!/bin/bash

# Скрипт оптимизации для production окружения
# Использование: bash bin/optimize-production.sh

set -e

echo "🚀 Начинаем оптимизацию для production..."

# 1. Очистка кэша
echo "📦 Очистка кэша..."
php bin/console cache:clear --env=prod --no-debug

# 2. Прогрев кэша
echo "🔥 Прогрев кэша..."
php bin/console cache:warmup --env=prod --no-debug

# 3. Оптимизация автозагрузки Composer
echo "⚡ Оптимизация автозагрузки..."
composer dump-autoload --optimize --no-dev --classmap-authoritative

# 4. Установка asset-ов
echo "📁 Установка asset-ов..."
php bin/console assets:install public --env=prod --no-debug

# 5. Компиляция .env файлов
echo "🔐 Компиляция .env файлов..."
composer dump-env prod || echo "⚠️  Пропущено: symfony/flex не установлен"

# 6. Валидация схемы БД
echo "🗄️  Валидация схемы БД..."
php bin/console doctrine:schema:validate --env=prod || echo "⚠️  Предупреждение: проблемы со схемой БД"

# 7. Применение миграций
echo "📊 Применение миграций..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# 8. Очистка старых логов (опционально)
echo "🧹 Очистка старых логов..."
find var/log -name "*.log" -mtime +30 -delete 2>/dev/null || true

# 9. Установка правильных прав доступа
echo "🔒 Установка прав доступа..."
chmod -R 755 var/cache var/log public/uploads 2>/dev/null || true

echo "✅ Оптимизация завершена успешно!"
echo ""
echo "📝 Рекомендации:"
echo "   - Убедитесь, что APP_ENV=prod в .env"
echo "   - Проверьте настройки opcache в php.ini"
echo "   - Настройте supervisor для messenger:consume"
echo "   - Включите HTTP кэширование (Varnish/CloudFlare)"
