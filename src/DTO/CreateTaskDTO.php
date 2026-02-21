<?php

namespace App\DTO;

use App\Domain\Task\ValueObject\TaskPriority;
use App\Domain\Task\ValueObject\TaskTitle;
use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для создания задачи
 */
final readonly class CreateTaskDTO
{
    /**
     * @param non-empty-string       $title
     * @param ?non-empty-string      $description
     * @param 'pending'|'in_progress'|'completed' $status
     * @param 'low'|'medium'|'high'|'urgent' $priority
     * @param ?int                   $assignedUserId
     * @param ?int                   $categoryId
     * @param ?string                $dueDate ISO 8601 format (Y-m-d\TH:i:sP)
     * @param ?int                   $parentId
     * @param array<int>             $tagIds
     */
    private function __construct(
        private string $title,
        private ?string $description,
        private string $status,
        private string $priority,
        private ?int $assignedUserId,
        private ?int $categoryId,
        private ?string $dueDate,
        private ?int $parentId,
        private array $tagIds,
    ) {
    }

    /**
     * Создать DTO из HTTP запроса
     */
    public static function fromRequest(Request $request): self
    {
        $data = $request->request->all();

        return new self(
            title: trim($data['title'] ?? ''),
            description: isset($data['description']) ? trim($data['description']) : null,
            status: $data['status'] ?? 'pending',
            priority: $data['priority'] ?? 'medium',
            assignedUserId: isset($data['assignedUserId']) ? (int) $data['assignedUserId'] : null,
            categoryId: isset($data['categoryId']) ? (int) $data['categoryId'] : null,
            dueDate: $data['dueDate'] ?? null,
            parentId: isset($data['parentId']) ? (int) $data['parentId'] : null,
            tagIds: array_map('intval', $data['tags'] ?? []),
        );
    }

    /**
     * Создать DTO из массива данных (например, из API или формы)
     *
     * @param array{
     *     title: string,
     *     description?: string|null,
     *     status?: string,
     *     priority?: string,
     *     assignedUserId?: int|null,
     *     categoryId?: int|null,
     *     dueDate?: string|null,
     *     parentId?: int|null,
     *     tags?: array<int>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: trim($data['title'] ?? ''),
            description: isset($data['description']) ? trim($data['description']) : null,
            status: $data['status'] ?? 'pending',
            priority: $data['priority'] ?? 'medium',
            assignedUserId: $data['assignedUserId'] ?? null,
            categoryId: $data['categoryId'] ?? null,
            dueDate: $data['dueDate'] ?? null,
            parentId: $data['parentId'] ?? null,
            tagIds: $data['tags'] ?? [],
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getAssignedUserId(): ?int
    {
        return $this->assignedUserId;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getDueDate(): ?string
    {
        return $this->dueDate;
    }

    public function getDueDateAsDateTime(): ?\DateTimeInterface
    {
        if (!$this->dueDate) {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->dueDate);
        } catch (\Exception) {
            return null;
        }
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    /**
     * @return array<int>
     */
    public function getTagIds(): array
    {
        return $this->tagIds;
    }

    /**
     * Создать Value Object TaskTitle
     */
    public function toTaskTitle(): TaskTitle
    {
        return TaskTitle::fromString($this->title);
    }

    /**
     * Создать Value Object TaskPriority
     */
    public function toTaskPriority(): TaskPriority
    {
        return TaskPriority::from($this->priority);
    }

    /**
     * Преобразовать в массив для создания сущности
     *
     * @return array{
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     priority: string,
     *     assignedUserId: int|null,
     *     categoryId: int|null,
     *     dueDate: \DateTimeInterface|null,
     *     parentId: int|null,
     *     tags: array<int>
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assignedUserId' => $this->assignedUserId,
            'categoryId' => $this->categoryId,
            'dueDate' => $this->getDueDateAsDateTime(),
            'parentId' => $this->parentId,
            'tags' => $this->tagIds,
        ];
    }
}
