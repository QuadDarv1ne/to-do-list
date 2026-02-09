<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AnalyticsService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private TaskRepository $taskRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        TaskRepository $taskRepository
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->taskRepository = $taskRepository;
    }

    /**
     * Get comprehensive task analytics for a user
     */
    public function getUserTaskAnalytics(User $user): array
    {
        $stats = [
            'overview' => $this->getOverviewStats($user),
            'completion_rates' => $this->getCompletionRates($user),
            'productivity_trends' => $this->getProductivityTrends($user),
            'category_analysis' => $this->getCategoryAnalysis($user),
            'priority_analysis' => $this->getPriorityAnalysis($user),
            'time_analysis' => $this->getTimeAnalysis($user),
            'performance_metrics' => $this->getPerformanceMetrics($user)
        ];

        return $stats;
    }

    /**
     * Get basic overview statistics
     */
    private function getOverviewStats(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Total tasks
        $totalTasks = $qb->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('t.assignedTo = :user OR t.createdBy = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Completed tasks
        $completedTasks = $qb->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Overdue tasks
        $overdueTasks = $qb->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :status')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Pending tasks
        $pendingTasks = $qb->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_tasks' => (int) $totalTasks,
            'completed_tasks' => (int) $completedTasks,
            'overdue_tasks' => (int) $overdueTasks,
            'pending_tasks' => (int) $pendingTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
        ];
    }

    /**
     * Get completion rates over time periods
     */
    private function getCompletionRates(User $user): array
    {
        $periods = [
            'today' => new \DateTime('today'),
            'this_week' => new \DateTime('monday this week'),
            'this_month' => new \DateTime('first day of this month'),
            'this_year' => new \DateTime('first day of january this year')
        ];

        $rates = [];
        foreach ($periods as $periodName => $startDate) {
            $qb = $this->entityManager->createQueryBuilder();
            
            $total = $qb->select('COUNT(t.id)')
                ->from(Task::class, 't')
                ->where('(t.assignedTo = :user OR t.createdBy = :user)')
                ->andWhere('t.createdAt >= :start_date')
                ->setParameter('user', $user)
                ->setParameter('start_date', $startDate)
                ->getQuery()
                ->getSingleScalarResult();

            $completed = $qb->select('COUNT(t.id)')
                ->from(Task::class, 't')
                ->where('(t.assignedTo = :user OR t.createdBy = :user)')
                ->andWhere('t.status = :status')
                ->andWhere('t.updatedAt >= :start_date')
                ->setParameter('user', $user)
                ->setParameter('status', 'completed')
                ->setParameter('start_date', $startDate)
                ->getQuery()
                ->getSingleScalarResult();

            $rates[$periodName] = [
                'total' => (int) $total,
                'completed' => (int) $completed,
                'rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
            ];
        }

        return $rates;
    }

    /**
     * Get productivity trends (tasks completed per day/week)
     */
    private function getProductivityTrends(User $user): array
    {
        // Last 30 days daily completion
        $qb = $this->entityManager->createQueryBuilder();
        $dailyData = $qb->select('DATE(t.updatedAt) as date, COUNT(t.id) as count')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.status = :status')
            ->andWhere('t.updatedAt >= :start_date')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('start_date', new \DateTime('-30 days'))
            ->getQuery()
            ->getArrayResult();

        // Weekly averages
        $weeklyData = $qb->select('YEARWEEK(t.updatedAt) as week, COUNT(t.id) as count')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.status = :status')
            ->andWhere('t.updatedAt >= :start_date')
            ->groupBy('week')
            ->orderBy('week', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('start_date', new \DateTime('-12 weeks'))
            ->getQuery()
            ->getArrayResult();

        return [
            'daily_completion' => $dailyData,
            'weekly_completion' => $weeklyData,
            'trend_analysis' => $this->analyzeTrend($dailyData)
        ];
    }

    /**
     * Analyze trend direction
     */
    private function analyzeTrend(array $data): string
    {
        if (count($data) < 2) {
            return 'insufficient_data';
        }

        $values = array_column($data, 'count');
        $slope = $this->calculateLinearRegressionSlope($values);

        if ($slope > 0.5) {
            return 'improving';
        } elseif ($slope < -0.5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Simple linear regression slope calculation
     */
    private function calculateLinearRegressionSlope(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0;
        }

        $sumX = $sumY = $sumXY = $sumXX = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumXX += $i * $i;
        }

        $denominator = ($n * $sumXX - $sumX * $sumX);
        if ($denominator == 0) {
            return 0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }

    /**
     * Get category-wise analysis
     */
    private function getCategoryAnalysis(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $categories = $qb->select('c.name, COUNT(t.id) as task_count, 
                                  SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_count')
            ->from('App\Entity\Category', 'c')
            ->leftJoin('c.tasks', 't')
            ->where('t.assignedTo = :user OR t.createdBy = :user')
            ->groupBy('c.id')
            ->orderBy('task_count', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getArrayResult();

        foreach ($categories as &$category) {
            $category['completion_rate'] = $category['task_count'] > 0 ? 
                round(($category['completed_count'] / $category['task_count']) * 100, 1) : 0;
        }

        return $categories;
    }

    /**
     * Get priority analysis
     */
    private function getPriorityAnalysis(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $priorities = $qb->select('t.priority, COUNT(t.id) as count, 
                                   SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->groupBy('t.priority')
            ->orderBy('t.priority', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($priorities as $priority) {
            $result[$priority['priority']] = [
                'total' => (int) $priority['count'],
                'completed' => (int) $priority['completed'],
                'completion_rate' => $priority['count'] > 0 ? 
                    round(($priority['completed'] / $priority['count']) * 100, 1) : 0
            ];
        }

        return $result;
    }

    /**
     * Get time-based analysis
     */
    private function getTimeAnalysis(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Average completion time
        $avgCompletionTime = $qb->select('AVG(TIMESTAMPDIFF(HOUR, t.createdAt, t.updatedAt)) as avg_hours')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->andWhere('t.status = :status')
            ->andWhere('t.updatedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Tasks by hour of day
        $hourlyDistribution = $qb->select('HOUR(t.createdAt) as hour, COUNT(t.id) as count')
            ->from(Task::class, 't')
            ->where('(t.assignedTo = :user OR t.createdBy = :user)')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return [
            'average_completion_time_hours' => $avgCompletionTime ? round((float) $avgCompletionTime, 1) : 0,
            'hourly_task_creation' => $hourlyDistribution
        ];
    }

    /**
     * Get performance metrics and insights
     */
    private function getPerformanceMetrics(User $user): array
    {
        $overview = $this->getOverviewStats($user);
        
        $insights = [];
        
        // Productivity insights
        if ($overview['completion_rate'] < 50) {
            $insights[] = 'Низкий уровень завершения задач. Рассмотрите возможность разбиения задач на более мелкие части.';
        } elseif ($overview['completion_rate'] > 80) {
            $insights[] = 'Отличный уровень продуктивности! Продолжайте в том же духе.';
        }

        // Overdue tasks insight
        if ($overview['overdue_tasks'] > 0) {
            $insights[] = "У вас {$overview['overdue_tasks']} просроченных задач. Приоритизируйте их выполнение.";
        }

        // Priority insights
        $priorityAnalysis = $this->getPriorityAnalysis($user);
        if (isset($priorityAnalysis['high']) && $priorityAnalysis['high']['completion_rate'] < 70) {
            $insights[] = 'Задачи высокого приоритета выполняются медленно. Возможно, они требуют пересмотра.';
        }

        return [
            'efficiency_score' => $this->calculateEfficiencyScore($overview),
            'insights' => $insights,
            'recommendations' => $this->generateRecommendations($overview, $priorityAnalysis)
        ];
    }

    /**
     * Calculate efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $overview): int
    {
        $score = 0;
        
        // Completion rate weight: 40%
        $score += $overview['completion_rate'] * 0.4;
        
        // Overdue penalty: -20% if has overdue tasks
        if ($overview['overdue_tasks'] > 0) {
            $penalty = min(20, ($overview['overdue_tasks'] / max(1, $overview['total_tasks'])) * 100);
            $score -= $penalty;
        }
        
        // Pending tasks factor: 20%
        if ($overview['total_tasks'] > 0) {
            $pendingRatio = $overview['pending_tasks'] / $overview['total_tasks'];
            $score += (1 - $pendingRatio) * 20;
        }
        
        return max(0, min(100, round($score)));
    }

    /**
     * Generate personalized recommendations
     */
    private function generateRecommendations(array $overview, array $priorityAnalysis): array
    {
        $recommendations = [];
        
        if ($overview['completion_rate'] < 60) {
            $recommendations[] = 'Начните с малого: ставьте простые, достижимые цели';
        }
        
        if (isset($priorityAnalysis['high']) && $priorityAnalysis['high']['completion_rate'] < 70) {
            $recommendations[] = 'Пересмотрите задачи высокого приоритета - возможно, они слишком сложные или неясные';
        }
        
        if ($overview['overdue_tasks'] > $overview['total_tasks'] * 0.2) {
            $recommendations[] = 'Используйте фильтры по срокам для лучшего планирования';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Ваша продуктивность на хорошем уровне. Попробуйте новые функции системы!';
        }
        
        return $recommendations;
    }

    /**
     * Export analytics data to CSV
     */
    public function exportAnalyticsToCsv(User $user): string
    {
        $analytics = $this->getUserTaskAnalytics($user);
        
        $csv = "Метрика,Значение\n";
        $csv .= "Всего задач,{$analytics['overview']['total_tasks']}\n";
        $csv .= "Завершено,{$analytics['overview']['completed_tasks']}\n";
        $csv .= "Просрочено,{$analytics['overview']['overdue_tasks']}\n";
        $csv .= "В ожидании,{$analytics['overview']['pending_tasks']}\n";
        $csv .= "Процент завершения,{$analytics['overview']['completion_rate']}%\n";
        $csv .= "Эффективность,{$analytics['performance_metrics']['efficiency_score']}/100\n";
        
        return $csv;
    }
}