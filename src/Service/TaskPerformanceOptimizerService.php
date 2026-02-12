<?php

namespace App\Service;

use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\TaskCategoryRepository;
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
        QueryCacheService $queryCacheService
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
            
            $this->logger->info("Regenerating dashboard stats for user {$user->getId()}");
            
            $stats = [
                'total_tasks' => $this->taskRepository->count(['user' => $user]),
                'completed_tasks' => $this->taskRepository->countByStatus($user, null, 'completed'),
                'pending_tasks' => $this->taskRepository->countByStatus($user, null, 'pending'),
                'in_progress_tasks' => $this->taskRepository->countByStatus($user, null, 'in_progress'),
                'urgent_tasks' => $this->taskRepository->countByPriority($user, null, 'high'),
                'upcoming_deadlines' => $this->getUpcomingDeadlines($user),
                'recent_categories' => $this->getRecentCategories($user),
                'last_updated' => date('Y-m-d H:i:s')
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
            
            $this->logger->info("Regenerating task list for user {$userId} with filters");
            
            $criteria = $this->buildCriteria($filters);
            $limit = $filters['limit'] ?? 50;
            
            $tasks = $this->taskRepository->searchTasks($criteria, $limit);
            
            return [
                'tasks' => $this->transformTaskData($tasks),
                'total_count' => count($tasks),
                'cache_hit' => false,
                'timestamp' => microtime(true)
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
            
            $assignedTasks = $this->taskRepository->findBy(['assignedUser' => $user], null, 20);
            $collaborators = [];
            
            foreach ($assignedTasks as $task) {
                $owner = $task->getUser();
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
        $this->cache->delete("dashboard_stats_user_{$user->getId()}");
        $this->cache->delete("task_list_user_{$user->getId()}");
        $this->cache->delete("frequent_collaborators_{$user->getId()}");
        
        $this->logger->info("Cache invalidated for user {$user->getId()}");
    }

    /**
     * Invalidate all cache (system cleanup)
     */
    public function clearAllCache(): void
    {
        // Note: Symfony cache clearing requires specific keys
        // In a real implementation, you would iterate through known cache keys
        $this->logger->info("Cache clearing initiated");
    }

    /**
     * Preload data for user into cache
     */
    public function preloadUserCache($user): void
    {
        $this->getUserDashboardStats($user);
        $this->getOptimizedTaskList(['user' => $user, 'limit' => 10]);
        $this->getFrequentCollaborators($user);
        
        $this->logger->info("Cache preloaded for user {$user->getId()}");
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
        
        return array_slice(array_map(function ($task) {
            return [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'priority' => $task->getPriority()
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
                'task_count' => $category->getTasks()->count()
            ];
        }, $categories);
    }
}