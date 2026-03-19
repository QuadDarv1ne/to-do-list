<?php

namespace App\Service;

use App\Entity\PushNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Сервис для отправки push-уведомлений в реальном времени
 *
 * Поддерживаемые каналы:
 * - Database (для polling через AJAX)
 * - WebSocket (через Mercure/Gonkey)
 * - Web Push API (браузерные уведомления)
 */
class PushNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Отправить уведомление пользователю
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = [],
        ?string $channel = null,
    ): PushNotification {
        $notification = new PushNotification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setActionUrl($actionUrl);
        $notification->setData($data);
        $notification->setChannel($channel ?? 'database');

        $this->em->persist($notification);
        $this->em->flush();

        $this->logger->info('Push notification sent', [
            'user_id' => $user->getId(),
            'type' => $type,
            'channel' => $channel ?? 'database',
        ]);

        // Отправка через WebSocket (если настроено)
        $this->sendViaWebSocket($notification);

        // Отправка через Web Push API (если настроено)
        if ($channel === 'webpush' || $channel === null) {
            $this->sendViaWebPush($notification);
        }

        return $notification;
    }

    /**
     * Отправить уведомление о новой задаче
     */
    public function sendTaskCreated(User $user, \App\Entity\Task $task): PushNotification
    {
        return $this->send(
            $user,
            'task.created',
            '📝 Новая задача',
            sprintf('Создана задача: %s', $task->getTitle()),
            '/tasks/' . $task->getId(),
            ['task_id' => $task->getId(), 'title' => $task->getTitle()],
        );
    }

    /**
     * Отправить уведомление об изменении задачи
     */
    public function sendTaskUpdated(User $user, \App\Entity\Task $task, string $change): PushNotification
    {
        return $this->send(
            $user,
            'task.updated',
            '🔄 Изменение задачи',
            sprintf('Задача "%s": %s', $task->getTitle(), $change),
            '/tasks/' . $task->getId(),
            ['task_id' => $task->getId(), 'change' => $change],
        );
    }

    /**
     * Отправить уведомление о дедлайне
     */
    public function sendTaskDeadline(User $user, \App\Entity\Task $task, string $timeRemaining): PushNotification
    {
        return $this->send(
            $user,
            'task.deadline',
            '⏰ Дедлайн приближается',
            sprintf('Задача "%s": осталось %s', $task->getTitle(), $timeRemaining),
            '/tasks/' . $task->getId(),
            ['task_id' => $task->getId(), 'deadline' => $timeRemaining],
            'webpush', // Приоритет через Web Push
        );
    }

    /**
     * Отправить уведомление о упоминании
     */
    public function sendMention(User $user, \App\Entity\User $mentionedBy, string $context): PushNotification
    {
        return $this->send(
            $user,
            'mention',
            '🔔 Вас упомянули',
            sprintf('%s упомянул(а) вас: %s', $mentionedBy->getFullName(), $context),
            null,
            ['mentioned_by' => $mentionedBy->getId(), 'context' => $context],
        );
    }

    /**
     * Отправить уведомление о новом комментарии
     */
    public function sendComment(User $user, \App\Entity\Task $task, \App\Entity\User $author): PushNotification
    {
        return $this->send(
            $user,
            'comment.created',
            '💬 Новый комментарий',
            sprintf('%s добавил(а) комментарий к задаче "%s"', $author->getFullName(), $task->getTitle()),
            '/tasks/' . $task->getId(),
            ['task_id' => $task->getId(), 'author_id' => $author->getId()],
        );
    }

    /**
     * Получить количество непрочитанных уведомлений
     */
    public function getUnreadCount(User $user): int
    {
        return $this->em->getRepository(PushNotification::class)->countUnreadForUser($user);
    }

    /**
     * Получить последние уведомления
     *
     * @return PushNotification[]
     */
    public function getNotifications(User $user, int $limit = 50, bool $unreadOnly = false): array
    {
        $repo = $this->em->getRepository(PushNotification::class);

        return $unreadOnly
            ? $repo->findUnreadForUser($user, $limit)
            : $repo->findForUser($user, $limit);
    }

    /**
     * Отметить все уведомления как прочитанные
     */
    public function markAllAsRead(User $user): int
    {
        return $this->em->getRepository(PushNotification::class)->markAllAsRead($user);
    }

    /**
     * Отметить конкретное уведомление как прочитанное
     */
    public function markAsRead(PushNotification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();
    }

    /**
     * Удалить старые уведомления
     */
    public function cleanupOldNotifications(User $user, int $days = 30): int
    {
        $date = new \DateTime(sprintf('-%d days', $days));
        return $this->em->getRepository(PushNotification::class)->removeOlderThan($user, $date);
    }

    /**
     * Сериализовать уведомление для отправки через WebSocket
     */
    public function serializeForWebSocket(PushNotification $notification): string
    {
        return $this->serializer->serialize([
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'actionUrl' => $notification->getActionUrl(),
            'data' => $notification->getData(),
            'createdAt' => $notification->getCreatedAt()->format(\DateTime::ATOM),
            'isRead' => $notification->isRead(),
        ], 'json');
    }

    /**
     * Отправка через WebSocket (Mercure/Gonkey)
     */
    private function sendViaWebSocket(PushNotification $notification): void
    {
        // TODO: Интеграция с Mercure или другим WebSocket сервером
        // Пример для Mercure:
        // $update = new Update(
        //     '/notifications/' . $notification->getUser()->getId(),
        //     $this->serializeForWebSocket($notification)
        // );
        // $this->hub->publish($update);

        $this->logger->debug('WebSocket notification prepared', [
            'notification_id' => $notification->getId(),
        ]);
    }

    /**
     * Отправка через Web Push API
     */
    private function sendViaWebPush(PushNotification $notification): void
    {
        // TODO: Интеграция с Web Push API
        // Требуется:
        // 1. Сохранение subscription данных пользователя
        // 2. Использование библиотеки minishlink/web-push
        // 3. Отправка push-сообщения

        $this->logger->debug('Web Push notification prepared', [
            'notification_id' => $notification->getId(),
        ]);
    }
}
