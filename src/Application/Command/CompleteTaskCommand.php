<?php

namespace App\Application\Command;

final readonly class CompleteTaskCommand
{
    public function __construct(
        private int $taskId,
        private int $completedByUserId,
    ) {
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getCompletedByUserId(): int
    {
        return $this->completedByUserId;
    }
}
