<?php

namespace App\Application\Command;

final readonly class CreateTaskCommand
{
    public function __construct(
        private string $title,
        private ?string $description,
        private string $priority,
        private int $userId,
        private int $assignedUserId,
        private ?int $categoryId = null,
        private ?\DateTimeImmutable $dueDate = null,
        private array $tagIds = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPriority(): string
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

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getTagIds(): array
    {
        return $this->tagIds;
    }
}
