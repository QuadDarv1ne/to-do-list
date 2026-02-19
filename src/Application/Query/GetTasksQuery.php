<?php

namespace App\Application\Query;

final readonly class GetTasksQuery
{
    public function __construct(
        private int $userId,
        private ?string $status = null,
        private ?string $priority = null,
        private ?int $categoryId = null,
        private int $page = 1,
        private int $limit = 10,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
