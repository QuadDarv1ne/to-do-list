<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Dto\TaskDto;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Symfony\Component\Security\Core\Security;

class TaskProvider implements ProviderInterface
{
    public function __construct(
        private TaskRepository $taskRepository,
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        // GetCollection
        if ($operation->getName() === 'api_tasks_get_collection') {
            return $this->getCollection($context);
        }

        // Get item
        $id = $uriVariables['id'] ?? null;
        if ($id) {
            return $this->getItem($id);
        }

        return null;
    }

    private function getCollection(array $context): array
    {
        $user = $this->security->getUser();
        
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn(Task $task) => $this->mapToDto($task), $tasks);
    }

    private function getItem(int $id): ?TaskDto
    {
        $user = $this->security->getUser();
        
        $task = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('t.id = :id')
            ->andWhere('t.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $task ? $this->mapToDto($task) : null;
    }

    private function mapToDto(Task $task): TaskDto
    {
        $dto = new TaskDto();
        $dto->id = $task->getId();
        $dto->title = $task->getTitle();
        $dto->description = $task->getDescription();
        $dto->status = $task->getStatus();
        $dto->priority = $task->getPriority();
        $dto->createdAt = $task->getCreatedAt();
        $dto->updatedAt = $task->getUpdatedAt();
        $dto->dueDate = $task->getDueDate();
        $dto->completedAt = $task->getCompletedAt();
        $dto->progress = $task->getProgress();
        $dto->userId = $task->getUser()?->getId();
        $dto->assignedUserId = $task->getAssignedUser()?->getId();
        $dto->categoryId = $task->getCategory()?->getId();
        $dto->tags = $task->getTags()->map(fn($tag) => ['id' => $tag->getId(), 'name' => $tag->getName()])->toArray();
        $dto->commentsCount = $task->getComments()->count();
        $dto->isOverdue = $task->isOverdue();

        return $dto;
    }
}
