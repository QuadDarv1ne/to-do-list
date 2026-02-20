<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Automatically invalidate cache when tasks are modified
 * Improves data consistency while maintaining performance
 */
#[AsDoctrineListener(event: Events::postPersist, priority: 500)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500)]
#[AsDoctrineListener(event: Events::postRemove, priority: 500)]
class TaskCacheInvalidationListener
{
    public function __construct(
        private QueryCacheService $cacheService,
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateCache($args->getObject(), 'created');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateCache($args->getObject(), 'updated');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateCache($args->getObject(), 'deleted');
    }

    private function invalidateCache(object $entity, string $action): void
    {
        if (!$entity instanceof Task) {
            return;
        }

        try {
            // Invalidate user-specific caches
            $userId = $entity->getUser()?->getId();
            $assignedUserId = $entity->getAssignedUser()?->getId();

            $keysToInvalidate = [];

            // User caches
            if ($userId) {
                $keysToInvalidate[] = 'quick_stats_' . $userId;
                $keysToInvalidate[] = 'trends_' . $userId . '_*';
                $keysToInvalidate[] = 'dashboard_data_' . $userId;
            }

            // Assigned user caches
            if ($assignedUserId && $assignedUserId !== $userId) {
                $keysToInvalidate[] = 'quick_stats_' . $assignedUserId;
                $keysToInvalidate[] = 'trends_' . $assignedUserId . '_*';
                $keysToInvalidate[] = 'dashboard_data_' . $assignedUserId;
            }

            // Task-specific cache
            if ($action !== 'created') {
                $keysToInvalidate[] = 'task_with_relations_' . $entity->getId();
            }

            // Global search caches
            $keysToInvalidate[] = 'search_tasks_*';
            $keysToInvalidate[] = 'count_search_tasks_*';

            // Invalidate all keys
            foreach ($keysToInvalidate as $key) {
                $this->cacheService->delete($key);
            }

            $this->logger->debug('Task cache invalidated', [
                'task_id' => $entity->getId(),
                'action' => $action,
                'keys_invalidated' => count($keysToInvalidate),
            ]);
        } catch (\Exception $e) {
            // Don't break the application if cache invalidation fails
            $this->logger->error('Failed to invalidate task cache', [
                'task_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
