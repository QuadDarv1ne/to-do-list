<?php

namespace App\EventListener;

use App\Domain\Task\Event\TaskAssigned;
use App\Entity\ActivityLog;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Слушатель событий TaskAssigned
 * 
 * Обрабатывает событие назначения задачи:
 * - Записывает запись в Activity Log
 * - Отправляет уведомление новому исполнителю
 */
#[AsEventListener(event: TaskAssigned::class, method: 'onTaskAssigned')]
final class TaskAssignedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onTaskAssigned(TaskAssigned $event): void
    {
        $taskId = $event->getTaskId()->toInt();
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            return;
        }

        // Создаем запись в Activity Log
        $this->logActivity($task, $event);

        // Отправляем уведомление новому исполнителю
        $this->notifyNewAssignee($task, $event);
    }

    private function logActivity($task, TaskAssigned $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setTask($task);
        $activityLog->setUser($task->getUser());
        $activityLog->setAction('assigned');
        $activityLog->setEventType('task.assigned');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        
        $description = $event->getPreviousAssigneeId()
            ? sprintf('Задача "%s" пере назначена', $task->getTitle())
            : sprintf('Задаче "%s" назначен исполнитель', $task->getTitle());
        
        $activityLog->setDescription($description);

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyNewAssignee($task, TaskAssigned $event): void
    {
        $newAssigneeId = $event->getNewAssigneeId();
        
        // Не отправляем уведомление, если задача назначена создателю
        if ($newAssigneeId === $event->getAssignedByUserId()) {
            return;
        }

        // Находим пользователя (это должно быть сделано через UserRepository)
        // Для простоты создаем уведомление напрямую
        $notification = new \App\Entity\Notification();
        $notification->setUser($task->getAssignedUser());
        $notification->setType('task_assigned');
        $notification->setTitle('Вам назначена задача');
        $notification->setMessage(sprintf(
            'Вам назначена задача: %s',
            $task->getTitle()
        ));
        $notification->setLink($this->urlGenerator->generate('app_task_show', ['id' => $task->getId()]));
        $notification->setMetadata([
            'task_id' => $task->getId(),
            'assigned_by' => $event->getAssignedByUserId(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
