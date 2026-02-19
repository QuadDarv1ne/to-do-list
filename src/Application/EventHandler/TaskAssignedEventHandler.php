<?php

namespace App\Application\EventHandler;

use App\Domain\Task\Event\TaskAssigned;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TaskAssignedEventHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TaskAssigned $event): void
    {
        $this->logger->info('Handling TaskAssigned event', [
            'task_id' => $event->getTaskId()->toInt(),
            'new_assignee_id' => $event->getNewAssigneeId(),
        ]);

        $task = $this->taskRepository->find($event->getTaskId()->toInt());

        if (!$task) {
            return;
        }

        $newAssignee = $this->userRepository->find($event->getNewAssigneeId());
        $assignedBy = $this->userRepository->find($event->getAssignedByUserId());

        if ($newAssignee && $assignedBy) {
            $this->notificationService->createNotification(
                $newAssignee,
                'Задача назначена',
                \sprintf(
                    'Вам назначена задача "%s" пользователем %s',
                    $task->getTitle(),
                    $assignedBy->getFullName(),
                ),
                $task,
            );
        }
    }
}
