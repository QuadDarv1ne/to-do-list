<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class RealTimeNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
    ) {
    }

    /**
     * Create and send notification
     */
    public function sendNotification(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?array $metadata = null,
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());

        if ($metadata) {
            $notification->setMetadata($metadata);
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->count([
            'user' => $user,
            'isRead' => false,
        ]);
    }

    /**
     * Get recent notifications
     */
    public function getRecentNotifications(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit,
        );
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
     * Mark all notifications as read
     */
    public function markAllAsRead(User $user): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->update(Notification::class, 'n')
            ->set('n.isRead', ':read')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :unread')
            ->setParameter('read', true)
            ->setParameter('unread', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete old notifications
     */
    public function deleteOldNotifications(int $daysOld = 30): int
    {
        $date = new \DateTime();
        $date->modify("-{$daysOld} days");

        $qb = $this->entityManager->createQueryBuilder();

        return $qb->delete(Notification::class, 'n')
            ->where('n.createdAt < :date')
            ->andWhere('n.isRead = :read')
            ->setParameter('date', $date)
            ->setParameter('read', true)
            ->getQuery()
            ->execute();
    }

    /**
     * Send task assignment notification
     */
    public function notifyTaskAssignment(User $user, $task): void
    {
        $this->sendNotification(
            $user,
            'Новая задача назначена',
            \sprintf('Вам назначена задача: %s', $task->getTitle()),
            'task_assigned',
            [
                'task_id' => $task->getId(),
                'task_title' => $task->getTitle(),
                'priority' => $task->getPriority(),
            ],
        );
    }

    /**
     * Send deadline reminder notification
     */
    public function notifyDeadlineApproaching(User $user, $task, int $hoursLeft): void
    {
        $this->sendNotification(
            $user,
            'Приближается дедлайн',
            \sprintf('До дедлайна задачи "%s" осталось %d часов', $task->getTitle(), $hoursLeft),
            'deadline_warning',
            [
                'task_id' => $task->getId(),
                'task_title' => $task->getTitle(),
                'hours_left' => $hoursLeft,
            ],
        );
    }

    /**
     * Send task completion notification
     */
    public function notifyTaskCompleted(User $user, $task): void
    {
        $this->sendNotification(
            $user,
            'Задача завершена',
            \sprintf('Задача "%s" была завершена', $task->getTitle()),
            'task_completed',
            [
                'task_id' => $task->getId(),
                'task_title' => $task->getTitle(),
            ],
        );
    }

    /**
     * Send task comment notification
     */
    public function notifyNewComment(User $user, $task, $comment): void
    {
        $this->sendNotification(
            $user,
            'Новый комментарий',
            \sprintf('Новый комментарий к задаче "%s"', $task->getTitle()),
            'new_comment',
            [
                'task_id' => $task->getId(),
                'task_title' => $task->getTitle(),
                'comment_id' => $comment->getId(),
            ],
        );
    }
}
