<?php

namespace App\Application\EventHandler;

use App\Domain\Task\Event\TaskCompleted;
use App\Service\NotificationService;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TaskCompletedEventHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(TaskCompleted $event): void
    {
        $this->logger->info('Handling TaskCompleted event', [
            'task_id' => $event->getTaskId()->toInt(),
        ]);

        // Find task to get creator
        $task = $this->taskRepository->find($event->getTaskId()->toInt());
        
        if (!$task) {
            $this->logger->warning('Task not found for completed event', [
                'task_id' => $event->getTaskId()->toInt(),
            ]);
            return;
        }

        $completedByUser = $this->userRepository->find($event->getCompletedByUserId());
        $taskCreator = $task->getUser();

        // Notify task creator if different from completer
        if ($taskCreator && $completedByUser && $taskCreator->getId() !== $completedByUser->getId()) {
            $this->notificationService->createNotification(
                $taskCreator,
                'Задача выполнена',
                sprintf(
                    'Задача "%s" выполнена пользователем %s',
                    $task->getTitle(),
                    $completedByUser->getFullName()
                ),
                $task
            );
        }

        // Log completion
        $this->logger->info('Task completed', [
            'task_id' => $event->getTaskId()->toInt(),
            'completed_by' => $event->getCompletedByUserId(),
            'completed_at' => $event->getCompletedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
