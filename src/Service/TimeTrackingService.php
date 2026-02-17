<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TimeTrackingService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Start time tracking for task
     */
    public function startTracking(Task $task, User $user): array
    {
        $session = [
            'task_id' => $task->getId(),
            'user_id' => $user->getId(),
            'started_at' => new \DateTime(),
            'status' => 'active'
        ];

        // Store in session for now
        $_SESSION['time_tracking'] = $session;

        return $session;
    }

    /**
     * Stop time tracking
     */
    public function stopTracking(): ?array
    {
        if (!isset($_SESSION['time_tracking'])) {
            return null;
        }

        $session = $_SESSION['time_tracking'];
        $session['stopped_at'] = new \DateTime();
        $session['status'] = 'stopped';
        
        $duration = $session['stopped_at']->getTimestamp() - $session['started_at']->getTimestamp();
        $session['duration'] = $duration;

        // TODO: Save to database
        
        unset($_SESSION['time_tracking']);

        return $session;
    }

    /**
     * Get active tracking session
     */
    public function getActiveSession(): ?array
    {
        return $_SESSION['time_tracking'] ?? null;
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
            'by_category' => []
        ];
    }

    /**
     * Format duration
     */
    public function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dч %dм', $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf('%dм %dс', $minutes, $secs);
        } else {
            return sprintf('%dс', $secs);
        }
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
                'days' => $from->diff($to)->days
            ],
            'summary' => [
                'total_time' => $stats['total_time'],
                'total_time_formatted' => $this->formatDuration($stats['total_time']),
                'tasks_tracked' => $stats['tasks_tracked'],
                'average_per_task' => $stats['average_per_task'],
                'average_per_task_formatted' => $this->formatDuration($stats['average_per_task']),
                'average_per_day' => $stats['total_time'] / max(1, $from->diff($to)->days),
                'productivity_score' => $this->getProductivityScore($user, $from->diff($to)->days)
            ],
            'by_day' => $stats['by_day'],
            'by_category' => $stats['by_category']
        ];
    }
}
