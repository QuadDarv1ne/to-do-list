<?php

namespace App\Application\CommandHandler;

use App\Application\Command\CreateTaskCommand;
use App\Domain\Task\Event\TaskCreated;
use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskPriority;
use App\Domain\Task\ValueObject\TaskTitle;
use App\Entity\Task;
use App\Repository\TagRepository;
use App\Repository\TaskCategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateTaskCommandHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TaskCategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateTaskCommand $command): int
    {
        // Validate value objects
        $title = TaskTitle::fromString($command->getTitle());
        $priority = TaskPriority::fromString($command->getPriority());

        // Get entities
        $user = $this->userRepository->find($command->getUserId());
        $assignedUser = $this->userRepository->find($command->getAssignedUserId());

        if (!$user || !$assignedUser) {
            throw new \InvalidArgumentException('User not found');
        }

        // Create task entity
        $task = new Task();
        $task->setTitle($title->toString());
        $task->setDescription($command->getDescription());
        $task->setPriority($priority->value);
        $task->setStatus('pending');
        $task->setUser($user);
        $task->setAssignedUser($assignedUser);

        if ($command->getCategoryId()) {
            $category = $this->categoryRepository->find($command->getCategoryId());
            if ($category) {
                $task->setCategory($category);
            }
        }

        if ($command->getDueDate()) {
            $task->setDueDate(\DateTime::createFromImmutable($command->getDueDate()));
        }

        // Add tags
        foreach ($command->getTagIds() as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $task->addTag($tag);
            }
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        // Dispatch domain event
        $taskId = TaskId::fromInt($task->getId());
        $event = TaskCreated::create(
            $taskId,
            $title,
            $priority,
            $command->getUserId(),
            $command->getAssignedUserId(),
        );

        $this->eventBus->dispatch($event);

        return $task->getId();
    }
}
