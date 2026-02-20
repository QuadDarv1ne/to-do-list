<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Advanced Performance Optimization Service
 *
 * Умное кеширование, предзагрузка данных, оптимизация запросов
 */
class PerformanceOptimizerService
{
    private array $queryCache = [];

    private array $preloadData = [];

    private array $optimizationStats = [
        'queries_saved' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'optimization_time' => 0,
    ];

    public function __construct(
        private CacheItemPoolInterface $cache,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Умное кеширование запроса с автоматической инвалидацией
     */
    public function cacheQuery(
        string $key,
        callable $query,
        int $ttl = 300,
        array $tags = [],
    ): mixed {
        $startTime = microtime(true);

        // Проверяем локальный кэш
        if (isset($this->queryCache[$key])) {
            $this->optimizationStats['queries_saved']++;
            $this->optimizationStats['cache_hits']++;

            return $this->queryCache[$key];
        }

        // Проверяем системный кэш
        $cacheKey = $this->normalizeCacheKey($key);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $this->queryCache[$key] = $cacheItem->get();
            $this->optimizationStats['queries_saved']++;
            $this->optimizationStats['cache_hits']++;

            return $cacheItem->get();
        }

        // Выполняем запрос
        $this->optimizationStats['cache_misses']++;
        $result = $query();

        // Сохраняем в кэш
        $cacheItem->set($result);
        $cacheItem->expiresAfter($ttl);

        // Добавляем теги для инвалидации
        if (!empty($tags) && method_exists($cacheItem, 'tag')) {
            $cacheItem->tag($tags);
        }

        $this->cache->save($cacheItem);
        $this->queryCache[$key] = $result;

        $this->optimizationStats['optimization_time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * Предзагрузка данных для улучшения производительности
     */
    public function preloadData(array $config): void
    {
        foreach ($config as $key => $loader) {
            if (!isset($this->preloadData[$key])) {
                $this->preloadData[$key] = $loader();
            }
        }
    }

    /**
     * Получение предзагруженных данных
     */
    public function getPreloaded(string $key): mixed
    {
        return $this->preloadData[$key] ?? null;
    }

    /**
     * Массовая инвалидация кэша по тегам
     */
    public function invalidateByTags(array $tags): void
    {
        if (method_exists($this->cache, 'invalidateTags')) {
            $this->cache->invalidateTags($tags);
        }

        // Очищаем локальный кэш
        $this->queryCache = [];
    }

    /**
     * Оптимизация batch операций
     */
    public function batchOperation(
        callable $operation,
        int $batchSize = 100,
        int $totalItems = 0,
    ): array {
        $results = [];
        $processed = 0;

        $this->em->getConnection()->beginTransaction();

        try {
            foreach ($operation() as $item) {
                $results[] = $item;
                $processed++;

                // Флеш каждые N элементов
                if ($processed % $batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            $this->em->flush();
            $this->em->getConnection()->commit();

        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();

            throw $e;
        }

        return $results;
    }

    /**
     * Ленивая загрузка связанных данных
     */
    public function lazyLoad(
        string $entityClass,
        array $ids,
        string $association,
    ): array {
        if (empty($ids)) {
            return [];
        }

        $key = \sprintf('lazy_%s_%s_%s', $entityClass, md5(implode(',', $ids)), $association);

        return $this->cacheQuery($key, function () use ($entityClass, $ids, $association) {
            $qb = $this->em->createQueryBuilder();

            return $qb->select('e', 'a')
                ->from($entityClass, 'e')
                ->leftJoin('e.' . $association, 'a')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        }, 600);
    }

    /**
     * Получение статистики оптимизации
     */
    public function getStats(): array
    {
        $hitRate = $this->optimizationStats['cache_hits'] + $this->optimizationStats['cache_misses'] > 0
            ? round(
                ($this->optimizationStats['cache_hits'] /
                ($this->optimizationStats['cache_hits'] + $this->optimizationStats['cache_misses'])) * 100,
                2,
            )
            : 0;

        return [
            ...$this->optimizationStats,
            'hit_rate' => $hitRate . '%',
            'local_cache_size' => \count($this->queryCache),
            'preload_size' => \count($this->preloadData),
        ];
    }

    /**
     * Сброс статистики
     */
    public function resetStats(): void
    {
        $this->optimizationStats = [
            'queries_saved' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'optimization_time' => 0,
        ];
    }

    /**
     * Нормализация ключа кэша
     */
    private function normalizeCacheKey(string $key): string
    {
        return 'perf_' . md5($key);
    }

    /**
     * Get optimized dashboard statistics for user (from TaskPerformanceOptimizerService)
     */
    public function getUserDashboardStats($user, $taskRepository): array
    {
        $cacheKey = "dashboard_stats_user_{$user->getId()}";

        return $this->cacheQuery($cacheKey, function () use ($user, $taskRepository) {
            $repositoryStats = $taskRepository->getDashboardStats($user);

            $stats = [
                'total_tasks' => $repositoryStats['total_tasks'] ?? $taskRepository->count(['user' => $user]),
                'completed_tasks' => $repositoryStats['completed_tasks'] ?? $taskRepository->countByStatus($user, null, 'completed'),
                'pending_tasks' => $repositoryStats['pending_tasks'] ?? $taskRepository->countByStatus($user, null, 'pending'),
                'in_progress_tasks' => $repositoryStats['in_progress_tasks'] ?? $taskRepository->countByStatus($user, null, 'in_progress'),
                'last_updated' => date('Y-m-d H:i:s'),
            ];

            $stats['completion_percentage'] = $stats['total_tasks'] > 0 ?
                round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0;

            return $stats;
        }, 300, ['user_dashboard_stats', "user_{$user->getId()}"]);
    }

    /**
     * Invalidate dashboard cache for user
     */
    public function invalidateUserCache($user): void
    {
        $userId = $user->getId();
        $this->invalidateByTags(["user_{$userId}", 'user_dashboard_stats', 'dashboard']);
    }

    /**
     * Деструктор для сохранения статистики
     */
    public function __destruct()
    {
        // Логируем статистику при завершении запроса
        if ($this->optimizationStats['optimization_time'] > 0.1) {
            error_log(\sprintf(
                '[PerformanceOptimizer] Queries saved: %d, Cache hits: %d, Time: %.3fs',
                $this->optimizationStats['queries_saved'],
                $this->optimizationStats['cache_hits'],
                $this->optimizationStats['optimization_time'],
            ));
        }
    }
}
