<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\PerformanceMonitoringService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AnalyticsService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private TaskRepository $taskRepository;
    private PerformanceMonitoringService $performanceMonitor;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        TaskRepository $taskRepository,
        PerformanceMonitoringService $performanceMonitor
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->taskRepository = $taskRepository;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Get comprehensive task analytics for a user
     */
    public function getUserTaskAnalytics(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_user_task_analytics');
        try {
            $stats = [
                'overview' => $this->getOverviewStats($user),
                'completion_rates' => $this->getCompletionRates($user),
                'productivity_trends' => $this->getProductivityTrends($user),
                'category_analysis' => $this->getCategoryAnalysis($user),
                'priority_analysis' => $this->getPriorityAnalysis($user),
                'time_analysis' => $this->getTimeAnalysis($user),
                'performance_metrics' => $this->getPerformanceMetrics($user),
                'prediction_analysis' => $this->getPredictionAnalysis($user),
                'dependency_analysis' => $this->getDependencyAnalysis($user)
            ];

            return $stats;
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_user_task_analytics');
        }
    }

    /**
     * Get dashboard-specific analytics data with caching
     */
    public function getDashboardData(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_dashboard_data');
        try {
            // Get quick stats from task repository
            $quickStats = $this->taskRepository->getQuickStats($user);
            
            // Get basic overview for dashboard
            $overview = $this->getOverviewStats($user);
            $completionRates = $this->getCompletionRates($user);
            $productivityTrends = $this->getProductivityTrends($user);
            $priorityAnalysis = $this->getPriorityAnalysis($user);
            $categoryAnalysis = $this->getCategoryAnalysis($user);
            $dependencyAnalysis = $this->getDependencyAnalysis($user);
            $timeAnalysis = $this->getTimeAnalysis($user);
            $performanceMetrics = $this->getPerformanceMetrics($user);
            $predictionAnalysis = $this->getPredictionAnalysis($user);
            
            // Create dummy data for missing methods
            $tagAnalysis = [
                'tag_distribution' => [],
                'tag_completion_rates' => []
            ];
            $recentActivity = [
                'recent_tasks' => []
            ];
            
            return [
                'quickStats' => $quickStats,
                'overview' => $overview,
                'completionRates' => $completionRates,
                'productivityTrends' => $productivityTrends,
                'priorityAnalysis' => $priorityAnalysis,
                'categoryAnalysis' => $categoryAnalysis,
                'dependencyAnalysis' => $dependencyAnalysis,
                'timeAnalysis' => $timeAnalysis,
                'performanceMetrics' => $performanceMetrics,
                'predictionAnalysis' => $predictionAnalysis,
                'tag_stats' => $tagAnalysis['tag_distribution'] ?? [],
                'tag_completion_rates' => $tagAnalysis['tag_completion_rates'] ?? [],
                'categories' => $categoryAnalysis['categories'] ?? [],
                'recent_tasks' => $quickStats['recent_tasks'] ?? [],
                'recent_activity' => $recentActivity,
                'user_activity_stats' => [
                    'total_tasks_created' => 0,
                    'recent_activities' => []
                ],
                'platform_activity_stats' => [
                    'total_users' => 0,
                    'active_today' => 0
                ]
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_dashboard_data');
        }
    }

    /**
     * Get basic overview statistics
     */
    private function getOverviewStats(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_overview_stats');
        try {
            // Single query to get all stats at once for better performance
            $results = $this->entityManager->createQueryBuilder()
                ->select(
                    'COUNT(t.id) as total_tasks',
                    'SUM(CASE WHEN t.status = :completed_status THEN 1 ELSE 0 END) as completed_tasks',
                    'SUM(CASE WHEN t.dueDate IS NOT NULL AND t.dueDate < :now AND t.status != :completed_status THEN 1 ELSE 0 END) as overdue_tasks',
                    'SUM(CASE WHEN t.status = :pending_status THEN 1 ELSE 0 END) as pending_tasks'
                )
                ->from(Task::class, 't')
                ->where('t.assignedUser = :user OR t.user = :user')
                ->setParameter('user', $user)
                ->setParameter('completed_status', 'completed')
                ->setParameter('pending_status', 'pending')
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getSingleResult();

            $totalTasks = (int) $results['total_tasks'];
            $completedTasks = (int) $results['completed_tasks'];
            $overdueTasks = (int) $results['overdue_tasks'];
            $pendingTasks = (int) $results['pending_tasks'];

            return [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'overdue_tasks' => $overdueTasks,
                'pending_tasks' => $pendingTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_overview_stats');
        }
    }

    /**
     * Get completion rates over time periods
     */
    private function getCompletionRates(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_completion_rates');
        try {
            $periods = [
                'today' => new \DateTime('today'),
                'this_week' => new \DateTime('monday this week'),
                'this_month' => new \DateTime('first day of this month'),
                'this_year' => new \DateTime('first day of january this year')
            ];

            $rates = [];
            foreach ($periods as $periodName => $startDate) {
                $total = $this->entityManager->createQueryBuilder()
                    ->select('COUNT(t.id)')
                    ->from(Task::class, 't')
                    ->where('(t.assignedUser = :user OR t.user = :user)')
                    ->andWhere('t.createdAt >= :start_date')
                    ->setParameter('user', $user)
                    ->setParameter('start_date', $startDate)
                    ->getQuery()
                    ->getSingleScalarResult();

                $completed = $this->entityManager->createQueryBuilder()
                    ->select('COUNT(t.id)')
                    ->from(Task::class, 't')
                    ->where('(t.assignedUser = :user OR t.user = :user)')
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_completion_rates');
        }
    }

    /**
     * Get productivity trends (tasks completed per day/week)
     */
    private function getProductivityTrends(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_productivity_trends');
        try {
            // Last 30 days daily completion
            $dailyData = $this->entityManager->createQueryBuilder()
                ->select('SUBSTRING(t.updatedAt, 1, 10) as date, COUNT(t.id) as count')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.status = :status')
                ->andWhere('t.updatedAt >= :start_date')
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->setParameter('user', $user)
                ->setParameter('status', 'completed')
                ->setParameter('start_date', new \DateTime('-30 days'))
                ->getQuery()
                ->getArrayResult();

            // Weekly averages - use strftime for SQLite
            $weeklyData = $this->entityManager->createQueryBuilder()
                ->select('SUBSTRING(t.updatedAt, 1, 7) as week, COUNT(t.id) as count')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_productivity_trends');
        }
    }

    /**
     * Analyze trend direction
     */
    private function analyzeTrend(array $data): string
    {
        $this->performanceMonitor->startTiming('analytics_service_analyze_trend');
        try {
            if (count($data) < 2) {
                return 'insufficient_data';
            }

            $values = array_column($data, 'count');

            return $this->calculateTrendDirection($values);
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_analyze_trend');
        }
    }

    /**
     * Calculate trend direction based on values
     */
    private function calculateTrendDirection(array $values): string
    {
        $this->performanceMonitor->startTiming('analytics_service_calculate_trend_direction');
        try {
            $slope = $this->calculateLinearRegressionSlope($values);

            if ($slope > 0.5) {
                return 'improving';
            } elseif ($slope < -0.5) {
                return 'declining';
            } else {
                return 'stable';
            }
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_calculate_trend_direction');
        }
    }

    /**
     * Simple linear regression slope calculation
     */
    private function calculateLinearRegressionSlope(array $values): float
    {
        $this->performanceMonitor->startTiming('analytics_service_calculate_linear_regression_slope');
        try {
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_calculate_linear_regression_slope');
        }
    }

    /**
     * Get category-wise analysis
     */
    private function getCategoryAnalysis(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_category_analysis');
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $categories = $qb->select('c.name, c.id, COUNT(t.id) as task_count, 
                                      SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_count')
                ->from('App\Entity\TaskCategory', 'c')
                ->leftJoin('c.tasks', 't')
                ->where('c.user = :user')
                ->groupBy('c.id')
                ->orderBy('task_count', 'DESC')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->getQuery()
                ->getArrayResult();

            foreach ($categories as &$category) {
                $category['completion_rate'] = $category['task_count'] > 0 ? 
                    round(($category['completed_count'] / $category['task_count']) * 100, 1) : 0;
                $category['avg_completion_time'] = 0; // Simplified for SQLite compatibility
            }

            return $categories;
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_category_analysis');
        }
    }

    /**
     * Get priority analysis
     */
    private function getPriorityAnalysis(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_priority_analysis');
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $priorities = $qb->select('t.priority, COUNT(t.id) as count, 
                                       SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_priority_analysis');
        }
    }

    /**
     * Get time-based analysis
     */
    private function getTimeAnalysis(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_time_analysis');
        try {
            
            // Average completion time - calculate in PHP for SQLite compatibility
            $completedTasks = $this->entityManager->createQueryBuilder()
                ->select('t.createdAt, t.updatedAt')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.status = :status')
                ->andWhere('t.updatedAt IS NOT NULL')
                ->setParameter('user', $user)
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getArrayResult();

            $totalHours = 0;
            $count = 0;
            foreach ($completedTasks as $task) {
                if ($task['createdAt'] && $task['updatedAt']) {
                    $created = $task['createdAt'] instanceof \DateTime ? $task['createdAt'] : new \DateTime($task['createdAt']);
                    $updated = $task['updatedAt'] instanceof \DateTime ? $task['updatedAt'] : new \DateTime($task['updatedAt']);
                    $diff = $updated->getTimestamp() - $created->getTimestamp();
                    $totalHours += $diff / 3600;
                    $count++;
                }
            }
            $avgCompletionTime = $count > 0 ? $totalHours / $count : 0;

            // Tasks by hour of day - extract hour in PHP for SQLite compatibility
            $allTasks = $this->entityManager->createQueryBuilder()
                ->select('t.createdAt')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult();

            $hourlyDistribution = array_fill(0, 24, 0);
            foreach ($allTasks as $task) {
                if ($task['createdAt']) {
                    $date = $task['createdAt'] instanceof \DateTime ? $task['createdAt'] : new \DateTime($task['createdAt']);
                    $hour = (int) $date->format('H');
                    $hourlyDistribution[$hour]++;
                }
            }

            $hourlyData = [];
            foreach ($hourlyDistribution as $hour => $count) {
                if ($count > 0) {
                    $hourlyData[] = ['hour' => $hour, 'count' => $count];
                }
            }

            return [
                'average_completion_time_hours' => round($avgCompletionTime, 1),
                'hourly_task_creation' => $hourlyData
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_time_analysis');
        }
    }

    /**
     * Get performance metrics and insights
     */
    private function getPerformanceMetrics(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_performance_metrics');
        try {
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_performance_metrics');
        }
    }

    /**
     * Calculate efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $overview): int
    {
        $this->performanceMonitor->startTiming('analytics_service_calculate_efficiency_score');
        try {
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_calculate_efficiency_score');
        }
    }

    /**
     * Generate personalized recommendations
     */
    private function generateRecommendations(array $overview, array $priorityAnalysis): array
    {
        $this->performanceMonitor->startTiming('analytics_service_generate_recommendations');
        try {
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
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_generate_recommendations');
        }
    }

    /**
     * Get prediction analysis for future task completion
     */
    private function getPredictionAnalysis(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_prediction_analysis');
        try {
            // Get completion rate for the last 4 weeks
            $fourWeeksAgo = new \DateTime('-4 weeks');
            $weeklyCompletions = $this->entityManager->createQueryBuilder()
                ->select('SUBSTRING(t.updatedAt, 1, 7) as week, COUNT(t.id) as completions')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.status = :status')
                ->andWhere('t.updatedAt >= :start_date')
                ->groupBy('week')
                ->orderBy('week', 'ASC')
                ->setParameter('user', $user)
                ->setParameter('status', 'completed')
                ->setParameter('start_date', $fourWeeksAgo)
                ->getQuery()
                ->getArrayResult();
            
            // Calculate average weekly completion rate
            $avgWeeklyCompletion = 0;
            if (!empty($weeklyCompletions)) {
                $totalCompletions = array_sum(array_column($weeklyCompletions, 'completions'));
                $avgWeeklyCompletion = $totalCompletions / max(1, count($weeklyCompletions));
            }
            
            // Count remaining tasks
            $remainingTasksQuery = $this->entityManager->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.status != :completed_status')
                ->setParameter('user', $user)
                ->setParameter('completed_status', 'completed')
                ->getQuery()
                ->getSingleScalarResult();
            
            $remainingTasks = (int)$remainingTasksQuery;
            
            // Predict how many weeks it will take to complete remaining tasks
            $predictedWeeks = $avgWeeklyCompletion > 0 ? $remainingTasks / $avgWeeklyCompletion : 0;
            
            // Analyze productivity trend
            $productivityTrend = 'stable';
            if (count($weeklyCompletions) >= 2) {
                $recentCompletions = array_slice($weeklyCompletions, -2);
                if (count($recentCompletions) >= 2) {
                    $prevWeek = $recentCompletions[0]['completions'];
                    $currWeek = $recentCompletions[1]['completions'];
                    
                    if ($currWeek > $prevWeek * 1.1) {
                        $productivityTrend = 'improving';
                    } elseif ($currWeek < $prevWeek * 0.9) {
                        $productivityTrend = 'declining';
                    }
                }
            }
            
            return [
                'average_weekly_completion' => round($avgWeeklyCompletion, 1),
                'remaining_tasks' => $remainingTasks,
                'predicted_weeks_to_complete' => round($predictedWeeks, 1),
                'productivity_trend' => $productivityTrend,
                'estimated_completion_date' => $predictedWeeks > 0 ? 
                    (new \DateTime())->modify("+" . ceil($predictedWeeks) . " weeks")->format('Y-m-d') : null
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_prediction_analysis');
        }
    }
    
    /**
     * Get comparative analytics between two time periods
     */
    public function getPeriodComparison(User $user, string $period1 = 'this_month', string $period2 = 'last_month'): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_period_comparison');
        try {
            $periodRanges = $this->getPeriodDateRanges($period1, $period2);
            
            // Get analytics for period 1
            $period1Stats = $this->entityManager->createQueryBuilder()
                ->select(
                    'COUNT(t.id) as total_tasks',
                    'SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks'
                )
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.createdAt BETWEEN :start1 AND :end1')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start1', $periodRanges['period1']['start'])
                ->setParameter('end1', $periodRanges['period1']['end'])
                ->getQuery()
                ->getSingleResult();
            
            // Get analytics for period 2
            $period2Stats = $this->entityManager->createQueryBuilder()
                ->select(
                    'COUNT(t.id) as total_tasks',
                    'SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks'
                )
                ->from(Task::class, 't')
                ->where('(t.assignedUser = :user OR t.user = :user)')
                ->andWhere('t.createdAt BETWEEN :start2 AND :end2')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start2', $periodRanges['period2']['start'])
                ->setParameter('end2', $periodRanges['period2']['end'])
                ->getQuery()
                ->getSingleResult();
            
            // Calculate completion rates
            $period1Rate = $period1Stats['total_tasks'] > 0 ? 
                round(($period1Stats['completed_tasks'] / $period1Stats['total_tasks']) * 100, 1) : 0;
            $period2Rate = $period2Stats['total_tasks'] > 0 ? 
                round(($period2Stats['completed_tasks'] / $period2Stats['total_tasks']) * 100, 1) : 0;
            
            // Calculate differences
            $differences = [
                'total_tasks_diff' => (int)$period1Stats['total_tasks'] - (int)$period2Stats['total_tasks'],
                'completed_tasks_diff' => (int)$period1Stats['completed_tasks'] - (int)$period2Stats['completed_tasks'],
                'completion_rate_diff' => $period1Rate - $period2Rate,
                'avg_completion_time_diff' => 0 // Simplified for SQLite compatibility
            ];
            
            return [
                'period1' => [
                    'name' => $period1,
                    'total_tasks' => (int)$period1Stats['total_tasks'],
                    'completed_tasks' => (int)$period1Stats['completed_tasks'],
                    'completion_rate' => $period1Rate,
                    'avg_completion_time' => 0 // Simplified for SQLite compatibility
                ],
                'period2' => [
                    'name' => $period2,
                    'total_tasks' => (int)$period2Stats['total_tasks'],
                    'completed_tasks' => (int)$period2Stats['completed_tasks'],
                    'completion_rate' => $period2Rate,
                    'avg_completion_time' => 0 // Simplified for SQLite compatibility
                ],
                'differences' => $differences,
                'trend' => $this->getComparisonTrend($differences)
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_period_comparison');
        }
    }
    
    /**
     * Helper to get date ranges for different periods
     */
    private function getPeriodDateRanges(string $period1, string $period2): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_period_date_ranges');
        try {
            $ranges = [];
            
            // Process period 1
            switch ($period1) {
                case 'today':
                    $start1 = new \DateTime('today');
                    $end1 = new \DateTime('tomorrow');
                    break;
                case 'yesterday':
                    $start1 = new \DateTime('yesterday');
                    $end1 = new \DateTime('today');
                    break;
                case 'this_week':
                    $start1 = new \DateTime('monday this week');
                    $end1 = new \DateTime('monday next week');
                    break;
                case 'last_week':
                    $start1 = new \DateTime('monday last week');
                    $end1 = new \DateTime('monday this week');
                    break;
                case 'this_month':
                    $start1 = new \DateTime('first day of this month');
                    $end1 = new \DateTime('first day of next month');
                    break;
                case 'last_month':
                    $start1 = new \DateTime('first day of last month');
                    $end1 = new \DateTime('first day of this month');
                    break;
                case 'this_year':
                    $start1 = new \DateTime('first day of january this year');
                    $end1 = new \DateTime('first day of january next year');
                    break;
                case 'last_year':
                    $start1 = new \DateTime('first day of january last year');
                    $end1 = new \DateTime('first day of january this year');
                    break;
                default:
                    // Default to this month
                    $start1 = new \DateTime('first day of this month');
                    $end1 = new \DateTime('first day of next month');
            }
            
            // Process period 2
            switch ($period2) {
                case 'today':
                    $start2 = new \DateTime('today');
                    $end2 = new \DateTime('tomorrow');
                    break;
                case 'yesterday':
                    $start2 = new \DateTime('yesterday');
                    $end2 = new \DateTime('today');
                    break;
                case 'this_week':
                    $start2 = new \DateTime('monday this week');
                    $end2 = new \DateTime('monday next week');
                    break;
                case 'last_week':
                    $start2 = new \DateTime('monday last week');
                    $end2 = new \DateTime('monday this week');
                    break;
                case 'this_month':
                    $start2 = new \DateTime('first day of this month');
                    $end2 = new \DateTime('first day of next month');
                    break;
                case 'last_month':
                    $start2 = new \DateTime('first day of last month');
                    $end2 = new \DateTime('first day of this month');
                    break;
                case 'this_year':
                    $start2 = new \DateTime('first day of january this year');
                    $end2 = new \DateTime('first day of january next year');
                    break;
                case 'last_year':
                    $start2 = new \DateTime('first day of january last year');
                    $end2 = new \DateTime('first day of january this year');
                    break;
                default:
                    // Default to last month
                    $start2 = new \DateTime('first day of last month');
                    $end2 = new \DateTime('first day of this month');
            }
            
            return [
                'period1' => ['start' => $start1, 'end' => $end1],
                'period2' => ['start' => $start2, 'end' => $end2]
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_period_date_ranges');
        }
    }
    
    /**
     * Determine trend from comparison differences
     */
    private function getComparisonTrend(array $differences): string
    {
        $this->performanceMonitor->startTiming('analytics_service_get_comparison_trend');
        try {
            // Positive changes in completed tasks and completion rate indicate improvement
            $positiveFactors = 0;
            $negativeFactors = 0;
            
            if ($differences['completed_tasks_diff'] > 0) $positiveFactors++;
            else if ($differences['completed_tasks_diff'] < 0) $negativeFactors++;
            
            if ($differences['completion_rate_diff'] > 0) $positiveFactors++;
            else if ($differences['completion_rate_diff'] < 0) $negativeFactors++;
            
            if ($positiveFactors > $negativeFactors) return 'improving';
            if ($negativeFactors > $positiveFactors) return 'declining';
            
            return 'stable';
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_comparison_trend');
        }
    }
    
    /**
     * Export analytics data to CSV
     */
    public function exportAnalyticsToCsv(User $user): string
    {
        $this->performanceMonitor->startTiming('analytics_service_export_analytics_to_csv');
        try {
            $analytics = $this->getUserTaskAnalytics($user);
            
            $csv = "Метрика,Значение\n";
            $csv .= "Всего задач,{$analytics['overview']['total_tasks']}\n";
            $csv .= "Завершено,{$analytics['overview']['completed_tasks']}\n";
            $csv .= "Просрочено,{$analytics['overview']['overdue_tasks']}\n";
            $csv .= "В ожидании,{$analytics['overview']['pending_tasks']}\n";
            $csv .= "Процент завершения,{$analytics['overview']['completion_rate']}%\n";
            $csv .= "Эффективность,{$analytics['performance_metrics']['efficiency_score']}/100\n";
            $csv .= "Среднее завершение в неделю,{$analytics['prediction_analysis']['average_weekly_completion']}\n";
            $csv .= "Оставшиеся задачи,{$analytics['prediction_analysis']['remaining_tasks']}\n";
            
            return $csv;
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_export_analytics_to_csv');
        }
    }
    
    /**
     * Get dependency-related analytics
     */
    public function getDependencyAnalysis(User $user): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_dependency_analysis');
        try {
            // Count total dependencies for user's tasks
            $totalDependencies = $this->entityManager->createQueryBuilder()
                ->select('COUNT(td.id)')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependentTask', 'dt')
                ->where('dt.assignedUser = :user OR dt.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();
            
            // Count blocking dependencies
            $blockingDependencies = $this->entityManager->createQueryBuilder()
                ->select('COUNT(td.id)')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependentTask', 'dt')
                ->where('dt.assignedUser = :user OR dt.user = :user')
                ->andWhere('td.type = :type')
                ->setParameter('user', $user)
                ->setParameter('type', 'blocking')
                ->getQuery()
                ->getSingleScalarResult();
            
            // Count dependencies on completed tasks
            $completedDependencies = $this->entityManager->createQueryBuilder()
                ->select('COUNT(td.id)')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependencyTask', 'dtt')
                ->where('dtt.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult();
            
            // Count unsatisfied dependencies
            $unsatisfiedDependencies = $this->entityManager->createQueryBuilder()
                ->select('COUNT(td.id)')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependencyTask', 'dtt')
                ->where('dtt.status != :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult();
            
            // Get tasks with the most dependencies
            $topDependentTasks = $this->entityManager->createQueryBuilder()
                ->select('dt.id, dt.title, COUNT(td.id) as dependency_count')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependentTask', 'dt')
                ->where('dt.assignedUser = :user OR dt.user = :user')
                ->groupBy('dt.id, dt.title')
                ->orderBy('dependency_count', 'DESC')
                ->setMaxResults(5)
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult();
            
            // Get tasks that block the most other tasks
            $topBlockingTasks = $this->entityManager->createQueryBuilder()
                ->select('dtt.id, dtt.title, COUNT(td.id) as blocked_count')
                ->from('App\\Entity\\TaskDependency', 'td')
                ->join('td.dependencyTask', 'dtt')
                ->where('dtt.assignedUser = :user OR dtt.user = :user')
                ->groupBy('dtt.id, dtt.title')
                ->orderBy('blocked_count', 'DESC')
                ->setMaxResults(5)
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult();
            
            return [
                'total_dependencies' => (int) $totalDependencies,
                'blocking_dependencies' => (int) $blockingDependencies,
                'completed_dependencies' => (int) $completedDependencies,
                'unsatisfied_dependencies' => (int) $unsatisfiedDependencies,
                'top_dependent_tasks' => $topDependentTasks,
                'top_blocking_tasks' => $topBlockingTasks
            ];
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_dependency_analysis');
        }
    }
    
    /**
     * Get system performance metrics
     */
    public function getSystemPerformanceMetrics(): array
    {
        $this->performanceMonitor->startTiming('analytics_service_get_system_performance_metrics');
        try {
            return $this->performanceMonitor->getPerformanceReport();
        } finally {
            $this->performanceMonitor->stopTiming('analytics_service_get_system_performance_metrics');
        }
    }
}
