# Design Document: Dashboard Analytics Enhancement

## Overview

Данный документ описывает дизайн расширения функциональности Dashboard Analytics для CRM системы управления задачами. Решение интегрируется с существующей архитектурой Symfony 7.x, использует DDD подход и расширяет текущие сервисы `AnalyticsService`, `AdvancedAnalyticsService` и `DashboardWidgetService`.

### Цели дизайна

1. Предоставить интерактивные виджеты для глубокого анализа продуктивности
2. Обеспечить real-time обновления данных через Mercure
3. Оптимизировать производительность через кэширование и асинхронную обработку
4. Поддержать настраиваемый layout dashboard с drag-and-drop
5. Реализовать экспорт данных в PDF/Excel
6. Обеспечить responsive дизайн для мобильных устройств

### Технологический стек

- **Backend**: Symfony 7.x (PHP 8.2+), Doctrine ORM
- **Database**: PostgreSQL с оптимизированными индексами
- **Caching**: Symfony Cache (Redis/Memcached)
- **Real-time**: Mercure Hub для push-уведомлений
- **Frontend**: Stimulus.js + Turbo + Chart.js для визуализации
- **Export**: TCPDF для PDF, PhpSpreadsheet для Excel
- **Async Processing**: Symfony Messenger для тяжелых вычислений

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Dashboard   │  │   Widgets    │  │  Chart.js    │      │
│  │  Controller  │  │  (Stimulus)  │  │  Visualizer  │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Dashboard   │  │  Analytics   │  │   Export     │      │
│  │   Service    │  │   Service    │  │   Service    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Achievement │  │  Time Track  │  │  Real-Time   │      │
│  │   Service    │  │   Service    │  │   Service    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Domain Layer                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │     Task     │  │     Goal     │  │    Habit     │      │
│  │    Entity    │  │    Entity    │  │    Entity    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Achievement  │  │  Dashboard   │  │  TimeTrack   │      │
│  │    Entity    │  │   Config     │  │    Entity    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Infrastructure Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  PostgreSQL  │  │  Redis Cache │  │   Mercure    │      │
│  │   Database   │  │              │  │     Hub      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

### Component Interaction Flow

1. **User Request** → DashboardController → DashboardService
2. **DashboardService** → проверяет Cache → если miss, запрашивает AnalyticsService
3. **AnalyticsService** → агрегирует данные из Repositories → возвращает результат
4. **DashboardService** → сохраняет в Cache → возвращает данные в Controller
5. **Controller** → рендерит Twig template с данными
6. **Frontend (Stimulus)** → инициализирует виджеты и подписывается на Mercure
7. **Real-Time Updates** → Mercure Hub → push обновления → Stimulus обновляет виджеты

## Components and Interfaces

### 1. Enhanced Dashboard Service

**Файл**: `src/Service/EnhancedDashboardService.php`

**Ответственность**: Координация всех dashboard-виджетов, управление layout, кэширование

**Интерфейс**:

```php
class EnhancedDashboardService
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private AchievementService $achievementService,
        private TimeTrackingService $timeTrackingService,
        private HabitAnalyticsService $habitAnalyticsService,
        private PriorityCalculatorService $priorityCalculator,
        private CacheInterface $cache,
        private EntityManagerInterface $em
    ) {}
    
    /**
     * Получить все данные dashboard с кэшированием
     * @return array Агрегированные данные всех виджетов
     */
    public function getDashboardData(User $user): array;
    
    /**
     * Получить конфигурацию layout пользователя
     * @return DashboardConfig
     */
    public function getUserLayout(User $user): DashboardConfig;
    
    /**
     * Сохранить конфигурацию layout
     */
    public function saveUserLayout(User $user, array $config): void;
    
    /**
     * Инвалидировать кэш dashboard для пользователя
     */
    public function invalidateCache(User $user): void;
}
```

### 2. Activity Heatmap Service

**Файл**: `src/Service/ActivityHeatmapService.php`

**Ответственность**: Генерация данных для heatmap календаря активности

**Интерфейс**:

