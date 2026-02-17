<?php

namespace App\Application\Query;

final readonly class GetTaskQuery
{
    public function __construct(
        private int $taskId
    ) {
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }
}
