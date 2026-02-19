<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class TaskStatisticsService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * Get comprehensive statistics
     */
    public function getComprehensiveStats(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'overview' => $this->getOverviewStats($user, $from, $to),
            'by_status' => $this->getStatsByStatus($user, $from, $to),
            'by_priority' => $this->getStatsByPriority($user, $from, $to),
            'by_category' => $this->getStatsByCategory($user, $from, $to),
            'by_day' => $this->getStatsByDay($user, $from, $to),
            'by_week' => $this->getStatsByWeek($user, $from, $to),
            'by_month' => $this->getStatsByMonth($user, $from, $to),
            'completion_rate' => $this->getCompletionRate($user, $from, $to),
            'average_completion_time' => $this->getAverageCompletionTime($user, $from, $to),
            'productivity_trend' => $this->getProductivityTrend($user, $from, $to),
        ];
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(User $user, \DateTime $from, \DateTime $to): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $total = (int)$qb->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        $completed = (int)$qb->select('COUNT(t.id)')
            ->andWhere('t.status = :completed')
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $inProgress = (int)$qb->select('COUNT(t.id)')
            ->andWhere('t.status = :in_progress')
            ->setParameter('in_progress', 'in_progress')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int)$qb->select('COUNT(t.id)')
            ->andWhere('t.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get statistics by status
     */
    private function getStatsByStatus(User $user, \DateTime $from, \DateTime $to): array
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

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['status']] = (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by priority
     */
    private function getStatsByPriority(User $user, \DateTime $from, \DateTime $to): array
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

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['priority']] = (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by category
     */
    private function getStatsByCategory(User $user, \DateTime $from, \DateTime $to): array
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

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['name'] ?? 'Без категории'] = (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by day
     */
    private function getStatsByDay(User $user, \DateTime $from, \DateTime $to): array
    {
        $stats = [];
        $current = clone $from;

        while ($current <= $to) {
            $nextDay = (clone $current)->modify('+1 day');

            $count = (int)$this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $current)
                ->setParameter('end', $nextDay)
                ->getQuery()
                ->getSingleScalarResult();

            $stats[$current->format('Y-m-d')] = $count;
            $current = $nextDay;
        }

        return $stats;
    }

    /**
     * Get statistics by week
     */
    private function getStatsByWeek(User $user, \DateTime $from, \DateTime $to): array
    {
        $stats = [];
        $current = clone $from;
        $current->modify('monday this week');

        while ($current <= $to) {
            $weekEnd = (clone $current)->modify('+6 days');

            $count = (int)$this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $current)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $weekKey = $current->format('Y-W');
            $stats[$weekKey] = $count;

            $current->modify('+7 days');
        }

        return $stats;
    }

    /**
     * Get statistics by month
     */
    private function getStatsByMonth(User $user, \DateTime $from, \DateTime $to): array
    {
        $stats = [];
        $current = clone $from;
        $current->modify('first day of this month');

        while ($current <= $to) {
            $monthEnd = (clone $current)->modify('last day of this month');

            $count = (int)$this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $current)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $monthKey = $current->format('Y-m');
            $stats[$monthKey] = $count;

            $current->modify('first day of next month');
        }

        return $stats;
    }

    /**
     * Get completion rate
     */
    private function getCompletionRate(User $user, \DateTime $from, \DateTime $to): float
    {
        $overview = $this->getOverviewStats($user, $from, $to);

        return $overview['completion_rate'];
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
     * Get productivity trend
     */
    private function getProductivityTrend(User $user, \DateTime $from, \DateTime $to): string
    {
        // TODO: Calculate trend (increasing/decreasing/stable)
        return 'stable';
    }

    /**
     * Compare periods
     */
    public function comparePeriods(User $user, \DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): array
    {
        $stats1 = $this->getOverviewStats($user, $period1Start, $period1End);
        $stats2 = $this->getOverviewStats($user, $period2Start, $period2End);

        return [
            'period1' => $stats1,
            'period2' => $stats2,
            'difference' => [
                'total' => $stats2['total'] - $stats1['total'],
                'completed' => $stats2['completed'] - $stats1['completed'],
                'completion_rate' => $stats2['completion_rate'] - $stats1['completion_rate'],
            ],
        ];
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(int $limit = 10): array
    {
        // TODO: Get users with highest completion rates
        return [];
    }

    /**
     * Export statistics to CSV
     */
    public function exportToCSV(array $stats): string
    {
        $csv = "Метрика,Значение\n";

        foreach ($stats['overview'] as $key => $value) {
            $csv .= "$key,$value\n";
        }

        return $csv;
    }
}
