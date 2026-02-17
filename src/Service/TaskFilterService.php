<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for advanced task filtering and search capabilities
 */
class TaskFilterService
{
    private EntityManagerInterface $entityManager;
    private TaskRepository $taskRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->logger = $logger;
    }

    /**
     * Advanced task search with multiple criteria
     */
    public function advancedSearch(array $criteria, User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('t')
           ->from(Task::class, 't')
           ->where('(t.user = :user OR t.assignedUser = :user)');
        
        $parameters = ['user' => $user];
        
        // Text search
        if (!empty($criteria['search'])) {
            $qb->andWhere('(
                LOWER(t.title) LIKE :search OR 
                LOWER(t.description) LIKE :search OR
                LOWER(t.status) LIKE :search OR
                LOWER(t.priority) LIKE :search
            )');
            $parameters['search'] = '%' . strtolower($criteria['search']) . '%';
        }
        
        // Status filter
        if (!empty($criteria['status'])) {
            $qb->andWhere('t.status = :status');
            $parameters['status'] = $criteria['status'];
        }
        
        // Priority filter
        if (!empty($criteria['priority'])) {
            $qb->andWhere('t.priority = :priority');
            $parameters['priority'] = $criteria['priority'];
        }
        
        // Category filter
        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category');
            $parameters['category'] = $criteria['category'];
        }
        
        // Date range filters
        if (!empty($criteria['created_from'])) {
            $qb->andWhere('t.createdAt >= :created_from');
            $parameters['created_from'] = new \DateTime($criteria['created_from']);
        }
        
        if (!empty($criteria['created_to'])) {
            $qb->andWhere('t.createdAt <= :created_to');
            $parameters['created_to'] = new \DateTime($criteria['created_to']);
        }
        
        if (!empty($criteria['due_from'])) {
            $qb->andWhere('t.dueDate >= :due_from');
            $parameters['due_from'] = new \DateTime($criteria['due_from']);
        }
        
        if (!empty($criteria['due_to'])) {
            $qb->andWhere('t.dueDate <= :due_to');
            $parameters['due_to'] = new \DateTime($criteria['due_to']);
        }
        
        // Overdue filter
        if (!empty($criteria['overdue']) && $criteria['overdue']) {
            $qb->andWhere('t.dueDate < :now AND t.status != :completed');
            $parameters['now'] = new \DateTime();
            $parameters['completed'] = 'completed';
        }
        
        // Completed filter
        if (isset($criteria['completed'])) {
            if ($criteria['completed']) {
                $qb->andWhere('t.status = :completed');
                $parameters['completed'] = 'completed';
            } else {
                $qb->andWhere('t.status != :completed');
                $parameters['completed'] = 'completed';
            }
        }
        
        // Tag filter
        if (!empty($criteria['tags'])) {
            $qb->join('t.tags', 'tag')
               ->andWhere('tag.id IN (:tag_ids)');
            $parameters['tag_ids'] = is_array($criteria['tags']) ? $criteria['tags'] : [$criteria['tags']];
        }
        
        // Sort options
        $sortField = $criteria['sort_by'] ?? 'createdAt';
        $sortDirection = strtoupper($criteria['sort_direction'] ?? 'DESC');
        
        $allowedSortFields = ['title', 'createdAt', 'dueDate', 'priority', 'status'];
        if (in_array($sortField, $allowedSortFields)) {
            $qb->orderBy("t.{$sortField}", $sortDirection);
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }
        
        // Limit results
        if (!empty($criteria['limit'])) {
            $qb->setMaxResults((int) $criteria['limit']);
        }
        
        // Apply parameters
        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }
        
        $results = $qb->getQuery()->getResult();
        
        $this->logger->info("Advanced search executed", [
            'user_id' => $user->getId(),
            'criteria' => $criteria,
            'result_count' => count($results)
        ]);
        
        return $results;
    }

    /**
     * Get filter suggestions based on user's tasks
     */
    public function getFilterSuggestions(User $user): array
    {
        $suggestions = [
            'categories' => [],
            'tags' => [],
            'priorities' => [],
            'statuses' => []
        ];
        
        // Get categories
        $categories = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT t.category')
            ->from(Task::class, 't')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->andWhere('t.category IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();
            
        $suggestions['categories'] = array_column($categories, 'category');
        
        // Get tags (simplified - would need tag repository in real implementation)
        $tags = $this->entityManager->createQueryBuilder()
            ->select('tag.name, COUNT(t.id) as usage_count')
            ->from(Task::class, 't')
            ->join('t.tags', 'tag')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->groupBy('tag.id')
            ->orderBy('usage_count', 'DESC')
            ->setMaxResults(10)
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();
            
        $suggestions['tags'] = array_column($tags, 'name');
        
        // Get priorities
        $priorities = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT t.priority')
            ->from(Task::class, 't')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();
            
        $suggestions['priorities'] = array_column($priorities, 'priority');
        
        // Get statuses
        $statuses = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT t.status')
            ->from(Task::class, 't')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();
            
        $suggestions['statuses'] = array_column($statuses, 'status');
        
        return $suggestions;
    }

    /**
     * Save custom filter preset
     */
    public function saveFilterPreset(User $user, string $name, array $criteria): void
    {
        // In a real implementation, this would save to a FilterPreset entity
        $preset = [
            'name' => $name,
            'criteria' => $criteria,
            'user_id' => $user->getId(),
            'created_at' => new \DateTime()
        ];
        
        // Store in user preferences or separate entity
        $this->logger->info("Saved filter preset", $preset);
    }

    /**
     * Get saved filter presets for user
     */
    public function getFilterPresets(User $user): array
    {
        // In a real implementation, this would fetch from database
        return [
            [
                'id' => 1,
                'name' => 'Просроченные задачи',
                'criteria' => ['overdue' => true, 'completed' => false]
            ],
            [
                'id' => 2,
                'name' => 'Высокий приоритет',
                'criteria' => ['priority' => 'high']
            ],
            [
                'id' => 3,
                'name' => 'Назначенные мне',
                'criteria' => ['assigned_to_me' => true]
            ]
        ];
    }

    /**
     * Get task statistics by various filters
     */
    public function getTaskStatistics(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $stats = $qb->select('
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status != :completed THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN t.dueDate < :now AND t.status != :completed THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN t.priority = :high THEN 1 ELSE 0 END) as high_priority_tasks
            ')
            ->from(Task::class, 't')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('now', new \DateTime())
            ->setParameter('high', 'high')
            ->getQuery()
            ->getSingleResult();
            
        return [
            'total' => (int) $stats['total_tasks'],
            'completed' => (int) $stats['completed_tasks'],
            'pending' => (int) $stats['pending_tasks'],
            'overdue' => (int) $stats['overdue_tasks'],
            'high_priority' => (int) $stats['high_priority_tasks'],
            'completion_rate' => $stats['total_tasks'] > 0 ? 
                round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0
        ];
    }

    /**
     * Quick filter presets
     */
    public function getQuickFilters(): array
    {
        return [
            'today' => [
                'name' => 'Сегодня',
                'criteria' => [
                    'due_from' => (new \DateTime())->format('Y-m-d'),
                    'due_to' => (new \DateTime())->format('Y-m-d')
                ]
            ],
            'this_week' => [
                'name' => 'Эта неделя',
                'criteria' => [
                    'due_from' => (new \DateTime())->modify('monday this week')->format('Y-m-d'),
                    'due_to' => (new \DateTime())->modify('sunday this week')->format('Y-m-d')
                ]
            ],
            'overdue' => [
                'name' => 'Просроченные',
                'criteria' => ['overdue' => true]
            ],
            'high_priority' => [
                'name' => 'Высокий приоритет',
                'criteria' => ['priority' => 'high']
            ]
        ];
    }
}
