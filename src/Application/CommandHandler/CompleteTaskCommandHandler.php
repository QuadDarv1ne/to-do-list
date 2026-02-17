<?php

namespace App\Application\CommandHandler;

use App\Application\Command\CompleteTaskCommand;
use App\Domain\Task\Event\TaskCompleted;
use App\Domain\Task\ValueObject\TaskId;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CompleteTaskCommandHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private MessageBusInterface $eventBus
    ) {
    }

    public function __invoke(CompleteTaskCommand $command): void
    {
        $task = $this->taskRepository->find($command->getTaskId());

        if (!$task) {
            throw new \InvalidArgumentException('Task not found');
        }

        $task->setStatus('completed');
        $task->setCompletedAt(new \DateTime());

        $this->entityManager->flush();

        // Dispatch domain event
        $taskId = TaskId::fromInt($task->getId());
        $event = TaskCompleted::create(
            $taskId,
            $command->getCompletedByUserId(),
            new \DateTimeImmutable()
        );

        $this->eventBus->dispatch($event);
    }
}
