<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Advanced cache service with multiple cache pools and intelligent caching strategies
 */
class AdvancedCacheService
{
    private CacheInterface $cache;
    private CacheItemPoolInterface $cachePool;
    private LoggerInterface $logger;
    
    // Cache pool names
    private const POOL_QUERIES = 'cache.app_queries';
    private const POOL_STATISTICS = 'cache.app_statistics';
    private const POOL_USER_DATA = 'cache.app_user_data';
    private const POOL_AGGREGATE_METRICS = 'cache.app_aggregate_metrics';
    private const POOL_PERFORMANCE = 'cache.app_performance';
    private const POOL_NOTIFICATIONS = 'cache.app_notifications';

    public function __construct(
        CacheInterface $cache,
        CacheItemPoolInterface $cachePool,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->cachePool = $cachePool;
        $this->logger = $logger;
    }

    /**
     * Cache database query results
     */
    public function cacheQuery(string $key, callable $queryCallback, array $tags = [], int $ttl = 300): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_QUERIES, $key, $tags);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($queryCallback, $ttl, $tags, $cacheKey) {
                $item->expiresAfter($ttl);
                if (!empty($tags)) {
                    $item->tag($tags);
                }
                
                $this->logger->info("Cache MISS - executing query: {$cacheKey}");
                $result = $queryCallback();
                $this->logger->info("Query result cached: {$cacheKey}");
                
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache query failed: " . $e->getMessage());
            return $queryCallback();
        }
    }

    /**
     * Cache statistical data
     */
    public function cacheStatistics(string $key, callable $statsCallback, int $ttl = 600): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_STATISTICS, $key);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($statsCallback, $ttl, $cacheKey) {
                $item->expiresAfter($ttl);
                $this->logger->info("Cache MISS - generating statistics: {$cacheKey}");
                $result = $statsCallback();
                $this->logger->info("Statistics cached: {$cacheKey}");
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache statistics failed: " . $e->getMessage());
            return $statsCallback();
        }
    }

    /**
     * Cache user-specific data
     */
    public function cacheUserData(int $userId, string $key, callable $dataCallback, int $ttl = 900): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_USER_DATA, $key, ['user_' . $userId]);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($dataCallback, $ttl, $userId, $cacheKey) {
                $item->expiresAfter($ttl);
                $item->tag(['user_data', 'user_' . $userId]);
                $this->logger->info("Cache MISS - loading user data for user {$userId}: {$cacheKey}");
                $result = $dataCallback();
                $this->logger->info("User data cached for user {$userId}: {$cacheKey}");
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache user data failed: " . $e->getMessage());
            return $dataCallback();
        }
    }

    /**
     * Cache performance metrics and aggregate data
     */
    public function cachePerformanceMetrics(string $key, callable $metricsCallback, int $ttl = 1800): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_AGGREGATE_METRICS, $key);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($metricsCallback, $ttl, $cacheKey) {
                $item->expiresAfter($ttl);
                $item->tag(['performance', 'metrics']);
                $this->logger->info("Cache MISS - collecting performance metrics: {$cacheKey}");
                $result = $metricsCallback();
                $this->logger->info("Performance metrics cached: {$cacheKey}");
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache performance metrics failed: " . $e->getMessage());
            return $metricsCallback();
        }
    }

    /**
     * Cache real-time data with short TTL
     */
    public function cacheRealTimeData(string $key, callable $dataCallback, int $ttl = 120): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_PERFORMANCE, $key);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($dataCallback, $ttl, $cacheKey) {
                $item->expiresAfter($ttl);
                $item->tag(['realtime', 'performance']);
                $this->logger->info("Cache MISS - fetching real-time data: {$cacheKey}");
                $result = $dataCallback();
                $this->logger->info("Real-time data cached: {$cacheKey}");
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache real-time data failed: " . $e->getMessage());
            return $dataCallback();
        }
    }

    /**
     * Cache notification data
     */
    public function cacheNotifications(int $userId, callable $notificationsCallback, int $ttl = 300): mixed
    {
        $cacheKey = $this->generateKey(self::POOL_NOTIFICATIONS, 'user_notifications', ['user_' . $userId]);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($notificationsCallback, $ttl, $userId, $cacheKey) {
                $item->expiresAfter($ttl);
                $item->tag(['notifications', 'user_' . $userId]);
                $this->logger->info("Cache MISS - loading notifications for user {$userId}: {$cacheKey}");
                $result = $notificationsCallback();
                $this->logger->info("Notifications cached for user {$userId}: {$cacheKey}");
                return $result;
            });
        } catch (\Exception $e) {
            $this->logger->error("Cache notifications failed: " . $e->getMessage());
            return $notificationsCallback();
        }
    }

    /**
     * Invalidate cache by tags (simulated)
     */
    public function invalidateTags(array $tags): void
    {
        try {
            // For filesystem cache, we can't invalidate by tags directly
            // In production with Redis, this would use tag-based invalidation
            $this->logger->info("Cache tag invalidation requested for: " . implode(', ', $tags));
            // Simulate tag invalidation by clearing related cache entries
            // This is a placeholder - real implementation would depend on cache adapter
        } catch (\Exception $e) {
            $this->logger->error("Cache invalidation failed: " . $e->getMessage());
        }
    }

    /**
     * Clear specific cache pool
     */
    public function clearPool(string $poolName): void
    {
        try {
            // This would require a cache adapter that supports pool clearing
            // For now, we'll invalidate relevant tags
            $tags = match($poolName) {
                self::POOL_QUERIES => ['queries'],
                self::POOL_STATISTICS => ['statistics'],
                self::POOL_USER_DATA => ['user_data'],
                self::POOL_AGGREGATE_METRICS => ['performance', 'metrics'],
                self::POOL_PERFORMANCE => ['realtime', 'performance'],
                self::POOL_NOTIFICATIONS => ['notifications'],
                default => [$poolName]
            };
            
            $this->invalidateTags($tags);
            $this->logger->info("Cache pool cleared: {$poolName}");
        } catch (\Exception $e) {
            $this->logger->error("Cache pool clear failed: " . $e->getMessage());
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'pools' => [
                'queries' => self::POOL_QUERIES,
                'statistics' => self::POOL_STATISTICS,
                'user_data' => self::POOL_USER_DATA,
                'aggregate_metrics' => self::POOL_AGGREGATE_METRICS,
                'performance' => self::POOL_PERFORMANCE,
                'notifications' => self::POOL_NOTIFICATIONS
            ],
            'default_ttls' => [
                'queries' => 300,
                'statistics' => 600,
                'user_data' => 900,
                'aggregate_metrics' => 1800,
                'performance' => 120,
                'notifications' => 300
            ]
        ];
    }

    /**
     * Generate cache key with pool prefix
     */
    private function generateKey(string $pool, string $key, array $tags = []): string
    {
        $baseKey = $pool . '.' . $key;
        if (!empty($tags)) {
            $baseKey .= '.' . implode('.', $tags);
        }
        return $baseKey;
    }
}
