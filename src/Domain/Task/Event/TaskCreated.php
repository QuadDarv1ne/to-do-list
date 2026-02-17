<?php

namespace App\Domain\Task\Event;

use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskTitle;
use App\Domain\Task\ValueObject\TaskPriority;

final readonly class TaskCreated implements DomainEventInterface
{
    public function __construct(
        private TaskId $taskId,
        private TaskTitle $title,
        private TaskPriority $priority,
        private int $userId,
        private int $assignedUserId,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public static function create(
        TaskId $taskId,
        TaskTitle $title,
        TaskPriority $priority,
        int $userId,
        int $assignedUserId
    ): self {
        return new self(
            $taskId,
            $title,
            $priority,
            $userId,
            $assignedUserId,
            new \DateTimeImmutable()
        );
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getTitle(): TaskTitle
    {
        return $this->title;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAssignedUserId(): int
    {
        return $this->assignedUserId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'task.created';
    }

    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId->toInt(),
            'title' => $this->title->toString(),
            'priority' => $this->priority->value,
            'user_id' => $this->userId,
            'assigned_user_id' => $this->assignedUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
