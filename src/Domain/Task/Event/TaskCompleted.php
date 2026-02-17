<?php

namespace App\Domain\Task\Event;

use App\Domain\Task\ValueObject\TaskId;

final readonly class TaskCompleted implements DomainEventInterface
{
    public function __construct(
        private TaskId $taskId,
        private int $completedByUserId,
        private \DateTimeImmutable $completedAt,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public static function create(
        TaskId $taskId,
        int $completedByUserId,
        \DateTimeImmutable $completedAt
    ): self {
        return new self(
            $taskId,
            $completedByUserId,
            $completedAt,
            new \DateTimeImmutable()
        );
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getCompletedByUserId(): int
    {
        return $this->completedByUserId;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'task.completed';
    }

    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId->toInt(),
            'completed_by_user_id' => $this->completedByUserId,
            'completed_at' => $this->completedAt->format(\DateTimeInterface::ATOM),
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
