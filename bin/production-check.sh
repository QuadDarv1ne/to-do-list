#!/bin/bash
# Production Readiness Check Script
# Проверяет готовность проекта к развертыванию в продакшене

echo "🔍 Проверка готовности к продакшену..."
echo "======================================"
echo ""

ERRORS=0
WARNINGS=0

# Цвета для вывода
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

# Функция для вывода ошибок
error() {
    echo -e "${RED}❌ ОШИБКА: $1${NC}"
    ((ERRORS++))
}

# Функция для вывода предупреждений
warning() {
    echo -e "${YELLOW}⚠️  ПРЕДУПРЕЖДЕНИЕ: $1${NC}"
    ((WARNINGS++))
}

# Функция для вывода успеха
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

echo "1. Проверка окружения..."
echo "------------------------"

# Проверка APP_ENV
if grep -q "APP_ENV=prod" .env 2>/dev/null; then
    success "APP_ENV установлен в prod"
else
    warning "APP_ENV не установлен в prod (текущее: $(grep APP_ENV .env 2>/dev/null || echo 'не найдено'))"
fi

# Проверка APP_DEBUG
if grep -q "APP_DEBUG=0" .env 2>/dev/null || grep -q "APP_DEBUG=false" .env 2>/dev/null; then
    success "APP_DEBUG отключен"
else
    error "APP_DEBUG должен быть отключен в продакшене"
fi

# Проверка APP_SECRET
if grep -q "APP_SECRET=123e4567" .env 2>/dev/null; then
    error "APP_SECRET использует значение по умолчанию! Сгенерируйте новый секрет"
else
    success "APP_SECRET настроен"
fi

echo ""
echo "2. Проверка базы данных..."
echo "--------------------------"

# Проверка DATABASE_URL
if grep -q "sqlite" .env 2>/dev/null; then
    warning "Используется SQLite. Для продакшена рекомендуется MySQL или PostgreSQL"
else
    success "Используется продакшенная СУБД"
fi

# Проверка миграций
echo "Проверка миграций..."
php bin/console doctrine:migrations:status --no-interaction > /dev/null 2>&1
if [ $? -eq 0 ]; then
    success "Миграции в порядке"
else
    error "Проблемы с миграциями"
fi

echo ""
echo "3. Проверка зависимостей..."
echo "---------------------------"

# Проверка composer
if [ -f "composer.lock" ]; then
    success "composer.lock найден"
    
    # Проверка dev зависимостей
    if composer show --no-dev 2>/dev/null | grep -q "symfony/debug-bundle"; then
        warning "Dev зависимости установлены. Запустите: composer install --no-dev --optimize-autoloader"
    else
        success "Dev зависимости не установлены"
    fi
else
    error "composer.lock не найден"
fi

echo ""
echo "4. Проверка кэша..."
echo "-------------------"

# Проверка директории кэша
if [ -d "var/cache/prod" ]; then
    success "Продакшен кэш существует"
else
    warning "Продакшен кэш не найден. Запустите: php bin/console cache:warmup --env=prod"
fi

# Проверка прав доступа
if [ -w "var/cache" ]; then
    success "Директория var/cache доступна для записи"
else
    error "Директория var/cache недоступна для записи"
fi

echo ""
echo "5. Проверка безопасности..."
echo "---------------------------"

# Проверка .env в .gitignore
if grep -q "^\.env$" .gitignore 2>/dev/null; then
    success ".env в .gitignore"
else
    error ".env должен быть в .gitignore"
fi

# Проверка var/ в .gitignore
if grep -q "^/var/" .gitignore 2>/dev/null; then
    success "var/ в .gitignore"
else
    warning "var/ должен быть в .gitignore"
fi

# Проверка vendor/ в .gitignore
if grep -q "^/vendor/" .gitignore 2>/dev/null; then
    success "vendor/ в .gitignore"
else
    error "vendor/ должен быть в .gitignore"
fi

echo ""
echo "6. Проверка производительности..."
echo "----------------------------------"

# Проверка OPcache
if php -r "echo ini_get('opcache.enable');" | grep -q "1"; then
    success "OPcache включен"
else
    warning "OPcache не включен. Включите для лучшей производительности"
fi

# Проверка memory_limit
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
success "PHP memory_limit: $MEMORY_LIMIT"

echo ""
echo "7. Проверка файлов..."
echo "---------------------"

# Проверка критичных файлов
FILES=("public/index.php" "config/services.yaml" "composer.json" ".env")
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        success "$file существует"
    else
        error "$file не найден"
    fi
done

echo ""
echo "8. Проверка прав доступа..."
echo "---------------------------"

# Проверка прав на var/
if [ -w "var" ]; then
    success "var/ доступна для записи"
else
    error "var/ должна быть доступна для записи"
fi

# Проверка прав на public/
if [ -r "public" ]; then
    success "public/ доступна для чтения"
else
    error "public/ должна быть доступна для чтения"
fi

echo ""
echo "9. Проверка конфигурации..."
echo "----------------------------"

# Проверка routes
php bin/console debug:router --env=prod > /dev/null 2>&1
if [ $? -eq 0 ]; then
    success "Маршруты настроены корректно"
else
    error "Проблемы с маршрутами"
fi

# Проверка контейнера
php bin/console debug:container --env=prod > /dev/null 2>&1
if [ $? -eq 0 ]; then
    success "Контейнер настроен корректно"
else
    error "Проблемы с контейнером"
fi

echo ""
echo "======================================"
echo "Результаты проверки:"
echo "======================================"

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}🎉 Отлично! Проект готов к продакшену!${NC}"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  Найдено предупреждений: $WARNINGS${NC}"
    echo "Рекомендуется исправить перед деплоем"
    exit 0
else
    echo -e "${RED}❌ Найдено ошибок: $ERRORS${NC}"
    echo -e "${YELLOW}⚠️  Найдено предупреждений: $WARNINGS${NC}"
    echo ""
    echo "Исправьте ошибки перед деплоем в продакшен!"
    exit 1
fi
