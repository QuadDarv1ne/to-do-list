<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Dto\TaskDto;
use App\Entity\Task;
use App\Repository\TaskCategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class TaskProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TaskCategoryRepository $categoryRepository,
        private Security $security
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new \RuntimeException('User not authenticated');
        }

        // Determine if this is a create or update operation
        $isUpdate = isset($uriVariables['id']);

        if ($isUpdate) {
            return $this->update($data, $uriVariables['id']);
        }

        return $this->create($data);
    }

    private function create(TaskDto $data): TaskDto
    {
        $task = new Task();
        $this->mapDtoToEntity($data, $task);

        $task->setUser($this->security->getUser());

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $this->mapEntityToDto($task);
    }

    private function update(TaskDto $data, int $id): TaskDto
    {
        $task = $this->entityManager->getRepository(Task::class)->find($id);

        if (!$task) {
            throw new \RuntimeException('Task not found');
        }

        // Check permissions
        if ($task->getUser() !== $this->security->getUser()) {
            throw new \RuntimeException('Access denied');
        }

        $this->mapDtoToEntity($data, $task);
        $this->entityManager->flush();

        return $this->mapEntityToDto($task);
    }

    private function mapDtoToEntity(TaskDto $dto, Task $task): void
    {
        if ($dto->title !== null) {
            $task->setTitle($dto->title);
        }
        if ($dto->description !== null) {
            $task->setDescription($dto->description);
        }
        if ($dto->status !== null) {
            $task->setStatus($dto->status);
        }
        if ($dto->priority !== null) {
            $task->setPriority($dto->priority);
        }
        if ($dto->dueDate !== null) {
            $task->setDueDate($dto->dueDate);
        }
        if ($dto->progress !== null) {
            $task->setProgress($dto->progress);
        }
        if ($dto->assignedUserId !== null) {
            $user = $this->userRepository->find($dto->assignedUserId);
            if ($user) {
                $task->setAssignedUser($user);
            }
        }
        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category) {
                $task->setCategory($category);
            }
        }
    }

    private function mapEntityToDto(Task $task): TaskDto
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
