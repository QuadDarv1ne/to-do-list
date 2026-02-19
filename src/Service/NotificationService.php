<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationService
{
    private EntityManagerInterface $entityManager;

    private LoggerInterface $logger;

    private NotificationRepository $notificationRepository;

    private ?PerformanceMonitorService $performanceMonitor;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        NotificationRepository $notificationRepository,
        ?PerformanceMonitorService $performanceMonitor = null,
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->notificationRepository = $notificationRepository;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Create a notification for a user
     */
    public function createNotification(
        User $user,
        string $title,
        string $message,
        ?Task $task = null,
    ): Notification {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_create_notification');
        }

        try {
            $notification = new Notification();
            $notification->setUser($user);
            // XSS protection: escape HTML characters in title and message
            $notification->setTitle(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
            $notification->setMessage(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $notification->setIsRead(false);
            if ($task) {
                $notification->setTask($task);
            }

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->logger->info("Created notification for user {$user->getId()}: {$title}");

            return $notification;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_create_notification');
            }
        }
    }

    /**
     * Create task-related notification
     */
    public function createTaskNotification(
        User $user,
        string $title,
        string $message,
        int $taskId,
        ?string $taskTitle = null,
    ): Notification {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_create_task_notification');
        }

        try {
            // For now, we'll create a basic notification
            // In a real implementation, you might want to extend the Notification entity
            return $this->createNotification($user, htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), null);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_create_task_notification');
            }
        }
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(User $user): array
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_get_unread_notifications');
        }

        try {
            return $this->notificationRepository->findBy([
                'user' => $user,
                'isRead' => false,
            ], ['createdAt' => 'DESC']);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_get_unread_notifications');
            }
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_mark_as_read');
        }

        try {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_mark_as_read');
            }
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): int
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_mark_all_as_read');
        }

        try {
            $unreadNotifications = $this->getUnreadNotifications($user);
            $count = \count($unreadNotifications);

            foreach ($unreadNotifications as $notification) {
                $notification->setIsRead(true);
            }

            $this->entityManager->flush();

            return $count;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_mark_all_as_read');
            }
        }
    }

    /**
     * Server-Sent Events stream for real-time notifications
     * Fixed to prevent infinite loops and page lag
     */
    public function createNotificationStream(User $user): StreamedResponse
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_create_notification_stream');
        }

        try {
            $response = new StreamedResponse(function () use ($user) {
                // Send headers
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('Access-Control-Allow-Origin: *');

                // Send initial connection confirmation
                echo "event: connected\n";
                echo 'data: ' . json_encode(['status' => 'connected', 'user_id' => $user->getId()]) . "\n\n";
                flush();

                $lastCheck = new \DateTime();
                $heartbeatInterval = 45; // seconds
                $checkInterval = 15; // seconds
                $maxDuration = 900; // 15 minutes maximum connection time
                $startTime = time();

                while ((time() - $startTime) < $maxDuration && !connection_aborted()) {
                    // Check for new notifications
                    $newNotifications = $this->getNewNotifications($user, $lastCheck);

                    if (!empty($newNotifications)) {
                        foreach ($newNotifications as $notification) {
                            // Handle both object and array formats for backward compatibility
                            $id = \is_object($notification) ? $notification->getId() : $notification['id'];
                            $title = \is_object($notification) ? htmlspecialchars($notification->getTitle(), ENT_QUOTES, 'UTF-8') : htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8');
                            $message = \is_object($notification) ? htmlspecialchars($notification->getMessage(), ENT_QUOTES, 'UTF-8') : htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8');
                            $createdAt = \is_object($notification) ? $notification->getCreatedAt() : new \DateTime($notification['createdAt']);
                            $taskId = \is_object($notification) ? ($notification->getTask() ? $notification->getTask()->getId() : null) : ($notification['taskId'] ?? null);

                            echo "event: notification\n";
                            echo 'data: ' . json_encode([
                                'id' => $id,
                                'title' => $title,
                                'message' => $message,
                                'task_id' => $taskId,
                                'created_at' => $createdAt->format('c'),
                            ]) . "\n\n";
                            if (ob_get_level()) {
                                ob_flush();
                            }
                            flush();
                        }

                        $lastCheck = new \DateTime();
                    }

                    // Send heartbeat to keep connection alive
                    $elapsed = time() - $startTime;
                    if ($elapsed % $heartbeatInterval === 0) {
                        echo "event: heartbeat\n";
                        echo 'data: ' . json_encode(['time' => date('c'), 'elapsed' => $elapsed]) . "\n\n";
                        flush();
                    }

                    // Sleep with connection check
                    $remainingSleep = $checkInterval;
                    $chunkSize = 2;

                    while ($remainingSleep > 0 && !connection_aborted()) {
                        $sleepChunk = min($chunkSize, $remainingSleep);
                        sleep($sleepChunk);
                        $remainingSleep -= $sleepChunk;
                    }

                    if (connection_aborted()) {
                        break;
                    }
                }

                // Send disconnect event before closing
                $reason = (time() - $startTime) >= $maxDuration ? 'timeout' : 'client_disconnect';
                echo "event: disconnected\n";
                echo 'data: ' . json_encode(['status' => 'disconnected', 'reason' => $reason]) . "\n\n";
                flush();
            });

            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('Connection', 'keep-alive');
            $response->headers->set('X-Accel-Buffering', 'no');

            return $response;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_create_notification_stream');
            }
        }
    }

    /**
     * Get notifications created after a specific time
     */
    private function getNewNotifications(User $user, \DateTime $since): array
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_get_new_notifications');
        }

        try {
            $sinceImmutable = \DateTimeImmutable::createFromMutable($since);

            // More optimized query with explicit field selection to reduce memory usage
            return $this->notificationRepository->createQueryBuilder('n')
                ->select('n.id, n.title, n.message, n.createdAt, n.isRead, t.id as taskId')
                ->where('n.user = :user')
                ->andWhere('n.createdAt > :since')
                ->andWhere('n.isRead = false')
                ->leftJoin('n.task', 't') // Use left join to include task id if exists
                ->setParameter('user', $user)
                ->setParameter('since', $sinceImmutable)
                ->orderBy('n.createdAt', 'ASC')
                ->setMaxResults(10) // Limit results to prevent excessive data transfer
                ->getQuery()
                ->getArrayResult(); // Use array result to reduce object instantiation overhead
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_get_new_notifications');
            }
        }
    }

    /**
     * Send task assignment notification
     */
    public function sendTaskAssignmentNotification(
        User $assignedUser,
        User $assigner,
        int $taskId,
        string $taskTitle,
        string $priority = 'medium',
    ): void {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_send_task_assignment_notification');
        }

        try {
            $title = 'Новая задача назначена';

            // Include priority in message if urgent
            $priorityLabel = '';
            if ($priority === 'urgent') {
                $priorityLabel = ' (срочная)';
            } elseif ($priority === 'high') {
                $priorityLabel = ' (высокий приоритет)';
            }

            $message = \sprintf(
                'Пользователь %s назначил вам задачу "%s"%s',
                $assigner->getFullName(),
                $taskTitle,
                $priorityLabel,
            );

            $this->createTaskNotification($assignedUser, $title, $message, $taskId, $taskTitle);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_send_task_assignment_notification');
            }
        }
    }

    /**
     * Send task completion notification
     */
    public function sendTaskCompletionNotification(
        User $taskCreator,
        User $completer,
        int $taskId,
        string $taskTitle,
    ): void {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_send_task_completion_notification');
        }

        try {
            if ($taskCreator->getId() !== $completer->getId()) {
                $title = 'Задача завершена';
                $message = \sprintf(
                    'Пользователь %s завершил задачу "%s"',
                    $completer->getFullName(),
                    $taskTitle,
                );

                $this->createTaskNotification($taskCreator, $title, $message, $taskId, $taskTitle);
            }
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_send_task_completion_notification');
            }
        }
    }

    /**
     * Send deadline reminder notification
     */
    public function sendDeadlineReminder(
        User $user,
        int $taskId,
        string $taskTitle,
        \DateTime $deadline,
    ): void {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_send_deadline_reminder');
        }

        try {
            $title = 'Напоминание о сроке';
            $message = \sprintf(
                'Задача "%s" должна быть завершена до %s',
                $taskTitle,
                $deadline->format('d.m.Y H:i'),
            );

            $this->createTaskNotification($user, $title, $message, $taskId, $taskTitle);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_send_deadline_reminder');
            }
        }
    }

    /**
     * Send system notification to all users
     */
    public function sendSystemNotification(
        string $title,
        string $message,
        ?string $type = 'info',
    ): void {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_send_system_notification');
        }

        try {
            // This would typically send to all active users
            // For now, we'll just log it
            $this->logger->info("System notification: {$title} - {$message}");
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_send_system_notification');
            }
        }
    }

    /**
     * Get notification statistics for user
     */
    public function getNotificationStats(User $user): array
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_get_notification_stats');
        }

        try {
            $total = $this->notificationRepository->count(['user' => $user]);
            $unread = $this->notificationRepository->count([
                'user' => $user,
                'isRead' => false,
            ]);
            $today = $this->notificationRepository->count([
                'user' => $user,
                'createdAt' => \DateTimeImmutable::createFromMutable(new \DateTime('today')),
            ]);

            return [
                'total' => $total,
                'unread' => $unread,
                'today' => $today,
                'read' => $total - $unread,
            ];
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_get_notification_stats');
            }
        }
    }

    /**
     * Clean old notifications (older than 30 days)
     */
    public function cleanupOldNotifications(): int
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('notification_service_cleanup_old_notifications');
        }

        try {
            $cutoffDate = \DateTimeImmutable::createFromMutable(new \DateTime('-30 days'));

            $oldNotifications = $this->notificationRepository->createQueryBuilder('n')
                ->where('n.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoffDate)
                ->getQuery()
                ->getResult();

            $count = \count($oldNotifications);

            foreach ($oldNotifications as $notification) {
                $this->entityManager->remove($notification);
            }

            $this->entityManager->flush();

            $this->logger->info("Cleaned up {$count} old notifications");

            return $count;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('notification_service_cleanup_old_notifications');
            }
        }
    }
}
