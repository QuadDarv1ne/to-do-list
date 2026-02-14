<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for optimizing data loading operations
 */
class DataLoaderOptimizerService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;
    private array $loadedEntities = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Load entities with optimized queries using joins and batch processing
     */
    public function loadTasksWithRelations(array $userIds, array $options = []): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('t, u, tc, ta')
           ->from('App\Entity\Task', 't')
           ->leftJoin('t.user', 'u')
           ->leftJoin('t.category', 'tc')
           ->leftJoin('t.assignedUser', 'ta')
           ->where('t.user IN (:userIds)')
           ->setParameter('userIds', $userIds);

        // Add additional filters based on options
        if (isset($options['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $options['status']);
        }

        if (isset($options['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $options['priority']);
        }

        if (isset($options['dueDateFrom'])) {
            $qb->andWhere('t.dueDate >= :dueDateFrom')
               ->setParameter('dueDateFrom', $options['dueDateFrom']);
        }

        if (isset($options['limit'])) {
            $qb->setMaxResults($options['limit']);
        }

        if (isset($options['offset'])) {
            $qb->setFirstResult($options['offset']);
        }

        $startTime = microtime(true);
        $tasks = $qb->getQuery()->getResult();
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $this->logger->info("Loaded " . count($tasks) . " tasks with relations", [
            'execution_time_ms' => round($executionTime, 2),
            'user_ids' => $userIds,
            'options' => $options
        ]);

        return $tasks;
    }

    /**
     * Batch load entities to minimize database queries
     */
    public function batchLoadUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        // Remove duplicates and filter out already loaded entities
        $uniqueUserIds = array_unique($userIds);
        $newUserIds = array_diff($uniqueUserIds, array_keys($this->loadedEntities['users'] ?? []));

        if (empty($newUserIds)) {
            // Return already loaded users
            $result = [];
            foreach ($userIds as $id) {
                if (isset($this->loadedEntities['users'][$id])) {
                    $result[] = $this->loadedEntities['users'][$id];
                }
            }
            return $result;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
           ->from('App\Entity\User', 'u')
           ->where('u.id IN (:userIds)')
           ->setParameter('userIds', $newUserIds);

        $startTime = microtime(true);
        $users = $qb->getQuery()->getResult();
        $executionTime = (microtime(true) - $startTime) * 1000;

        // Cache loaded users
        foreach ($users as $user) {
            $this->loadedEntities['users'][$user->getId()] = $user;
        }

        $this->logger->info("Batch loaded " . count($users) . " users", [
            'execution_time_ms' => round($executionTime, 2),
            'user_ids_count' => count($newUserIds)
        ]);

        // Return users in the requested order
        $result = [];
        foreach ($userIds as $id) {
            if (isset($this->loadedEntities['users'][$id])) {
                $result[] = $this->loadedEntities['users'][$id];
            }
        }

        return $result;
    }

    /**
     * Load paginated tasks with optimized query
     */
    public function loadPaginatedTasks(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t, u, tc') // Select only necessary fields
           ->from('App\Entity\Task', 't')
           ->leftJoin('t.user', 'u')
           ->leftJoin('t.category', 'tc');

        // Apply filters
        $params = [];
        if (!empty($filters['userId'])) {
            $qb->andWhere('t.user = :userId');
            $params['userId'] = $filters['userId'];
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status');
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority');
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['searchTerm'])) {
            $qb->andWhere('t.title LIKE :searchTerm OR t.description LIKE :searchTerm');
            $params['searchTerm'] = '%' . $filters['searchTerm'] . '%';
        }

        foreach ($params as $key => $value) {
            $qb->setParameter($key, $value);
        }
        
        $qb->setFirstResult($offset)
           ->setMaxResults($limit)
           ->orderBy('t.createdAt', 'DESC');

        $startTime = microtime(true);
        $tasks = $qb->getQuery()->getResult();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->logger->info("Loaded paginated tasks", [
            'execution_time_ms' => round($executionTime, 2),
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $filters,
            'result_count' => count($tasks)
        ]);

        return $tasks;
    }

    /**
     * Load related entities in bulk to prevent N+1 queries
     */
    public function loadRelatedEntities(array $entities, string $entityClass, string $association): array
    {
        if (empty($entities)) {
            return [];
        }

        // Get IDs of entities to load related data for
        $ids = [];
        foreach ($entities as $entity) {
            $method = 'get' . ucfirst($association);
            if (method_exists($entity, $method)) {
                $related = $entity->$method();
                if ($related && isset($related->id)) {
                    $ids[] = $related->id;
                }
            }
        }

        if (empty($ids)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
           ->from($entityClass, 'r')
           ->where('r.id IN (:ids)')
           ->setParameter('ids', array_unique($ids));

        $startTime = microtime(true);
        $relatedEntities = $qb->getQuery()->getResult();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->logger->info("Loaded related entities", [
            'execution_time_ms' => round($executionTime, 2),
            'entity_class' => $entityClass,
            'association' => $association,
            'count' => count($relatedEntities)
        ]);

        return $relatedEntities;
    }

    /**
     * Preload commonly accessed data
     */
    public function preloadCommonData(): void
    {
        $startTime = microtime(true);

        // Preload active users
        $activeUsersQb = $this->entityManager->createQueryBuilder();
        $activeUsers = $activeUsersQb->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.isActive = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        // Cache active users
        foreach ($activeUsers as $user) {
            $this->loadedEntities['users'][$user->getId()] = $user;
        }

        // Preload common task categories
        $categoriesQb = $this->entityManager->createQueryBuilder();
        $categories = $categoriesQb->select('tc')
            ->from('App\Entity\TaskCategory', 'tc')
            ->getQuery()
            ->getResult();

        $this->loadedEntities['categories'] = [];
        foreach ($categories as $category) {
            $this->loadedEntities['categories'][$category->getId()] = $category;
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->logger->info("Preloaded common data", [
            'execution_time_ms' => round($executionTime, 2),
            'active_users_count' => count($activeUsers),
            'categories_count' => count($categories)
        ]);
    }

    /**
     * Get optimized task statistics
     */
    public function getOptimizedTaskStats(array $userIds): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $stats = $qb->select([
                'u.id as userId',
                'COUNT(t.id) as totalTasks',
                "SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completedTasks",
                "SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pendingTasks",
                "SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as inProgressTasks",
                "SUM(CASE WHEN t.priority = 'high' THEN 1 ELSE 0 END) as highPriorityTasks",
                "AVG(DATEDIFF(t.dueDate, CURRENT_DATE())) as avgDaysToDue"
            ])
            ->from('App\Entity\Task', 't')
            ->join('t.user', 'u')
            ->where('u.id IN (:userIds)')
            ->groupBy('u.id')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        $this->logger->info("Retrieved optimized task statistics", [
            'user_ids_count' => count($userIds),
            'result_sets' => count($stats)
        ]);

        return $stats;
    }

    /**
     * Clear loaded entities cache
     */
    public function clearCache(): void
    {
        $this->loadedEntities = [];
        $this->logger->info("Cleared data loader cache");
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $totalCount = 0;
        foreach ($this->loadedEntities as $type => $entities) {
            $totalCount += count($entities);
        }

        return [
            'cached_entity_types' => array_keys($this->loadedEntities),
            'total_cached_entities' => $totalCount,
            'breakdown' => array_map('count', $this->loadedEntities)
        ];
    }
}