```php
class ActivityHeatmapService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private GoalRepository $goalRepository,
        private HabitLogRepository $habitLogRepository,
        private CacheInterface $cache
    ) {}
    
    /**
     * Получить данные heatmap за период
     * @param User $user
     * @param int $days Количество дней (по умолчанию 365)
     * @return array ['date' => 'Y-m-d', 'count' => int, 'level' => 0-4]
     */
    public function getHeatmapData(User $user, int $days = 365): array;
    
    /**
     * Получить детальную информацию за конкретный день
     * @return array ['tasks' => [], 'goals' => [], 'habits' => [], 'time_spent' => float]
     */
    public function getDayDetails(User $user, \DateTime $date): array;
    
    /**
     * Вычислить уровень активности (0-4) на основе количества
     */
    private function calculateActivityLevel(int $count): int;
}
```

### 3. Achievement Service

**Файл**: `src/Service/AchievementService.php`

**Ответственность**: Управление системой достижений и бейджей

**Интерфейс**:

```php
class AchievementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private RealTimeService $realTimeService
    ) {}
    
    /**
     * Проверить и присвоить достижения пользователю
     * @return array Массив новых достижений
     */
    public function checkAndAwardAchievements(User $user): array;
    
    /**
     * Получить все достижения пользователя
     * @return array ['earned' => [], 'available' => [], 'progress' => []]
     */
    public function getUserAchievements(User $user): array;
    
    /**
     * Получить прогресс до следующего достижения
     */
    public function getNextAchievementProgress(User $user): array;
    
    /**
     * Определения всех доступных достижений
     */
    private function getAchievementDefinitions(): array;
}
```

### 4. Habit Analytics Service

**Файл**: `src/Service/HabitAnalyticsService.php`

**Ответственность**: Расширенная аналитика привычек с трендами и корреляциями

**Интерфейс**:

```php
class HabitAnalyticsService
{
    public function __construct(
        private HabitRepository $habitRepository,
        private HabitLogRepository $habitLogRepository,
        private CacheInterface $cache
    ) {}
    
    /**
     * Получить расширенную аналитику привычки
     * @return array ['completion_rate' => [], 'streaks' => [], 'trends' => [], 'predictions' => []]
     */
    public function getHabitAnalytics(Habit $habit, int $days = 90): array;
    
    /**
     * Вычислить корреляцию между привычками
     * @return array ['habit1_id' => int, 'habit2_id' => int, 'correlation' => float]
     */
    public function calculateHabitCorrelations(User $user): array;
    
    /**
     * Предсказать вероятность выполнения привычки
     * @return float Вероятность от 0 до 1
     */
    public function predictCompletionProbability(Habit $habit): float;
    
    /**
     * Получить completion rate по дням недели
     */
    public function getWeekdayCompletionRates(Habit $habit): array;
}
```

### 5. Priority Calculator Service

**Файл**: `src/Service/PriorityCalculatorService.php`

**Ответственность**: Умный расчет приоритетов задач

**Интерфейс**:

```php
class PriorityCalculatorService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private TaskDependencyService $dependencyService
    ) {}
    
    /**
     * Вычислить priority score для задачи
     * @return float Score от 0 до 100
     */
    public function calculatePriorityScore(Task $task): float;
    
    /**
     * Получить топ приоритетных задач для пользователя
     * @return array Отсортированный массив задач с scores
     */
    public function getTopPriorityTasks(User $user, int $limit = 10): array;
    
    /**
     * Группировать задачи по матрице Эйзенхауэра
     * @return array ['urgent_important' => [], 'urgent_not_important' => [], ...]
     */
    public function groupByEisenhowerMatrix(User $user): array;
    
    /**
     * Вычислить факторы приоритета
     */
    private function calculatePriorityFactors(Task $task): array;
}
```

### 6. Export Service Enhancement

**Файл**: `src/Service/EnhancedExportService.php`

**Ответственность**: Экспорт аналитических данных в PDF и Excel

**Интерфейс**:

```php
class EnhancedExportService
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private ActivityHeatmapService $heatmapService,
        private MessengerInterface $messenger,
        private string $projectDir
    ) {}
    
    /**
     * Экспортировать dashboard данные в PDF
     * @return string Путь к сгенерированному файлу
     */
    public function exportToPDF(User $user, array $options = []): string;
    
    /**
     * Экспортировать dashboard данные в Excel
     * @return string Путь к сгенерированному файлу
     */
    public function exportToExcel(User $user, array $options = []): string;
    
    /**
     * Асинхронный экспорт для больших объемов данных
     */
    public function exportAsync(User $user, string $format, array $options = []): void;
    
    /**
     * Генерация PDF с графиками
     */
    private function generatePDFWithCharts(array $data): string;
    
    /**
     * Генерация Excel с множественными листами
     */
    private function generateExcelWorkbook(array $data): string;
}
```

