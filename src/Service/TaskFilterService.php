<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class TaskFilterService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Get predefined filters
     */
    public function getPredefinedFilters(): array
    {
        return [
            'my_tasks' => [
                'name' => 'Мои задачи',
                'icon' => 'fa-user',
                'description' => 'Задачи, созданные мной',
                'filter' => ['created_by_me' => true]
            ],
            'assigned_to_me' => [
                'name' => 'Назначенные мне',
                'icon' => 'fa-user-check',
                'description' => 'Задачи, назначенные мне',
                'filter' => ['assigned_to_me' => true]
            ],
            'today' => [
                'name' => 'Сегодня',
                'icon' => 'fa-calendar-day',
                'description' => 'Задачи на сегодня',
                'filter' => ['deadline' => 'today']
            ],
            'this_week' => [
                'name' => 'На этой неделе',
                'icon' => 'fa-calendar-week',
                'description' => 'Задачи на текущую неделю',
                'filter' => ['deadline' => 'this_week']
            ],
            'overdue' => [
                'name' => 'Просроченные',
                'icon' => 'fa-exclamation-triangle',
                'description' => 'Просроченные задачи',
                'filter' => ['is_overdue' => true],
                'color' => 'danger'
            ],
            'urgent' => [
                'name' => 'Срочные',
                'icon' => 'fa-fire',
                'description' => 'Срочные задачи',
                'filter' => ['priority' => 'urgent'],
                'color' => 'danger'
            ],
            'in_progress' => [
                'name' => 'В процессе',
                'icon' => 'fa-spinner',
                'description' => 'Задачи в работе',
                'filter' => ['status' => 'in_progress'],
                'color' => 'warning'
            ],
            'completed' => [
                'name' => 'Завершенные',
                'icon' => 'fa-check-circle',
                'description' => 'Завершенные задачи',
                'filter' => ['status' => 'completed'],
                'color' => 'success'
            ],
            'no_deadline' => [
                'name' => 'Без дедлайна',
                'icon' => 'fa-calendar-times',
                'description' => 'Задачи без дедлайна',
                'filter' => ['has_deadline' => false]
            ],
            'unassigned' => [
                'name' => 'Не назначенные',
                'icon' => 'fa-user-slash',
                'description' => 'Задачи без исполнителя',
                'filter' => ['is_unassigned' => true]
            ]
        ];
    }

    /**
     * Apply filter
     */
    public function applyFilter(string $filterKey, User $user): array
    {
        $filters = $this->getPredefinedFilters();
        
        if (!isset($filters[$filterKey])) {
            return [];
        }

        $filter = $filters[$filterKey]['filter'];
        
        return $this->executeFilter($filter, $user);
    }

    /**
     * Execute filter
     */
    private function executeFilter(array $filter, User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t');

        // Created by me
        if (isset($filter['created_by_me']) && $filter['created_by_me']) {
            $qb->andWhere('t.user = :user')
               ->setParameter('user', $user);
        }

        // Assigned to me
        if (isset($filter['assigned_to_me']) && $filter['assigned_to_me']) {
            $qb->andWhere('t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        // Deadline filters
        if (isset($filter['deadline'])) {
            switch ($filter['deadline']) {
                case 'today':
                    $start = new \DateTime('today 00:00:00');
                    $end = new \DateTime('today 23:59:59');
                    $qb->andWhere('t.deadline BETWEEN :start AND :end')
                       ->setParameter('start', $start)
                       ->setParameter('end', $end);
                    break;
                case 'this_week':
                    $start = new \DateTime('monday this week');
                    $end = new \DateTime('sunday this week');
                    $qb->andWhere('t.deadline BETWEEN :start AND :end')
                       ->setParameter('start', $start)
                       ->setParameter('end', $end);
                    break;
            }
        }

        // Overdue
        if (isset($filter['is_overdue']) && $filter['is_overdue']) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        // Priority
        if (isset($filter['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filter['priority']);
        }

        // Status
        if (isset($filter['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filter['status']);
        }

        // Has deadline
        if (isset($filter['has_deadline'])) {
            if ($filter['has_deadline']) {
                $qb->andWhere('t.deadline IS NOT NULL');
            } else {
                $qb->andWhere('t.deadline IS NULL');
            }
        }

        // Unassigned
        if (isset($filter['is_unassigned']) && $filter['is_unassigned']) {
            $qb->andWhere('t.assignedUser IS NULL');
        }

        return $qb->orderBy('t.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get filter count
     */
    public function getFilterCount(string $filterKey, User $user): int
    {
        $results = $this->applyFilter($filterKey, $user);
        return count($results);
    }

    /**
     * Get all filter counts
     */
    public function getAllFilterCounts(User $user): array
    {
        $filters = $this->getPredefinedFilters();
        $counts = [];

        foreach (array_keys($filters) as $key) {
            $counts[$key] = $this->getFilterCount($key, $user);
        }

        return $counts;
    }

    /**
     * Create custom filter
     */
    public function createCustomFilter(string $name, array $filter, User $user): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'name' => $name,
            'filter' => $filter,
            'user_id' => $user->getId(),
            'created_at' => new \DateTime()
        ];
    }

    /**
     * Get user custom filters
     */
    public function getUserCustomFilters(User $user): array
    {
        // TODO: Get from database
        return [];
    }
}
