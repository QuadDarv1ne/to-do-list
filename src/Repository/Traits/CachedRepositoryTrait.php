<?php

namespace App\Repository\Traits;

use App\Service\QueryCacheService;

trait CachedRepositoryTrait
{
    private ?QueryCacheService $cacheService = null;

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Execute cached query with automatic key generation
     */
    protected function cachedQuery(string $queryName, callable $queryCallback, array $parameters = [], int $ttl = 300)
    {
        if (!$this->cacheService) {
            return $queryCallback();
        }

        $key = $this->generateCacheKey($queryName, $parameters);
        return $this->cacheService->cacheQuery($key, $queryCallback, $ttl);
    }

    /**
     * Execute cached user query
     */
    protected function cachedUserQuery(int $userId, string $queryName, callable $queryCallback, array $parameters = [], int $ttl = 300)
    {
        if (!$this->cacheService) {
            return $queryCallback();
        }

        $key = "user_{$userId}_{$queryName}_" . md5(serialize($parameters));
        return $this->cacheService->cacheQuery($key, $queryCallback, $ttl);
    }

    /**
     * Cache dashboard statistics for user
     */
    protected function cachedDashboardStats(int $userId, callable $statsCallback, int $ttl = 300)
    {
        if (!$this->cacheService) {
            return $statsCallback();
        }

        return $this->cacheService->cacheDashboardStats($userId, $statsCallback, $ttl);
    }

    /**
     * Cache paginated results
     */
    protected function cachedPaginatedQuery(string $queryName, callable $queryCallback, array $parameters = [], int $page = 1, int $ttl = 120)
    {
        if (!$this->cacheService) {
            return $queryCallback();
        }

        $parameters['page'] = $page;
        $key = $this->generateCacheKey($queryName, $parameters);
        
        return $this->cacheService->cacheQuery($key, $queryCallback, $ttl);
    }

    /**
     * Invalidate cache for specific query
     */
    protected function invalidateCache(string $queryName, array $parameters = []): bool
    {
        if (!$this->cacheService) {
            return false;
        }

        $key = $this->generateCacheKey($queryName, $parameters);
        return $this->cacheService->invalidate($key);
    }

    /**
     * Invalidate all user-related cache
     */
    protected function invalidateUserCache(int $userId): void
    {
        if ($this->cacheService) {
            $this->cacheService->invalidateUserCache($userId);
        }
    }

    /**
     * Generate consistent cache key
     */
    private function generateCacheKey(string $queryName, array $parameters = []): string
    {
        $baseKey = get_class($this) . '_' . $queryName;
        
        if (!empty($parameters)) {
            $baseKey .= '_' . md5(serialize($parameters));
        }
        
        return $baseKey;
    }

    /**
     * Cache frequently accessed entity
     */
    protected function cachedFind(int $id, int $ttl = 600)
    {
        return $this->cachedQuery(
            "find_{$id}",
            fn() => $this->find($id),
            ['id' => $id],
            $ttl
        );
    }

    /**
     * Cache count queries
     */
    protected function cachedCount(array $criteria = [], int $ttl = 300): int
    {
        $key = 'count_' . md5(serialize($criteria));
        
        return $this->cachedQuery(
            $key,
            fn() => $this->count($criteria),
            $criteria,
            $ttl
        );
    }

    /**
     * Cache list queries with filters
     */
    protected function cachedFindBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null, int $ttl = 120): array
    {
        $keyParams = [
            'criteria' => $criteria,
            'orderBy' => $orderBy,
            'limit' => $limit,
            'offset' => $offset
        ];
        
        $key = 'findby_' . md5(serialize($keyParams));
        
        return $this->cachedQuery(
            $key,
            fn() => $this->findBy($criteria, $orderBy, $limit, $offset),
            $keyParams,
            $ttl
        );
    }

    /**
     * Cache single result queries
     */
    protected function cachedFindOneBy(array $criteria, array $orderBy = null, int $ttl = 300)
    {
        $keyParams = [
            'criteria' => $criteria,
            'orderBy' => $orderBy
        ];
        
        $key = 'findoneby_' . md5(serialize($keyParams));
        
        return $this->cachedQuery(
            $key,
            fn() => $this->findOneBy($criteria, $orderBy),
            $keyParams,
            $ttl
        );
    }
}