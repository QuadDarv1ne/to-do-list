<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardWidgetService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}
    
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
                'size' => 'col-md-6'
            ],
            'recent_tasks' => [
                'name' => 'Последние задачи',
                'description' => 'Недавно созданные задачи',
                'icon' => 'fa-clock',
                'size' => 'col-md-6'
            ],
            'overdue_tasks' => [
                'name' => 'Просроченные задачи',
                'description' => 'Задачи с истекшим дедлайном',
                'icon' => 'fa-exclamation-triangle',
                'size' => 'col-md-6'
            ],
            'upcoming_deadlines' => [
                'name' => 'Ближайшие дедлайны',
                'description' => 'Задачи на ближайшую неделю',
                'icon' => 'fa-calendar-alt',
                'size' => 'col-md-6'
            ],
            'productivity_chart' => [
                'name' => 'График продуктивности',
                'description' => 'Динамика выполнения задач',
                'icon' => 'fa-chart-line',
                'size' => 'col-md-12'
            ],
            'priority_distribution' => [
                'name' => 'Распределение по приоритетам',
                'description' => 'Задачи по уровням приоритета',
                'icon' => 'fa-chart-pie',
                'size' => 'col-md-6'
            ],
            'category_breakdown' => [
                'name' => 'Задачи по категориям',
                'description' => 'Распределение по категориям',
                'icon' => 'fa-folder',
                'size' => 'col-md-6'
            ],
            'team_activity' => [
                'name' => 'Активность команды',
                'description' => 'Последние действия команды',
                'icon' => 'fa-users',
                'size' => 'col-md-12'
            ],
            'quick_actions' => [
                'name' => 'Быстрые действия',
                'description' => 'Часто используемые действия',
                'icon' => 'fa-bolt',
                'size' => 'col-md-6'
            ],
            'notifications_widget' => [
                'name' => 'Уведомления',
                'description' => 'Последние уведомления',
                'icon' => 'fa-bell',
                'size' => 'col-md-6'
            ]
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
            default => []
        };
    }
    
    /**
     * Get user's widget configuration
     */
    public function getUserWidgets(User $user): array
    {
        // Default widgets for new users
        $defaultWidgets = [
            'task_stats',
            'recent_tasks',
            'upcoming_deadlines',
            'productivity_chart'
        ];
        
        // TODO: Load from user preferences table
        return $defaultWidgets;
    }
    
    /**
     * Save user's widget configuration
     */
    public function saveUserWidgets(User $user, array $widgets): void
    {
        // TODO: Save to user preferences table
        // For now, just validate widgets exist
        $available = array_keys($this->getAvailableWidgets());
        $validWidgets = array_intersect($widgets, $available);
        
        // TODO: Store in database instead of session
        // This is a placeholder implementation
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
            ->andWhere('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->orderBy('t.deadline', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        return [
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    }
    
    private function getUpcomingDeadlinesData(User $user): array
    {
        $now = new \DateTime();
        $nextWeek = (clone $now)->modify('+7 days');
        
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :now AND :nextWeek')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('nextWeek', $nextWeek)
            ->setParameter('completed', 'completed')
            ->orderBy('t.deadline', 'ASC')
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
                'completed' => (int)$completed
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
            'urgent' => 0
        ];
        
        foreach ($results as $result) {
            $distribution[$result['priority']] = (int)$result['count'];
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
        // Get recent activity logs
        $activities = $this->entityManager->getRepository('App\Entity\ActivityLog')
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        return ['activities' => $activities];
    }
    
    private function getQuickActionsData(User $user): array
    {
        return [
            'actions' => [
                ['icon' => 'fa-plus', 'label' => 'Новая задача', 'url' => '/tasks/new'],
                ['icon' => 'fa-calendar', 'label' => 'Календарь', 'url' => '/calendar'],
                ['icon' => 'fa-chart-bar', 'label' => 'Отчеты', 'url' => '/reports'],
                ['icon' => 'fa-file-import', 'label' => 'Импорт', 'url' => '/import'],
            ]
        ];
    }
    
    private function getNotificationsData(User $user): array
    {
        $notifications = $this->entityManager->getRepository('App\Entity\Notification')
            ->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                5
            );
        
        return ['notifications' => $notifications];
    }
}
