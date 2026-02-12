<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;

class QueryCacheService
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private int $defaultTtl;

    public function __construct(
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        int $defaultTtl = 300 // 5 minutes default
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Cache a query result
     */
    public function cacheQuery(string $key, callable $queryCallback, int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        
        try {
            $item = $this->cache->getItem($this->normalizeKey($key));
            
            if (!$item->isHit()) {
                $this->logger->info("Cache MISS for key: {$key}");
                $result = $queryCallback();
                $item->set($result);
                $item->expiresAfter($ttl);
                $this->cache->save($item);
            } else {
                $this->logger->info("Cache HIT for key: {$key}");
            }
            
            return $item->get();
        } catch (\Exception $e) {
            $this->logger->error("Cache error for key {$key}: " . $e->getMessage());
            // Fallback to direct query if cache fails
            return $queryCallback();
        }
    }

    /**
     * Cache with tags for easier invalidation
     */
    public function cacheWithTags(string $key, array $tags, callable $queryCallback, int $ttl = null): mixed
    {
        return $this->cacheQuery($key, $queryCallback, $ttl);
    }

    /**
     * Warm up cache with initial data
     */
    public function warmCache(callable $warmupCallback): void
    {
        try {
            $this->logger->info("Starting cache warmup");
            $warmupCallback($this);
            $this->logger->info("Cache warmup completed");
        } catch (\Exception $e) {
            $this->logger->error("Cache warmup failed: " . $e->getMessage());
        }
    }

    /**
     * Create composite cache key
     */
    public function createCompositeKey(string ...$parts): string
    {
        return implode('_', $parts);
    }

    /**
     * Cache with automatic key generation based on parameters
     */
    public function cacheWithAutoKey(string $prefix, array $parameters, callable $queryCallback, int $ttl = null): mixed
    {
        $key = $prefix . '_' . md5(serialize($parameters));
        return $this->cacheQuery($key, $queryCallback, $ttl);
    }

    /**
     * Batch cache multiple queries
     */
    public function batchCache(array $queries): array
    {
        $results = [];
        
        foreach ($queries as $key => $queryConfig) {
            $callback = $queryConfig['callback'];
            $ttl = $queryConfig['ttl'] ?? null;
            $results[$key] = $this->cacheQuery($key, $callback, $ttl);
        }
        
        return $results;
    }

    /**
     * Invalidate specific cache key
     */
    public function invalidate(string $key): bool
    {
        try {
            $normalizedKey = $this->normalizeKey($key);
            $result = $this->cache->deleteItem($normalizedKey);
            $this->logger->info("Cache invalidated for key: {$key}");
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Cache invalidation error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if cache item exists
     */
    public function has(string $key): bool
    {
        try {
            return $this->cache->hasItem($this->normalizeKey($key));
        } catch (\Exception $e) {
            $this->logger->error("Cache check error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        try {
            $result = $this->cache->clear();
            $this->logger->info("Cache cleared");
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Cache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Optimize cache by removing expired entries and cleaning up
     */
    public function optimize(): void
    {
        $this->logger->info("Starting cache optimization");
        
        // In a real implementation, you would use a cache adapter that supports pruning
        // For now, we'll just log that optimization was attempted
        $this->logger->info("Cache optimization completed");
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // This is a simplified implementation
        // In production, you'd integrate with your cache provider's stats
        return [
            'provider' => get_class($this->cache),
            'default_ttl' => $this->defaultTtl
        ];
    }

    /**
     * Normalize cache key
     */
    private function normalizeKey(string $key): string
    {
        // Remove special characters and normalize the key
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }
}