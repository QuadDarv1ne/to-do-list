<?php

namespace App\EventListener;

use App\Domain\Comment\Event\CommentUpdated;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события CommentUpdated
 * 
 * Обрабатывает обновление комментария:
 * - Записывает запись в Activity Log
 */
#[AsEventListener(event: CommentUpdated::class, method: 'onCommentUpdated')]
final class CommentUpdatedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onCommentUpdated(CommentUpdated $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);
    }

    private function logActivity(CommentUpdated $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('comment_updated');
        $activityLog->setEventType('comment.updated');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Обновлён комментарий #%d к задаче #%d',
            $event->getCommentId(),
            $event->getTaskId()
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }
}