### 7. Real-Time Service

**Файл**: `src/Service/RealTimeService.php`

**Ответственность**: Управление real-time обновлениями через Mercure

**Интерфейс**:

```php
class RealTimeService
{
    public function __construct(
        private HubInterface $mercureHub,
        private SerializerInterface $serializer
    ) {}
    
    /**
     * Отправить обновление dashboard пользователю
     */
    public function publishDashboardUpdate(User $user, string $widgetId, array $data): void;
    
    /**
     * Отправить уведомление о новом достижении
     */
    public function publishAchievementNotification(User $user, Achievement $achievement): void;
    
    /**
     * Отправить обновление статуса задачи
     */
    public function publishTaskUpdate(Task $task): void;
    
    /**
     * Получить topic для пользователя
     */
    private function getUserTopic(User $user): string;
}
```

### 8. Dashboard Layout Manager

**Файл**: `src/Service/DashboardLayoutManager.php`

**Ответственность**: Управление конфигурацией layout dashboard

**Интерфейс**:

```php
class DashboardLayoutManager
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}
    
    /**
     * Получить layout конфигурацию пользователя
     */
    public function getUserLayout(User $user): DashboardConfig;
    
    /**
     * Сохранить layout конфигурацию
     */
    public function saveLayout(User $user, array $widgets): void;
    
    /**
     * Сбросить layout к значениям по умолчанию
     */
    public function resetToDefault(User $user): void;
    
    /**
     * Получить preset layouts
     */
    public function getPresetLayouts(): array;
    
    /**
     * Применить preset layout
     */
    public function applyPreset(User $user, string $presetName): void;
}
```

## Data Models

### New Entities

#### 1. Achievement Entity

**Файл**: `src/Entity/Achievement.php`

```php
#[ORM\Entity]
#[ORM\Table(name: 'achievements')]
class Achievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $type; // task_count, streak, goal_completion, time_tracking

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 50)]
    private string $badge; // bronze, silver, gold, platinum

    #[ORM\Column(type: Types::JSON)]
    private array $criteria; // Условия получения

    #[ORM\Column]
    private \DateTimeImmutable $earnedAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null; // Дополнительные данные
}
```

#### 2. DashboardConfig Entity

**Файл**: `src/Entity/DashboardConfig.php`

```php
#[ORM\Entity]
#[ORM\Table(name: 'dashboard_configs')]
class DashboardConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::JSON)]
    private array $widgets = []; // [{id, position, size, visible}]

    #[ORM\Column(length: 50)]
    private string $layout = 'default'; // default, minimal, detailed

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
}
```

### Extended Entities

Существующие сущности `Task`, `Goal`, `Habit`, `TaskTimeTracking` используются без изменений. Добавляются только индексы для оптимизации:

