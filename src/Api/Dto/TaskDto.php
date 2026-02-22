<?php

namespace App\Api\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Controller\TaskController;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
    operations: [
        new GetCollection(
            uriTemplate: '/api/tasks',
            controller: TaskController::class . '::apiList',
            paginationEnabled: true,
            paginationItemsPerPage: 30,
            filters: ['task.search_filter', 'task.order_filter', 'task.status_filter']
        ),
        new Get(
            uriTemplate: '/api/tasks/{id}',
            requirements: ['id' => '\d+']
        ),
        new Post(
            uriTemplate: '/api/tasks',
            security: 'is_granted("ROLE_USER")'
        ),
        new Put(
            uriTemplate: '/api/tasks/{id}',
            requirements: ['id' => '\d+'],
            security: 'is_granted("TASK_EDIT", object)'
        ),
        new Patch(
            uriTemplate: '/api/tasks/{id}',
            requirements: ['id' => '\d+'],
            security: 'is_granted("TASK_EDIT", object)'
        ),
        new Delete(
            uriTemplate: '/api/tasks/{id}',
            requirements: ['id' => '\d+'],
            security: 'is_granted("TASK_DELETE", object)'
        ),
    ],
    order: ['createdAt' => 'DESC']
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'description' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'title', 'createdAt', 'dueDate', 'priority', 'status'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'dueDate', 'completedAt'])]
class TaskDto
{
    #[ApiProperty(identifier: true)]
    #[Groups(['task:read'])]
    public ?int $id = null;

    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Groups(['task:read', 'task:write'])]
    #[Assert\Length(max: 10000)]
    public ?string $description = null;

    #[Groups(['task:read', 'task:write'])]
    public ?string $status = 'pending';

    #[Groups(['task:read', 'task:write'])]
    public ?string $priority = 'medium';

    #[Groups(['task:read'])]
    public ?\DateTimeInterface $createdAt = null;

    #[Groups(['task:read'])]
    public ?\DateTimeInterface $updatedAt = null;

    #[Groups(['task:read', 'task:write'])]
    public ?\DateTimeInterface $dueDate = null;

    #[Groups(['task:read'])]
    public ?\DateTimeInterface $completedAt = null;

    #[Groups(['task:read', 'task:write'])]
    #[Assert\Range(min: 0, max: 100)]
    public int $progress = 0;

    #[Groups(['task:read'])]
    public ?int $userId = null;

    #[Groups(['task:read', 'task:write'])]
    public ?int $assignedUserId = null;

    #[Groups(['task:read', 'task:write'])]
    public ?int $categoryId = null;

    #[Groups(['task:read'])]
    public array $tags = [];

    #[Groups(['task:read'])]
    public int $commentsCount = 0;

    #[Groups(['task:read'])]
    public bool $isOverdue = false;

    // Computed fields
    public function getIsCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
