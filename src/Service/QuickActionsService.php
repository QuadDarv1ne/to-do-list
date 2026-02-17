<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuickActionsService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}

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
     * Quick complete task
     */
    public function quickComplete(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setStatus('completed');
        $task->setCompletedAt(new \DateTime());
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick delete task
     */
    public function quickDelete(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick assign task
     */
    public function quickAssign(int $taskId, User $user): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setAssignedUser($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick change priority
     */
    public function quickChangePriority(int $taskId, string $priority): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setPriority($priority);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick change status
     */
    public function quickChangeStatus(int $taskId, string $status): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setStatus($status);
        $this->entityManager->flush();

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
     * Quick move to today
     */
    public function quickMoveToToday(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('today 23:59:59'));
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick move to tomorrow
     */
    public function quickMoveToTomorrow(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('tomorrow 23:59:59'));
        $this->entityManager->flush();

        return true;
    }

    /**
     * Quick move to next week
     */
    public function quickMoveToNextWeek(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return false;
        }

        $task->setDeadline(new \DateTime('+1 week'));
        $this->entityManager->flush();

        return true;
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
