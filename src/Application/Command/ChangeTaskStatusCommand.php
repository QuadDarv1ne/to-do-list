<?php

namespace App\Application\Command;

final readonly class ChangeTaskStatusCommand
{
    public function __construct(
        private int $taskId,
        private string $newStatus,
        private int $changedByUserId
    ) {
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function getChangedByUserId(): int
    {
        return $this->changedByUserId;
    }
}
