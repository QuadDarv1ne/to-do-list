#!/bin/bash

# Скрипт проверки производительности
# Использование: bash bin/check-performance.sh

echo "🔍 Проверка производительности приложения..."
echo ""

# 1. Проверка размера кэша
echo "📦 Размер кэша:"
du -sh var/cache/* 2>/dev/null || echo "Кэш пуст"
echo ""

# 2. Проверка количества файлов в кэше
echo "📁 Количество файлов в кэше:"
find var/cache -type f | wc -l
echo ""

# 3. Проверка размера логов
echo "📝 Размер логов:"
du -sh var/log 2>/dev/null || echo "Логи пусты"
echo ""

# 4. Проверка автозагрузки Composer
echo "⚡ Статус автозагрузки Composer:"
if [ -f vendor/composer/autoload_classmap.php ]; then
    CLASSES=$(php -r "echo count(require 'vendor/composer/autoload_classmap.php');")
    echo "Оптимизированная автозагрузка: $CLASSES классов"
else
    echo "⚠️  Автозагрузка не оптимизирована"
fi
echo ""

# 5. Проверка opcache (если доступен)
echo "🚀 Статус OPcache:"
php -r "if (function_exists('opcache_get_status')) { 
    \$status = opcache_get_status(); 
    echo 'Включен: ' . (\$status['opcache_enabled'] ? 'Да' : 'Нет') . PHP_EOL;
    if (\$status['opcache_enabled']) {
        echo 'Использовано памяти: ' . round(\$status['memory_usage']['used_memory'] / 1024 / 1024, 2) . ' MB' . PHP_EOL;
        echo 'Кэшировано скриптов: ' . \$status['opcache_statistics']['num_cached_scripts'] . PHP_EOL;
    }
} else { 
    echo '⚠️  OPcache не установлен'; 
}"
echo ""

# 6. Проверка размера БД
echo "🗄️  Размер базы данных:"
if [ -f var/data.db ]; then
    du -sh var/data.db
else
    echo "SQLite БД не найдена"
fi
echo ""

# 7. Проверка количества сервисов
echo "🔧 Количество зарегистрированных сервисов:"
php bin/console debug:container --env=prod 2>/dev/null | grep -c "App\\\\" || echo "Не удалось получить"
echo ""

# 8. Рекомендации
echo "💡 Рекомендации по оптимизации:"
echo "   1. Запустите: composer dump-autoload --optimize --classmap-authoritative"
echo "   2. Включите OPcache в php.ini"
echo "   3. Используйте APCu для кэширования метаданных Doctrine"
echo "   4. Настройте HTTP кэширование"
echo "   5. Минифицируйте CSS/JS файлы"
echo "   6. Используйте CDN для статических ресурсов"
