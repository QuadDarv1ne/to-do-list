<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\QueryBuilder;

class TaskFilterService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Apply filters to query builder
     */
    public function applyFilters(QueryBuilder $qb, array $filters, User $user): QueryBuilder
    {
        // User filter
        if (!isset($filters['show_all']) || !$filters['show_all']) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $qb->andWhere('t.status IN (:statuses)')
                   ->setParameter('statuses', $filters['status']);
            } else {
                $qb->andWhere('t.status = :status')
                   ->setParameter('status', $filters['status']);
            }
        }

        // Priority filter
        if (isset($filters['priority']) && !empty($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $qb->andWhere('t.priority IN (:priorities)')
                   ->setParameter('priorities', $filters['priority']);
            } else {
                $qb->andWhere('t.priority = :priority')
                   ->setParameter('priority', $filters['priority']);
            }
        }

        // Category filter
        if (isset($filters['category']) && !empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        // Assigned user filter
        if (isset($filters['assigned_to']) && !empty($filters['assigned_to'])) {
            $qb->andWhere('t.assignedUser = :assignedUser')
               ->setParameter('assignedUser', $filters['assigned_to']);
        }

        // Date range filter
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        // Deadline filter
        if (isset($filters['deadline_from']) && !empty($filters['deadline_from'])) {
            $qb->andWhere('t.deadline >= :deadlineFrom')
               ->setParameter('deadlineFrom', new \DateTime($filters['deadline_from']));
        }

        if (isset($filters['deadline_to']) && !empty($filters['deadline_to'])) {
            $qb->andWhere('t.deadline <= :deadlineTo')
               ->setParameter('deadlineTo', new \DateTime($filters['deadline_to']));
        }

        // Overdue filter
        if (isset($filters['overdue']) && $filters['overdue']) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        // Has deadline filter
        if (isset($filters['has_deadline'])) {
            if ($filters['has_deadline']) {
                $qb->andWhere('t.deadline IS NOT NULL');
            } else {
                $qb->andWhere('t.deadline IS NULL');
            }
        }

        // Tags filter
        if (isset($filters['tags']) && !empty($filters['tags'])) {
            $qb->leftJoin('t.tags', 'tag')
               ->andWhere('tag.id IN (:tags)')
               ->setParameter('tags', $filters['tags']);
        }

        // Search query
        if (isset($filters['search']) && !empty($filters['search'])) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Sorting
        $sortField = $filters['sort_by'] ?? 'createdAt';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        
        $qb->orderBy('t.' . $sortField, $sortOrder);

        return $qb;
    }

    /**
     * Get predefined filters
     */
    public function getPredefinedFilters(): array
    {
        return [
            'my_tasks' => [
                'name' => 'Мои задачи',
                'icon' => 'fa-user',
                'filters' => ['show_all' => false]
            ],
            'assigned_to_me' => [
                'name' => 'Назначенные мне',
                'icon' => 'fa-user-check',
                'filters' => ['assigned_to' => 'current_user']
            ],
            'urgent' => [
                'name' => 'Срочные',
                'icon' => 'fa-exclamation-circle',
                'filters' => ['priority' => 'urgent']
            ],
            'overdue' => [
                'name' => 'Просроченные',
                'icon' => 'fa-clock',
                'filters' => ['overdue' => true]
            ],
            'in_progress' => [
                'name' => 'В работе',
                'icon' => 'fa-spinner',
                'filters' => ['status' => 'in_progress']
            ],
            'completed' => [
                'name' => 'Завершенные',
                'icon' => 'fa-check-circle',
                'filters' => ['status' => 'completed']
            ],
            'this_week' => [
                'name' => 'На этой неделе',
                'icon' => 'fa-calendar-week',
                'filters' => [
                    'deadline_from' => 'monday this week',
                    'deadline_to' => 'sunday this week'
                ]
            ],
            'no_deadline' => [
                'name' => 'Без дедлайна',
                'icon' => 'fa-calendar-times',
                'filters' => ['has_deadline' => false]
            ]
        ];
    }

    /**
     * Save custom filter
     */
    public function saveCustomFilter(User $user, string $name, array $filters): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'name' => $name,
            'filters' => $filters,
            'user' => $user
        ];
    }

    /**
     * Get user's custom filters
     */
    public function getUserFilters(User $user): array
    {
        // TODO: Load from database
        return [];
    }
}
