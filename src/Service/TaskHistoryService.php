<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskHistory;
use App\Entity\User;
use App\Repository\TaskHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskHistoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskHistoryRepository $historyRepository
    ) {
    }

    /**
     * Записать создание задачи
     */
    public function logTaskCreated(Task $task, User $user): void
    {
        $history = new TaskHistory();
        $history->setTask($task);
        $history->setUser($user);
        $history->setAction('created');
        $history->setMetadata([
            'title' => $task->getTitle(),
            'priority' => $task->getPriority(),
            'status' => $task->getStatus()
        ]);

        $this->historyRepository->save($history, true);
    }

    /**
     * Записать изменение задачи
     */
    public function logTaskUpdated(Task $task, User $user, array $changes): void
    {
        foreach ($changes as $field => $values) {
            $history = new TaskHistory();
            $history->setTask($task);
            $history->setUser($user);
            $history->setAction('updated');
            $history->setField($field);
            $history->setOldValue($this->formatValue($values['old']));
            $history->setNewValue($this->formatValue($values['new']));

            $this->historyRepository->save($history);
        }

        $this->entityManager->flush();
    }

    /**
     * Записать удаление задачи
     */
    public function logTaskDeleted(Task $task, User $user): void
    {
        $history = new TaskHistory();
        $history->setTask($task);
        $history->setUser($user);
        $history->setAction('deleted');
        $history->setMetadata([
            'title' => $task->getTitle(),
            'deleted_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        $this->historyRepository->save($history, true);
    }

    /**
     * Записать изменение статуса
     */
    public function logStatusChanged(Task $task, User $user, string $oldStatus, string $newStatus): void
    {
        $history = new TaskHistory();
        $history->setTask($task);
        $history->setUser($user);
        $history->setAction('status_changed');
        $history->setField('status');
        $history->setOldValue($oldStatus);
        $history->setNewValue($newStatus);

        $this->historyRepository->save($history, true);
    }

    /**
     * Записать назначение задачи
     */
    public function logTaskAssigned(Task $task, User $assignedBy, User $assignedTo): void
    {
        $history = new TaskHistory();
        $history->setTask($task);
        $history->setUser($assignedBy);
        $history->setAction('assigned');
        $history->setMetadata([
            'assigned_to' => $assignedTo->getFullName(),
            'assigned_to_id' => $assignedTo->getId()
        ]);

        $this->historyRepository->save($history, true);
    }

    /**
     * Записать добавление комментария
     */
    public function logCommentAdded(Task $task, User $user): void
    {
        $history = new TaskHistory();
        $history->setTask($task);
        $history->setUser($user);
        $history->setAction('comment_added');

        $this->historyRepository->save($history, true);
    }

    /**
     * Получить историю задачи
     */
    public function getTaskHistory(Task $task, int $limit = 50): array
    {
        return $this->historyRepository->findByTask($task, $limit);
    }

    /**
     * Получить активность пользователя
     */
    public function getUserActivity(User $user, int $limit = 50): array
    {
        return $this->historyRepository->findByUser($user, $limit);
    }

    /**
     * Получить последние изменения
     */
    public function getRecentActivity(int $limit = 20): array
    {
        return $this->historyRepository->findRecent($limit);
    }

    /**
     * Получить статистику за период
     */
    public function getActivityStats(\DateTime $from, \DateTime $to): array
    {
        return $this->historyRepository->getStatsByPeriod($from, $to);
    }

    /**
     * Очистить старую историю (старше N дней)
     */
    public function cleanOldHistory(int $days = 90): int
    {
        $date = new \DateTime("-{$days} days");
        return $this->historyRepository->deleteOlderThan($date);
    }

    /**
     * Форматировать значение для хранения
     */
    private function formatValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            if (method_exists($value, 'getId')) {
                return get_class($value) . '#' . $value->getId();
            }
            return get_class($value);
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Получить читаемое описание изменения
     */
    public function getChangeDescription(TaskHistory $history): string
    {
        $user = $history->getUser();
        $userName = $user ? $user->getFullName() : 'Система';
        
        return match($history->getAction()) {
            'created' => "{$userName} создал(а) задачу",
            'updated' => "{$userName} изменил(а) {$this->getFieldName($history->getField())} с '{$history->getOldValue()}' на '{$history->getNewValue()}'",
            'deleted' => "{$userName} удалил(а) задачу",
            'status_changed' => "{$userName} изменил(а) статус с '{$history->getOldValue()}' на '{$history->getNewValue()}'",
            'assigned' => "{$userName} назначил(а) задачу",
            'comment_added' => "{$userName} добавил(а) комментарий",
            default => "{$userName} выполнил(а) действие: {$history->getAction()}"
        };
    }

    /**
     * Получить читаемое название поля
     */
    private function getFieldName(?string $field): string
    {
        return match($field) {
            'title' => 'название',
            'description' => 'описание',
            'priority' => 'приоритет',
            'status' => 'статус',
            'dueDate' => 'срок выполнения',
            'assignedUser' => 'исполнителя',
            'category' => 'категорию',
            default => $field ?? 'поле'
        };
    }
}
