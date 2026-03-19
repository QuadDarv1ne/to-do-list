<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Сервис для управления кэшированием
 *
 * Использование:
 *
 * 1. Кэширование данных:
 *    $data = $cacheService->get('user_tasks_123', function() {
 *        return $this->taskRepo->findBy(['user' => $user]);
 *    }, 300); // 5 минут
 *
 * 2. Инвалидация по тегам:
 *    $cacheService->invalidateTags(['user_123', 'tasks']);
 *
 * 3. Очистка пула:
 *    $cacheService->clearPool('analytics');
 */
class CacheService
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Получить данные из кэша или выполнить callback
     *
     * @param string $key Ключ кэша
     * @param callable $callback Функция для получения данных если нет в кэше
     * @param int $ttl Время жизни в секундах
     * @param array<string> $tags Теги для инвалидации
     */
    public function get(string $key, callable $callback, int $ttl = 300, array $tags = []): mixed
    {
        try {
            $item = $this->cache->getItem($this->normalizeKey($key));

            if ($item->isHit()) {
                $this->logger->debug('Cache hit', ['key' => $key]);
                return $item->get();
            }

            $this->logger->debug('Cache miss', ['key' => $key]);
            $data = $callback();

            $item->set($data);
            $item->expiresAfter($ttl);

            if (!empty($tags) && method_exists($item, 'tag')) {
                $item->tag($tags);
            }

            $this->cache->save($item);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Cache error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Возвращаем результат callback при ошибке кэша
            return $callback();
        }
    }

    /**
     * Сохранить данные в кэш
     *
     * @param string $key Ключ кэша
     * @param mixed $value Данные
     * @param int $ttl Время жизни в секундах
     * @param array<string> $tags Теги для инвалидации
     */
    public function set(string $key, mixed $value, int $ttl = 300, array $tags = []): void
    {
        try {
            $item = $this->cache->getItem($this->normalizeKey($key));
            $item->set($value);
            $item->expiresAfter($ttl);

            if (!empty($tags) && method_exists($item, 'tag')) {
                $item->tag($tags);
            }

            $this->cache->save($item);

            $this->logger->debug('Cache set', ['key' => $key, 'ttl' => $ttl]);
        } catch (\Throwable $e) {
            $this->logger->error('Cache set error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Удалить из кэша
     */
    public function delete(string $key): void
    {
        try {
            $this->cache->deleteItem($this->normalizeKey($key));
            $this->logger->debug('Cache delete', ['key' => $key]);
        } catch (\Throwable $e) {
            $this->logger->error('Cache delete error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Очистить кэш по тегам
     *
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): void
    {
        try {
            if (method_exists($this->cache, 'invalidateTags')) {
                $this->cache->invalidateTags($tags);
                $this->logger->debug('Cache invalidate tags', ['tags' => $tags]);
            } else {
                $this->logger->warning('Cache adapter does not support tags');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Cache invalidate tags error', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Очистить весь кэш
     */
    public function clear(): void
    {
        try {
            $this->cache->clear();
            $this->logger->info('Cache cleared');
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверить наличие в кэше
     */
    public function has(string $key): bool
    {
        try {
            $item = $this->cache->getItem($this->normalizeKey($key));
            return $item->isHit();
        } catch (\Throwable $e) {
            $this->logger->error('Cache has error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить статистику кэша
     */
    public function getStats(): array
    {
        return [
            'adapter' => $this->cache::class,
            'supports_tags' => method_exists($this->cache, 'invalidateTags'),
        ];
    }

    /**
     * Нормализация ключа
     */
    private function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }
}
