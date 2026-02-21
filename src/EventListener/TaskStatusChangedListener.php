<?php

namespace App\EventListener;

use App\Domain\Task\Event\TaskStatusChanged;
use App\Entity\ActivityLog;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель событий TaskStatusChanged
 * 
 * Обрабатывает событие изменения статуса задачи:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления при изменении статуса
 */
#[AsEventListener(event: TaskStatusChanged::class, method: 'onTaskStatusChanged')]
final class TaskStatusChangedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
    ) {
    }

    public function onTaskStatusChanged(TaskStatusChanged $event): void
    {
        $taskId = $event->getTaskId()->toInt();
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            return;
        }

        // Создаем запись в Activity Log
        $this->logActivity($task, $event);
    }

    private function logActivity($task, TaskStatusChanged $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setTask($task);
        $activityLog->setUser($task->getAssignedUser() ?? $task->getUser());
        $activityLog->setAction('status_changed');
        $activityLog->setEventType('task.status_changed');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Статус задачи "%s" изменён с "%s" на "%s"',
            $task->getTitle(),
            $this->getStatusLabel($event->getOldStatus()->value),
            $this->getStatusLabel($event->getNewStatus()->value)
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'Ожидает',
            'in_progress' => 'В работе',
            'completed' => 'Завершено',
            default => $status,
        };
    }
}
