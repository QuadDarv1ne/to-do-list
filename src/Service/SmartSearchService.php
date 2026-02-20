<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

class SmartSearchService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CommentRepository $commentRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Smart search across all entities
     */
    public function search(string $query, User $user, array $options = []): array
    {
        $results = [
            'tasks' => [],
            'comments' => [],
            'users' => [],
            'total' => 0,
        ];

        // Search tasks
        if (!isset($options['entities']) || \in_array('tasks', $options['entities'])) {
            $results['tasks'] = $this->searchTasks($query, $user, $options);
        }

        // Search comments
        if (!isset($options['entities']) || \in_array('comments', $options['entities'])) {
            $results['comments'] = $this->searchComments($query, $user, $options);
        }

        // Search users
        if (!isset($options['entities']) || \in_array('users', $options['entities'])) {
            $results['users'] = $this->searchUsers($query, $options);
        }

        $results['total'] = \count($results['tasks']) + \count($results['comments']) + \count($results['users']);

        return $results;
    }

    /**
     * Search tasks
     */
    private function searchTasks(string $query, User $user, array $options): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('(t.title LIKE :query OR t.description LIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.createdAt', 'DESC');

        // Apply filters
        if (isset($options['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $options['status']);
        }

        if (isset($options['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $options['priority']);
        }

        if (isset($options['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $options['category']);
        }

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search comments
     */
    private function searchComments(string $query, User $user, array $options): array
    {
        $qb = $this->commentRepository->createQueryBuilder('c')
            ->join('c.task', 't')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('c.content LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC');

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search users
     */
    private function searchUsers(string $query, array $options): array
    {
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query OR CONCAT(u.firstName, \' \', u.lastName) LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC');

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get search suggestions
     */
    public function getSuggestions(string $query, User $user, int $limit = 5): array
    {
        $suggestions = [];

        // Get recent searches
        // TODO: Store in database

        // Get popular searches
        // TODO: Track in database

        // Get task titles matching query
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.title LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $suggestions[] = $task['title'];
        }

        return array_unique($suggestions);
    }

    /**
     * Advanced search with filters
     */
    public function advancedSearch(array $filters, User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user);

        // Text search
        if (!empty($filters['query'])) {
            $qb->andWhere('(t.title LIKE :query OR t.description LIKE :query)')
               ->setParameter('query', '%' . $filters['query'] . '%');
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('t.status IN (:statuses)')
               ->setParameter('statuses', $filters['status']);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority IN (:priorities)')
               ->setParameter('priorities', $filters['priority']);
        }

        // Category filter
        if (!empty($filters['category'])) {
            $qb->andWhere('t.category IN (:categories)')
               ->setParameter('categories', $filters['category']);
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        // Deadline range
        if (!empty($filters['deadline_from'])) {
            $qb->andWhere('t.deadline >= :deadlineFrom')
               ->setParameter('deadlineFrom', new \DateTime($filters['deadline_from']));
        }

        if (!empty($filters['deadline_to'])) {
            $qb->andWhere('t.deadline <= :deadlineTo')
               ->setParameter('deadlineTo', new \DateTime($filters['deadline_to']));
        }

        // Assigned user
        if (!empty($filters['assigned_user'])) {
            $qb->andWhere('t.assignedUser = :assignedUser')
               ->setParameter('assignedUser', $filters['assigned_user']);
        }

        // Has deadline
        if (isset($filters['has_deadline'])) {
            if ($filters['has_deadline']) {
                $qb->andWhere('t.deadline IS NOT NULL');
            } else {
                $qb->andWhere('t.deadline IS NULL');
            }
        }

        // Is overdue
        if (isset($filters['is_overdue']) && $filters['is_overdue']) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'createdAt';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        $qb->orderBy('t.' . $sortBy, $sortOrder);

        return $qb->getQuery()->getResult();
    }

    /**
     * Save search query
     */
    public function saveSearch(string $name, array $filters, User $user): array
    {
        // TODO: Save to database
        return [
            'name' => $name,
            'filters' => $filters,
            'user_id' => $user->getId(),
            'created_at' => new \DateTime(),
        ];
    }

    /**
     * Get saved searches
     */
    public function getSavedSearches(User $user): array
    {
        // TODO: Get from database
        return [];
    }
}
