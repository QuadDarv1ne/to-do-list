<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskTimeTracking;
use App\Entity\User;
use App\Repository\TaskTimeTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TimeTrackingService
{
    public function __construct(
        private TaskTimeTrackingRepository $timeTrackingRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Start time tracking for task
     */
    public function startTracking(Task $task, User $user, ?string $description = null): TaskTimeTracking
    {
        // Stop any active sessions for this user
        $this->stopAllActiveSessions($user);

        $tracking = new TaskTimeTracking();
        $tracking->setUser($user);
        $tracking->setTask($task);
        $tracking->setDescription($description);
        $tracking->start();

        $this->entityManager->persist($tracking);
        $this->entityManager->flush();

        $this->logger->info('Time tracking started', [
            'tracking_id' => $tracking->getId(),
            'task_id' => $task->getId(),
            'user_id' => $user->getId(),
        ]);

        return $tracking;
    }

    /**
     * Stop time tracking for specific session
     */
    public function stopTracking(TaskTimeTracking $tracking): TaskTimeTracking
    {
        $tracking->stop();
        $this->entityManager->flush();

        $this->logger->info('Time tracking stopped', [
            'tracking_id' => $tracking->getId(),
            'duration_seconds' => $tracking->getDurationSeconds(),
        ]);

        return $tracking;
    }

    /**
     * Stop time tracking by tracking ID
     */
    public function stopTrackingById(int $trackingId): ?TaskTimeTracking
    {
        $tracking = $this->timeTrackingRepository->find($trackingId);

        if ($tracking === null) {
            return null;
        }

        return $this->stopTracking($tracking);
    }

    /**
     * Stop all active sessions for user
     */
    public function stopAllActiveSessions(User $user): int
    {
        $activeSessions = $this->timeTrackingRepository->findActiveByUser($user);
        $count = 0;

        foreach ($activeSessions as $session) {
            /** @var TaskTimeTracking $session */
            $this->stopTracking($session);
            $count++;
        }

        return $count;
    }

    /**
     * Get active tracking session for user
     */
    public function getActiveSession(User $user): ?TaskTimeTracking
    {
        return $this->timeTrackingRepository->findOneActiveByUser($user);
    }

    /**
     * Get active session for specific task
     */
    public function getActiveSessionForTask(Task $task, User $user): ?TaskTimeTracking
    {
        return $this->timeTrackingRepository->findOneActiveByTaskAndUser($task, $user);
    }

    /**
     * Get time spent on task (total seconds)
     */
    public function getTimeSpent(Task $task): int
    {
        return $this->timeTrackingRepository->getTotalTimeByTask($task);
    }

    /**
     * Get time tracking statistics for user
     */
    public function getStatistics(User $user, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $from = $from ?? (new \DateTime())->modify('-7 days');
        $to = $to ?? new \DateTime();

        $trackings = $this->timeTrackingRepository->findByUserAndDateRange($user, $from, $to);

        $totalTime = 0;
        $tasksTracked = [];
        $byDay = [];
        $byCategory = [];

        foreach ($trackings as $tracking) {
            /** @var TaskTimeTracking $tracking */
            $duration = $tracking->getDurationSeconds();
            $totalTime += $duration;

            $taskId = $tracking->getTask()->getId();
            // Use empty string for null task IDs to avoid deprecation
            $taskKey = $taskId ?? '';
            if (!isset($tasksTracked[$taskKey])) {
                $tasksTracked[$taskKey] = [
                    'task' => $tracking->getTask(),
                    'time' => 0,
                ];
            }
            $tasksTracked[$taskKey]['time'] += $duration;

            // Group by day
            $dayKey = $tracking->getDateLogged()->format('Y-m-d');
            if (!isset($byDay[$dayKey])) {
                $byDay[$dayKey] = 0;
            }
            $byDay[$dayKey] += $duration;

            // Group by category
            $category = $tracking->getTask()->getCategory();
            $categoryName = $category ? ($category->getName() ?? 'Без категории') : 'Без категории';
            if (!isset($byCategory[$categoryName])) {
                $byCategory[$categoryName] = 0;
            }
            $byCategory[$categoryName] += $duration;
        }

        $tasksCount = count($tasksTracked);
        $avgPerTask = $tasksCount > 0 ? intdiv($totalTime, $tasksCount) : 0;
        $daysCount = max(1, $from->diff($to)->days + 1);
        $avgPerDay = intdiv($totalTime, $daysCount);

        return [
            'total_time' => $totalTime,
            'total_time_formatted' => $this->formatDuration($totalTime),
            'tasks_tracked' => $tasksCount,
            'average_per_task' => $avgPerTask,
            'average_per_task_formatted' => $this->formatDuration($avgPerTask),
            'average_per_day' => $avgPerDay,
            'average_per_day_formatted' => $this->formatDuration($avgPerDay),
            'by_day' => $byDay,
            'by_category' => $byCategory,
            'tasks' => $tasksTracked,
            'period' => [
                'from' => $from,
                'to' => $to,
                'days' => $daysCount,
            ],
        ];
    }

    /**
     * Format duration in seconds to human readable format
     */
    public function formatDuration(int $seconds): string
    {
        return TaskTimeTracking::formatDuration($seconds);
    }

    /**
     * Get productivity score based on time tracking
     */
    public function getProductivityScore(User $user, int $days = 7): float
    {
        $from = new \DateTime("-{$days} days");
        $to = new \DateTime();

        $stats = $this->getStatistics($user, $from, $to);

        // Calculate score based on multiple factors
        $score = 0;

        // Factor 1: Total time tracked (max 40 points)
        // Assume 8 hours per day = 28800 seconds
        $expectedTime = 28800 * $days;
        $timeRatio = min(1, $stats['total_time'] / $expectedTime);
        $score += $timeRatio * 40;

        // Factor 2: Tasks completed (max 40 points)
        // Assume 5 tasks per day
        $expectedTasks = 5 * $days;
        $taskRatio = min(1, $stats['tasks_tracked'] / $expectedTasks);
        $score += $taskRatio * 40;

        // Factor 3: Consistency (max 20 points)
        // Check if time was tracked on most days
        $daysWithTracking = count($stats['by_day']);
        $consistencyRatio = min(1, $daysWithTracking / $days);
        $score += $consistencyRatio * 20;

        return round(min(100, max(0, $score)), 2);
    }

    /**
     * Get time tracking report
     */
    public function getReport(User $user, \DateTime $from, \DateTime $to): array
    {
        $stats = $this->getStatistics($user, $from, $to);

        return [
            'period' => [
                'from' => $from->format('d.m.Y'),
                'to' => $to->format('d.m.Y'),
                'days' => $stats['period']['days'],
            ],
            'summary' => [
                'total_time' => $stats['total_time'],
                'total_time_formatted' => $stats['total_time_formatted'],
                'tasks_tracked' => $stats['tasks_tracked'],
                'average_per_task' => $stats['average_per_task'],
                'average_per_task_formatted' => $stats['average_per_task_formatted'],
                'average_per_day' => $stats['average_per_day'],
                'average_per_day_formatted' => $stats['average_per_day_formatted'],
                'productivity_score' => $this->getProductivityScore($user, $stats['period']['days']),
            ],
            'by_day' => $stats['by_day'],
            'by_category' => $stats['by_category'],
            'tasks' => $stats['tasks'],
        ];
    }

    /**
     * Get today's tracking summary
     */
    public function getTodaySummary(User $user): array
    {
        $today = new \DateTime('today');
        $now = new \DateTime();

        $stats = $this->getStatistics($user, $today, $now);

        $activeSession = $this->getActiveSession($user);
        $currentSessionTime = 0;
        
        if ($activeSession !== null) {
            $currentSessionTime = $activeSession->calculateDuration();
        }

        return [
            'total_time' => $stats['total_time'] + $currentSessionTime,
            'total_time_formatted' => $this->formatDuration($stats['total_time'] + $currentSessionTime),
            'tasks_tracked' => $stats['tasks_tracked'],
            'active_session' => $activeSession,
            'current_session_time' => $currentSessionTime,
            'current_session_formatted' => $this->formatDuration($currentSessionTime),
        ];
    }

    /**
     * Get weekly summary
     */
    public function getWeeklySummary(User $user): array
    {
        $monday = new \DateTime('monday this week');
        $sunday = new \DateTime('sunday this week');

        return $this->getStatistics($user, $monday, $sunday);
    }

    /**
     * Get monthly summary
     */
    public function getMonthlySummary(User $user): array
    {
        $firstDay = new \DateTime('first day of this month');
        $lastDay = new \DateTime('last day of this month');

        return $this->getStatistics($user, $firstDay, $lastDay);
    }

    /**
     * Toggle tracking (start/stop)
     */
    public function toggleTracking(Task $task, User $user): array
    {
        $activeSession = $this->getActiveSessionForTask($task, $user);

        if ($activeSession !== null) {
            $this->stopTracking($activeSession);
            return [
                'action' => 'stopped',
                'tracking' => $activeSession,
                'duration' => $activeSession->getDurationSeconds(),
                'duration_formatted' => $activeSession->getFormattedDuration(),
            ];
        }

        $newTracking = $this->startTracking($task, $user);
        return [
            'action' => 'started',
            'tracking' => $newTracking,
        ];
    }

    /**
     * Get recent tracking sessions
     */
    public function getRecentSessions(User $user, int $limit = 10): array
    {
        return $this->timeTrackingRepository->findByUser($user, ['id' => 'DESC'], $limit);
    }

    /**
     * Delete tracking session
     */
    public function deleteSession(TaskTimeTracking $session): bool
    {
        if ($session->isActive()) {
            $this->stopTracking($session);
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Update session description
     */
    public function updateSessionDescription(TaskTimeTracking $session, string $description): TaskTimeTracking
    {
        $session->setDescription($description);
        $this->entityManager->flush();

        return $session;
    }
}
