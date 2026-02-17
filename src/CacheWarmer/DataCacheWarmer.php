<?php

namespace App\CacheWarmer;

use App\Service\QueryCacheService;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Cache warmer for pre-warming commonly accessed data
 */
class DataCacheWarmer implements CacheWarmerInterface
{
    private QueryCacheService $cacheService;
    private TaskRepository $taskRepository;
    private UserRepository $userRepository;

    public function __construct(
        QueryCacheService $cacheService,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ) {
        $this->cacheService = $cacheService;
        $this->taskRepository = $taskRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Warms up the cache.
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            // Pre-warm common task queries
            $this->warmTaskQueries();
            
            // Pre-warm common user queries
            $this->warmUserQueries();
            
            // Pre-warm common dashboard queries
            $this->warmDashboardQueries();
            
        } catch (\Exception $e) {
            // Silently fail if warming fails - shouldn't break the application
            error_log('Data cache warming failed: ' . $e->getMessage());
        }

        // Return list of classes to preload
        return [];
    }

    private function warmTaskQueries(): void
    {
        try {
            // Pre-warm common task counts
            $statuses = ['pending', 'in_progress', 'completed'];
            foreach ($statuses as $status) {
                // Call the method - the repository will handle caching internally
                $this->taskRepository->countByStatus(null, null, $status);
            }
            
            // Warm search queries with common search terms
            $commonSearches = ['important', 'urgent', 'meeting', 'deadline', 'today', 'week'];
            foreach ($commonSearches as $search) {
                $this->taskRepository->findBySearchQuery($search, 10);
            }
        } catch (\Exception $e) {
            error_log('Task cache warming failed: ' . $e->getMessage());
        }
    }

    private function warmUserQueries(): void
    {
        try {
            // Pre-warm user statistics 
            $this->userRepository->getStatistics();
            
            // Pre-warm active users list 
            $this->userRepository->findActiveUsers();
            
            // Pre-warm role-based user lists
            $roles = ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_USER'];
            foreach ($roles as $role) {
                $this->userRepository->findByRole($role);
            }
        } catch (\Exception $e) {
            error_log('User cache warming failed: ' . $e->getMessage());
        }
    }

    private function warmDashboardQueries(): void
    {
        // This would typically involve warming dashboard-specific queries
        // that combine data from multiple sources
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true for this warmer
     */
    public function isOptional(): bool
    {
        // This warmer is optional - if it fails, the cache will still work
        return true;
    }
}
