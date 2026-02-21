<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для обновления задачи
 */
final readonly class UpdateTaskDTO
{
    /**
     * @param positive-int         $id
     * @param ?non-empty-string    $title
     * @param ?non-empty-string    $description
     * @param ?'pending'|'in_progress'|'completed' $status
     * @param ?'low'|'medium'|'high'|'urgent' $priority
     * @param ?int                 $assignedUserId
     * @param ?int                 $categoryId
     * @param ?string              $dueDate ISO 8601 format
     * @param ?int                 $progress 0-100
     * @param array<int>           $tagIds
     */
    private function __construct(
        private int $id,
        private ?string $title,
        private ?string $description,
        private ?string $status,
        private ?string $priority,
        private ?int $assignedUserId,
        private ?int $categoryId,
        private ?string $dueDate,
        private ?int $progress,
        private array $tagIds,
        private bool $notify = true,
    ) {
    }

    /**
     * Создать DTO из HTTP запроса
     */
    public static function fromRequest(Request $request, int $id): self
    {
        $data = $request->request->all();

        return new self(
            id: $id,
            title: isset($data['title']) ? trim($data['title']) : null,
            description: isset($data['description']) ? trim($data['description']) : null,
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            assignedUserId: $data['assignedUserId'] ?? null,
            categoryId: $data['categoryId'] ?? null,
            dueDate: $data['dueDate'] ?? null,
            progress: isset($data['progress']) ? (int) $data['progress'] : null,
            tagIds: $data['tags'] ?? [],
            notify: $data['notify'] ?? true,
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     id: int,
     *     title?: string|null,
     *     description?: string|null,
     *     status?: string|null,
     *     priority?: string|null,
     *     assignedUserId?: int|null,
     *     categoryId?: int|null,
     *     dueDate?: string|null,
     *     progress?: int|null,
     *     tags?: array<int>,
     *     notify?: bool
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: isset($data['title']) ? trim($data['title']) : null,
            description: isset($data['description']) ? trim($data['description']) : null,
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            assignedUserId: $data['assignedUserId'] ?? null,
            categoryId: $data['categoryId'] ?? null,
            dueDate: $data['dueDate'] ?? null,
            progress: $data['progress'] ?? null,
            tagIds: $data['tags'] ?? [],
            notify: $data['notify'] ?? true,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPriority(): ?string
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

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    /**
     * @return array<int>
     */
    public function getTagIds(): array
    {
        return $this->tagIds;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }

    /**
     * Проверить, есть ли данные для обновления
     */
    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->description !== null
            || $this->status !== null
            || $this->priority !== null
            || $this->assignedUserId !== null
            || $this->categoryId !== null
            || $this->dueDate !== null
            || $this->progress !== null;
    }

    /**
     * Преобразовать в массив для обновления сущности
     *
     * @return array{
     *     title: string|null,
     *     description: string|null,
     *     status: string|null,
     *     priority: string|null,
     *     assignedUserId: int|null,
     *     categoryId: int|null,
     *     dueDate: \DateTimeInterface|null,
     *     progress: int|null,
     *     tags: array<int>,
     *     notify: bool
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
            'progress' => $this->progress,
            'tags' => $this->tagIds,
            'notify' => $this->notify,
        ];
    }
}
