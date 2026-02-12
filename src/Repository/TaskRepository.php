<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
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
     * Find tasks assigned to a specific user
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
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
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
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
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
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
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
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
            ->andWhere('(t.assignedUser = :user OR t.createdBy = :user)')
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
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c');

        if (!empty($criteria['user'])) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
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

        if (!empty($criteria['user'])) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $criteria['user']);
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
            $allowedSortFields = ['title', 'createdAt', 'dueDate', 'priority', 'status'];
            $direction = (!empty($criteria['sortDirection']) && strtoupper($criteria['sortDirection']) === 'ASC') ? 'ASC' : 'DESC';
            
            if ($criteria['sortBy'] === 'tag_count') {
                // Sort by tag count (tasks with more tags first)
                $qb->select('t, COUNT(tg.id) as HIDDEN tag_count')
                   ->leftJoin('t.tags', 'tg')
                   ->groupBy('t.id')
                   ->orderBy('tag_count', $direction)
                   ->addOrderBy('t.createdAt', 'DESC');
            } elseif (in_array($criteria['sortBy'], $allowedSortFields)) {
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
     * Find tasks by tag
     */
    public function findByTag(int $tagId): array
    {
        return $this->createQueryBuilder('t')
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
            ->select('t, COUNT(tg.id) as HIDDEN tag_count')
            ->leftJoin('t.tags', 'tg')
            ->groupBy('t.id')
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
        // Count total tasks
        $totalTasks = $this->cachedCount(['user' => $user]);
        
        // Count pending tasks
        $pendingTasks = $this->cachedCount(['user' => $user, 'status' => 'pending']);
        
        // Count completed tasks
        $completedTasks = $this->cachedCount(['user' => $user, 'status' => 'completed']);
        
        // Count overdue tasks
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed');
        
        $overdueTasks = $qb->getQuery()->getSingleScalarResult();
        
        // Get recent tasks (last 5)
        $recentTasks = $this->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        return [
            'total' => $totalTasks,
            'pending' => $pendingTasks,
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
}
