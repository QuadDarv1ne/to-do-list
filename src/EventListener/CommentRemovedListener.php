<?php

namespace App\EventListener;

use App\Domain\Comment\Event\CommentRemoved;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события CommentRemoved
 * 
 * Обрабатывает удаление комментария:
 * - Записывает запись в Activity Log
 */
#[AsEventListener(event: CommentRemoved::class, method: 'onCommentRemoved')]
final class CommentRemovedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onCommentRemoved(CommentRemoved $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);
    }

    private function logActivity(CommentRemoved $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('comment_removed');
        $activityLog->setEventType('comment.removed');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Удалён комментарий #%d из задачи #%d (автор: #%d, удалил: #%d)',
            $event->getCommentId(),
            $event->getTaskId(),
            $event->getAuthorId(),
            $event->getRemovedByUserId()
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }
}
