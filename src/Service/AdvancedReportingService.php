<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdvancedReportingService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport(array $config, User $user): array
    {
        $data = [];

        foreach ($config['metrics'] as $metric) {
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
            'average_time' => $this->getAverageCompletionTime($user, $from, $to),
            'overdue_tasks' => $this->getOverdueTasks($user, $from, $to),
            'tasks_by_priority' => $this->getTasksByPriority($user, $from, $to),
            'tasks_by_status' => $this->getTasksByStatus($user, $from, $to),
            'tasks_by_category' => $this->getTasksByCategory($user, $from, $to),
            'productivity_score' => $this->getProductivityScore($user, $from, $to),
            'team_performance' => $this->getTeamPerformance($from, $to),
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
            default => new \DateTime('first day of this month')
        };
    }

    /**
     * Get total tasks
     */
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

    /**
     * Get completed tasks
     */
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

    /**
     * Get completion rate
     */
    private function getCompletionRate(User $user, \DateTime $from, \DateTime $to): float
    {
        $total = $this->getTotalTasks($user, $from, $to);
        if ($total === 0) {
            return 0;
        }

        $completed = $this->getCompletedTasks($user, $from, $to);

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average completion time
     */
    private function getAverageCompletionTime(User $user, \DateTime $from, \DateTime $to): float
    {
        // TODO: Calculate average time from creation to completion
        return 0;
    }

    /**
     * Get overdue tasks
     */
    private function getOverdueTasks(User $user, \DateTime $from, \DateTime $to): int
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

    /**
     * Get tasks by priority
     */
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

    /**
     * Get tasks by status
     */
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

    /**
     * Get tasks by category
     */
    private function getTasksByCategory(User $user, \DateTime $from, \DateTime $to): array
    {
        $results = $this->taskRepository->createQueryBuilder('t')
            ->select('c.name, COUNT(t.id) as count')
            ->leftJoin('t.category', 'c')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->groupBy('c.id, c.name')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['name'] ?? 'Без категории'] = (int)$result['count'];
        }

        return $data;
    }

    /**
     * Get productivity score
     */
    private function getProductivityScore(User $user, \DateTime $from, \DateTime $to): int
    {
        $completed = $this->getCompletedTasks($user, $from, $to);
        $overdue = $this->getOverdueTasks($user, $from, $to);

        // Score: completed tasks - overdue tasks
        return max(0, $completed - $overdue);
    }

    /**
     * Get team performance
     */
    private function getTeamPerformance(\DateTime $from, \DateTime $to): array
    {
        // TODO: Get all users performance
        return [];
    }

    /**
     * Generate charts
     */
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

        if (isset($data['tasks_by_category'])) {
            $charts['category_chart'] = [
                'type' => 'doughnut',
                'data' => $data['tasks_by_category'],
                'title' => 'Задачи по категориям',
            ];
        }

        return $charts;
    }

    /**
     * Export report to PDF
     */
    public function exportToPDF(array $report): string
    {
        // TODO: Generate PDF
        return '';
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel(array $report): string
    {
        // TODO: Generate Excel
        return '';
    }

    /**
     * Schedule report
     */
    public function scheduleReport(array $config, User $user, string $frequency): array
    {
        // TODO: Save scheduled report to database
        return [
            'id' => uniqid(),
            'config' => $config,
            'user_id' => $user->getId(),
            'frequency' => $frequency,
            'next_run' => new \DateTime('+1 ' . $frequency),
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
                'period' => 'today',
                'metrics' => ['total_tasks', 'completed_tasks', 'completion_rate'],
            ],
            'weekly_performance' => [
                'title' => 'Недельная производительность',
                'period' => 'week',
                'metrics' => ['total_tasks', 'completed_tasks', 'completion_rate', 'productivity_score'],
            ],
            'monthly_overview' => [
                'title' => 'Месячный обзор',
                'period' => 'month',
                'metrics' => ['total_tasks', 'completed_tasks', 'tasks_by_priority', 'tasks_by_status', 'tasks_by_category'],
            ],
            'team_report' => [
                'title' => 'Отчет команды',
                'period' => 'month',
                'metrics' => ['team_performance', 'total_tasks', 'completion_rate'],
            ],
        ];
    }
}
