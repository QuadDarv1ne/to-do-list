<?php

namespace App\Application\EventHandler;

use App\Domain\Task\Event\TaskCreated;
use App\Service\NotificationService;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TaskCreatedEventHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(TaskCreated $event): void
    {
        $this->logger->info('Handling TaskCreated event', [
            'task_id' => $event->getTaskId()->toInt(),
            'title' => $event->getTitle()->toString(),
        ]);

        // Send notification to assigned user
        $assignedUser = $this->userRepository->find($event->getAssignedUserId());
        $createdByUser = $this->userRepository->find($event->getUserId());

        if ($assignedUser && $createdByUser && $assignedUser->getId() !== $createdByUser->getId()) {
            $this->notificationService->createNotification(
                $assignedUser,
                'Новая задача',
                sprintf(
                    'Вам назначена задача "%s" пользователем %s',
                    $event->getTitle()->toString(),
                    $createdByUser->getFullName()
                ),
                null
            );
        }

        // Log to activity log
        $this->logger->info('Task created', [
            'task_id' => $event->getTaskId()->toInt(),
            'user_id' => $event->getUserId(),
            'assigned_user_id' => $event->getAssignedUserId(),
        ]);
    }
}
