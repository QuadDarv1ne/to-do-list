# 📋 Audit Log - Журнал аудита действий

## Обзор

**Audit Log** — система для отслеживания и логирования всех критических действий пользователей в системе.

## 🔧 Компоненты системы

### 1. Сущность `AuditLog`

**Файл:** `src/Entity/AuditLog.php`

Хранит информацию о действиях:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | ID записи |
| `entityClass` | string | Класс сущности (App\Entity\Task) |
| `entityId` | string | ID сущности |
| `action` | string | Действие (create, update, delete, login, etc.) |
| `changes` | array | JSON с изменениями |
| `oldValues` | array | Старые значения |
| `newValues` | array | Новые значения |
| `user` | User | Пользователь |
| `userName` | string | Имя пользователя |
| `userEmail` | string | Email пользователя |
| `ipAddress` | string | IP адрес |
| `userAgent` | string | User Agent |
| `reason` | string | Причина (для админ действий) |
| `createdAt` | datetime | Дата и время |

### 2. Сервис `AuditLogService`

**Файл:** `src/Service/AuditLogService.php`

Основные методы:

```php
// Базовое логирование
$auditLogService->log(
    entityClass: 'App\Entity\Task',
    entityId: 123,
    action: 'update',
    changes: ['old' => [...], 'new' => [...]],
    reason: 'Изменение приоритета'
);

// Логирование создания
$auditLogService->logCreate($task, ['title' => 'Задача']);

// Логирование обновления
$auditLogService->logUpdate($task, $oldValues, $newValues, $reason);

// Логирование удаления
$auditLogService->logDelete($task, $oldValues);

// Логирование входа
$auditLogService->logLogin($user);

// Логирование экспорта
$auditLogService->logExport('tasks_csv', 100);

// Логирование изменения настроек
$auditLogService->logSettingsChange('timezone', 'UTC', 'Europe/Moscow');

// Логирование изменения прав
$auditLogService->logPermissionChange($user, 'ROLE_USER', 'ROLE_MANAGER');
```

### 3. EventListener `AuditLogListener`

**Файл:** `src/EventListener/AuditLogListener.php`

Автоматически логирует изменения сущностей через Doctrine события:

- `postPersist` — создание сущности
- `postUpdate` — обновление сущности
- `postRemove` — удаление сущности

**Важно:** Сущность `AuditLog` исключена из логирования для предотвращения бесконечного цикла.

### 4. Контроллер `AuditLogController`

**Файл:** `src/Controller/AuditLogController.php`

Маршруты:

| Маршрут | Метод | Описание |
|---------|-------|----------|
| `/admin/audit` | GET | Страница журнала (UI) |
| `/admin/audit/api` | GET | JSON API для журнала |
| `/admin/audit/{id}` | GET | Детали записи |
| `/admin/audit/statistics` | GET | Статистика аудита |

**Требуется роль:** `ROLE_ADMIN`

## 🎯 Что логируется автоматически

### Через EventListener

| Сущность | События |
|----------|---------|
| Task | create, update, delete |
| User | create, update, delete |
| Deal | create, update, delete |
| Client | create, update, delete |
| Product | create, update, delete |
| ... | ... |

### Через явные вызовы сервиса

| Событие | Метод | Пример |
|---------|-------|--------|
| Вход пользователя | `logLogin()` | Аутентификация |
| Выход пользователя | `logLogout()` | Завершение сессии |
| Экспорт данных | `logExport()` | Выгрузка в CSV/Excel |
| Изменение настроек | `logSettingsChange()` | Смена пароля |
| Изменение прав | `logPermissionChange()` | Назначение роли |

## 📊 Просмотр журнала

### Веб-интерфейс

1. Откройте `/admin/audit`
2. Используйте фильтры:
   - Действие (create, update, delete, login...)
   - Сущность (Task, User, Deal...)
   - Дата range
   - Пользователь

### API

```bash
# Получить последние записи
GET /admin/audit/api?page=1&limit=50

# Фильтр по действию
GET /admin/audit/api?action=update

# Фильтр по сущности
GET /admin/audit/api?entity=Task

# Статистика
GET /admin/audit/statistics
```

