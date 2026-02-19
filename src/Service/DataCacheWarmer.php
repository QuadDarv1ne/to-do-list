<?php

namespace App\Service;

use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Предварительный прогрев кэша для часто используемых данных
 */
class DataCacheWarmer
{
    public function __construct(
        private CacheInterface $cache,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Прогрев кэша для всех активных пользователей
     */
    public function warmupAll(): array
    {
        $results = [
            'users_processed' => 0,
            'cache_entries' => 0,
            'errors' => 0,
        ];

        try {
            // Используем пагинацию вместо findAll() для экономии памяти
            $batchSize = 50;
            $offset = 0;

            while (true) {
                $users = $this->userRepository->createQueryBuilder('u')
                    ->setMaxResults($batchSize)
                    ->setFirstResult($offset)
                    ->getQuery()
                    ->getResult();

                if (empty($users)) {
                    break;
                }

                foreach ($users as $user) {
                    try {
                        $this->warmupUserCache($user);
                        $results['users_processed']++;
                        $results['cache_entries'] += 3; // stats, trends, quick_stats
                    } catch (\Exception $e) {
                        $results['errors']++;
                        $this->logger->error('Cache warmup failed for user', [
                            'user_id' => $user->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $offset += $batchSize;

                // Очищаем EntityManager для освобождения памяти
                $this->userRepository->getEntityManager()->clear();
            }
        } catch (\Exception $e) {
            $this->logger->error('Cache warmup failed', ['error' => $e->getMessage()]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Прогрев кэша для конкретного пользователя
     */
    public function warmupUserCache($user): void
    {
        // Кэшируем статистику задач
        $this->cache->get(
            'task_stats_' . $user->getId(),
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(300); // 5 минут

                return $this->taskRepository->getQuickStats($user);
            },
        );

        // Кэшируем тренды
        $this->cache->get(
            'task_trends_' . $user->getId(),
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(600); // 10 минут

                return $this->taskRepository->getTaskCompletionTrendsByDate($user, 30);
            },
        );

        // Кэшируем данные дашборда
        $this->cache->get(
            'dashboard_data_' . $user->getId(),
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(120); // 2 минуты

                return [
                    'timestamp' => time(),
                    'user_id' => $user->getId(),
                ];
            },
        );
    }

    /**
     * Инвалидация кэша пользователя
     */
    public function invalidateUserCache($user): void
    {
        $keys = [
            'task_stats_' . $user->getId(),
            'task_trends_' . $user->getId(),
            'dashboard_data_' . $user->getId(),
        ];

        foreach ($keys as $key) {
            try {
                $this->cache->delete($key);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to invalidate cache', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Очистка всего кэша
     */
    public function clearAll(): void
    {
        try {
            $this->cache->clear();
            $this->logger->info('All cache cleared');
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear cache', ['error' => $e->getMessage()]);
        }
    }
}
