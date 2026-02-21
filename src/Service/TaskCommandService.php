<?php

namespace App\Service;

use App\Domain\Task\Event\TaskAssigned;
use App\Domain\Task\Event\TaskCompleted;
use App\Domain\Task\Event\TaskCreated;
use App\Domain\Task\Event\TaskStatusChanged;
use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskPriority;
use App\Domain\Task\ValueObject\TaskStatus;
use App\Domain\Task\ValueObject\TaskTitle;
use App\DTO\CompleteTaskDTO;
use App\DTO\CreateTaskDTO;
use App\DTO\UpdateTaskDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Сервис для управления задачами с использованием DTO и Domain Events
 */
final class TaskCommandService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Создать задачу
     */
    public function createTask(CreateTaskDTO $dto, User $creator): Task
    {
        // Создаем сущность из DTO
        $task = new Task();
        $task->setTitle($dto->getTitle());
        $task->setDescription($dto->getDescription());
        $task->setStatus($dto->getStatus());
        $task->setPriority($dto->getPriority());
        $task->setUser($creator);

        // Устанавливаем назначенного пользователя
        if ($dto->getAssignedUserId()) {
            $assignedUser = $this->entityManager->getRepository(User::class)->find($dto->getAssignedUserId());
            if ($assignedUser) {
                $task->setAssignedUser($assignedUser);
            }
        }

        // Устанавливаем категорию
        if ($dto->getCategoryId()) {
            $category = $this->entityManager->getRepository(\App\Entity\TaskCategory::class)->find($dto->getCategoryId());
            if ($category) {
                $task->setCategory($category);
            }
        }

        // Устанавливаем дедлайн
        $dueDate = $dto->getDueDateAsDateTime();
        if ($dueDate) {
            $task->setDueDate($dueDate);
        }

        // Устанавливаем родительскую задачу
        if ($dto->getParentId()) {
            $parentTask = $this->taskRepository->find($dto->getParentId());
            if ($parentTask) {
                $task->setParent($parentTask);
            }
        }

        // Сохраняем задачу
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        // Добавляем теги
        if (!empty($dto->getTagIds())) {
            $tagRepository = $this->entityManager->getRepository(\App\Entity\Tag::class);
            foreach ($dto->getTagIds() as $tagId) {
                $tag = $tagRepository->find($tagId);
                if ($tag) {
                    $task->addTag($tag);
                }
            }
        }

        $this->entityManager->flush();

        // Генерируем Domain Event
        $this->dispatchTaskCreated($task, $creator);

        return $task;
    }

    /**
     * Обновить задачу
     */
    public function updateTask(UpdateTaskDTO $dto, User $updater): Task
    {
        $task = $this->taskRepository->find($dto->getId());

        if (!$task) {
            throw new \InvalidArgumentException(sprintf('Task with id %d not found', $dto->getId()));
        }

        // Сохраняем старые значения для Domain Events
        $oldStatus = $task->getStatus();
        $oldPriority = $task->getPriority();
        $oldAssignee = $task->getAssignedUser()?->getId();

        // Обновляем поля из DTO
        if ($dto->getTitle() !== null) {
            $task->setTitle($dto->getTitle());
        }

        if ($dto->getDescription() !== null) {
            $task->setDescription($dto->getDescription());
        }

        if ($dto->getStatus() !== null) {
            $task->setStatus($dto->getStatus());
        }

        if ($dto->getPriority() !== null) {
            $task->setPriority($dto->getPriority());
        }

        if ($dto->getAssignedUserId() !== null) {
            $assignedUser = $this->entityManager->getRepository(User::class)->find($dto->getAssignedUserId());
            $task->setAssignedUser($assignedUser);
        }

        if ($dto->getCategoryId() !== null) {
            if ($dto->getCategoryId() === 0) {
                $task->setCategory(null);
            } else {
                $category = $this->entityManager->getRepository(\App\Entity\TaskCategory::class)->find($dto->getCategoryId());
                if ($category) {
                    $task->setCategory($category);
                }
            }
        }

        if ($dto->getDueDate() !== null) {
            $dueDate = $dto->getDueDateAsDateTime();
            if ($dueDate) {
                $task->setDueDate($dueDate);
            }
        }

        if ($dto->getProgress() !== null) {
            $task->setProgress($dto->getProgress());
        }

        // Обновляем теги
        if (!empty($dto->getTagIds())) {
            $task->getTags()->clear();
            $tagRepository = $this->entityManager->getRepository(\App\Entity\Tag::class);
            foreach ($dto->getTagIds() as $tagId) {
                $tag = $tagRepository->find($tagId);
                if ($tag) {
                    $task->addTag($tag);
                }
            }
        }

        $this->entityManager->flush();

        // Генерируем Domain Events
        $this->dispatchTaskEvents($task, $updater, $oldStatus, $oldAssignee);

        return $task;
    }

    /**
     * Завершить задачу
     */
    public function completeTask(CompleteTaskDTO $dto, User $completer): Task
    {
        $task = $this->taskRepository->find($dto->getId());

        if (!$task) {
            throw new \InvalidArgumentException(sprintf('Task with id %d not found', $dto->getId()));
        }

        $task->setStatus('completed');
        $task->setProgress(100);
        $task->setCompletedAt(new \DateTime());

        $this->entityManager->flush();

        // Генерируем Domain Event
        $this->dispatchTaskCompleted($task, $completer);

        return $task;
    }

    /**
     * Отправить Domain Event о создании задачи
     */
    private function dispatchTaskCreated(Task $task, User $creator): void
    {
        $event = TaskCreated::create(
            TaskId::fromInt($task->getId()),
            TaskTitle::fromString($task->getTitle()),
            TaskPriority::from($task->getPriority()),
            $creator->getId(),
            $task->getAssignedUser()?->getId() ?? 0,
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Events об изменениях задачи
     */
    private function dispatchTaskEvents(Task $task, User $updater, string $oldStatus, ?int $oldAssignee): void
    {
        // Event изменения статуса
        if ($task->getStatus() !== $oldStatus) {
            $event = TaskStatusChanged::create(
                TaskId::fromInt($task->getId()),
                TaskStatus::from($oldStatus),
                TaskStatus::from($task->getStatus()),
                $updater->getId(),
            );
            $this->eventDispatcher->dispatch($event);
        }

        // Event назначения исполнителя
        $newAssignee = $task->getAssignedUser()?->getId();
        if ($newAssignee !== $oldAssignee) {
            $event = TaskAssigned::create(
                TaskId::fromInt($task->getId()),
                $oldAssignee,
                $newAssignee ?? 0,
                $updater->getId(),
            );
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * Отправить Domain Event о завершении задачи
     */
    private function dispatchTaskCompleted(Task $task, User $completer): void
    {
        $event = TaskCompleted::create(
            TaskId::fromInt($task->getId()),
            $completer->getId(),
            $task->getCompletedAt() ?? new \DateTimeImmutable(),
        );

        $this->eventDispatcher->dispatch($event);
    }
}
