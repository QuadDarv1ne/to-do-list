<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;

class CacheOptimizationService
{
    public function __construct(
        private CacheInterface $cache,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Кэширование результатов запросов с автоматической инвалидацией
     */
    public function cacheQuery(string $key, callable $callback, int $ttl = 3600, array $tags = []): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $tags) {
            $item->expiresAfter($ttl);
            if (!empty($tags)) {
                $item->tag($tags);
            }
            return $callback();
        });
    }

    /**
     * Кэширование статистики пользователя
     */
    public function getUserStats(int $userId): array
    {
        return $this->cacheQuery(
            "user_stats_{$userId}",
            function() use ($userId) {
                $qb = $this->entityManager->createQueryBuilder();
                return $qb->select([
                    'COUNT(CASE WHEN t.status = :completed THEN 1 END) as completed_tasks',
                    'COUNT(CASE WHEN t.status = :pending THEN 1 END) as pending_tasks',
                    'COUNT(CASE WHEN t.status = :in_progress THEN 1 END) as in_progress_tasks',
                    'COUNT(t.id) as total_tasks'
                ])
                ->from('App\Entity\Task', 't')
                ->where('t.user = :userId OR t.assignedUser = :userId')
                ->setParameter('userId', $userId)
                ->setParameter('completed', 'completed')
                ->setParameter('pending', 'pending')
                ->setParameter('in_progress', 'in_progress')
                ->getQuery()
                ->getSingleResult();
            },
            600, // 10 минут
            ['user_stats', "user_{$userId}"]
        );
    }

    /**
     * Кэширование популярных тегов
     */
    public function getPopularTags(int $limit = 20): array
    {
        return $this->cacheQuery(
            'popular_tags',
            function() use ($limit) {
                $qb = $this->entityManager->createQueryBuilder();
                return $qb->select('tag.name, COUNT(tt.task) as usage_count')
                    ->from('App\Entity\Tag', 'tag')
                    ->leftJoin('tag.tasks', 'tt')
                    ->groupBy('tag.id')
                    ->orderBy('usage_count', 'DESC')
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();
            },
            1800, // 30 минут
            ['tags', 'popular_tags']
        );
    }

    /**
     * Кэширование категорий с количеством задач
     */
    public function getCategoriesWithCounts(): array
    {
        return $this->cacheQuery(
            'categories_with_counts',
            function() {
                $qb = $this->entityManager->createQueryBuilder();
                return $qb->select('c, COUNT(t.id) as task_count')
                    ->from('App\Entity\TaskCategory', 'c')
                    ->leftJoin('c.tasks', 't')
                    ->groupBy('c.id')
                    ->orderBy('c.name', 'ASC')
                    ->getQuery()
                    ->getResult();
            },
            900, // 15 минут
            ['categories']
        );
    }

    /**
     * Инвалидация кэша по тегам
     */
    public function invalidateByTags(array $tags): void
    {
        if (method_exists($this->cache, 'invalidateTags')) {
            $this->cache->invalidateTags($tags);
        }
    }

    /**
     * Инвалидация кэша пользователя при изменении задач
     */
    public function invalidateUserCache(int $userId): void
    {
        $this->invalidateByTags(["user_{$userId}", 'user_stats']);
    }

    /**
     * Инвалидация кэша при изменении тегов
     */
    public function invalidateTagsCache(): void
    {
        $this->invalidateByTags(['tags', 'popular_tags']);
    }

    /**
     * Инвалидация кэша при изменении категорий
     */
    public function invalidateCategoriesCache(): void
    {
        $this->invalidateByTags(['categories']);
    }

    /**
     * Предварительная загрузка часто используемых данных
     */
    public function warmupCache(): void
    {
        // Загружаем популярные теги
        $this->getPopularTags();
        
        // Загружаем категории
        $this->getCategoriesWithCounts();
        
        // Можно добавить другие часто используемые данные
    }

    /**
     * Очистка всего кэша
     */
    public function clearAll(): void
    {
        $this->cache->clear();
    }
}