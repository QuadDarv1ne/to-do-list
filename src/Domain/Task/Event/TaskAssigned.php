<?php

namespace App\Domain\Task\Event;

use App\Domain\Task\ValueObject\TaskId;

final readonly class TaskAssigned implements DomainEventInterface
{
    public function __construct(
        private TaskId $taskId,
        private ?int $previousAssigneeId,
        private int $newAssigneeId,
        private int $assignedByUserId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        TaskId $taskId,
        ?int $previousAssigneeId,
        int $newAssigneeId,
        int $assignedByUserId,
    ): self {
        return new self(
            $taskId,
            $previousAssigneeId,
            $newAssigneeId,
            $assignedByUserId,
            new \DateTimeImmutable(),
        );
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getPreviousAssigneeId(): ?int
    {
        return $this->previousAssigneeId;
    }

    public function getNewAssigneeId(): int
    {
        return $this->newAssigneeId;
    }

    public function getAssignedByUserId(): int
    {
        return $this->assignedByUserId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'task.assigned';
    }

    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId->toInt(),
            'previous_assignee_id' => $this->previousAssigneeId,
            'new_assignee_id' => $this->newAssigneeId,
            'assigned_by_user_id' => $this->assignedByUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
