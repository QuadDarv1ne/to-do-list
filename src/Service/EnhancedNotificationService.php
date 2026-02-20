<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnhancedNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private NotificationRepository $notificationRepository,
        private MultiChannelNotificationService $multiChannelService,
    ) {
    }

    /**
     * Create and send notification through multiple channels
     */
    public function createNotification(
        User $user,
        string $title,
        string $message,
        ?Task $task = null,
        string $type = Notification::TYPE_INFO,
        array $channels = [Notification::CHANNEL_IN_APP],
        ?string $templateKey = null,
        array $templateVariables = [],
    ): Notification {
        try {
            $notification = $this->multiChannelService->sendNotification(
                $user,
                $title,
                $message,
                $type,
                $channels,
                ['task_id' => $task?->getId()],
                $templateKey,
                $templateVariables
            );

            if ($task) {
                $notification->setTask($task);
            }

            $this->logger->info("Created notification for user {$user->getId()}: {$title}");

            return $notification;
        } finally {
            // Cleanup if needed
        }
    }

    /**
     * Send real-time update (placeholder for future implementation)
     */
    private function sendRealTimeUpdate(Notification $notification): void
    {
        // Future implementation with Mercure or WebSocket
        $this->logger->debug("Real-time update would be sent here");
    }

    /**
     * Server-Sent Events stream for real-time notifications
     */
    public function createNotificationStream(User $user): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($user) {
            // Send headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');
            header('X-Accel-Buffering: no'); // Disable buffering for Nginx

            // Send initial connection confirmation
            echo "event: connected\n";
            echo 'data: ' . json_encode(['status' => 'connected', 'user_id' => $user->getId()]) . "\n\n";
            flush();

            $lastCheck = new \DateTime();
            $heartbeatInterval = 30; // seconds
            $checkInterval = 10; // seconds
            $maxDuration = 1800; // 30 minutes maximum connection time
            $startTime = time();
            $lastHeartbeat = time();

            while ((time() - $startTime) < $maxDuration && !connection_aborted()) {
                // Send heartbeat
                if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
                    echo "event: heartbeat\n";
                    echo 'data: ' . json_encode(['timestamp' => time()]) . "\n\n";
                    flush();
                    $lastHeartbeat = time();
                }

                // Check for new notifications
                $newNotifications = $this->getNewNotifications($user, $lastCheck);

                if (!empty($newNotifications)) {
                    foreach ($newNotifications as $notification) {
                        echo "event: notification\n";
                        echo 'data: ' . json_encode([
                            'id' => $notification->getId(),
                            'title' => $notification->getTitle(),
                            'message' => $notification->getMessage(),
                            'type' => $notification->getType(),
                            'created_at' => $notification->getCreatedAt()->format('c'),
                            'is_read' => $notification->isRead(),
                            'task_id' => $notification->getTask()?->getId(),
                        ]) . "\n\n";
                        flush();
                    }
                    $lastCheck = new \DateTime();
                }

                sleep($checkInterval);
            }

            // Send disconnect message
            echo "event: disconnected\n";
            echo 'data: ' . json_encode(['status' => 'disconnected', 'reason' => 'timeout']) . "\n\n";
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Get new notifications since last check
     */
    private function getNewNotifications(User $user, \DateTime $lastCheck): array
    {
        return $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.createdAt > :lastCheck')
            ->andWhere('n.channel = :channel')
            ->setParameter('user', $user)
            ->setParameter('lastCheck', $lastCheck)
            ->setParameter('channel', Notification::CHANNEL_IN_APP)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(User $user): array
    {
        return $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Get notification statistics for user
     */
    public function getNotificationStats(User $user): array
    {
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
            'read_percentage' => $total > 0 ? round(($total - $unread) / $total * 100, 1) : 0,
        ];
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
        $count = $this->entityManager->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :notRead')
            ->setParameter('isRead', true)
            ->setParameter('notRead', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return $count;
    }
}