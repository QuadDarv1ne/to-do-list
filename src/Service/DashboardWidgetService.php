<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\TaskRepository;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class DashboardWidgetService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private UserPreferenceRepository $preferenceRepository,
        private ?CacheItemPoolInterface $cache = null,
    ) {
    }

    /**
     * Get all available widgets
     */
    public function getAvailableWidgets(): array
    {
        return [
            'task_stats' => [
                'name' => 'Статистика задач',
                'description' => 'Общая статистика по задачам',
                'icon' => 'fa-tasks',
                'size' => 'col-md-6',
            ],
            'recent_tasks' => [
                'name' => 'Последние задачи',
                'description' => 'Недавно созданные задачи',
                'icon' => 'fa-clock',
                'size' => 'col-md-6',
            ],
            'overdue_tasks' => [
                'name' => 'Просроченные задачи',
                'description' => 'Задачи с истекшим дедлайном',
                'icon' => 'fa-exclamation-triangle',
                'size' => 'col-md-6',
            ],
            'upcoming_deadlines' => [
                'name' => 'Ближайшие дедлайны',
                'description' => 'Задачи на ближайшую неделю',
                'icon' => 'fa-calendar-alt',
                'size' => 'col-md-6',
            ],
            'productivity_chart' => [
                'name' => 'График продуктивности',
                'description' => 'Динамика выполнения задач',
                'icon' => 'fa-chart-line',
                'size' => 'col-md-12',
            ],
            'priority_distribution' => [
                'name' => 'Распределение по приоритетам',
                'description' => 'Задачи по уровням приоритета',
                'icon' => 'fa-chart-pie',
                'size' => 'col-md-6',
            ],
            'category_breakdown' => [
                'name' => 'Задачи по категориям',
                'description' => 'Распределение по категориям',
                'icon' => 'fa-folder',
                'size' => 'col-md-6',
            ],
            'team_activity' => [
                'name' => 'Активность команды',
                'description' => 'Последние действия команды',
                'icon' => 'fa-users',
                'size' => 'col-md-12',
            ],
            'quick_actions' => [
                'name' => 'Быстрые действия',
                'description' => 'Часто используемые действия',
                'icon' => 'fa-bolt',
                'size' => 'col-md-6',
            ],
            'notifications_widget' => [
                'name' => 'Уведомления',
                'description' => 'Последние уведомления',
                'icon' => 'fa-bell',
                'size' => 'col-md-6',
            ],
            'time_tracking' => [
                'name' => 'Трекер времени',
                'description' => 'Учет рабочего времени',
                'icon' => 'fa-stopwatch',
                'size' => 'col-md-6',
            ],
            'goals_progress' => [
                'name' => 'Прогресс по целям',
                'description' => 'Достижение ключевых целей',
                'icon' => 'fa-bullseye',
                'size' => 'col-md-6',
            ],
            'workload_distribution' => [
                'name' => 'Распределение нагрузки',
                'description' => 'Загрузка по дням недели',
                'icon' => 'fa-chart-bar',
                'size' => 'col-md-12',
            ],
            'skill_development' => [
                'name' => 'Развитие навыков',
                'description' => 'Прогресс в обучении',
                'icon' => 'fa-graduation-cap',
                'size' => 'col-md-6',
            ],
            'focus_time' => [
                'name' => 'Время фокусировки',
                'description' => 'Периоды максимальной концентрации',
                'icon' => 'fa-brain',
                'size' => 'col-md-6',
            ],
        ];
    }

    /**
     * Get widget data
     */
    public function getWidgetData(string $widgetId, User $user): array
    {
        return match($widgetId) {
            'task_stats' => $this->getTaskStatsData($user),
            'recent_tasks' => $this->getRecentTasksData($user),
            'overdue_tasks' => $this->getOverdueTasksData($user),
            'upcoming_deadlines' => $this->getUpcomingDeadlinesData($user),
            'productivity_chart' => $this->getProductivityChartData($user),
            'priority_distribution' => $this->getPriorityDistributionData($user),
            'category_breakdown' => $this->getCategoryBreakdownData($user),
            'team_activity' => $this->getTeamActivityData($user),
            'quick_actions' => $this->getQuickActionsData($user),
            'notifications_widget' => $this->getNotificationsData($user),
            'time_tracking' => $this->getTimeTrackingData($user),
            'goals_progress' => $this->getGoalsProgressData($user),
            'workload_distribution' => $this->getWorkloadDistributionData($user),
            'skill_development' => $this->getSkillDevelopmentData($user),
            'focus_time' => $this->getFocusTimeData($user),
            default => []
        };
    }

    /**
     * Get user's widget configuration
     */
    public function getUserWidgets(User $user): array
    {
        $preference = $this->preferenceRepository->findByUserAndKey(
            $user->getId(),
            UserPreference::KEY_WIDGET_SETTINGS,
        );

        if (!$preference || !$preference->getPreferenceValue()) {
            // Возвращаем виджеты по умолчанию
            return [
                'task_stats' => ['enabled' => true, 'position' => 1, 'collapsed' => false],
                'recent_tasks' => ['enabled' => true, 'position' => 2, 'collapsed' => false, 'limit' => 5],
                'upcoming_deadlines' => ['enabled' => true, 'position' => 3, 'collapsed' => false, 'days_ahead' => 7],
                'productivity_chart' => ['enabled' => true, 'position' => 4, 'collapsed' => false],
            ];
        }

        return $preference->getPreferenceValue();
    }

    /**
     * Save user's widget configuration
     */
    public function saveUserWidgets(User $user, array $widgets): void
    {
        // Валидация виджетов
        $available = array_keys($this->getAvailableWidgets());
        $validWidgets = [];

        foreach ($widgets as $widgetId => $config) {
            if (\in_array($widgetId, $available, true)) {
                $validWidgets[$widgetId] = array_merge(
                    ['enabled' => true, 'position' => 999, 'collapsed' => false],
                    $config,
                );
            }
        }

        $this->preferenceRepository->setValue(
            $user->getId(),
            $user,
            UserPreference::KEY_WIDGET_SETTINGS,
            $validWidgets,
        );
    }

    /**
     * Enable widget for user
     */
    public function enableWidget(User $user, string $widgetId): bool
    {
        $widgets = $this->getUserWidgets($user);

        if (!isset($widgets[$widgetId])) {
            $widgets[$widgetId] = ['enabled' => true, 'position' => 999, 'collapsed' => false];
        } else {
            $widgets[$widgetId]['enabled'] = true;
        }

        $this->saveUserWidgets($user, $widgets);

        return true;
    }

    /**
     * Disable widget for user
     */
    public function disableWidget(User $user, string $widgetId): bool
    {
        $widgets = $this->getUserWidgets($user);

        if (isset($widgets[$widgetId])) {
            $widgets[$widgetId]['enabled'] = false;
            $this->saveUserWidgets($user, $widgets);
        }

        return true;
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(User $user, string $widgetId, array $config): bool
    {
        $widgets = $this->getUserWidgets($user);

        if (!isset($widgets[$widgetId])) {
            return false;
        }

        $widgets[$widgetId] = array_merge($widgets[$widgetId], $config);
        $this->saveUserWidgets($user, $widgets);

        return true;
    }

    /**
     * Get enabled widgets for user
     */
    public function getEnabledWidgets(User $user): array
    {
        $widgets = $this->getUserWidgets($user);

        $enabledWidgets = array_filter(
            $widgets,
            fn ($config) => $config['enabled'] ?? true,
        );

        // Сортируем по позиции
        uasort($enabledWidgets, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $enabledWidgets;
    }

    // Widget data methods

    private function getTaskStatsData(User $user): array
    {
        return $this->taskRepository->getQuickStats($user);
    }

    private function getRecentTasksData(User $user): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return ['tasks' => $tasks];
    }

    private function getOverdueTasksData(User $user): array
    {
        $now = new \DateTime();

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->orderBy('t.dueDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'tasks' => $tasks,
            'count' => \count($tasks),
        ];
    }

    private function getUpcomingDeadlinesData(User $user): array
    {
        $now = new \DateTime();
        $nextWeek = (clone $now)->modify('+7 days');

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.dueDate BETWEEN :now AND :nextWeek')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('nextWeek', $nextWeek)
            ->setParameter('completed', 'completed')
            ->orderBy('t.dueDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return ['tasks' => $tasks];
    }

    private function getProductivityChartData(User $user): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $nextDay = (clone $date)->modify('+1 day');

            $completed = $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.status = :completed')
                ->andWhere('t.completedAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start', $date)
                ->setParameter('end', $nextDay)
                ->getQuery()
                ->getSingleScalarResult();

            $data[] = [
                'date' => $date->format('d.m'),
                'completed' => (int)$completed,
            ];
        }

        return ['data' => $data];
    }

    private function getPriorityDistributionData(User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.priority, COUNT(t.id) as count')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->groupBy('t.priority')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');

        $results = $qb->getQuery()->getResult();

        $distribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'urgent' => 0,
        ];

        foreach ($results as $result) {
            $priority = $result['priority'] ?? 'medium';
            if (isset($distribution[$priority])) {
                $distribution[$priority] = (int)$result['count'];
            }
        }

        return $distribution;
    }

    private function getCategoryBreakdownData(User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('c.name, COUNT(t.id) as count')
            ->leftJoin('t.category', 'c')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->groupBy('c.id, c.name')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5)
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');

        return ['categories' => $qb->getQuery()->getResult()];
    }

    private function getTeamActivityData(User $user): array
    {
        // Get recent activity logs if entity exists
        try {
            $activityRepo = $this->entityManager->getRepository('App\\Entity\\ActivityLog');
            $activities = $activityRepo->createQueryBuilder('a')
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            return ['activities' => $activities];
        } catch (\Exception $e) {
            // ActivityLog entity might not exist yet
            return ['activities' => []];
        }
    }

    private function getQuickActionsData(User $user): array
    {
        return [
            'actions' => [
                ['icon' => 'fa-plus', 'label' => 'Новая задача', 'url' => '/tasks/new'],
                ['icon' => 'fa-calendar', 'label' => 'Календарь', 'url' => '/calendar'],
                ['icon' => 'fa-chart-bar', 'label' => 'Отчеты', 'url' => '/reports'],
                ['icon' => 'fa-file-import', 'label' => 'Импорт', 'url' => '/import'],
            ],
        ];
    }

    private function getNotificationsData(User $user): array
    {
        try {
            $notificationRepo = $this->entityManager->getRepository('App\Entity\Notification');
            $notifications = $notificationRepo->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                5,
            );
    
            return ['notifications' => $notifications];
        } catch (\Exception $e) {
            return ['notifications' => []];
        }
    }
    
    private function getTimeTrackingData(User $user): array
    {
        // Получаем задачи с отслеживанием времени
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title, t.timeSpent, t.estimatedTime')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5)
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');
    
        $tasks = $qb->getQuery()->getResult();
    
        $totalTime = 0;
        foreach ($tasks as $task) {
            $totalTime += (int)($task['timeSpent'] ?? 0);
        }
    
        return [
            'active_tasks' => $tasks,
            'total_time_today' => $totalTime,
            'average_time' => \count($tasks) > 0 ? round($totalTime / \count($tasks)) : 0,
        ];
    }
    
    private function getGoalsProgressData(User $user): array
    {
        // Пример данных для целей (в будущем можно подключить Goals entity)
        return [
            'goals' => [
                ['title' => 'Завершить 10 задач', 'current' => 7, 'target' => 10, 'progress' => 70],
                ['title' => 'Учиться 20 часов', 'current' => 12, 'target' => 20, 'progress' => 60],
                ['title' => 'Заработать $5000', 'current' => 3200, 'target' => 5000, 'progress' => 64],
            ],
        ];
    }
    
    private function getWorkloadDistributionData(User $user): array
    {
        // Кэшируем на 1 час
        if ($this->cache) {
            $cacheKey = 'workload_distribution_' . $user->getId();
            $cacheItem = $this->cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        
        // Распределение задач по дням недели
        $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $distribution = array_fill(0, 7, 0);
    
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t.createdAt')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    
        foreach ($tasks as $task) {
            if (isset($task['createdAt'])) {
                $date = $task['createdAt'] instanceof \DateTime ? $task['createdAt'] : new \DateTime($task['createdAt']);
                $dayOfWeek = (int)$date->format('N') - 1;
                if ($dayOfWeek >= 0 && $dayOfWeek < 7) {
                    $distribution[$dayOfWeek]++;
                }
            }
        }
    
        return [
            'distribution' => array_combine($days, $distribution),
            'busiest_day' => $days[array_search(max($distribution), $distribution)],
            'average_per_day' => round(array_sum($distribution) / 7, 1),
        ];
    }
    
    private function getSkillDevelopmentData(User $user): array
    {
        // Прогресс по навыкам (заглушка для будущего функционала)
        return [
            'skills' => [
                ['name' => 'PHP/Symfony', 'level' => 75, 'color' => '#3b82f6'],
                ['name' => 'JavaScript', 'level' => 60, 'color' => '#f59e0b'],
                ['name' => 'CSS/Tailwind', 'level' => 70, 'color' => '#8b5cf6'],
                ['name' => 'Database', 'level' => 65, 'color' => '#10b981'],
            ],
        ];
    }
    
    private function getFocusTimeData(User $user): array
    {
        // Анализ продуктивных часов
        $hours = array_fill(0, 24, 0);
    
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t.createdAt, t.updatedAt')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getResult();
    
        foreach ($tasks as $task) {
            if (isset($task['updatedAt'])) {
                $date = $task['updatedAt'] instanceof \DateTime ? $task['updatedAt'] : new \DateTime($task['updatedAt']);
                $hour = (int)$date->format('H');
                $hours[$hour]++;
            }
        }
    
        $peakHour = array_search(max($hours), $hours);
    
        return [
            'hours' => $hours,
            'peak_hour' => $peakHour,
            'peak_period' => $peakHour >= 9 && $peakHour <= 12 ? 'Утро' : ($peakHour >= 13 && $peakHour <= 17 ? 'День' : 'Вечер'),
        ];
    }
}
