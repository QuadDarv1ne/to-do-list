# ============================================
# CRM To-Do List - Makefile
# ============================================
# Использование:
#   make help        - Показать справку
#   make install     - Установить зависимости
#   make test        - Запустить тесты
#   make build       - Production сборка
# ============================================

.PHONY: help install test check clean cache db docker assets

# Цвета для вывода
COLOR_RESET=\033[0m
COLOR_GREEN=\033[32m
COLOR_YELLOW=\033[33m
COLOR_BLUE=\033[36m
COLOR_RED=\033[31m

# ============================================
# HELPERS
# ============================================

help: ## 📚 Показать справку по всем командам
	@echo ""
	@echo "${COLOR_BLUE}╔═══════════════════════════════════════════════════════════╗${COLOR_RESET}"
	@echo "${COLOR_BLUE}║${COLOR_RESET}  ${COLOR_GREEN}CRM To-Do List - Доступные команды${COLOR_BLUE}                      ║${COLOR_RESET}"
	@echo "${COLOR_BLUE}╚═══════════════════════════════════════════════════════════╝${COLOR_RESET}"
	@echo ""
	@echo "${COLOR_YELLOW}📦 Установка зависимостей:${COLOR_RESET}"
	@echo "  make install         - Установить PHP и npm зависимости"
	@echo "  make install-php     - Только Composer зависимости"
	@echo "  make install-npm     - Только npm зависимости"
	@echo ""
	@echo "${COLOR_YELLOW}🧪 Тестирование и проверки:${COLOR_RESET}"
	@echo "  make test            - Запустить PHPUnit тесты"
	@echo "  make test-coverage   - Тесты с отчётом покрытия"
	@echo "  make test-watch      - Тесты в режиме watching"
	@echo "  make check           - Все проверки (cs + phpstan + test)"
	@echo "  make cs              - Проверка стиля кода"
	@echo "  make cs-fix          - Исправить стиль кода"
	@echo "  make phpstan         - Статический анализ PHPStan"
	@echo ""
	@echo "${COLOR_YELLOW}🏗️ Сборка и assets:${COLOR_RESET}"
	@echo "  make build           - Production сборка"
	@echo "  make assets          - Собрать frontend assets"
	@echo "  make minify-css      - Минифицировать CSS"
	@echo "  make minify-js       - Минифицировать JS"
	@echo ""
	@echo "${COLOR_YELLOW}🗄️ База данных:${COLOR_RESET}"
	@echo "  make db-create       - Создать базу данных"
	@echo "  make db-drop         - Удалить базу данных"
	@echo "  make db-migrate      - Применить миграции"
	@echo "  make db-diff         - Создать миграцию (diff)"
	@echo "  make db-reset        - Сбросить и создать БД"
	@echo "  make fixtures        - Загрузить фикстуры"
	@echo ""
	@echo "${COLOR_YELLOW}🔧 Кэш и обслуживание:${COLOR_RESET}"
	@echo "  make cache-clear     - Очистить кэш"
	@echo "  make cache-warmup    - Прогреть кэш"
	@echo "  make cache-cleanup   - Умная очистка кэша (истёкший)"
	@echo "  make cache-cleanup-all - Полная очистка кэша"
	@echo "  make garbage-cleanup - Очистка мусора (временные файлы, логи)"
	@echo "  make garbage-cleanup-dry - Проверка что будет очищено"
	@echo "  make cleanup-all     - Полная очистка кэша и мусора"
	@echo "  make logs            - Просмотр логов"
	@echo "  make clean           - Очистить временные файлы"
	@echo ""
	@echo "${COLOR_YELLOW}🐳 Docker:${COLOR_RESET}"
	@echo "  make docker-up       - Запустить Docker контейнеры"
	@echo "  make docker-down     - Остановить Docker контейнеры"
	@echo "  make docker-logs     - Просмотр логов Docker"
	@echo "  make docker-restart  - Перезапустить Docker"
	@echo ""
	@echo "${COLOR_YELLOW}🚀 Разное:${COLOR_RESET}"
	@echo "  make server          - Запустить dev сервер"
	@echo "  make test-users      - Создать тестовых пользователей"
	@echo "  make setup-hooks     - Настроить pre-commit hooks"
	@echo ""

# ============================================
# INSTALLATION
# ============================================

install: install-php install-npm ## 📦 Установить все зависимости
	@echo "${COLOR_GREEN}✅ Все зависимости установлены!${COLOR_RESET}"
	@echo "${COLOR_YELLOW}💡 Далее выполните:${COLOR_RESET}"
	@echo "   make db-create"
	@echo "   make db-migrate"
	@echo "   make fixtures"
	@echo ""

install-php: ## 📦 Установить PHP зависимости (Composer)
	@echo "${COLOR_BLUE}⏳ Установка PHP зависимостей...${COLOR_RESET}"
	composer install --no-progress --prefer-dist
	@echo "${COLOR_GREEN}✅ Composer зависимости установлены${COLOR_RESET}"

install-npm: ## 📦 Установить npm зависимости
	@echo "${COLOR_BLUE}⏳ Установка npm зависимостей...${COLOR_RESET}"
	npm install --progress=false
	@echo "${COLOR_GREEN}✅ Npm зависимости установлены${COLOR_RESET}"

# ============================================
# TESTING
# ============================================

test: ## 🧪 Запустить PHPUnit тесты
	@echo "${COLOR_BLUE}⏳ Запуск тестов...${COLOR_RESET}"
	php bin/phpunit --testdox --colors=always

test-coverage: ## 🧪 Тесты с отчётом покрытия (HTML)
	@echo "${COLOR_BLUE}⏳ Запуск тестов с coverage...${COLOR_RESET}"
	php bin/phpunit --coverage-html coverage/html --colors=always
	@echo "${COLOR_GREEN}✅ Отчёт доступен в coverage/html/index.html${COLOR_RESET}"

test-coverage-text: ## 🧪 Тесты с coverage в терминале
	php bin/phpunit --coverage-text --colors=always

test-watch: ## 🧪 Тесты в режиме watching (npm)
	npm run test:watch

check: check-cs check-phpstan check-test ## ✅ Все проверки
	@echo ""
	@echo "${COLOR_GREEN}🎉 Все проверки пройдены успешно!${COLOR_RESET}"

check-cs: ## 🔍 Проверка стиля кода
	@echo "${COLOR_BLUE}⏳ Проверка стиля кода...${COLOR_RESET}"
	composer cs

check-phpstan: ## 🔍 Статический анализ
	@echo "${COLOR_BLUE}⏳ Статический анализ...${COLOR_RESET}"
	composer phpstan

check-test: ## 🧪 Быстрый запуск тестов
	@echo "${COLOR_BLUE}⏳ Запуск тестов...${COLOR_RESET}"
	php bin/phpunit --stop-on-failure --colors=always

cs: ## 🎨 Проверка стиля кода (PHP CS Fixer)
	composer cs

cs-fix: ## 🎨 Исправить стиль кода автоматически
	@echo "${COLOR_BLUE}⏳ Исправление стиля кода...${COLOR_RESET}"
	composer cs:fix
	@echo "${COLOR_GREEN}✅ Стиль кода исправлен${COLOR_RESET}"

phpstan: ## 📊 Статический анализ PHPStan
	composer phpstan

phpstan-baseline: ## 📊 Создать baseline для PHPStan
	php vendor/bin/phpstan analyse --generate-baseline

# ============================================
# BUILD & ASSETS
# ============================================

build: build-prod ## 🏗️ Production сборка
	@echo "${COLOR_GREEN}✅ Production сборка завершена!${COLOR_RESET}"

build-prod: ## 🏗️ Полная production сборка
	@echo "${COLOR_BLUE}⏳ Production сборка...${COLOR_RESET}"
	npm run build:prod
	php bin/console cache:clear --env=prod
	php bin/console cache:warmup --env=prod
	@echo "${COLOR_GREEN}✅ Production сборка завершена${COLOR_RESET}"

build-dev: ## 🏗️ Dev сборка
	@echo "${COLOR_BLUE}⏳ Dev сборка...${COLOR_RESET}"
	npm run build
	php bin/console cache:clear
	@echo "${COLOR_GREEN}✅ Dev сборка завершена${COLOR_RESET}"

assets: ## 🎨 Собрать frontend assets
	@echo "${COLOR_BLUE}⏳ Сборка assets...${COLOR_RESET}"
	npm run build

minify-css: ## 🗜️ Минифицировать CSS файлы
	@echo "${COLOR_BLUE}⏳ Минификация CSS...${COLOR_RESET}"
	npm run minify:css
	@echo "${COLOR_GREEN}✅ CSS минифицирован${COLOR_RESET}"

minify-js: ## 🗜️ Минифицировать JS файлы
	@echo "${COLOR_BLUE}⏳ Минификация JS...${COLOR_RESET}"
	npm run minify:js
	@echo "${COLOR_GREEN}✅ JS минифицирован${COLOR_RESET}"

minify-design: ## 🗜️ Минифицировать design-system.css
	@echo "${COLOR_BLUE}⏳ Минификация design-system.css...${COLOR_RESET}"
	npm run minify:design
	@echo "${COLOR_GREEN}✅ design-system.min.css создан${COLOR_RESET}"

# ============================================
# DATABASE
# ============================================

db-create: ## 🗄️ Создать базу данных
	@echo "${COLOR_BLUE}⏳ Создание базы данных...${COLOR_RESET}"
	php bin/console doctrine:database:create --if-not-exists
	@echo "${COLOR_GREEN}✅ База данных создана${COLOR_RESET}"

db-drop: ## 🗄️ Удалить базу данных
	@echo "${COLOR_RED}⚠️  Удаление базы данных...${COLOR_RESET}"
	php bin/console doctrine:database:drop --if-exists --force
	@echo "${COLOR_GREEN}✅ База данных удалена${COLOR_RESET}"

db-migrate: ## 🗄️ Применить миграции
	@echo "${COLOR_BLUE}⏳ Применение миграций...${COLOR_RESET}"
	php bin/console doctrine:migrations:migrate --no-interaction
	@echo "${COLOR_GREEN}✅ Миграции применены${COLOR_RESET}"

db-diff: ## 🗄️ Создать миграцию (diff)
	@echo "${COLOR_BLUE}⏳ Создание миграции...${COLOR_RESET}"
	php bin/console doctrine:migrations:diff
	@echo "${COLOR_GREEN}✅ Миграция создана${COLOR_RESET}"

db-status: ## 🗄️ Статус миграций
	php bin/console doctrine:migrations:status

db-reset: ## 🗄️ Сбросить и создать БД заново
	@echo "${COLOR_RED}⚠️  Сброс базы данных...${COLOR_RESET}"
	@echo "${COLOR_YELLOW}💡 Эта операция удалит все данные!${COLOR_RESET}"
	@read -p "Вы уверены? (y/N): " confirm && \
	if [ "$$confirm" = "y" ]; then \
		$(MAKE) db-drop; \
		$(MAKE) db-create; \
		$(MAKE) db-migrate; \
		$(MAKE) fixtures; \
		echo "${COLOR_GREEN}✅ База данных сброшена${COLOR_RESET}"; \
	else \
		echo "${COLOR_YELLOW}⏭️  Отменено${COLOR_RESET}"; \
	fi

fixtures: ## 📚 Загрузить фикстуры
	@echo "${COLOR_BLUE}⏳ Загрузка фикстур...${COLOR_RESET}"
	php bin/console doctrine:fixtures:load --no-interaction --append
	@echo "${COLOR_GREEN}✅ Фикстуры загружены${COLOR_RESET}"

fixtures-purge: ## 📚 Очистить и загрузить фикстуры
	@echo "${COLOR_BLUE}⏳ Очистка и загрузка фикстур...${COLOR_RESET}"
	php bin/console doctrine:fixtures:load --no-interaction --purge-except
	@echo "${COLOR_GREEN}✅ Фикстуры загружены${COLOR_RESET}"

# ============================================
# CACHE & MAINTENANCE
# ============================================

cache-clear: ## 🧹 Очистить кэш
	@echo "${COLOR_BLUE}⏳ Очистка кэша...${COLOR_RESET}"
	php bin/console cache:clear
	@echo "${COLOR_GREEN}✅ Кэш очищен${COLOR_RESET}"

cache-warmup: ## 🔥 Прогреть кэш
	@echo "${COLOR_BLUE}⏳ Прогрев кэша...${COLOR_RESET}"
	php bin/console cache:warmup
	@echo "${COLOR_GREEN}✅ Кэш прогрет${COLOR_RESET}"

cache-cleanup: ## 🧹 Умная очистка кэша
	@echo "${COLOR_BLUE}⏳ Умная очистка кэша...${COLOR_RESET}"
	php bin/console app:cache-cleanup --expired --stats
	@echo "${COLOR_GREEN}✅ Кэш очищен${COLOR_RESET}"

cache-cleanup-all: ## 🧹 Полная очистка кэша
	@echo "${COLOR_BLUE}⏳ Полная очистка кэша...${COLOR_RESET}"
	php bin/console app:cache-cleanup --all
	@echo "${COLOR_GREEN}✅ Кэш полностью очищен${COLOR_RESET}"

garbage-cleanup: ## 🧹 Очистка мусора (временные файлы, логи, сессии)
	@echo "${COLOR_BLUE}⏳ Очистка мусора...${COLOR_RESET}"
	php bin/console app:garbage-cleanup --all --stats --days=7
	@echo "${COLOR_GREEN}✅ Мусор очищен${COLOR_RESET}"

garbage-cleanup-dry: ## 🧹 Проверка что будет очищено (dry-run)
	@echo "${COLOR_BLUE}⏳ Проверка мусора...${COLOR_RESET}"
	php bin/console app:garbage-cleanup --all --dry-run --stats --days=7

cleanup-all: cache-cleanup garbage-cleanup ## 🧹 Полная очистка кэша и мусора
	@echo "${COLOR_GREEN}✅ Полная очистка завершена${COLOR_RESET}"

logs: ## 📋 Просмотр логов
	tail -f var/log/dev.log

logs-prod: ## 📋 Просмотр production логов
	tail -f var/log/prod.log

clean: ## 🧹 Очистить временные файлы
	@echo "${COLOR_BLUE}⏳ Очистка временных файлов...${COLOR_RESET}"
	rm -rf var/cache/*
	rm -rf var/log/*
	rm -rf public/bundles/*
	find . -name ".DS_Store" -delete
	find . -name "Thumbs.db" -delete
	@echo "${COLOR_GREEN}✅ Очистка завершена${COLOR_RESET}"

clean-vendor: ## 🧹 Очистить vendor и node_modules
	@echo "${COLOR_RED}⚠️  Очистка vendor и node_modules...${COLOR_RESET}"
	rm -rf vendor/
	rm -rf node_modules/
	@echo "${COLOR_GREEN}✅ Очистка завершена. Выполните 'make install' для переустановки${COLOR_RESET}"

# ============================================
# DOCKER
# ============================================

docker-up: ## 🐳 Запустить Docker контейнеры
	@echo "${COLOR_BLUE}⏳ Запуск Docker контейнеров...${COLOR_RESET}"
	docker compose up -d
	@echo "${COLOR_GREEN}✅ Контейнеры запущены${COLOR_RESET}"
	@echo "${COLOR_YELLOW}💡 Проверьте статус: make docker-logs${COLOR_RESET}"

docker-down: ## 🐳 Остановить Docker контейнеры
	@echo "${COLOR_BLUE}⏳ Остановка Docker контейнеров...${COLOR_RESET}"
	docker compose down
	@echo "${COLOR_GREEN}✅ Контейнеры остановлены${COLOR_RESET}"

docker-restart: ## 🐳 Перезапустить Docker контейнеры
	@echo "${COLOR_BLUE}⏳ Перезапуск Docker контейнеров...${COLOR_RESET}"
	docker compose restart
	@echo "${COLOR_GREEN}✅ Контейнеры перезапущены${COLOR_RESET}"

docker-logs: ## 🐳 Просмотр логов Docker
	docker compose logs -f

docker-ps: ## 🐳 Статус Docker контейнеров
	docker compose ps

docker-build: ## 🐳 Пересобрать Docker образы
	@echo "${COLOR_BLUE}⏳ Сборка Docker образов...${COLOR_RESET}"
	docker compose build
	@echo "${COLOR_GREEN}✅ Образы собраны${COLOR_RESET}"

# ============================================
# SERVER & DEV
# ============================================

server: ## 🚀 Запустить Symfony dev сервер
	@echo "${COLOR_GREEN}🚀 Запуск dev сервера...${COLOR_RESET}"
	@echo "${COLOR_YELLOW}💡 Откройте: http://127.0.0.1:8000${COLOR_RESET}"
	php -S 127.0.0.1:8000 -t public

server-symfony: ## 🚀 Запустить через Symfony CLI
	@echo "${COLOR_GREEN}🚀 Запуск Symfony CLI...${COLOR_RESET}"
	symfony server:start
	@echo "${COLOR_YELLOW}💡 Откройте: https://127.0.0.1:8000${COLOR_RESET}"

test-users: ## 👥 Создать тестовых пользователей
	@echo "${COLOR_BLUE}⏳ Создание тестовых пользователей...${COLOR_RESET}"
	php bin/console app:create-test-data
	@echo "${COLOR_GREEN}✅ Тестовые пользователи созданы${COLOR_RESET}"
	@echo "${COLOR_YELLOW}💡 Логин: admin@example.com / admin123${COLOR_RESET}"

setup-hooks: ## ⚙️ Настроить pre-commit hooks
	@echo "${COLOR_BLUE}⏳ Настройка pre-commit hooks...${COLOR_RESET}"
	git config core.hooksPath .githooks
	@echo "${COLOR_GREEN}✅ Pre-commit hooks настроены${COLOR_RESET}"

# ============================================
# UTILITIES
# ============================================

router: ## 🛣️ Показать все маршруты
	php bin/console debug:router

container: ## 📦 Показать сервисы контейнера
	php bin/console debug:container

config: ## ⚙️ Показать конфигурацию
	php bin/console debug:config

twig-lint: ## 🌳 Проверка Twig шаблонов
	php bin/console lint:twig templates/

translator: ## 🌐 Статус переводов
	php bin/console debug:translation

security: ## 🔒 Проверка безопасности
	composer audit

health: ## 🏥 Проверка здоровья системы
	php bin/console app:health-check

performance: ## ⚡ Отчёт о производительности
	php bin/console app:performance-monitor --action=report

backup: ## 💾 Создать резервную копию
	php bin/console app:backup

optimize: ## ⚡ Оптимизировать базу данных
	php bin/console app:optimize-database --analyze --optimize

# ============================================
# CI/CD
# ============================================

ci: check ## 🔄 Запустить CI проверки локально
	@echo "${COLOR_GREEN}✅ CI проверки пройдены${COLOR_RESET}"

ci-full: install check build ## 🔄 Полная CI сборка локально
	@echo "${COLOR_GREEN}✅ Полная CI сборка завершена${COLOR_RESET}"

# ============================================
# SHORTCUTS
# ============================================

i: install ## (alias) install
t: test ## (alias) test
b: build ## (alias) build
c: cache-clear ## (alias) cache-clear
h: help ## (alias) help
