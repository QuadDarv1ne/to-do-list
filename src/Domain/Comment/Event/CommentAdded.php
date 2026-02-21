<?php

namespace App\Domain\Comment\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Комментарий добавлен
 */
final readonly class CommentAdded implements DomainEventInterface
{
    public function __construct(
        private int $commentId,
        private int $taskId,
        private string $content,
        private int $authorId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $commentId,
        int $taskId,
        string $content,
        int $authorId,
    ): self {
        return new self(
            $commentId,
            $taskId,
            $content,
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

    public function getContent(): string
    {
        return $this->content;
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
        return 'comment.added';
    }

    public function toArray(): array
    {
        return [
            'comment_id' => $this->commentId,
            'task_id' => $this->taskId,
            'content' => $this->content,
            'author_id' => $this->authorId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
