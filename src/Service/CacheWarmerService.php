<?php

namespace App\Service;

use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

/**
 * Service to warm up caches with commonly accessed data
 */
class CacheWarmerService
{
    private QueryCacheService $queryCacheService;
    private TaskRepository $taskRepository;
    private UserRepository $userRepository;
    private LoggerInterface $logger;

    public function __construct(
        QueryCacheService $queryCacheService,
        TaskRepository $taskRepository,
        UserRepository $userRepository,
        LoggerInterface $logger
    ) {
        $this->queryCacheService = $queryCacheService;
        $this->taskRepository = $taskRepository;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * Warm up caches for commonly accessed data
     */
    public function warmCaches(): void
    {
        $this->logger->info('Starting cache warming process');

        try {
            // Warm up user statistics
            $this->warmUserStatistics();
            
            // Warm up common task queries
            $this->warmTaskStatistics();
            
            // Warm up active users list
            $this->warmActiveUsers();
            
            $this->logger->info('Cache warming process completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Cache warming process failed: ' . $e->getMessage());
        }
    }

    private function warmUserStatistics(): void
    {
        $this->queryCacheService->cacheQuery(
            'user_statistics_global',
            function() {
                return $this->userRepository->getStatistics();
            },
            1800 // 30 minutes
        );
        
        $this->logger->info('User statistics warmed up');
    }

    private function warmTaskStatistics(): void
    {
        // Warm up completion stats by priority
        $this->queryCacheService->cacheQuery(
            'task_completion_stats_by_priority',
            function() {
                return $this->taskRepository->getCompletionStatsByPriority();
            },
            1800 // 30 minutes
        );
        
        $this->logger->info('Task statistics warmed up');
    }

    private function warmActiveUsers(): void
    {
        $this->queryCacheService->cacheQuery(
            'active_users_list',
            function() {
                return $this->userRepository->findActiveUsers();
            },
            900 // 15 minutes
        );
        
        $this->logger->info('Active users list warmed up');
    }
}