### Пример ответа API

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "entityClass": "App\\Entity\\Task",
      "entityId": "123",
      "action": "update",
      "changes": {
        "old": {"priority": "medium"},
        "new": {"priority": "high"}
      },
      "user": {"id": 1, "email": "admin@example.com"},
      "ipAddress": "127.0.0.1",
      "createdAt": "2026-03-18T10:30:00+00:00"
    }
  ],
  "meta": {
    "total": 150,
    "page": 1,
    "limit": 50,
    "pages": 3
  }
}
```

## 🔍 Статистика

Endpoint `/admin/audit/statistics` возвращает:

```json
{
  "success": true,
  "data": {
    "by_action": [
      {"action": "update", "count": 85},
      {"action": "create", "count": 45},
      {"action": "delete", "count": 20}
    ],
    "by_entity": [
      {"entity": "App\\Entity\\Task", "count": 100},
      {"entity": "App\\Entity\\User", "count": 50}
    ],
    "daily_activity": [
      {"date": "2026-03-18", "count": 25},
      {"date": "2026-03-17", "count": 30}
    ],
    "top_users": [
      {"email": "admin@example.com", "count": 75},
      {"email": "manager@example.com", "count": 50}
    ]
  }
}
```

## 🛠️ Использование в коде

### Пример 1: Логирование в сервисе

```php
namespace App\Service;

use App\Service\AuditLogService;

class TaskService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    public function changePriority(Task $task, string $newPriority): void
    {
        $oldPriority = $task->getPriority();
        $task->setPriority($newPriority);
        
        $this->auditLog->logUpdate(
            $task,
            ['priority' => $oldPriority],
            ['priority' => $newPriority],
            sprintf('Изменение приоритета: %s → %s', $oldPriority, $newPriority)
        );
    }
}
```

### Пример 2: Логирование в контроллере

```php
#[Route('/export', name: 'tasks_export')]
public function export(): Response
{
    $tasks = $this->taskRepo->findAll();
    
    // Экспорт...
    
    // Логирование экспорта
    $this->auditLog->logExport('tasks_csv', count($tasks));
    
    return $this->exportFile;
}
```

### Пример 3: Логирование изменения настроек

```php
#[Route('/settings/password', name: 'change_password')]
public function changePassword(): Response
{
    $oldHash = $user->getPassword();
    $user->setPassword($newHash);
    
    $this->auditLog->logSettingsChange(
        'password',
        substr($oldHash, 0, 10) . '...',
        substr($newHash, 0, 10) . '...'
    );
}
```

## 🔐 Безопасность

### Кто имеет доступ

| Роль | Просмотр | Экспорт |
|------|----------|---------|
| `ROLE_ADMIN` | ✅ | ✅ |
| `ROLE_MANAGER` | ❌ | ❌ |
| `ROLE_USER` | ❌ | ❌ |

### Что защищено

- ✅ Только HTTPS в production
- ✅ Rate limiting на API
- ✅ Валидация входных данных
- ✅ SQL injection защита (Doctrine)
- ✅ XSS защита (Twig autoescape)

## 📈 Производительность

### Индексы в БД

```sql
CREATE INDEX idx_audit_user ON audit_logs (user_id);
CREATE INDEX idx_audit_entity ON audit_logs (entity_class, entity_id);
CREATE INDEX idx_audit_action ON audit_logs (action);
CREATE INDEX idx_audit_created ON audit_logs (created_at);
```

### Рекомендации

1. **Асинхронная запись:** Для high-load систем используйте Messenger
2. **Очистка старых записей:** Настройте cron для удаления записей старше 90 дней
3. **Партиционирование:** Для больших объёмов рассмотрите партиционирование по датам

## 🧹 Обслуживание

### Очистка старых записей

```bash
# Создать команду для очистки
php bin/console app:cleanup-audit-log --days=90
```

### Резервное копирование

```bash
# Экспорт в CSV
php bin/console app:export-audit-log --format=csv --output=audit_backup.csv
```

## 🔮 Планы развития

- [ ] Добавить вебхуки для уведомлений о критических действиях
- [ ] Экспорт в SIEM системы (Splunk, ELK)
- [ ] Графики активности в реальном времени
- [ ] Поиск по содержимому изменений
- [ ] Сравнение версий сущностей

## 📚 Связанная документация

- [API Документация](API_DOCUMENTATION.md)
- [Безопасность](SECURITY.md)
- [Производительность](PERFORMANCE_OPTIMIZATION.md)

---

**Версия:** 1.0  
**Дата:** 18 марта 2026 г.
