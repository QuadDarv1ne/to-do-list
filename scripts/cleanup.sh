#!/bin/bash
# ============================================
# Скрипт автоматической очистки кеша и мусора
# Для cron
# ============================================

set -e

# Настройки
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="php"
LOG_FILE="${PROJECT_DIR}/var/log/cleanup.log"
DAYS_TO_KEEP=7

# Создаем директорию для логов если не существует
mkdir -p "${PROJECT_DIR}/var/log"

echo "============================================" >> "${LOG_FILE}"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Начало очистки" >> "${LOG_FILE}"
echo "============================================" >> "${LOG_FILE}"

# Переходим в директорию проекта
cd "${PROJECT_DIR}"

# Очищаем кэш
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Очистка кэша..." >> "${LOG_FILE}"
${PHP_BIN} bin/console app:cache-cleanup --expired --days=${DAYS_TO_KEEP} >> "${LOG_FILE}" 2>&1
if [ $? -ne 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ОШИБКА: Очистка кэша не удалась" >> "${LOG_FILE}"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Очистка кэша завершена успешно" >> "${LOG_FILE}"
fi

# Очищаем мусор
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Очистка мусора..." >> "${LOG_FILE}"
${PHP_BIN} bin/console app:garbage-cleanup --all --days=${DAYS_TO_KEEP} >> "${LOG_FILE}" 2>&1
if [ $? -ne 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ОШИБКА: Очистка мусора не удалась" >> "${LOG_FILE}"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Очистка мусора завершена успешно" >> "${LOG_FILE}"
fi

# Очищаем кэш Symfony стандартными средствами
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Очистка кэша Symfony..." >> "${LOG_FILE}"
${PHP_BIN} bin/console cache:clear --no-warmup >> "${LOG_FILE}" 2>&1 || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Все задачи очистки завершены" >> "${LOG_FILE}"
echo "============================================" >> "${LOG_FILE}"

echo "Очистка завершена! Лог: ${LOG_FILE}"
