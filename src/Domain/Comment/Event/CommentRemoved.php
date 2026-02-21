<?php

namespace App\Domain\Comment\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Комментарий удалён
 */
final readonly class CommentRemoved implements DomainEventInterface
{
    public function __construct(
        private int $commentId,
        private int $taskId,
        private int $authorId,
        private int $removedByUserId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $commentId,
        int $taskId,
        int $authorId,
        int $removedByUserId,
    ): self {
        return new self(
            $commentId,
            $taskId,
            $authorId,
            $removedByUserId,
            new \DateTimeImmutable(),
        );
    }

    public function getCommentId(): int
    {
        return $this->commentId;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function getRemovedByUserId(): int
    {
        return $this->removedByUserId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'comment.removed';
    }

    public function toArray(): array
    {
        return [
            'comment_id' => $this->commentId,
            'task_id' => $this->taskId,
            'author_id' => $this->authorId,
            'removed_by_user_id' => $this->removedByUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
