<?php

namespace App\Domain\Comment\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Комментарий обновлён
 */
final readonly class CommentUpdated implements DomainEventInterface
{
    public function __construct(
        private int $commentId,
        private int $taskId,
        private string $newContent,
        private int $authorId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $commentId,
        int $taskId,
        string $newContent,
        int $authorId,
    ): self {
        return new self(
            $commentId,
            $taskId,
            $newContent,
            $authorId,
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

    public function getNewContent(): string
    {
        return $this->newContent;
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'comment.updated';
    }

    public function toArray(): array
    {
        return [
            'comment_id' => $this->commentId,
            'task_id' => $this->taskId,
            'new_content' => $this->newContent,
            'author_id' => $this->authorId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
