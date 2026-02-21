<?php

namespace App\EventListener;

use App\Domain\Task\Event\TaskCompleted;
use App\Entity\ActivityLog;
use App\Entity\Notification;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Слушатель событий TaskCompleted
 * 
 * Обрабатывает событие завершения задачи:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления создателю и наблюдателям
 * - Обновляет статистику
 */
#[AsEventListener(event: TaskCompleted::class, method: 'onTaskCompleted')]
final class TaskCompletedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onTaskCompleted(TaskCompleted $event): void
    {
        $taskId = $event->getTaskId()->toInt();
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            return;
        }

        // Создаем запись в Activity Log
        $this->logActivity($task, $event);

        // Отправляем уведомления
        $this->notifyStakeholders($task, $event);

        // Обновляем статистику (опционально)
        $this->updateStatistics($task, $event);
    }

    private function logActivity($task, TaskCompleted $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setTask($task);
        $activityLog->setUser($task->getAssignedUser() ?? $task->getUser());
        $activityLog->setAction('completed');
        $activityLog->setEventType('task.completed');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Задача "%s" завершена',
            $task->getTitle()
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyStakeholders($task, TaskCompleted $event): void
    {
        $completedByUserId = $event->getCompletedByUserId();
        $taskOwnerId = $task->getUser()->getId();

        // Уведомляем владельца задачи (если он не тот, кто завершил)
        if ($taskOwnerId !== $completedByUserId) {
            $this->createNotification(
                $task->getUser(),
                'Задача завершена',
                sprintf('Пользователь завершил вашу задачу: %s', $task->getTitle()),
                $task
            );
        }

        // Уведомляем наблюдателей (watchers)
        // Примечание: функционал watchers требует добавления связи ManyToMany
        // между Task и User (таблица task_watchers)
    }

    private function createNotification($user, string $title, string $message, $task): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('task_completed');
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setLink($this->urlGenerator->generate('app_task_show', ['id' => $task->getId()]));
        $notification->setMetadata([
            'task_id' => $task->getId(),
            'completed_by' => $task->getAssignedUser()?->getId(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function updateStatistics($task, TaskCompleted $event): void
    {
        // Здесь можно обновлять кэш статистики пользователя
        // Например, увеличивать счетчик выполненных задач
        // Или обновлять данные для дашборда
    }
}