```sql
-- Индексы для Task
CREATE INDEX idx_task_completed_at ON tasks(completed_at);
CREATE INDEX idx_task_user_created ON tasks(user_id, created_at);

-- Индексы для HabitLog
CREATE INDEX idx_habit_log_date ON habit_logs(habit_id, date);

-- Индексы для TaskTimeTracking
CREATE INDEX idx_time_tracking_user_date ON task_time_trackings(user_id, start_time);
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property 1: Heatmap Data Completeness

*For any* user and requested period in days, the heatmap data should contain entries for each day in the period (or fewer if user is new), with each entry having date, count, and level fields.

**Validates: Requirements 1.1**

### Property 2: Day Details Completeness

*For any* user and any date, the day details should contain all required fields: tasks array, goals array, habits array, and time_spent numeric value.

**Validates: Requirements 1.2, 1.3**

### Property 3: Activity Level Mapping

*For any* task count value, the calculated activity level should be in range 0-4, where 0 maps to count=0, and higher counts map to higher levels with 10+ mapping to level 4.

**Validates: Requirements 1.4**

### Property 4: Heatmap Grouping Structure

*For any* heatmap data array, the data should be groupable by week and month without data loss, maintaining chronological order.

**Validates: Requirements 1.5**

### Property 5: Time Tracking Round-Trip

*For any* task and user, starting a timer should create a TaskTimeTracking record with start_time, and stopping it should update the same record with end_time, preserving the start_time value.

**Validates: Requirements 2.1, 2.2**

### Property 6: Time Aggregation Correctness

*For any* user and time period (day, week, month), the total time spent should equal the sum of all TaskTimeTracking durations within that period.

**Validates: Requirements 2.3**

### Property 7: Top Tasks Limit and Ordering

*For any* user, the top tasks by time spent should return at most 5 tasks, sorted in descending order by time spent.

**Validates: Requirements 2.4**

### Property 8: Time Grouping Completeness

*For any* user, time statistics grouped by category and priority should include all categories and priorities that have tracked time, with correct sums for each group.

**Validates: Requirements 2.5**

### Property 9: Average Completion Time Calculation

*For any* set of completed tasks, the average completion time should equal the sum of individual completion times divided by the count of tasks.

**Validates: Requirements 2.6**

### Property 10: Achievement Award Trigger

*For any* user who meets achievement criteria (e.g., 10 tasks completed in a day), the system should create an Achievement entity with the correct type, name, and earnedAt timestamp.

**Validates: Requirements 3.1**

### Property 11: Achievement Data Completeness

*For any* user, the achievements data should contain three arrays: earned achievements, available achievements, and progress data, with each achievement having all required fields (name, description, badge, criteria).

**Validates: Requirements 3.2, 3.5**

### Property 12: Achievement Notification Creation

*For any* newly created Achievement, the system should create a corresponding Notification entity for the user.

**Validates: Requirements 3.4**

### Property 13: Habit Analytics Completeness

*For any* habit and time period, the analytics should contain completion_rate array, current and longest streaks, weekday completion rates, trend data, and period comparison data.

**Validates: Requirements 4.1, 4.4, 4.6**

### Property 14: Streak Calculation Correctness

*For any* habit with logs, the current streak should count consecutive days from today backwards, and longest streak should be the maximum consecutive days in history.

**Validates: Requirements 4.2**

### Property 15: Correlation Coefficient Range

*For any* pair of habits, the calculated correlation coefficient should be a float value between -1.0 and 1.0.

**Validates: Requirements 4.3**

### Property 16: Completion Probability Range

*For any* habit, the predicted completion probability should be a float value between 0.0 and 1.0.

**Validates: Requirements 4.5**

### Property 17: Priority Score Factors

*For any* task, the priority score should be calculated considering due_date proximity, priority level, dependency status, and estimated time, resulting in a score between 0 and 100.

**Validates: Requirements 5.1, 5.3, 5.5**

### Property 18: Top Priority Tasks Limit and Ordering

*For any* user, the top priority tasks should return at most 10 tasks, sorted in descending order by priority score.

**Validates: Requirements 5.2**

### Property 19: Eisenhower Matrix Grouping

*For any* user's tasks, the Eisenhower matrix grouping should produce exactly 4 groups (urgent_important, urgent_not_important, not_urgent_important, not_urgent_not_important), with each task appearing in exactly one group.

**Validates: Requirements 5.4**

### Property 20: PDF Export Validity

*For any* user and export options, the generated PDF file should be a valid PDF format containing all requested sections (task stats, time analytics, habits data, goals progress, heatmap data).

**Validates: Requirements 6.2, 6.4**

### Property 21: Excel Export Validity

*For any* user and export options, the generated Excel file should be a valid XLSX format containing all requested sections in separate sheets.

**Validates: Requirements 6.3, 6.4**

### Property 22: Export Filter Application

*For any* user with applied filters (date range, categories, priorities), the exported data should contain only items matching all applied filters.

**Validates: Requirements 6.6**

### Property 23: Layout Persistence

*For any* user and layout configuration, saving the layout should create or update a DashboardConfig entity, and retrieving it should return the same configuration.

**Validates: Requirements 7.3, 7.7**

### Property 24: Real-Time Task Update Publication

*For any* task status, priority, or time change, the system should publish a Mercure update to the task owner's and assigned user's dashboard topics.

**Validates: Requirements 8.1**

### Property 25: Achievement Notification Publication

*For any* newly created Achievement, the system should publish a Mercure notification to the user's topic with achievement details.

**Validates: Requirements 8.3**

### Property 26: Selective Cache Invalidation

*For any* task modification, only the cache keys related to that task's user and affected widgets should be invalidated, not the entire cache.

**Validates: Requirements 9.7**

## Error Handling

### Error Scenarios and Handling Strategy

#### 1. Data Retrieval Errors

**Scenario**: Database query fails or returns unexpected data

**Handling**:
- Catch `Doctrine\DBAL\Exception` and log error details
- Return empty arrays with default values instead of null
- Display user-friendly error message in widget
- Retry query once with exponential backoff

**Example**:
```php
try {
    $data = $this->repository->getHeatmapData($user, $days);
} catch (DBALException $e) {
    $this->logger->error('Heatmap data retrieval failed', [
        'user_id' => $user->getId(),
        'error' => $e->getMessage()
    ]);
    return ['data' => [], 'error' => 'Unable to load heatmap data'];
}
```

#### 2. Cache Failures

**Scenario**: Redis/Memcached unavailable or cache corruption

**Handling**:
- Catch `Psr\Cache\InvalidArgumentException` and `Psr\Cache\CacheException`
- Fall back to direct database queries
- Log cache failure for monitoring
- Continue operation without caching

**Example**:
```php
try {
    $cachedData = $this->cache->get($cacheKey);
} catch (CacheException $e) {
    $this->logger->warning('Cache unavailable, falling back to DB', [
        'key' => $cacheKey,
        'error' => $e->getMessage()
    ]);
    $cachedData = null; // Will trigger DB query
}
```

#### 3. Export Generation Failures

**Scenario**: PDF/Excel generation fails due to memory limits or invalid data

**Handling**:
- Catch `Exception` during export generation
- Send error notification to user
- Log detailed error with stack trace
- Offer alternative: smaller date range or simplified export

**Example**:
```php
try {
    $pdfPath = $this->generatePDFWithCharts($data);
} catch (\Exception $e) {
    $this->logger->error('PDF generation failed', [
        'user_id' => $user->getId(),
        'data_size' => count($data),
        'error' => $e->getMessage()
    ]);
    $this->notificationService->notify($user, 
        'Export failed. Try reducing the date range.');
    throw new ExportException('PDF generation failed', 0, $e);
}
```

#### 4. Real-Time Service Failures

**Scenario**: Mercure Hub unavailable or publish fails

**Handling**:
- Catch `Symfony\Component\Mercure\Exception\RuntimeException`
- Log failure but don't block main operation
- Queue retry with Messenger
- Graceful degradation: system works without real-time updates

**Example**:
```php
try {
    $this->mercureHub->publish($update);
} catch (RuntimeException $e) {
    $this->logger->warning('Mercure publish failed, queuing retry', [
        'topic' => $update->getTopics(),
        'error' => $e->getMessage()
    ]);
    $this->messenger->dispatch(new RetryMercurePublish($update));
}
```

#### 5. Achievement Calculation Errors

**Scenario**: Invalid criteria or calculation logic error

**Handling**:
- Validate achievement criteria before processing
- Catch arithmetic errors (division by zero, etc.)
- Skip invalid achievements and log warning
- Continue processing other achievements

**Example**:
```php
foreach ($definitions as $definition) {
    try {
        if ($this->meetsAchievementCriteria($user, $definition)) {
            $this->awardAchievement($user, $definition);
        }
    } catch (\DivisionByZeroError | \ArithmeticError $e) {
        $this->logger->warning('Achievement calculation error', [
            'achievement' => $definition['name'],
            'error' => $e->getMessage()
        ]);
        continue; // Skip this achievement
    }
}
```

#### 6. Async Processing Failures

**Scenario**: Messenger handler fails during async export or calculation

**Handling**:
- Configure retry strategy in Messenger (3 retries with exponential backoff)
- Send failure notification after final retry
- Store failed job details for manual review
- Implement dead letter queue for persistent failures

**Example in messenger.yaml**:
```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
```

#### 7. Invalid User Input

**Scenario**: User provides invalid date range, negative values, or malformed data

**Handling**:
- Validate input with Symfony Validator
- Return `400 Bad Request` with clear error messages
- Sanitize input before processing
- Use default values for optional parameters

**Example**:
```php
$violations = $this->validator->validate($exportRequest);
if (count($violations) > 0) {
    $errors = [];
    foreach ($violations as $violation) {
        $errors[$violation->getPropertyPath()] = $violation->getMessage();
    }
    return new JsonResponse(['errors' => $errors], 400);
}
```

### Error Response Format

All API endpoints return consistent error format:

```json
{
    "success": false,
    "error": {
        "code": "EXPORT_FAILED",
        "message": "Unable to generate PDF export",
        "details": "Memory limit exceeded. Try reducing date range.",
        "timestamp": "2026-02-20T10:30:00Z"
    }
}
```

## Testing Strategy

### Dual Testing Approach

The testing strategy combines unit tests for specific scenarios and property-based tests for comprehensive coverage:

**Unit Tests**: Focus on specific examples, edge cases, error conditions, and integration points
**Property Tests**: Verify universal properties across randomized inputs using PHPUnit with data providers

### Unit Testing Focus Areas

1. **Specific Examples**:
   - Heatmap with exactly 365 days of data
   - Achievement awarded for exactly 10 completed tasks
   - Export with specific date range and filters

2. **Edge Cases**:
   - New user with no data (empty heatmap)
   - Habit with zero logs (streak = 0)
   - Task with no time tracking data
   - Export with very large dataset (memory limits)

3. **Error Conditions**:
   - Database connection failure
   - Cache unavailable
   - Invalid user input
   - Mercure Hub offline

4. **Integration Points**:
   - DashboardService → AnalyticsService interaction
   - ExportService → Messenger queue
   - RealTimeService → Mercure Hub

### Property-Based Testing Configuration

**Library**: PHPUnit with custom data providers for randomization

**Configuration**:
- Minimum 100 iterations per property test
- Each test references its design document property
- Tag format: `@group Feature:dashboard-analytics-enhancement,Property{N}`

**Example Property Test**:

```php
/**
 * @test
 * @group Feature:dashboard-analytics-enhancement,Property1
 * Property 1: Heatmap Data Completeness
 * For any user and requested period in days, the heatmap data should contain 
 * entries for each day in the period (or fewer if user is new)
 */
