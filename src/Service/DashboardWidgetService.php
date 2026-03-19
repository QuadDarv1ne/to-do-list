<?php

namespace App\Service;

use App\Entity\DashboardWidget;
use App\Entity\User;
use App\Repository\DashboardWidgetRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис для управления виджетами дашборда
 */
class DashboardWidgetService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaskRepository $taskRepo,
        private DashboardWidgetRepository $widgetRepo,
    ) {
    }

    /**
     * Получить виджеты для пользователя
     *
     * @return DashboardWidget[]
     */
    public function getUserWidgets(User $user): array
    {
        return $this->widgetRepo->findBy(
            ['user' => $user, 'isActive' => true],
            ['position' => 'ASC']
        );
    }

    /**
     * Получить данные для виджета
     */
    public function getWidgetData(DashboardWidget $widget, User $user): array
    {
        $config = $widget->getConfiguration() ?? [];

        return match ($widget->getType()) {
            'stats_overview' => $this->getStatsOverview($user, $config),
            'task_progress' => $this->getTaskProgress($user, $config),
            'recent_tasks' => $this->getRecentTasks($user, $config),
            'overdue_tasks' => $this->getOverdueTasks($user, $config),
            'activity_feed' => $this->getActivityFeed($user, $config),
            'quick_actions' => $this->getQuickActions($user, $config),
            default => [],
        };
    }

    /**
     * Статистика overview
     */
    private function getStatsOverview(User $user, array $config): array
    {
        $stats = $this->taskRepo->performGetDashboardStats($user);

        return [
            'total' => $stats['total_tasks'],
            'completed' => $stats['completed_tasks'],
            'pending' => $stats['pending_tasks'],
            'in_progress' => $stats['in_progress_tasks'],
            'overdue' => $stats['overdue_tasks'],
            'completion_rate' => $stats['total_tasks'] > 0
                ? round($stats['completed_tasks'] / $stats['total_tasks'] * 100, 1)
                : 0,
        ];
    }

    /**
     * Прогресс задач
     */
    private function getTaskProgress(User $user, array $config): array
    {
        $limit = $config['limit'] ?? 5;

        $tasks = $this->taskRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );

        $byStatus = [];
        $byPriority = [];

        foreach ($tasks as $task) {
            $status = $task->getStatus();
            $priority = $task->getPriority();

            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;
        }

        return [
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'total' => count($tasks),
        ];
    }

    /**
     * Последние задачи
     */
    private function getRecentTasks(User $user, array $config): array
    {
        $limit = $config['limit'] ?? 10;
        $status = $config['status'] ?? null;

        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }

        return $this->taskRepo->findByUserWithFilters($user, $filters);
    }

    /**
     * Просроченные задачи
     */
    private function getOverdueTasks(User $user, array $config): array
    {
        $tasks = $this->taskRepo->findByUserWithFilters($user, [
            'overdue' => true,
        ]);

        return array_slice($tasks, 0, $config['limit'] ?? 10);
    }

    /**
     * Лента активности
     */
    private function getActivityFeed(User $user, array $config): array
    {
        $limit = $config['limit'] ?? 20;

        $activityLogRepo = $this->em->getRepository(\App\Entity\ActivityLog::class);

        return $activityLogRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Быстрые действия
     */
    private function getQuickActions(User $user, array $config): array
    {
        $defaultActions = [
            ['id' => 'create_task', 'label' => 'Новая задача', 'icon' => 'fas fa-plus', 'url' => '/tasks/new'],
            ['id' => 'view_all', 'label' => 'Все задачи', 'icon' => 'fas fa-list', 'url' => '/tasks'],
            ['id' => 'calendar', 'label' => 'Календарь', 'icon' => 'fas fa-calendar', 'url' => '/calendar'],
            ['id' => 'export', 'label' => 'Экспорт', 'icon' => 'fas fa-download', 'url' => '/export'],
            ['id' => 'kanban', 'label' => 'Канбан', 'icon' => 'fas fa-columns', 'url' => '/kanban'],
            ['id' => 'reports', 'label' => 'Отчёты', 'icon' => 'fas fa-chart-bar', 'url' => '/reports'],
        ];

        return $config['actions'] ?? $defaultActions;
    }

    /**
     * Создать виджет по умолчанию
     */
    public function createDefaultWidgets(User $user): void
    {
        $defaultWidgets = [
            [
                'type' => 'stats_overview',
                'title' => 'Общая статистика',
                'width' => 3,
                'position' => 0,
                'configuration' => [],
            ],
            [
                'type' => 'recent_tasks',
                'title' => 'Последние задачи',
                'width' => 2,
                'position' => 1,
                'configuration' => ['limit' => 5],
            ],
            [
                'type' => 'overdue_tasks',
                'title' => 'Просроченные задачи',
                'width' => 1,
                'position' => 2,
                'configuration' => ['limit' => 5],
            ],
            [
                'type' => 'quick_actions',
                'title' => 'Быстрые действия',
                'width' => 1,
                'position' => 3,
                'configuration' => [],
            ],
        ];

        foreach ($defaultWidgets as $widgetData) {
            $existing = $this->widgetRepo->findOneBy([
                'user' => $user,
                'type' => $widgetData['type'],
            ]);

            if (!$existing) {
                $widget = new DashboardWidget();
                $widget->setUser($user);
                $widget->setType($widgetData['type']);
                $widget->setTitle($widgetData['title']);
                $widget->setWidth($widgetData['width']);
                $widget->setPosition($widgetData['position']);
                $widget->setConfiguration($widgetData['configuration']);

                $this->em->persist($widget);
            }
        }

        $this->em->flush();
    }

    /**
     * Обновить позицию виджета
     */
    public function updateWidgetPosition(DashboardWidget $widget, int $position): void
    {
        $widget->setPosition($position);
        $this->em->flush();
    }

    /**
     * Обновить конфигурацию виджета
     */
    public function updateWidgetConfiguration(DashboardWidget $widget, array $configuration): void
    {
        $widget->setConfiguration($configuration);
        $widget->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * Удалить виджет
     */
    public function removeWidget(DashboardWidget $widget): void
    {
        $widget->setIsActive(false);
        $this->em->flush();
    }

    /**
     * Сбросить виджеты к настройкам по умолчанию
     */
    public function resetToDefaults(User $user): void
    {
        // Деактивировать все виджеты
        $widgets = $this->getUserWidgets($user);
        foreach ($widgets as $widget) {
            $widget->setIsActive(false);
        }

        $this->em->flush();

        // Создать виджеты по умолчанию
        $this->createDefaultWidgets($user);
    }
}
