<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuickActionsService
{
    private array $taskCache = [];

    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get task by ID with caching
     */
    public function getTask(int $taskId): ?Task
    {
        if (!isset($this->taskCache[$taskId])) {
            $this->taskCache[$taskId] = $this->taskRepository->find($taskId);
        }
        return $this->taskCache[$taskId];
    }

    /**
     * Bulk operations for better performance
     */
    public function bulkQuickActions(array $operations): array
    {
        $results = [];
        $taskIds = array_unique(array_column($operations, 'taskId'));
        
        // Предзагружаем все задачи одним запросом
        $tasks = $this->taskRepository->findBy(['id' => $taskIds]);
        foreach ($tasks as $task) {
            $this->taskCache[$task->getId()] = $task;
        }

        foreach ($operations as $operation) {
            $taskId = $operation['taskId'];
            $action = $operation['action'];
            $params = $operation['params'] ?? [];

            $result = match($action) {
                'complete' => $this->quickComplete($taskId),
                'delete' => $this->quickDelete($taskId),
                'assign' => $this->quickAssign($taskId, $params['user']),
                'priority' => $this->quickChangePriority($taskId, $params['priority']),
                'status' => $this->quickChangeStatus($taskId, $params['status']),
                'move_today' => $this->quickMoveToToday($taskId),
                'move_tomorrow' => $this->quickMoveToTomorrow($taskId),
                'move_next_week' => $this->quickMoveToNextWeek($taskId),
                default => false
            };

            $results[$taskId] = $result;
        }

        // Один flush для всех операций
        $this->entityManager->flush();
        return $results;
    }

    /**
     * Quick create task
     */
    public function quickCreateTask(string $title, User $user, array $options = []): Task
    {
        $task = new Task();
        $task->setTitle($title);
        $task->setUser($user);
        $task->setStatus($options['status'] ?? 'pending');
        $task->setPriority($options['priority'] ?? 'medium');

        if (isset($options['deadline'])) {
            $task->setDeadline(new \DateTime($options['deadline']));
        }

        if (isset($options['description'])) {
            $task->setDescription($options['description']);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Quick complete task (optimized)
     */
    public function quickComplete(int $taskId): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setStatus('completed');
        $task->setCompletedAt(new \DateTime());
        // Не вызываем flush здесь для bulk операций

        return true;
    }

    /**
     * Quick delete task (optimized)
     */
    public function quickDelete(int $taskId): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $this->entityManager->remove($task);
        unset($this->taskCache[$taskId]);
        // Не вызываем flush здесь для bulk операций

        return true;
    }

    /**
     * Quick assign task (optimized)
     */
    public function quickAssign(int $taskId, User $user): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setAssignedUser($user);
        // Не вызываем flush здесь для bulk операций

        return true;
    }

    /**
     * Quick change priority (optimized)
     */
    public function quickChangePriority(int $taskId, string $priority): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setPriority($priority);
        // Не вызываем flush здесь для bulk операций

        return true;
    }

    /**
     * Quick change status (optimized)
     */
    public function quickChangeStatus(int $taskId, string $status): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setStatus($status);
        // Не вызываем flush здесь для bulk операций

        return true;
    }

    /**
     * Quick duplicate task
     */
    public function quickDuplicate(int $taskId): ?Task
    {
        $original = $this->taskRepository->find($taskId);
        if (!$original) {
            return null;
        }

        $duplicate = new Task();
        $duplicate->setTitle($original->getTitle() . ' (копия)');
        $duplicate->setDescription($original->getDescription());
        $duplicate->setPriority($original->getPriority());
        $duplicate->setStatus('pending');
        $duplicate->setUser($original->getUser());
        $duplicate->setCategory($original->getCategory());

        foreach ($original->getTags() as $tag) {
            $duplicate->addTag($tag);
        }

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }

    /**
     * Quick move to today (optimized)
     */
    public function quickMoveToToday(int $taskId): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('today 23:59:59'));
        return true;
    }

    /**
     * Quick move to tomorrow (optimized)
     */
    public function quickMoveToTomorrow(int $taskId): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('tomorrow 23:59:59'));
        return true;
    }

    /**
     * Quick move to next week (optimized)
     */
    public function quickMoveToNextWeek(int $taskId): bool
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('+1 week'));
        return true;
    }

    /**
     * Single operation with immediate flush (for backward compatibility)
     */
    public function executeQuickAction(int $taskId, string $action, array $params = []): bool
    {
        $result = $this->bulkQuickActions([
            ['taskId' => $taskId, 'action' => $action, 'params' => $params]
        ]);
        
        return $result[$taskId] ?? false;
    }

    /**
     * Get available quick actions
     */
    public function getAvailableActions(): array
    {
        return [
            'complete' => [
                'name' => 'Завершить',
                'icon' => 'fa-check',
                'color' => 'success',
                'shortcut' => 'C'
            ],
            'delete' => [
                'name' => 'Удалить',
                'icon' => 'fa-trash',
                'color' => 'danger',
                'shortcut' => 'D'
            ],
            'duplicate' => [
                'name' => 'Дублировать',
                'icon' => 'fa-copy',
                'color' => 'info',
                'shortcut' => 'Ctrl+D'
            ],
            'move_today' => [
                'name' => 'На сегодня',
                'icon' => 'fa-calendar-day',
                'color' => 'primary',
                'shortcut' => '1'
            ],
            'move_tomorrow' => [
                'name' => 'На завтра',
                'icon' => 'fa-calendar-plus',
                'color' => 'primary',
                'shortcut' => '2'
            ],
            'move_next_week' => [
                'name' => 'На след. неделю',
                'icon' => 'fa-calendar-week',
                'color' => 'primary',
                'shortcut' => '3'
            ]
        ];
    }
}
