<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class AdvancedSearchService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * Advanced search with multiple criteria
     */
    public function search(User $user, array $criteria): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg');

        // User access control
        if (!\in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        // Full-text search
        if (!empty($criteria['query'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('t.title', ':query'),
                    $qb->expr()->like('t.description', ':query'),
                    $qb->expr()->like('c.name', ':query'),
                    $qb->expr()->like('tg.name', ':query'),
                ),
            )->setParameter('query', '%' . $criteria['query'] . '%');
        }

        // Status filter
        if (!empty($criteria['status'])) {
            if (\is_array($criteria['status'])) {
                $qb->andWhere('t.status IN (:statuses)')
                   ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('t.status = :status')
                   ->setParameter('status', $criteria['status']);
            }
        }

        // Priority filter
        if (!empty($criteria['priority'])) {
            if (\is_array($criteria['priority'])) {
                $qb->andWhere('t.priority IN (:priorities)')
                   ->setParameter('priorities', $criteria['priority']);
            } else {
                $qb->andWhere('t.priority = :priority')
                   ->setParameter('priority', $criteria['priority']);
            }
        }

        // Category filter
        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        // Tags filter
        if (!empty($criteria['tags'])) {
            $qb->andWhere('tg.id IN (:tags)')
               ->setParameter('tags', $criteria['tags']);
        }

        // Assigned user filter
        if (!empty($criteria['assigned_user'])) {
            $qb->andWhere('t.assignedUser = :assignedUser')
               ->setParameter('assignedUser', $criteria['assigned_user']);
        }

        // Date range filters
        if (!empty($criteria['created_from'])) {
            $qb->andWhere('t.createdAt >= :createdFrom')
               ->setParameter('createdFrom', new \DateTime($criteria['created_from']));
        }

        if (!empty($criteria['created_to'])) {
            $qb->andWhere('t.createdAt <= :createdTo')
               ->setParameter('createdTo', new \DateTime($criteria['created_to']));
        }

        if (!empty($criteria['deadline_from'])) {
            $qb->andWhere('t.deadline >= :deadlineFrom')
               ->setParameter('deadlineFrom', new \DateTime($criteria['deadline_from']));
        }

        if (!empty($criteria['deadline_to'])) {
            $qb->andWhere('t.deadline <= :deadlineTo')
               ->setParameter('deadlineTo', new \DateTime($criteria['deadline_to']));
        }

        // Overdue tasks
        if (!empty($criteria['overdue'])) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        // Has deadline
        if (isset($criteria['has_deadline'])) {
            if ($criteria['has_deadline']) {
                $qb->andWhere('t.deadline IS NOT NULL');
            } else {
                $qb->andWhere('t.deadline IS NULL');
            }
        }

        // Sorting
        $sortBy = $criteria['sort_by'] ?? 'createdAt';
        $sortOrder = $criteria['sort_order'] ?? 'DESC';

        $allowedSortFields = ['createdAt', 'updatedAt', 'deadline', 'priority', 'title'];
        if (\in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('t.' . $sortBy, $sortOrder);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get search suggestions based on query
     */
    public function getSuggestions(User $user, string $query, int $limit = 5): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title, t.id')
            ->where('t.title LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit);

        if (!\in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms(User $user, int $limit = 10): array
    {
        // This would typically come from a search_log table
        // For now, return most common task titles
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title, COUNT(t.id) as task_count')
            ->groupBy('t.title')
            ->orderBy('task_count', 'DESC')
            ->setMaxResults($limit);

        if (!\in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
