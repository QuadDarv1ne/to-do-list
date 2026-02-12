<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Notification;
use App\Entity\Task;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private NotificationRepository $notificationRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        NotificationRepository $notificationRepository
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Create a notification for a user
     */
    public function createNotification(
        User $user,
        string $title,
        string $message,
        ?Task $task = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setIsRead(false);
        if ($task) {
            $notification->setTask($task);
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->logger->info("Created notification for user {$user->getId()}: {$title}");

        return $notification;
    }

    /**
     * Create task-related notification
     */
    public function createTaskNotification(
        User $user,
        string $title,
        string $message,
        int $taskId,
        ?string $taskTitle = null
    ): Notification {
        // For now, we'll create a basic notification
        // In a real implementation, you might want to extend the Notification entity
        return $this->createNotification($user, $title, $message, null);
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(User $user): array
    {
        return $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->entityManager->flush();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): int
    {
        $unreadNotifications = $this->getUnreadNotifications($user);
        $count = count($unreadNotifications);

        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();
        return $count;
    }

    /**
     * Server-Sent Events stream for real-time notifications
     * Fixed to prevent infinite loops and page lag
     */
    public function createNotificationStream(User $user): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($user) {
            // Send headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            // Send initial connection confirmation
            echo "event: connected\n";
            echo "data: " . json_encode(['status' => 'connected', 'user_id' => $user->getId()]) . "\n\n";
            flush();

            $lastCheck = new \DateTime();
            $heartbeatInterval = 30; // seconds
            $checkInterval = 10; // seconds - increased interval to reduce load
            $iterationCount = 0;
            $maxIterations = 300; // Maximum iterations to prevent indefinite running (300 * 10s = 50 minutes)
            
            while ($iterationCount < $maxIterations && !connection_aborted()) {
                // Check for new notifications
                $newNotifications = $this->getNewNotifications($user, $lastCheck);
                
                if (!empty($newNotifications)) {
                    foreach ($newNotifications as $notification) {
                        // Handle both object and array formats for backward compatibility
                        $id = is_object($notification) ? $notification->getId() : $notification['id'];
                        $title = is_object($notification) ? $notification->getTitle() : $notification['title'];
                        $message = is_object($notification) ? $notification->getMessage() : $notification['message'];
                        $createdAt = is_object($notification) ? $notification->getCreatedAt() : new \DateTime($notification['createdAt']);
                        $taskId = is_object($notification) ? ($notification->getTask() ? $notification->getTask()->getId() : null) : ($notification['taskId'] ?? null);
                        
                        echo "event: notification\n";
                        echo "data: " . json_encode([
                            'id' => $id,
                            'title' => $title,
                            'message' => $message,
                            'task_id' => $taskId,
                            'created_at' => $createdAt->format('c')
                        ]) . "\n\n";
                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();
                    }
                    
                    $lastCheck = new \DateTime();
                }

                // Send heartbeat to keep connection alive
                if ($iterationCount % $heartbeatInterval === 0) {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode(['time' => date('c')]) . "\n\n";
                    flush();
                }

                // Break the sleep into smaller chunks to allow for quicker response to connection abort
                $remainingSleep = $checkInterval;
                $chunkSize = 2; // 2 second chunks
                $continueLoop = true;
                
                while ($remainingSleep > 0 && $continueLoop && !connection_aborted()) {
                    $sleepChunk = min($chunkSize, $remainingSleep);
                    usleep($sleepChunk * 1000000); // usleep takes microseconds, not seconds
                    $remainingSleep -= $sleepChunk;
                    
                    // Check periodically if connection is still alive
                    if (connection_aborted()) {
                        $continueLoop = false;
                    }
                }
                
                $iterationCount++;
                
                // Final check if connection is still alive
                if (connection_aborted()) {
                    break;
                }
            }
            
            // Send disconnect event before closing
            echo "event: disconnected\n";
            echo "data: " . json_encode(['status' => 'disconnected', 'reason' => 'timeout or client disconnect']) . "\n\n";
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Get notifications created after a specific time
     */
    private function getNewNotifications(User $user, \DateTime $since): array
    {
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
    }

    /**
     * Send task assignment notification
     */
    public function sendTaskAssignmentNotification(
        User $assignedUser,
        User $assigner,
        int $taskId,
        string $taskTitle
    ): void {
        $title = 'Новая задача назначена';
        $message = sprintf(
            'Пользователь %s назначил вам задачу "%s"',
            $assigner->getFullName(),
            $taskTitle
        );

        $this->createTaskNotification($assignedUser, $title, $message, $taskId, $taskTitle);
    }

    /**
     * Send task completion notification
     */
    public function sendTaskCompletionNotification(
        User $taskCreator,
        User $completer,
        int $taskId,
        string $taskTitle
    ): void {
        if ($taskCreator->getId() !== $completer->getId()) {
            $title = 'Задача завершена';
            $message = sprintf(
                'Пользователь %s завершил задачу "%s"',
                $completer->getFullName(),
                $taskTitle
            );

            $this->createTaskNotification($taskCreator, $title, $message, $taskId, $taskTitle);
        }
    }

    /**
     * Send deadline reminder notification
     */
    public function sendDeadlineReminder(
        User $user,
        int $taskId,
        string $taskTitle,
        \DateTime $deadline
    ): void {
        $title = 'Напоминание о сроке';
        $message = sprintf(
            'Задача "%s" должна быть завершена до %s',
            $taskTitle,
            $deadline->format('d.m.Y H:i')
        );

        $this->createTaskNotification($user, $title, $message, $taskId, $taskTitle);
    }

    /**
     * Send system notification to all users
     */
    public function sendSystemNotification(
        string $title,
        string $message,
        ?string $type = 'info'
    ): void {
        // This would typically send to all active users
        // For now, we'll just log it
        $this->logger->info("System notification: {$title} - {$message}");
    }

    /**
     * Get notification statistics for user
     */
    public function getNotificationStats(User $user): array
    {
        $total = $this->notificationRepository->count(['user' => $user]);
        $unread = $this->notificationRepository->count([
            'user' => $user,
            'isRead' => false
        ]);
        $today = $this->notificationRepository->count([
            'user' => $user,
            'createdAt' => \DateTimeImmutable::createFromMutable(new \DateTime('today'))
        ]);

        return [
            'total' => $total,
            'unread' => $unread,
            'today' => $today,
            'read' => $total - $unread
        ];
    }

    /**
     * Clean old notifications (older than 30 days)
     */
    public function cleanupOldNotifications(): int
    {
        $cutoffDate = \DateTimeImmutable::createFromMutable(new \DateTime('-30 days'));
        
        $oldNotifications = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $count = count($oldNotifications);
        
        foreach ($oldNotifications as $notification) {
            $this->entityManager->remove($notification);
        }
        
        $this->entityManager->flush();
        
        $this->logger->info("Cleaned up {$count} old notifications");
        
        return $count;
    }
}