public function testHeatmapDataCompleteness(): void
{
    for ($i = 0; $i < 100; $i++) {
        $user = $this->createRandomUser();
        $days = rand(1, 365);
        $this->createRandomTasksForUser($user, rand(0, 100));
        
        $heatmapData = $this->heatmapService->getHeatmapData($user, $days);
        
        $this->assertIsArray($heatmapData);
        $this->assertLessThanOrEqual($days, count($heatmapData));
        
        foreach ($heatmapData as $entry) {
            $this->assertArrayHasKey('date', $entry);
            $this->assertArrayHasKey('count', $entry);
            $this->assertArrayHasKey('level', $entry);
            $this->assertIsInt($entry['count']);
            $this->assertGreaterThanOrEqual(0, $entry['level']);
            $this->assertLessThanOrEqual(4, $entry['level']);
        }
    }
}
```

### Test Coverage Goals

- **Unit Test Coverage**: Minimum 80% code coverage
- **Property Test Coverage**: All 26 correctness properties implemented
- **Integration Test Coverage**: All service interactions tested
- **E2E Test Coverage**: Critical user flows (dashboard load, export, layout save)

### Performance Testing

**Load Testing**:
- Dashboard load with 1000+ tasks: < 500ms response time
- Heatmap generation for 365 days: < 200ms
- Export generation for 1 year data: < 5 seconds (async)

**Stress Testing**:
- Concurrent dashboard requests: 100 users simultaneously
- Cache invalidation under load: no race conditions
- Mercure publish rate: 1000 updates/second

### Testing Tools

- **PHPUnit**: Unit and property tests
- **Symfony Profiler**: Performance profiling
- **Blackfire**: Performance optimization
- **PHPStan**: Static analysis (level 8)
- **PHP CS Fixer**: Code style enforcement

## Implementation Notes

### Database Migrations

New tables and indexes will be created via Doctrine migrations:

```php
// Migration for Achievement entity
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE achievements (
        id INT AUTO_INCREMENT NOT NULL,
        user_id INT NOT NULL,
        type VARCHAR(100) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        badge VARCHAR(50) NOT NULL,
        criteria JSON NOT NULL,
        earned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
        metadata JSON DEFAULT NULL,
        INDEX IDX_1F6A3A4FA76ED395 (user_id),
        INDEX idx_achievement_type (type),
        INDEX idx_achievement_earned (earned_at),
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
}

