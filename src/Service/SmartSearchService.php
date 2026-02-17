<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;

class SmartSearchService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CommentRepository $commentRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Smart search across all entities
     */
    public function search(string $query, User $user, array $filters = []): array
    {
        $results = [
            'tasks' => [],
            'comments' => [],
            'users' => [],
            'total' => 0
        ];

        // Search tasks
        if (!isset($filters['type']) || $filters['type'] === 'tasks') {
            $results['tasks'] = $this->searchTasks($query, $user, $filters);
        }

        // Search comments
        if (!isset($filters['type']) || $filters['type'] === 'comments') {
            $results['comments'] = $this->searchComments($query, $user);
        }

        // Search users
        if (!isset($filters['type']) || $filters['type'] === 'users') {
            $results['users'] = $this->searchUsers($query);
        }

        $results['total'] = count($results['tasks']) + 
                           count($results['comments']) + 
                           count($results['users']);

        return $results;
    }

    /**
     * Search tasks
     */
    private function searchTasks(string $query, User $user, array $filters): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('(t.title LIKE :query OR t.description LIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%');

        // Apply filters
        if (isset($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (isset($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
                  ->setMaxResults(20)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Search comments
     */
    private function searchComments(string $query, User $user): array
    {
        return $this->commentRepository->createQueryBuilder('c')
            ->join('c.task', 't')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('c.content LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users
     */
    private function searchUsers(string $query): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query OR u.fullName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get search suggestions
     */
    public function getSuggestions(string $query, User $user): array
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
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $suggestions[] = $task['title'];
        }

        return array_unique($suggestions);
    }

    /**
     * Get search history
     */
    public function getSearchHistory(User $user, int $limit = 10): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Save search query
     */
    public function saveSearch(string $query, User $user): void
    {
        // TODO: Save to database
    }

    /**
     * Get popular searches
     */
    public function getPopularSearches(int $limit = 10): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Advanced search with multiple criteria
     */
    public function advancedSearch(array $criteria, User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user);

        if (isset($criteria['title'])) {
            $qb->andWhere('t.title LIKE :title')
               ->setParameter('title', '%' . $criteria['title'] . '%');
        }

        if (isset($criteria['description'])) {
            $qb->andWhere('t.description LIKE :description')
               ->setParameter('description', '%' . $criteria['description'] . '%');
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('t.status IN (:statuses)')
               ->setParameter('statuses', $criteria['status']);
        }

        if (isset($criteria['priority'])) {
            $qb->andWhere('t.priority IN (:priorities)')
               ->setParameter('priorities', $criteria['priority']);
        }

        if (isset($criteria['deadline_from'])) {
            $qb->andWhere('t.deadline >= :deadline_from')
               ->setParameter('deadline_from', $criteria['deadline_from']);
        }

        if (isset($criteria['deadline_to'])) {
            $qb->andWhere('t.deadline <= :deadline_to')
               ->setParameter('deadline_to', $criteria['deadline_to']);
        }

        if (isset($criteria['created_from'])) {
            $qb->andWhere('t.createdAt >= :created_from')
               ->setParameter('created_from', $criteria['created_from']);
        }

        if (isset($criteria['created_to'])) {
            $qb->andWhere('t.createdAt <= :created_to')
               ->setParameter('created_to', $criteria['created_to']);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}
