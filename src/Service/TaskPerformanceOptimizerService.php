<?php

namespace App\Service;

use App\Repository\TaskCategoryRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service to optimize task performance with intelligent caching
 */
class TaskPerformanceOptimizerService
{
    private CacheInterface $cache;

    private TaskRepository $taskRepository;

    private UserRepository $userRepository;

    private TaskCategoryRepository $taskCategoryRepository;

    private LoggerInterface $logger;

    private QueryCacheService $queryCacheService;

    public function __construct(
        CacheInterface $cache,
        TaskRepository $taskRepository,
        UserRepository $userRepository,
        TaskCategoryRepository $taskCategoryRepository,
        LoggerInterface $logger,
        QueryCacheService $queryCacheService,
    ) {
        $this->cache = $cache;
        $this->taskRepository = $taskRepository;
        $this->userRepository = $userRepository;
        $this->taskCategoryRepository = $taskCategoryRepository;
        $this->logger = $logger;
        $this->queryCacheService = $queryCacheService;
    }

    /**
     * Get optimized dashboard statistics for user
     */
    public function getUserDashboardStats($user): array
    {
        $cacheKey = "dashboard_stats_user_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300); // 5 minutes cache
            $item->tag(['user_dashboard_stats', "user_{$user->getId()}", 'dashboard']);

            $this->logger->info("Regenerating dashboard stats for user {$user->getId()}");

            // Use optimized repository method for dashboard stats
            $repositoryStats = $this->taskRepository->getDashboardStats($user);

            $stats = [
                'total_tasks' => $repositoryStats['total_tasks'] ?? $this->taskRepository->count(['user' => $user]),
                'completed_tasks' => $repositoryStats['completed_tasks'] ?? $this->taskRepository->countByStatus($user, null, 'completed'),
                'pending_tasks' => $repositoryStats['pending_tasks'] ?? $this->taskRepository->countByStatus($user, null, 'pending'),
                'in_progress_tasks' => $repositoryStats['in_progress_tasks'] ?? $this->taskRepository->countByStatus($user, null, 'in_progress'),
                'urgent_tasks' => $repositoryStats['urgent_tasks'] ?? $this->taskRepository->countByPriority($user, null, 'high'),
                'upcoming_deadlines' => $this->getUpcomingDeadlines($user),
                'recent_categories' => $this->getRecentCategories($user),
                'last_updated' => date('Y-m-d H:i:s'),
                'cached_at' => microtime(true),
            ];

            // Calculate completion percentage
            $stats['completion_percentage'] = $stats['total_tasks'] > 0 ?
                round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0;

            return $stats;
        });
    }

    /**
     * Get optimized task list with filters
     */
    public function getOptimizedTaskList(array $filters = []): array
    {
        $user = $filters['user'] ?? null;
        $userId = $user ? $user->getId() : 'all';

        $cacheKey = "task_list_user_{$userId}_" . md5(serialize($filters));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters, $userId) {
            $item->expiresAfter(120); // 2 minutes cache
            $item->tag(['task_list', "user_{$userId}", 'filtered_tasks']);

            $this->logger->info("Regenerating task list for user {$userId} with filters");

            $criteria = $this->buildCriteria($filters);
            $limit = $filters['limit'] ?? 50;

            // Use the optimized dashboard method if no complex filters
            $useOptimized = empty(array_diff_key($criteria, array_flip(['user', 'hideCompleted', 'status', 'priority', 'limit'])));

            if ($useOptimized && isset($criteria['user'])) {
                $tasks = $this->taskRepository->findDashboardTasks($criteria['user'], $criteria);
            } else {
                $tasks = $this->taskRepository->searchTasks($criteria, $limit);
            }

            return [
                'tasks' => $this->transformTaskData($tasks),
                'total_count' => \count($tasks),
                'cache_hit' => false,
                'timestamp' => microtime(true),
                'optimized_query' => $useOptimized,
            ];
        });
    }

    /**
     * Get frequently accessed users with optimized caching
     */
    public function getFrequentCollaborators($user): array
    {
        $cacheKey = "frequent_collaborators_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(1800); // 30 minutes cache

            // Оптимизированный запрос с JOIN для избежания N+1 проблемы
            $assignedTasks = $this->taskRepository->createQueryBuilder('t')
                ->select('t, u')
                ->leftJoin('t.user', 'u')
                ->where('t.assignedUser = :user')
                ->setParameter('user', $user)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            $collaborators = [];

            foreach ($assignedTasks as $task) {
                $owner = $task->getUser(); // Уже загружен через JOIN
                if ($owner && $owner->getId() !== $user->getId()) {
                    $collaborators[$owner->getId()] = $owner;
                }
            }

            return array_values($collaborators);
        });
    }

    /**
     * Invalidate dashboard cache for user
     */
    public function invalidateUserCache($user): void
    {
        $userId = $user->getId();

        // Delete specific cache keys
        $this->cache->delete("dashboard_stats_user_{$userId}");
        $this->cache->delete("task_list_user_{$userId}");
        $this->cache->delete("frequent_collaborators_{$userId}");

        // Also attempt to invalidate by tags if possible
        $this->invalidateCacheByTag('user_dashboard_stats', $user);
        $this->invalidateCacheByTag('dashboard', $user);
        $this->invalidateCacheByTag('task_list', $user);

        $this->logger->info("Cache invalidated for user {$userId}");
    }

    /**
     * Invalidate all cache (system cleanup)
     */
    public function clearAllCache(): void
    {
        // Note: Symfony cache clearing requires specific keys
        // In a real implementation, you would iterate through known cache keys
        $this->logger->info('Cache clearing initiated');
    }

    /**
     * Invalidate cache by tag for specific user
     */
    public function invalidateCacheByTag(string $tag, $user = null): void
    {
        // In a real implementation with PSR-6 cache supporting tags, we would clear by tag
        // For now, log the attempt
        $this->logger->info("Attempting to clear cache by tag: {$tag}" . ($user ? " for user {$user->getId()}" : ''));
    }

    /**
     * Preload data for user into cache
     */
    public function preloadUserCache($user): void
    {
        $startTime = microtime(true);

        $this->getUserDashboardStats($user);
        $this->getOptimizedTaskList(['user' => $user, 'limit' => 10]);
        $this->getFrequentCollaborators($user);

        $preloadTime = microtime(true) - $startTime;

        $this->logger->info("Cache preloaded for user {$user->getId()}", [
            'preload_time' => round($preloadTime, 4),
            'preload_time_ms' => round($preloadTime * 1000, 2),
        ]);
    }

    /**
     * Build optimized query criteria
     */
    private function buildCriteria(array $filters): array
    {
        $criteria = [
            'user' => $filters['user'] ?? null,
            'hideCompleted' => $filters['hide_completed'] ?? false,
        ];

        // Add boolean filters
        $booleanFilters = ['completed', 'urgent', 'overdue'];
        foreach ($booleanFilters as $filter) {
            if (isset($filters[$filter])) {
                $criteria[$filter] = (bool) $filters[$filter];
            }
        }

        // Add string filters
        $stringFilters = ['status', 'priority', 'category', 'search', 'tag'];
        foreach ($stringFilters as $filter) {
            if (!empty($filters[$filter])) {
                $criteria[$filter] = $filters[$filter];
            }
        }

        // Add date filters
        if (!empty($filters['start_date'])) {
            $criteria['startDate'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $criteria['endDate'] = $filters['end_date'];
        }

        // Add sorting
        if (!empty($filters['sort_by'])) {
            $criteria['sortBy'] = $filters['sort_by'];
            $criteria['sortDirection'] = $filters['sort_direction'] ?? 'DESC';
        }

        return $criteria;
    }

    /**
     * Transform task data for optimized response
     */
    private function transformTaskData(array $tasks): array
    {
        return array_map(function ($task) {
            return [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'category' => $task->getCategory()?->getName(),
                'assigned_user' => $task->getAssignedUser()?->getUsername(),
                'tag_count' => $task->getTags()->count(),
            ];
        }, $tasks);
    }

    /**
     * Get upcoming deadlines with optimized query
     */
    private function getUpcomingDeadlines($user): array
    {
        $nextWeek = new \DateTimeImmutable('+1 week');
        $tasks = $this->taskRepository->findUpcomingDeadlines($nextWeek);

        return \array_slice(array_map(function ($task) {
            return [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'priority' => $task->getPriority(),
            ];
        }, $tasks), 0, 5);
    }

    /**
     * Get recent categories with optimized query
     */
    private function getRecentCategories($user): array
    {
        $categories = $this->taskCategoryRepository->findByUser($user, 5);

        return array_map(function ($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'task_count' => $category->getTasks()->count(),
            ];
        }, $categories);
    }
}
