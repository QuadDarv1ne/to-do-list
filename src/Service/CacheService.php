<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Centralized cache service for common operations
 */
class CacheService
{
    private const DEFAULT_TTL = 3600; // 1 hour
    private const STATS_TTL = 300; // 5 minutes
    private const USER_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function get(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $callback();
        });
    }

    public function getStats(string $key, callable $callback): mixed
    {
        return $this->get($key, $callback, self::STATS_TTL);
    }

    public function getUserData(string $key, callable $callback): mixed
    {
        return $this->get($key, $callback, self::USER_TTL);
    }

    public function invalidate(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function invalidateByPrefix(string $prefix): void
    {
        // Note: This requires cache adapter that supports tag-based invalidation
        // For simple implementation, you might need to track keys manually
    }

    public function clear(): void
    {
        $this->cache->clear();
    }
}
