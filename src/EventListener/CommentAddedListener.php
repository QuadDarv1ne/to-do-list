<?php

namespace App\EventListener;

use App\Domain\Comment\Event\CommentAdded;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события CommentAdded
 * 
 * Обрабатывает добавление комментария:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления участникам задачи
 */
#[AsEventListener(event: CommentAdded::class, method: 'onCommentAdded')]
final class CommentAddedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onCommentAdded(CommentAdded $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Отправляем уведомления участникам задачи (опционально)
        $this->notifyParticipants($event);
    }

    private function logActivity(CommentAdded $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('comment_added');
        $activityLog->setEventType('comment.added');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Добавлен комментарий к задаче #%d (длина: %d симв.)',
            $event->getTaskId(),
            strlen($event->getContent())
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyParticipants(CommentAdded $event): void
    {
        // Здесь можно отправить уведомления другим участникам задачи
        // Например, через NotificationService
        // Пока оставляем заглушку для будущей реализации
    }
}
