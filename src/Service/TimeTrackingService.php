<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TimeTrackingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Start time tracking for task
     */
    public function startTracking(Task $task, User $user): array
    {
        $session = [
            'task_id' => $task->getId(),
            'user_id' => $user->getId(),
            'started_at' => new \DateTime(),
            'status' => 'active',
        ];

        // TODO: Store in database instead of session
        // For now, this is a placeholder implementation

        return $session;
    }

    /**
     * Stop time tracking
     */
    /**
     * Stop time tracking
     */
    public function stopTracking(): ?array
    {
        // TODO: Implement database-based tracking
        // This is a placeholder implementation

        return null;
    }

    /**
     * Get active tracking session
     */
    /**
     * Get active tracking session
     */
    public function getActiveSession(): ?array
    {
        // TODO: Get from database
        return null;
    }

    /**
     * Get time spent on task
     */
    public function getTimeSpent(Task $task): int
    {
        // TODO: Get from database
        // For now, return 0
        return 0;
    }

    /**
     * Get time tracking statistics
     */
    public function getStatistics(User $user, \DateTime $from, \DateTime $to): array
    {
        // TODO: Implement database queries
        return [
            'total_time' => 0,
            'tasks_tracked' => 0,
            'average_per_task' => 0,
            'by_day' => [],
            'by_category' => [],
        ];
    }

    /**
     * Format duration
     */
    public function formatDuration(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}д";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}ч";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}м";
        }
        if ($secs > 0 || empty($parts)) {
            $parts[] = "{$secs}с";
        }

        return implode(' ', $parts);
    }

    /**
     * Get productivity score based on time tracking
     */
    public function getProductivityScore(User $user, int $days = 7): float
    {
        $from = new \DateTime("-{$days} days");
        $to = new \DateTime();

        $stats = $this->getStatistics($user, $from, $to);

        // Calculate score based on:
        // - Total time tracked
        // - Number of tasks completed
        // - Average time per task

        $score = 0;

        // TODO: Implement scoring algorithm

        return min(100, max(0, $score));
    }

    /**
     * Get time tracking report
     */
    public function getReport(User $user, \DateTime $from, \DateTime $to): array
    {
        $stats = $this->getStatistics($user, $from, $to);

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
                'days' => $from->diff($to)->days,
            ],
            'summary' => [
                'total_time' => $stats['total_time'],
                'total_time_formatted' => $this->formatDuration($stats['total_time']),
                'tasks_tracked' => $stats['tasks_tracked'],
                'average_per_task' => $stats['average_per_task'],
                'average_per_task_formatted' => $this->formatDuration($stats['average_per_task']),
                'average_per_day' => $stats['total_time'] / max(1, $from->diff($to)->days),
                'productivity_score' => $this->getProductivityScore($user, $from->diff($to)->days),
            ],
            'by_day' => $stats['by_day'],
            'by_category' => $stats['by_category'],
        ];
    }
}
