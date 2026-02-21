<?php

namespace App\EventListener;

use App\Domain\Task\Event\TaskCreated;
use App\Entity\ActivityLog;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Слушатель событий TaskCreated
 * 
 * Обрабатывает событие создания задачи:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления
 */
#[AsEventListener(event: TaskCreated::class, method: 'onTaskCreated')]
final class TaskCreatedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onTaskCreated(TaskCreated $event): void
    {
        $taskId = $event->getTaskId()->toInt();
        $task = $this->taskRepository->find($taskId);

        if (!$task instanceof Task) {
            return;
        }

        // Создаем запись в Activity Log
        $this->logActivity($task, $event);

        // Отправляем уведомления (если задача назначена другому пользователю)
        $this->notifyAssignee($task, $event);
    }

    private function logActivity(Task $task, TaskCreated $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setTask($task);
        $activityLog->setUser($task->getUser());
        $activityLog->setAction('created');
        $activityLog->setEventType('task.created');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Создана задача "%s" с приоритетом %s',
            $task->getTitle(),
            $this->getPriorityLabel($task->getPriority())
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyAssignee(Task $task, TaskCreated $event): void
    {
        $assignedUser = $task->getAssignedUser();

        if (!$assignedUser || $assignedUser->getId() === $event->getUserId()) {
            return; // Не отправляем уведомление, если задача назначена создателю
        }

        // Создаем уведомление
        $notification = new \App\Entity\Notification();
        $notification->setUser($assignedUser);
        $notification->setType('task_assigned');
        $notification->setTitle('Вам назначена новая задача');
        $notification->setMessage(sprintf(
            'Пользователь %s назначил вам задачу: %s',
            $task->getUser()->getFullName(),
            $task->getTitle()
        ));
        $notification->setLink($this->urlGenerator->generate('app_task_show', ['id' => $task->getId()]));
        $notification->setMetadata([
            'task_id' => $task->getId(),
            'priority' => $task->getPriority(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function getPriorityLabel(string $priority): string
    {
        return match($priority) {
            'urgent' => 'Срочный',
            'high' => 'Высокий',
            'medium' => 'Средний',
            'low' => 'Низкий',
            default => $priority,
        };
    }
}
