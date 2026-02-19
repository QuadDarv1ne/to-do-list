<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use DateTime;

/**
 * Service for generating task insights and analytics
 */
class TaskInsightsService
{
    private TaskRepository $taskRepository;

    private UserRepository $userRepository;

    public function __construct(
        TaskRepository $taskRepository,
        UserRepository $userRepository,
    ) {
        $this->taskRepository = $taskRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Get productivity insights for a user
     */
    public function getProductivityInsights(User $user, int $days = 30): array
    {
        $startDate = new DateTime("-{$days} days");
        $endDate = new DateTime();

        // Get completion data
        $completionData = $this->taskRepository->getTaskCompletionTrendsByDate($user, $days);

        // Calculate productivity metrics
        $totalCompleted = 0;
        $dailyCompletions = [];
        $weeklyCompletions = [];

        foreach ($completionData as $dayData) {
            $totalCompleted += $dayData['completed'];
            $date = new DateTime($dayData['date']);
            $dayKey = $date->format('Y-m-d');
            $weekKey = $date->format('Y-W');

            $dailyCompletions[$dayKey] = $dayData['completed'];
            $weeklyCompletions[$weekKey] = ($weeklyCompletions[$weekKey] ?? 0) + $dayData['completed'];
        }

        // Get average completion rate
        $avgPerDay = $days > 0 ? round($totalCompleted / $days, 2) : 0;

        // Get most productive day
        $mostProductiveDay = !empty($dailyCompletions) ? array_keys($dailyCompletions, max($dailyCompletions))[0] : null;
        $maxDailyCompletion = !empty($dailyCompletions) ? max($dailyCompletions) : 0;

        // Get priority distribution
        $priorityStats = $this->taskRepository->getCompletionStatsByPriority();

        // Get overdue tasks
        $overdueTasks = $this->taskRepository->findUpcomingDeadlines(new \DateTimeImmutable());

        return [
            'period_days' => $days,
            'total_completed' => $totalCompleted,
            'average_per_day' => $avgPerDay,
            'most_productive_day' => [
                'date' => $mostProductiveDay,
                'completed' => $maxDailyCompletion,
            ],
            'priority_distribution' => $priorityStats,
            'overdue_tasks_count' => \count($overdueTasks),
            'productivity_score' => $this->calculateProductivityScore($avgPerDay, $priorityStats),
        ];
    }

    /**
     * Calculate a productivity score based on completion rates and priority handling
     */
    private function calculateProductivityScore(float $avgPerDay, array $priorityStats): int
    {
        $score = 0;

        // Base score on average daily completions
        $score += min($avgPerDay * 10, 50); // Up to 50 points for daily completion rate

        // Bonus for completing high priority tasks
        if (isset($priorityStats['high']) && $priorityStats['high']['total'] > 0) {
            $highPriorityCompletionRate = $priorityStats['high']['percentage'];
            $score += min($highPriorityCompletionRate * 0.3, 25); // Up to 25 points for high priority completion
        }

        // Bonus for completing urgent tasks
        if (isset($priorityStats['urgent']) && $priorityStats['urgent']['total'] > 0) {
            $urgentPriorityCompletionRate = $priorityStats['urgent']['percentage'];
            $score += min($urgentPriorityCompletionRate * 0.5, 25); // Up to 25 points for urgent priority completion
        }

        return min(100, (int)round($score));
    }

    /**
     * Get task completion trends
     */
    public function getCompletionTrends(User $user, int $days = 30): array
    {
        $trends = $this->taskRepository->getTaskCompletionTrendsByDate($user, $days);

        $dates = [];
        $completions = [];
        $totals = [];

        foreach ($trends as $trend) {
            $dates[] = $trend['date'];
            $completions[] = (int)$trend['completed'];
            $totals[] = (int)$trend['total'];
        }

        return [
            'dates' => $dates,
            'completions' => $completions,
            'totals' => $totals,
            'trend_analysis' => $this->analyzeTrend($completions),
        ];
    }

    /**
     * Analyze trend direction
     */
    private function analyzeTrend(array $values): string
    {
        if (\count($values) < 2) {
            return 'insufficient_data';
        }

        $recentValues = \array_slice($values, -7); // Last 7 values
        $earlyValues = \array_slice($values, -14, 7); // Previous 7 values

        if (empty($recentValues) || empty($earlyValues)) {
            return 'insufficient_data';
        }

        $recentAvg = array_sum($recentValues) / \count($recentValues);
        $earlyAvg = array_sum($earlyValues) / \count($earlyValues);

        $change = $recentAvg - $earlyAvg;
        $percentChange = $earlyAvg > 0 ? ($change / $earlyAvg) * 100 : 0;

        if ($percentChange > 10) {
            return 'increasing';
        } elseif ($percentChange < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Get task efficiency metrics
     */
    public function getEfficiencyMetrics(User $user): array
    {
        // Get all user's tasks
        $allTasks = $this->taskRepository->findByAssignedToOrCreatedBy($user);

        $onTimeCount = 0;
        $lateCount = 0;
        $totalWithDueDate = 0;

        foreach ($allTasks as $task) {
            if ($task->getDueDate() && $task->getCompletedAt()) {
                $totalWithDueDate++;

                if ($task->getCompletedAt() <= $task->getDueDate()) {
                    $onTimeCount++;
                } else {
                    $lateCount++;
                }
            }
        }

        $onTimeRate = $totalWithDueDate > 0 ? ($onTimeCount / $totalWithDueDate) * 100 : 0;

        // Average completion time by priority
        $avgCompletionTime = $this->taskRepository->getAverageCompletionTimeByPriority();

        return [
            'total_tasks_with_due_date' => $totalWithDueDate,
            'on_time_completions' => $onTimeCount,
            'late_completions' => $lateCount,
            'on_time_rate' => round($onTimeRate, 2),
            'average_completion_times' => $avgCompletionTime,
            'efficiency_rating' => $this->calculateEfficiencyRating($onTimeRate, $avgCompletionTime),
        ];
    }

    /**
     * Calculate efficiency rating
     */
    private function calculateEfficiencyRating(float $onTimeRate, array $avgCompletionTimes): string
    {
        if ($onTimeRate >= 90) {
            return 'excellent';
        } elseif ($onTimeRate >= 75) {
            return 'good';
        } elseif ($onTimeRate >= 60) {
            return 'average';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * Get user collaboration insights
     */
    public function getCollaborationInsights(User $user): array
    {
        // Оптимизированный запрос с JOIN для избежания N+1 проблемы
        $assignedToMeQuery = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u')
            ->leftJoin('t.user', 'u')
            ->where('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->getQuery();

        $assignedToMe = $assignedToMeQuery->getResult();

        // Оптимизированный запрос для задач, созданных пользователем
        $assignedByMeQuery = $this->taskRepository->createQueryBuilder('t')
            ->select('t, au')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.user = :user')
            ->andWhere('t.assignedUser IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery();

        $assignedByMe = $assignedByMeQuery->getResult();

        $collaborators = [];
        foreach ($assignedToMe as $task) {
            $creator = $task->getUser(); // Уже загружен через JOIN
            if ($creator && $creator->getId() !== $user->getId()) {
                $collaboratorId = $creator->getId();
                $collaborators[$collaboratorId] = [
                    'name' => $creator->getFullName(),
                    'tasks_received' => ($collaborators[$collaboratorId]['tasks_received'] ?? 0) + 1,
                    'completed_tasks' => ($collaborators[$collaboratorId]['completed_tasks'] ?? 0) + ($task->isCompleted() ? 1 : 0),
                ];
            }
        }

        $assignedToOthers = [];
        foreach ($assignedByMe as $task) {
            $assignedUser = $task->getAssignedUser(); // Уже загружен через JOIN
            if ($assignedUser && $assignedUser->getId() !== $user->getId()) {
                $assignedUserId = $assignedUser->getId();
                $assignedToOthers[$assignedUserId] = [
                    'name' => $assignedUser->getFullName(),
                    'tasks_assigned' => ($assignedToOthers[$assignedUserId]['tasks_assigned'] ?? 0) + 1,
                    'completed_tasks' => ($assignedToOthers[$assignedUserId]['completed_tasks'] ?? 0) + ($task->isCompleted() ? 1 : 0),
                ];
            }
        }

        return [
            'collaborators' => $collaborators,
            'assigned_to_others' => $assignedToOthers,
            'total_collaboration_score' => $this->calculateCollaborationScore($collaborators, $assignedToOthers),
        ];
    }

    /**
     * Calculate collaboration score
     */
    private function calculateCollaborationScore(array $collaborators, array $assignedToOthers): int
    {
        $score = 0;

        // Score based on number of collaborators
        $score += min(\count($collaborators) * 10, 30);

        // Score based on tasks received and completed
        foreach ($collaborators as $collab) {
            $score += min($collab['tasks_received'] * 2, 20); // Up to 20 points for tasks received
            $completionRate = $collab['tasks_received'] > 0 ? ($collab['completed_tasks'] / $collab['tasks_received']) * 100 : 0;
            $score += min($completionRate * 0.2, 25); // Up to 25 points for completion rate
        }

        // Score based on tasks assigned to others
        foreach ($assignedToOthers as $assignee) {
            $score += min($assignee['tasks_assigned'] * 1, 15); // Up to 15 points for tasks assigned
        }

        return min(100, (int)round($score));
    }

    /**
     * Get comprehensive dashboard insights
     */
    public function getDashboardInsights(User $user): array
    {
        return [
            'productivity' => $this->getProductivityInsights($user),
            'trends' => $this->getCompletionTrends($user),
            'efficiency' => $this->getEfficiencyMetrics($user),
            'collaboration' => $this->getCollaborationInsights($user),
            'summary' => [
                'overall_productivity_score' => $this->calculateOverallScore(
                    $this->getProductivityInsights($user)['productivity_score'],
                    $this->getEfficiencyMetrics($user)['on_time_rate'],
                ),
                'quick_stats' => $this->taskRepository->getQuickStats($user),
            ],
        ];
    }

    /**
     * Calculate overall score combining productivity and efficiency
     */
    private function calculateOverallScore(int $productivityScore, float $onTimeRate): int
    {
        // Weighted combination of productivity and efficiency
        $weightedScore = ($productivityScore * 0.6) + ($onTimeRate * 0.4);

        return min(100, (int)round($weightedScore));
    }
}
