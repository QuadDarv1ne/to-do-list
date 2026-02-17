<?php

namespace App\Domain\Task\Event;

use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskStatus;

final readonly class TaskStatusChanged implements DomainEventInterface
{
    public function __construct(
        private TaskId $taskId,
        private TaskStatus $oldStatus,
        private TaskStatus $newStatus,
        private int $changedByUserId,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public static function create(
        TaskId $taskId,
        TaskStatus $oldStatus,
        TaskStatus $newStatus,
        int $changedByUserId
    ): self {
        return new self(
            $taskId,
            $oldStatus,
            $newStatus,
            $changedByUserId,
            new \DateTimeImmutable()
        );
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getOldStatus(): TaskStatus
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): TaskStatus
    {
        return $this->newStatus;
    }

    public function getChangedByUserId(): int
    {
        return $this->changedByUserId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'task.status_changed';
    }

    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId->toInt(),
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'changed_by_user_id' => $this->changedByUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