// Migration for DashboardConfig entity
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE dashboard_configs (
        id INT AUTO_INCREMENT NOT NULL,
        user_id INT NOT NULL,
        widgets JSON NOT NULL,
        layout VARCHAR(50) NOT NULL,
        updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
        UNIQUE INDEX UNIQ_dashboard_user (user_id),
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
}

// Migration for performance indexes
public function up(Schema $schema): void
{
    $this->addSql('CREATE INDEX idx_task_completed_at ON tasks(completed_at)');
    $this->addSql('CREATE INDEX idx_task_user_created ON tasks(user_id, created_at)');
    $this->addSql('CREATE INDEX idx_habit_log_date ON habit_logs(habit_id, date)');
    $this->addSql('CREATE INDEX idx_time_tracking_user_date ON task_time_trackings(user_id, start_time)');
}
```

### Caching Strategy

**Cache Keys Pattern**:
```
dashboard:{user_id}:heatmap:{days}
dashboard:{user_id}:analytics:overview
dashboard:{user_id}:achievements
dashboard:{user_id}:habits:{habit_id}:analytics
dashboard:{user_id}:priority_tasks
```

**TTL Configuration**:
- Heatmap data: 5 minutes (300s)
- Analytics overview: 5 minutes (300s)
- Achievements: 10 minutes (600s)
- Habit analytics: 5 minutes (300s)
- Priority tasks: 1 minute (60s) - more dynamic

**Invalidation Strategy**:
- Task created/updated → invalidate user's analytics, priority, heatmap
- Goal completed → invalidate user's achievements, analytics
- Habit logged → invalidate user's habits analytics, heatmap
- Time tracking stopped → invalidate user's time analytics

### Mercure Configuration

**Topics Structure**:
```
/dashboard/{user_id}/updates
/dashboard/{user_id}/achievements
/dashboard/{user_id}/tasks
```

**Update Format**:
```json
{
    "type": "widget_update",
    "widget_id": "activity_heatmap",
    "data": { ... },
    "timestamp": "2026-02-20T10:30:00Z"
}
```

### Frontend Integration

**Stimulus Controllers**:
- `dashboard_controller.js`: Main dashboard orchestration
- `heatmap_controller.js`: Activity heatmap visualization
- `chart_controller.js`: Chart.js wrapper for analytics charts
- `draggable_controller.js`: Drag-and-drop layout management
- `mercure_controller.js`: Real-time updates subscription

**Chart.js Configuration**:
- Line charts for productivity trends
- Bar charts for category/priority distribution
- Heatmap using custom Chart.js plugin
- Responsive breakpoints for mobile

### Security Considerations

1. **Authorization**: All dashboard endpoints check user ownership via Voters
2. **Rate Limiting**: Export endpoints limited to 10 requests/hour per user
3. **Input Validation**: All user input validated with Symfony Validator
4. **SQL Injection**: Doctrine ORM with parameterized queries
5. **XSS Protection**: Twig auto-escaping enabled
6. **CSRF Protection**: Forms protected with CSRF tokens

### Performance Optimizations

1. **Eager Loading**: Use JOIN FETCH for related entities
2. **Query Result Caching**: Cache expensive aggregation queries
3. **Partial Objects**: Use Doctrine partial objects for large datasets
4. **Async Processing**: Heavy calculations via Messenger
5. **CDN**: Static assets (Chart.js, CSS) served from CDN
6. **HTTP/2**: Enable HTTP/2 for multiplexing
7. **Gzip Compression**: Enable response compression

### Monitoring and Observability

**Metrics to Track**:
- Dashboard load time (p50, p95, p99)
- Cache hit rate
- Export generation time
- Mercure publish success rate
- Database query count per request
- Memory usage during export

**Logging**:
- Error logs: All exceptions with context
- Performance logs: Slow queries (> 100ms)
- Audit logs: Layout changes, exports
- Real-time logs: Mercure publish failures

**Alerts**:
- Dashboard load time > 1s
- Cache hit rate < 80%
- Export failures > 5% of requests
- Mercure Hub unavailable
