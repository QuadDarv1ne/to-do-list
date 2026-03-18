# 📚 API Документация (Swagger/OpenAPI)

## Обзор

Проект использует **NelmioApiDocBundle** для генерации интерактивной API документации в формате Swagger/OpenAPI.

## 🚀 Доступ к документации

После установки и настройки документация доступна по следующим адресам:

| Окружение | URL |
|-----------|-----|
| **Development** | http://localhost:8000/api/doc |
| **Production** | https://your-domain.com/api/doc |

## 📦 Установка

Установка уже выполнена:

```bash
composer require nelmio/api-doc-bundle
```

## 🔧 Конфигурация

### Основная конфигурация

**Файл:** `config/packages/nelmio_api_doc.yaml`

```yaml
nelmio_api_doc:
    documentation:
        info:
            title: CRM Task Management API
            description: API для системы управления задачами и CRM
            version: 3.2.0
        servers:
            - url: http://localhost:8000
            - url: https://api.example.com
        components:
            securitySchemes:
                Bearer:
                    type: http
                    scheme: bearer
                    bearerFormat: JWT
        security:
            - Bearer: []
        tags:
            - name: Tasks
            - name: Users
            - name: Deals
            - name: Clients
            - name: Analytics
            - name: Integrations
            - name: Notifications
    areas:
        path_patterns:
            - ^/api(?!/doc$)
```

### Маршруты

**Файл:** `config/routes/nelmio_api_doc.yaml`

```yaml
app.swagger_ui:
    path: /api/doc
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger_ui }

app.swagger:
    path: /api/doc.json
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger }
```

## 📝 Использование OpenAPI атрибутов

### Пример документирования контроллера

```php
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
#[OA\Tag(name: 'Tasks')]
class TaskApiController extends AbstractController
{
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Получить список задач',
        description: 'Возвращает список задач с фильтрацией и пагинацией',
        tags: ['Tasks'],
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Номер страницы',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Количество элементов',
        schema: new OA\Schema(type: 'integer', default: 20),
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешный ответ',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array'),
                new OA\Property(property: 'meta', type: 'object'),
            ],
        ),
    )]
    public function list(Request $request): JsonResponse
    {
        // ...
    }
}
```

## 🏷️ Поддерживаемые теги

| Тег | Описание |
|-----|----------|
| **Tasks** | Операции с задачами |
| **Users** | Управление пользователями |
| **Deals** | CRM сделки |
| **Clients** | CRM клиенты |
| **Analytics** | Аналитика и отчёты |
| **Integrations** | Внешние интеграции |
| **Notifications** | Уведомления |
| **Audit Log** | Журнал аудита |

## 🔐 Аутентификация

API использует **Bearer Token** аутентификацию:

```
Authorization: Bearer <your-token>
```

## 📊 API Endpoints

### Tasks API

| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/tasks` | Список задач |
| GET | `/api/tasks/{id}` | Получить задачу |
| POST | `/api/tasks` | Создать задачу |
| PUT | `/api/tasks/{id}` | Обновить задачу |
| PATCH | `/api/tasks/{id}/toggle` | Переключить статус |
| DELETE | `/api/tasks/{id}` | Удалить задачу |
| GET | `/api/tasks/statistics` | Статистика задач |
| POST | `/api/tasks/bulk-update` | Массовое обновление |

### Audit Log API

| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/admin/audit` | Страница журнала |
| GET | `/admin/audit/api` | JSON список записей |
| GET | `/admin/audit/{id}` | Детали записи |
| GET | `/admin/audit/statistics` | Статистика аудита |

## 🧪 Тестирование API

### Через Swagger UI

1. Откройте http://localhost:8000/api/doc
2. Выберите нужный endpoint
3. Нажмите "Try it out"
4. Заполните параметры
5. Нажмите "Execute"

### Через curl

```bash
# Получить список задач
curl -X GET "http://localhost:8000/api/tasks" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Создать задачу
curl -X POST "http://localhost:8000/api/tasks" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Новая задача","priority":"high"}'
```

## 📈 Мониторинг использования API

Audit Log автоматически логирует все API запросы:

- Кто сделал запрос (пользователь)
- IP адрес и User Agent
- Какая сущность изменялась
- Какие данные изменились

## 🔧 Полезные команды

```bash
# Очистить кэш документации
php bin/console cache:clear

# Проверить маршруты
php bin/console debug:router | grep swagger

# Экспорт OpenAPI спецификации
curl http://localhost:8000/api/doc.json > openapi.json
```

## 📚 Дополнительные ресурсы

- [NelmioApiDocBundle документация](https://github.com/nelmio/NelmioApiDocBundle)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

---

**Версия документации:** 1.0  
**Последнее обновление:** 18 марта 2026 г.
