<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }
    
    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }
    
    /**
     * Count tasks by status and optionally by user
     */
    public function countByStatus(?User $user = null, ?bool $isDone = null, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('t');
        
        if ($user !== null) {
            $qb->andWhere('t.assignedUser = :user')
               ->setParameter('user', $user);
        }
        
        if ($isDone !== null) {
            $qb->andWhere('t.status = :statusValue')
               ->setParameter('statusValue', $isDone ? 'completed' : 'pending');
        }
        
        if ($status !== null) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }
        
        return $qb->select('COUNT(t.id)')
                  ->getQuery()
                  ->getSingleScalarResult();
    }
    
    /**
     * Count tasks by priority and optionally by user
     */
    public function countByPriority(?User $user = null, ?bool $isDone = null, ?string $priority = null): int
    {
        $qb = $this->createQueryBuilder('t');
        
        if ($user !== null) {
            $qb->andWhere('t.assignedUser = :user OR t.user = :user')
               ->setParameter('user', $user);
        }
        
        if ($isDone !== null) {
            $qb->andWhere('t.status = :statusValue')
               ->setParameter('statusValue', $isDone ? 'completed' : 'pending');
        }
        
        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $priority);
        }
        
        return $qb->select('COUNT(t.id)')
                  ->getQuery()
                  ->getSingleScalarResult();
    }

    /**
     * Find tasks assigned to a specific user
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all tasks ordered by creation date
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks with deadlines approaching
     */
    public function findUpcomingDeadlines(\DateTimeImmutable $beforeDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate <= :beforeDate')
            ->andWhere('t.status = :status')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks assigned to user or created by user
     */
    public function findByAssignedToOrCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.user', 'cu')->addSelect('cu')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('au = :user OR cu = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks by search query with optimized performance
     * Uses full-text search where possible and limits results
     */
    public function findBySearchQuery(string $searchQuery, int $limit = 50): array
    {
        // Use cached query for common searches
        $cacheKey = 'search_' . md5($searchQuery . '_' . $limit);
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($searchQuery, $limit) {
                    return $this->performSearchQuery($searchQuery, $limit);
                },
                ['search' => $searchQuery, 'limit' => $limit],
                300 // 5 minutes cache
            );
        }
        
        return $this->performSearchQuery($searchQuery, $limit);
    }
    
    /**
     * Internal search implementation
     */
    private function performSearchQuery(string $searchQuery, int $limit): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.user', 'cu')->addSelect('cu')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->where('(
                LOWER(t.title) LIKE :search OR
                LOWER(t.description) LIKE :search OR
                LOWER(au.firstName) LIKE :search OR
                LOWER(au.lastName) LIKE :search OR
                LOWER(cu.firstName) LIKE :search OR
                LOWER(cu.lastName) LIKE :search OR
                LOWER(t.priority) LIKE :search OR
                t.status = :doneStatus
            )')
            ->setParameter('search', '%' . strtolower($searchQuery) . '%')
            ->setParameter('doneStatus', $searchQuery === 'completed' || $searchQuery === 'выполнено' || $searchQuery === 'done')
            ->setMaxResults($limit);
            
        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks by search query for specific user with caching
     */
    public function findBySearchQueryAndUser(string $searchQuery, User $user, int $limit = 50): array
    {
        // Use cached query for user-specific searches
        $cacheKey = 'user_search_' . $user->getId() . '_' . md5($searchQuery . '_' . $limit);
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($searchQuery, $user, $limit) {
                    return $this->performUserSearchQuery($searchQuery, $user, $limit);
                },
                ['user' => $user->getId(), 'search' => $searchQuery, 'limit' => $limit],
                300 // 5 minutes cache
            );
        }
        
        return $this->performUserSearchQuery($searchQuery, $user, $limit);
    }
    
    /**
     * Internal user search implementation
     */
    private function performUserSearchQuery(string $searchQuery, User $user, int $limit): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.user', 'cu')->addSelect('cu')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('(au = :user OR cu = :user)')
            ->andWhere('(
                LOWER(t.title) LIKE :search OR
                LOWER(t.description) LIKE :search OR
                LOWER(au.firstName) LIKE :search OR
                LOWER(au.lastName) LIKE :search OR
                LOWER(cu.firstName) LIKE :search OR
                LOWER(cu.lastName) LIKE :search OR
                LOWER(t.priority) LIKE :search OR
                t.status = :doneStatus
            )')
            ->setParameter('user', $user)
            ->setParameter('search', '%' . strtolower($searchQuery) . '%')
            ->setParameter('doneStatus', $searchQuery === 'completed' || $searchQuery === 'выполнено' || $searchQuery === 'done')
            ->setMaxResults($limit);
            
        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks by user and status within date range
     */
    public function findByUserAndStatus(User $user, string $status, \DateTime $fromDate, \DateTime $toDate): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.user', 'cu')->addSelect('cu')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('(t.assignedUser = :user OR t.user = :user)')
            ->andWhere('t.status = :status')
            ->andWhere('t.createdAt BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Search tasks by various criteria with improved performance
     */
    public function searchTasks(array $criteria = []): array
    {
        // Create cache key based on criteria
        $cacheKey = 'search_tasks_' . md5(serialize($criteria));
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($criteria) {
                    return $this->performSearchTasks($criteria);
                },
                $criteria,
                300 // 5 minutes cache
            );
        }
        
        return $this->performSearchTasks($criteria);
    }
    
    /**
     * Internal search tasks implementation with optimized query
     */
    private function performSearchTasks(array $criteria = []): array
    {
        // Use select() to explicitly define what to fetch and avoid fetching unnecessary data
        $qb = $this->createQueryBuilder('t')
            ->select('t, u, au, c') // Explicitly select only required associations
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c');

        if (!empty($criteria['user'])) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (!empty($criteria['search'])) {
            // Use CONCAT for better index utilization if available, or use direct comparison
            $qb->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search')
               ->setParameter('search', '%' . strtolower($criteria['search']) . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['startDate'])) {
            $qb->andWhere('t.dueDate >= :startDate')
               ->setParameter('startDate', $criteria['startDate']);
        }

        if (!empty($criteria['endDate'])) {
            $qb->andWhere('t.dueDate <= :endDate')
               ->setParameter('endDate', $criteria['endDate']);
        }
        
        // Advanced filtering options
        if (!empty($criteria['createdAfter'])) {
            $qb->andWhere('t.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['createdAfter']);
        }
        
        if (!empty($criteria['createdBefore'])) {
            $qb->andWhere('t.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['createdBefore']);
        }
        
        if (!empty($criteria['assignedToMe']) && $criteria['assignedToMe']) {
            $qb->andWhere('t.assignedUser = :currentUser')
               ->setParameter('currentUser', $criteria['assignedToMe']);
        }
        
        if (!empty($criteria['createdByMe']) && $criteria['createdByMe']) {
            $qb->andWhere('t.user = :currentUser')
               ->setParameter('currentUser', $criteria['createdByMe']);
        }
        
        if (!empty($criteria['overdue']) && $criteria['overdue']) {
            $qb->andWhere('t.dueDate < :now AND t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }
        
        if (!empty($criteria['tag'])) {
            $qb->join('t.tags', 'jt')
               ->andWhere('jt.id = :tagId')
               ->setParameter('tagId', $criteria['tag']);
        }
        
        if (!empty($criteria['hideCompleted']) && $criteria['hideCompleted']) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }
        
        if (!empty($criteria['sortBy'])) {
            $allowedSortFields = ['t.title', 't.createdAt', 't.dueDate', 't.priority', 't.status'];
            $direction = (!empty($criteria['sortDirection']) && strtoupper($criteria['sortDirection']) === 'ASC') ? 'ASC' : 'DESC';
            
            if ($criteria['sortBy'] === 'tag_count') {
                // Sort by tag count (tasks with more tags first) - requires special handling
                $qb = $this->createQueryBuilder('t')
                    ->select('t, u, au, c, COUNT(tg.id) as HIDDEN tag_count')
                    ->leftJoin('t.user', 'u')
                    ->leftJoin('t.assignedUser', 'au')
                    ->leftJoin('t.category', 'c')
                    ->leftJoin('t.tags', 'tg')
                    ->groupBy('t.id, u.id, au.id, c.id') // Group by all selected non-aggregate fields
                    ->orderBy('tag_count', $direction)
                    ->addOrderBy('t.createdAt', 'DESC');
                
                // Reapply conditions for this special query
                if (!empty($criteria['user'])) {
                    $qb->andWhere('t.user = :user OR t.assignedUser = :user')
                       ->setParameter('user', $criteria['user']);
                }
                
                if (!empty($criteria['search'])) {
                    $qb->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search')
                       ->setParameter('search', '%' . strtolower($criteria['search']) . '%');
                }
                
                if (!empty($criteria['status'])) {
                    $qb->andWhere('t.status = :status')
                       ->setParameter('status', $criteria['status']);
                }
                
                if (!empty($criteria['priority'])) {
                    $qb->andWhere('t.priority = :priority')
                       ->setParameter('priority', $criteria['priority']);
                }
                
                if (!empty($criteria['category'])) {
                    $qb->andWhere('t.category = :category')
                       ->setParameter('category', $criteria['category']);
                }
                
                if (!empty($criteria['startDate'])) {
                    $qb->andWhere('t.dueDate >= :startDate')
                       ->setParameter('startDate', $criteria['startDate']);
                }
                
                if (!empty($criteria['endDate'])) {
                    $qb->andWhere('t.dueDate <= :endDate')
                       ->setParameter('endDate', $criteria['endDate']);
                }
                
                if (!empty($criteria['createdAfter'])) {
                    $qb->andWhere('t.createdAt >= :createdAfter')
                       ->setParameter('createdAfter', $criteria['createdAfter']);
                }
                
                if (!empty($criteria['createdBefore'])) {
                    $qb->andWhere('t.createdAt <= :createdBefore')
                       ->setParameter('createdBefore', $criteria['createdBefore']);
                }
                
                if (!empty($criteria['assignedToMe']) && $criteria['assignedToMe']) {
                    $qb->andWhere('t.assignedUser = :currentUser')
                       ->setParameter('currentUser', $criteria['assignedToMe']);
                }
                
                if (!empty($criteria['createdByMe']) && $criteria['createdByMe']) {
                    $qb->andWhere('t.user = :currentUser')
                       ->setParameter('currentUser', $criteria['createdByMe']);
                }
                
                if (!empty($criteria['overdue']) && $criteria['overdue']) {
                    $qb->andWhere('t.dueDate < :now AND t.status != :completed')
                       ->setParameter('now', new \DateTime())
                       ->setParameter('completed', 'completed');
                }
                
                if (!empty($criteria['tag'])) {
                    $qb->join('t.tags', 'jt2')
                       ->andWhere('jt2.id = :tagId')
                       ->setParameter('tagId', $criteria['tag']);
                }
                
                if (!empty($criteria['hideCompleted']) && $criteria['hideCompleted']) {
                    $qb->andWhere('t.status != :completedStatus')
                       ->setParameter('completedStatus', 'completed');
                }
            } elseif (in_array($criteria['sortBy'], ['title', 'createdAt', 'dueDate', 'priority', 'status'])) {
                // Properly prefix the sort field to avoid ambiguity
                $qb->orderBy('t.' . $criteria['sortBy'], $direction);
            } else {
                $qb->orderBy('t.createdAt', 'DESC');
            }
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }
        
        // Apply pagination if offset and limit are provided
        if (isset($criteria['offset']) && isset($criteria['limit'])) {
            $qb->setFirstResult($criteria['offset'])
              ->setMaxResults($criteria['limit']);
        }

        return $qb->getQuery()
                  ->getResult();
    }
    
    /**
     * Count tasks by various criteria for pagination
     */
    public function countSearchTasks(array $criteria = []): int
    {
        // Create cache key based on criteria
        $cacheKey = 'count_search_tasks_' . md5(serialize($criteria));
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($criteria) {
                    return $this->performCountSearchTasks($criteria);
                },
                $criteria,
                300 // 5 minutes cache
            );
        }
        
        return $this->performCountSearchTasks($criteria);
    }
    
    /**
     * Internal count search tasks implementation with optimized query
     */
    private function performCountSearchTasks(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.id)') // Use DISTINCT to avoid counting duplicates in case of joins
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c');

        if (!empty($criteria['user'])) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search')
               ->setParameter('search', '%' . strtolower($criteria['search']) . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['startDate'])) {
            $qb->andWhere('t.dueDate >= :startDate')
               ->setParameter('startDate', $criteria['startDate']);
        }

        if (!empty($criteria['endDate'])) {
            $qb->andWhere('t.dueDate <= :endDate')
               ->setParameter('endDate', $criteria['endDate']);
        }
        
        // Advanced filtering options
        if (!empty($criteria['createdAfter'])) {
            $qb->andWhere('t.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['createdAfter']);
        }
        
        if (!empty($criteria['createdBefore'])) {
            $qb->andWhere('t.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['createdBefore']);
        }
        
        if (!empty($criteria['assignedToMe']) && $criteria['assignedToMe']) {
            $qb->andWhere('t.assignedUser = :currentUser')
               ->setParameter('currentUser', $criteria['assignedToMe']);
        }
        
        if (!empty($criteria['createdByMe']) && $criteria['createdByMe']) {
            $qb->andWhere('t.user = :currentUser')
               ->setParameter('currentUser', $criteria['createdByMe']);
        }
        
        if (!empty($criteria['overdue']) && $criteria['overdue']) {
            $qb->andWhere('t.dueDate < :now AND t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }
        
        if (!empty($criteria['tag'])) {
            $qb->join('t.tags', 'jt')
               ->andWhere('jt.id = :tagId')
               ->setParameter('tagId', $criteria['tag']);
        }
        
        if (!empty($criteria['hideCompleted']) && $criteria['hideCompleted']) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }

        return (int) $qb->getQuery()
                         ->getSingleScalarResult();
    }
    
    /**
     * Find tasks by tag
     */
    public function findByTag(int $tagId): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->join('t.tags', 'tag')
            ->andWhere('tag.id = :tagId')
            ->setParameter('tagId', $tagId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find all tasks sorted by tag count (tasks with more tags first)
     */
    public function findAllSortedByTagCount(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tg')->addSelect('tg')
            ->select('t, au, c, tg, COUNT(tg.id) as HIDDEN tag_count')
            ->groupBy('t.id, au.id, c.id, tg.id')
            ->orderBy('tag_count', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find completed tasks older than a specific date
     */
    public function findCompletedTasksOlderThan(\DateTime $date): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :completed')
            ->andWhere('t.createdAt < :date')
            ->setParameter('completed', 'completed')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Get task completion statistics by priority
     */
    public function getCompletionStatsByPriority(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.priority, COUNT(t.id) as total, SUM(CASE WHEN t.status = \'completed\' THEN 1 ELSE 0 END) as completed')
            ->groupBy('t.priority')
            ->orderBy('t.priority')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $item) {
            $stats[$item['priority']] = [
                'total' => (int)$item['total'],
                'completed' => (int)$item['completed'],
                'percentage' => $item['total'] > 0 ? round(($item['completed'] / $item['total']) * 100, 2) : 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Calculate average completion time by priority in days
     */
    public function getAverageCompletionTimeByPriority(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.priority, t.completedAt, t.createdAt')
            ->where('t.status = :completed')
            ->andWhere('t.completedAt IS NOT NULL')
            ->setParameter('completed', 'completed')
            ->orderBy('t.priority')
            ->getQuery()
            ->getResult();
        
        // Calculate average completion time in PHP to avoid database-specific functions
        $stats = [];
        $priorityTimes = [];
        
        foreach ($result as $item) {
            $priority = $item['priority'];
            $createdAt = $item['createdAt'];
            $completedAt = $item['completedAt'];
            
            if ($createdAt && $completedAt) {
                $diff = $completedAt->diff($createdAt);
                $days = $diff->days;
                
                if (!isset($priorityTimes[$priority])) {
                    $priorityTimes[$priority] = [];
                }
                $priorityTimes[$priority][] = $days;
            }
        }
        
        foreach ($priorityTimes as $priority => $times) {
            $avgDays = count($times) > 0 ? array_sum($times) / count($times) : 0;
            $stats[$priority] = [
                'avgDays' => round($avgDays, 2)
            ];
        }
        
        return $stats;
    }

    /**
     * Get task completion trends grouped by date for dashboard
     * Optimized with caching and better database grouping
     *
     * @return array
     */
    public function getTaskCompletionTrendsByDate(?User $user = null, int $days = 30): array
    {
        $cacheKey = 'trends_' . ($user ? $user->getId() : 'all') . '_' . $days;
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($user, $days) {
                    return $this->performTrendAnalysis($user, $days);
                },
                ['user' => $user?->getId(), 'days' => $days],
                600 // 10 minutes cache
            );
        }
        
        return $this->performTrendAnalysis($user, $days);
    }
    
    /**
     * Internal trend analysis implementation
     */
    private function performTrendAnalysis(?User $user, int $days): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('DATE(t.createdAt) as date, t.status, COUNT(t.id) as count')
            ->where('t.createdAt >= :startDate')
            ->groupBy('date, t.status')
            ->orderBy('date', 'DESC')
            ->setParameter('startDate', new \DateTime("-$days days"));

        if ($user !== null) {
            $qb->andWhere('t.assignedUser = :user OR t.user = :user')
                ->setParameter('user', $user);
        }

        $results = $qb->getQuery()->getResult();

        // Process results to group by date
        $dateCounts = [];
        
        foreach ($results as $result) {
            $date = $result['date'];
            if (!isset($dateCounts[$date])) {
                $dateCounts[$date] = ['date' => $date, 'total' => 0, 'completed' => 0];
            }
            $dateCounts[$date]['total'] += $result['count'];
            if ($result['status'] === 'completed') {
                $dateCounts[$date]['completed'] += $result['count'];
            }
        }
        
        return array_values($dateCounts);
    }
    
    /**
     * Get quick task statistics for dashboard with caching
     */
    public function getQuickStats(User $user): array
    {
        $cacheKey = 'quick_stats_' . $user->getId();
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($user) {
                    return $this->performQuickStats($user);
                },
                ['user' => $user->getId()],
                120 // 2 minutes cache
            );
        }
        
        return $this->performQuickStats($user);
    }
    
    /**
     * Internal quick stats implementation
     */
    private function performQuickStats(User $user): array
    {
        // Single query to get all counts at once for better performance
        $results = $this->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) as total',
                'SUM(CASE WHEN t.status = :pending_status THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN t.status = :in_progress_status THEN 1 ELSE 0 END) as in_progress',
                'SUM(CASE WHEN t.status = :completed_status THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN t.dueDate IS NOT NULL AND t.dueDate < :now AND t.status != :completed_status THEN 1 ELSE 0 END) as overdue'
            )
            ->where('t.assignedUser = :user OR t.user = :user')
            ->setParameter('user', $user)
            ->setParameter('pending_status', 'pending')
            ->setParameter('in_progress_status', 'in_progress')
            ->setParameter('completed_status', 'completed')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleResult();
        
        $totalTasks = (int) $results['total'];
        $pendingTasks = (int) $results['pending'];
        $inProgressTasks = (int) $results['in_progress'];
        $completedTasks = (int) $results['completed'];
        $overdueTasks = (int) $results['overdue'];
        
        // Get recent tasks (last 5) - limit to assigned or created by user
        $recentTasks = $this->createQueryBuilder('t')
            ->where('t.assignedUser = :user OR t.user = :user')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        return [
            'total' => $totalTasks,
            'pending' => $pendingTasks,
            'in_progress' => $inProgressTasks,
            'completed' => $completedTasks,
            'overdue' => $overdueTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            'recent_tasks' => $recentTasks
        ];
    }
    
    /**
     * Invalidate cache when task is modified
     */
    public function invalidateUserCache(User $user): void
    {
        if ($this->cacheService) {
            // Clear all user-specific caches
            $cacheKeys = [
                'user_search_' . $user->getId() . '_*',
                'search_' . $user->getId() . '_*',
                'quick_stats_' . $user->getId(),
                'trends_' . $user->getId() . '_*'
            ];
            
            foreach ($cacheKeys as $key) {
                $this->delete($key);
            }
        }
    }
    
    /**
     * Find task by ID with all relations to avoid N+1 queries
     */
    public function findTaskWithRelations(int $id): ?Task
    {
        $cacheKey = 'task_with_relations_' . $id;
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($id) {
                    return $this->performFindTaskWithRelations($id);
                },
                ['task_id' => $id],
                300 // 5 minutes cache
            );
        }
        
        return $this->performFindTaskWithRelations($id);
    }
    
    /**
     * Internal method to find task with relations
     */
    private function performFindTaskWithRelations(int $id): ?Task
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg')
            ->leftJoin('t.comments', 'cm')
            ->leftJoin('t.activityLogs', 'al')
            ->leftJoin('t.notifications', 'nt')
            ->leftJoin('t.dependencies', 'dep')
            ->leftJoin('t.dependents', 'dt')
            ->leftJoin('t.timeTrackings', 'tt')
            ->leftJoin('t.recurrence', 'r')
            ->addSelect('u', 'au', 'c', 'tg', 'cm', 'al', 'nt', 'dep', 'dt', 'tt', 'r')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getOneOrNullResult();
    }
    
    /**
     * Find tasks with upcoming deadlines (within next 24 hours)
     */
    public function findTasksWithUpcomingDeadlines(): array
    {
        $now = new \DateTime();
        $tomorrow = (clone $now)->modify('+1 day');
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.dueDate >= :now')
            ->andWhere('t.dueDate <= :tomorrow')
            ->andWhere('t.status != :completed')
            ->setParameter('now', $now)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('completed', 'completed')
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
