<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReportService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Generate productivity report for user
     */
    public function generateProductivityReport(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        $completed = array_filter($tasks, fn ($t) => $t->getStatus() === 'completed');
        $inProgress = array_filter($tasks, fn ($t) => $t->getStatus() === 'in_progress');
        $pending = array_filter($tasks, fn ($t) => $t->getStatus() === 'pending');
        $cancelled = array_filter($tasks, fn ($t) => $t->getStatus() === 'cancelled');

        $totalTasks = \count($tasks);
        $completionRate = $totalTasks > 0 ? (\count($completed) / $totalTasks) * 100 : 0;

        $completionTimes = [];
        foreach ($completed as $task) {
            if ($task->getCreatedAt() && $task->getUpdatedAt()) {
                $diff = $task->getUpdatedAt()->getTimestamp() - $task->getCreatedAt()->getTimestamp();
                $completionTimes[] = $diff / 86400;
            }
        }
        $avgCompletionTime = !empty($completionTimes) ? array_sum($completionTimes) / \count($completionTimes) : 0;

        $priorityDistribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'urgent' => 0,
        ];

        foreach ($tasks as $task) {
            $priority = $task->getPriority();
            if (isset($priorityDistribution[$priority])) {
                $priorityDistribution[$priority]++;
            }
        }

        $tasksByDayOfWeek = array_fill(1, 7, 0);
        foreach ($tasks as $task) {
            $dayOfWeek = (int)$task->getCreatedAt()->format('N');
            $tasksByDayOfWeek[$dayOfWeek]++;
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $startDate->diff($endDate)->days,
            ],
            'summary' => [
                'total_tasks' => $totalTasks,
                'completed' => \count($completed),
                'in_progress' => \count($inProgress),
                'pending' => \count($pending),
                'cancelled' => \count($cancelled),
                'completion_rate' => round($completionRate, 2),
                'avg_completion_time_days' => round($avgCompletionTime, 2),
            ],
            'priority_distribution' => $priorityDistribution,
            'tasks_by_day_of_week' => $tasksByDayOfWeek,
            'productivity_score' => $this->calculateProductivityScore($completionRate, $avgCompletionTime, $totalTasks),
        ];
    }

    /**
     * Generate team performance report
     */
    public function generateTeamReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('u.id, u.firstName, u.lastName, u.email, COUNT(t.id) as total_tasks,
                     SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks')
            ->leftJoin('t.assignedUser', 'u')
            ->where('t.createdAt BETWEEN :start AND :end')
            ->andWhere('u.id IS NOT NULL')
            ->groupBy('u.id, u.firstName, u.lastName, u.email')
            ->orderBy('completed_tasks', 'DESC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('completed', 'completed');

        $results = $qb->getQuery()->getResult();

        $teamMembers = [];
        foreach ($results as $result) {
            $completionRate = $result['total_tasks'] > 0
                ? ($result['completed_tasks'] / $result['total_tasks']) * 100
                : 0;

            $teamMembers[] = [
                'user_id' => $result['id'],
                'name' => trim($result['firstName'] . ' ' . $result['lastName']),
                'email' => $result['email'],
                'total_tasks' => (int)$result['total_tasks'],
                'completed_tasks' => (int)$result['completed_tasks'],
                'completion_rate' => round($completionRate, 2),
            ];
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'team_members' => $teamMembers,
            'team_summary' => [
                'total_members' => \count($teamMembers),
                'total_tasks' => array_sum(array_column($teamMembers, 'total_tasks')),
                'total_completed' => array_sum(array_column($teamMembers, 'completed_tasks')),
                'avg_completion_rate' => \count($teamMembers) > 0
                    ? round(array_sum(array_column($teamMembers, 'completion_rate')) / \count($teamMembers), 2)
                    : 0,
            ],
        ];
    }

    /**
     * Generate overdue tasks report
     */
    public function generateOverdueReport(): array
    {
        $now = new \DateTime();

        $overdueTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->andWhere('t.status != :cancelled')
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->setParameter('cancelled', 'cancelled')
            ->orderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        $groupedByUser = [];
        foreach ($overdueTasks as $task) {
            $userId = $task->getAssignedUser() ? $task->getAssignedUser()->getId() : 'unassigned';
            $userName = $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : 'Не назначено';

            if (!isset($groupedByUser[$userId])) {
                $groupedByUser[$userId] = [
                    'user_name' => $userName,
                    'tasks' => [],
                ];
            }

            $daysOverdue = $now->diff($task->getDeadline())->days;

            $groupedByUser[$userId]['tasks'][] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'priority' => $task->getPriority(),
                'deadline' => $task->getDeadline()->format('Y-m-d H:i'),
                'days_overdue' => $daysOverdue,
            ];
        }

        return [
            'total_overdue' => \count($overdueTasks),
            'grouped_by_user' => array_values($groupedByUser),
            'generated_at' => $now->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport(array $config, User $user): array
    {
        $data = [];

        foreach ($config['metrics'] ?? [] as $metric) {
            $data[$metric] = $this->calculateMetric($metric, $config, $user);
        }

        return [
            'title' => $config['title'] ?? 'Отчет',
            'period' => $config['period'] ?? 'month',
            'data' => $data,
            'charts' => $this->generateCharts($data, $config),
            'generated_at' => new \DateTime(),
        ];
    }

    /**
     * Get predefined reports
     */
    public function getPredefinedReports(): array
    {
        return [
            'daily_summary' => [
                'title' => 'Ежедневная сводка',
                'icon' => 'calendar-day',
                'period' => 'today',
                'metrics' => ['total_tasks', 'completed_tasks', 'completion_rate'],
            ],
            'weekly_performance' => [
                'title' => 'Недельная производительность',
                'icon' => 'chart-line',
                'period' => 'week',
                'metrics' => ['total_tasks', 'completed_tasks', 'completion_rate', 'productivity_score'],
            ],
            'monthly_overview' => [
                'title' => 'Месячный обзор',
                'icon' => 'chart-bar',
                'period' => 'month',
                'metrics' => ['total_tasks', 'completed_tasks', 'tasks_by_priority', 'tasks_by_status'],
            ],
            'overdue_tasks' => [
                'title' => 'Просроченные задачи',
                'icon' => 'exclamation-triangle',
                'period' => 'all',
                'metrics' => ['overdue_tasks'],
            ],
        ];
    }

    /**
     * Calculate metric
     */
    private function calculateMetric(string $metric, array $config, User $user): mixed
    {
        $from = $this->getPeriodStart($config['period'] ?? 'month');
        $to = new \DateTime();

        return match($metric) {
            'total_tasks' => $this->getTotalTasks($user, $from, $to),
            'completed_tasks' => $this->getCompletedTasks($user, $from, $to),
            'completion_rate' => $this->getCompletionRate($user, $from, $to),
            'overdue_tasks' => $this->getOverdueTasksCount($user, $from, $to),
            'tasks_by_priority' => $this->getTasksByPriority($user, $from, $to),
            'tasks_by_status' => $this->getTasksByStatus($user, $from, $to),
            'productivity_score' => $this->getProductivityScore($user, $from, $to),
            default => null
        };
    }

    /**
     * Get period start date
     */
    private function getPeriodStart(string $period): \DateTime
    {
        return match($period) {
            'today' => new \DateTime('today'),
            'week' => new \DateTime('monday this week'),
            'month' => new \DateTime('first day of this month'),
            'quarter' => new \DateTime('first day of -3 months'),
            'year' => new \DateTime('first day of january this year'),
            'all' => new \DateTime('2000-01-01'),
            default => new \DateTime('first day of this month')
        };
    }

    private function getTotalTasks(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getCompletedTasks(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getCompletionRate(User $user, \DateTime $from, \DateTime $to): float
    {
        $total = $this->getTotalTasks($user, $from, $to);
        if ($total === 0) {
            return 0;
        }

        $completed = $this->getCompletedTasks($user, $from, $to);
        return round(($completed / $total) * 100, 2);
    }

    private function getOverdueTasksCount(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->andWhere('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTasksByPriority(User $user, \DateTime $from, \DateTime $to): array
    {
        $results = $this->taskRepository->createQueryBuilder('t')
            ->select('t.priority, COUNT(t.id) as count')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->groupBy('t.priority')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['priority']] = (int)$result['count'];
        }

        return $data;
    }

    private function getTasksByStatus(User $user, \DateTime $from, \DateTime $to): array
    {
        $results = $this->taskRepository->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as count')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->groupBy('t.status')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['status']] = (int)$result['count'];
        }

        return $data;
    }

    private function getProductivityScore(User $user, \DateTime $from, \DateTime $to): int
    {
        $completed = $this->getCompletedTasks($user, $from, $to);
        $overdue = $this->getOverdueTasksCount($user, $from, $to);

        return max(0, $completed - $overdue);
    }

    private function generateCharts(array $data, array $config): array
    {
        $charts = [];

        if (isset($data['tasks_by_priority'])) {
            $charts['priority_chart'] = [
                'type' => 'pie',
                'data' => $data['tasks_by_priority'],
                'title' => 'Задачи по приоритету',
            ];
        }

        if (isset($data['tasks_by_status'])) {
            $charts['status_chart'] = [
                'type' => 'bar',
                'data' => $data['tasks_by_status'],
                'title' => 'Задачи по статусу',
            ];
        }

        return $charts;
    }

    private function calculateProductivityScore(float $completionRate, float $avgCompletionTime, int $totalTasks): float
    {
        $completionScore = $completionRate * 0.5;

        $speedScore = 0;
        if ($avgCompletionTime > 0) {
            $speedScore = min(100, (3 / $avgCompletionTime) * 100) * 0.3;
        }

        $volumeScore = min(100, ($totalTasks / 10) * 100) * 0.2;

        return round($completionScore + $speedScore + $volumeScore, 2);
    }
}
