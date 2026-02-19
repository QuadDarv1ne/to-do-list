<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RepositoryOptimizationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Оптимизация QueryBuilder с автоматическим добавлением JOIN для связанных сущностей
     */
    public function optimizeQueryBuilder(QueryBuilder $qb, array $associations = []): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];
        
        foreach ($associations as $association => $joinAlias) {
            if (is_numeric($association)) {
                // Если передан только алиас без ключа
                $association = $joinAlias;
                $joinAlias = $this->generateJoinAlias($association);
            }
            
            // Проверяем, не добавлен ли уже этот JOIN
            $joins = $qb->getDQLPart('join');
            $alreadyJoined = false;
            
            foreach ($joins as $joinPart) {
                foreach ($joinPart as $join) {
                    if ($join->getAlias() === $joinAlias) {
                        $alreadyJoined = true;
                        break 2;
                    }
                }
            }
            
            if (!$alreadyJoined) {
                $qb->leftJoin("{$alias}.{$association}", $joinAlias)
                   ->addSelect($joinAlias);
            }
        }
        
        return $qb;
    }

    /**
     * Генерация алиаса для JOIN
     */
    private function generateJoinAlias(string $association): string
    {
        // Преобразуем camelCase в короткий алиас
        $parts = preg_split('/(?=[A-Z])/', $association);
        $alias = strtolower(implode('_', array_filter($parts)));
        return substr($alias, 0, 10); // Ограничиваем длину
    }

    /**
     * Добавление пагинации к QueryBuilder
     */
    public function addPagination(QueryBuilder $qb, int $page = 1, int $limit = 20): QueryBuilder
    {
        $offset = ($page - 1) * $limit;
        
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit);
    }

    /**
     * Подсчет общего количества результатов без загрузки всех данных
     */
    public function countResults(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        $alias = $countQb->getRootAliases()[0];
        
        return (int) $countQb->select("COUNT(DISTINCT {$alias}.id)")
                            ->setFirstResult(0)
                            ->setMaxResults(null)
                            ->getQuery()
                            ->getSingleScalarResult();
    }

    /**
     * Оптимизация запроса с использованием частичных объектов (partial)
     */
    public function createPartialQuery(string $entityClass, array $fields, array $criteria = []): array
    {
        $alias = 'e';
        $qb = $this->entityManager->createQueryBuilder();
        
        // Формируем SELECT с нужными полями
        $selectFields = array_map(fn($field) => "{$alias}.{$field}", $fields);
        $qb->select(implode(', ', $selectFields))
           ->from($entityClass, $alias);
        
        // Добавляем критерии
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere("{$alias}.{$field} IN (:{$field})")
                   ->setParameter($field, $value);
            } else {
                $qb->andWhere("{$alias}.{$field} = :{$field}")
                   ->setParameter($field, $value);
            }
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Пакетная обработка больших наборов данных
     */
    public function batchProcess(QueryBuilder $qb, callable $processor, int $batchSize = 100): void
    {
        $offset = 0;
        $processed = 0;
        
        do {
            $batchQb = clone $qb;
            $results = $batchQb->setFirstResult($offset)
                              ->setMaxResults($batchSize)
                              ->getQuery()
                              ->getResult();
            
            if (empty($results)) {
                break;
            }
            
            $processor($results);
            
            // Очищаем EntityManager для освобождения памяти
            $this->entityManager->clear();
            
            $processed += count($results);
            $offset += $batchSize;
            
            $this->logger->info("Batch processed: {$processed} entities");
            
        } while (count($results) === $batchSize);
    }

    /**
     * Оптимизация запроса с использованием индексов
     */
    public function addIndexHints(QueryBuilder $qb, array $indexes): QueryBuilder
    {
        // PostgreSQL не поддерживает INDEX HINTS напрямую
        // Но можно использовать другие оптимизации
        
        // Добавляем ORDER BY для использования индексов
        foreach ($indexes as $field => $direction) {
            $alias = $qb->getRootAliases()[0];
            $qb->addOrderBy("{$alias}.{$field}", $direction);
        }
        
        return $qb;
    }

    /**
     * Создание оптимизированного запроса для выборки ID
     */
    public function fetchIds(string $entityClass, array $criteria = [], ?int $limit = null): array
    {
        $alias = 'e';
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select("{$alias}.id")
           ->from($entityClass, $alias);
        
        foreach ($criteria as $field => $value) {
            $qb->andWhere("{$alias}.{$field} = :{$field}")
               ->setParameter($field, $value);
        }
        
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        
        $results = $qb->getQuery()->getResult();
        
        return array_column($results, 'id');
    }

    /**
     * Оптимизация запроса с использованием подзапросов
     */
    public function addSubquery(QueryBuilder $qb, string $subqueryDQL, string $alias): QueryBuilder
    {
        $qb->andWhere($qb->expr()->in($alias, $subqueryDQL));
        return $qb;
    }

    /**
     * Анализ производительности запроса
     */
    public function analyzeQuery(QueryBuilder $qb): array
    {
        $query = $qb->getQuery();
        $sql = $query->getSQL();
        
        $startTime = microtime(true);
        $results = $query->getResult();
        $executionTime = microtime(true) - $startTime;
        
        $analysis = [
            'dql' => $qb->getDQL(),
            'sql' => $sql,
            'execution_time' => $executionTime,
            'result_count' => count($results),
            'memory_usage' => memory_get_usage(true),
            'parameters' => $query->getParameters()->toArray()
        ];
        
        // Логируем медленные запросы
        if ($executionTime > 0.5) {
            $this->logger->warning('Slow query detected', $analysis);
        }
        
        return $analysis;
    }

    /**
     * Оптимизация запроса с использованием DISTINCT
     */
    public function addDistinct(QueryBuilder $qb): QueryBuilder
    {
        return $qb->distinct();
    }

    /**
     * Создание оптимизированного запроса для агрегации
     */
    public function createAggregateQuery(
        string $entityClass,
        string $aggregateFunction,
        string $field,
        array $criteria = [],
        ?string $groupBy = null
    ): array {
        $alias = 'e';
        $qb = $this->entityManager->createQueryBuilder();
        
        $selectParts = ["{$aggregateFunction}({$alias}.{$field}) as aggregate_value"];
        
        if ($groupBy) {
            $selectParts[] = "{$alias}.{$groupBy}";
            $qb->groupBy("{$alias}.{$groupBy}");
        }
        
        $qb->select(implode(', ', $selectParts))
           ->from($entityClass, $alias);
        
        foreach ($criteria as $criteriaField => $value) {
            $qb->andWhere("{$alias}.{$criteriaField} = :{$criteriaField}")
               ->setParameter($criteriaField, $value);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Оптимизация запроса с использованием кэша результатов
     */
    public function enableResultCache(QueryBuilder $qb, int $lifetime = 3600, ?string $cacheKey = null): QueryBuilder
    {
        $query = $qb->getQuery();
        $query->useResultCache(true, $lifetime, $cacheKey);
        
        return $qb;
    }

    /**
     * Создание оптимизированного запроса для поиска
     */
    public function createSearchQuery(
        string $entityClass,
        array $searchFields,
        string $searchTerm,
        array $additionalCriteria = []
    ): QueryBuilder {
        $alias = 'e';
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select($alias)
           ->from($entityClass, $alias);
        
        // Добавляем условия поиска
        $searchConditions = [];
        foreach ($searchFields as $field) {
            $searchConditions[] = $qb->expr()->like(
                "LOWER({$alias}.{$field})",
                $qb->expr()->literal('%' . strtolower($searchTerm) . '%')
            );
        }
        
        if (!empty($searchConditions)) {
            $qb->andWhere($qb->expr()->orX(...$searchConditions));
        }
        
        // Добавляем дополнительные критерии
        foreach ($additionalCriteria as $field => $value) {
            $qb->andWhere("{$alias}.{$field} = :{$field}")
               ->setParameter($field, $value);
        }
        
        return $qb;
    }

    /**
     * Оптимизация запроса с использованием FETCH JOIN
     */
    public function addFetchJoin(QueryBuilder $qb, string $association, string $joinAlias): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];
        
        // Проверяем, не добавлен ли уже этот JOIN
        $joins = $qb->getDQLPart('join');
        $alreadyJoined = false;
        
        foreach ($joins as $joinPart) {
            foreach ($joinPart as $join) {
                if ($join->getAlias() === $joinAlias) {
                    $alreadyJoined = true;
                    break 2;
                }
            }
        }
        
        if (!$alreadyJoined) {
            $qb->leftJoin("{$alias}.{$association}", $joinAlias)
               ->addSelect($joinAlias);
        }
        
        return $qb;
    }

    /**
     * Создание оптимизированного запроса для получения связанных данных
     */
    public function fetchWithRelations(
        string $entityClass,
        array $relations,
        array $criteria = [],
        ?int $limit = null
    ): array {
        $alias = 'e';
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select($alias)
           ->from($entityClass, $alias);
        
        // Добавляем все связи
        foreach ($relations as $relation) {
            $relationAlias = $this->generateJoinAlias($relation);
            $qb->leftJoin("{$alias}.{$relation}", $relationAlias)
               ->addSelect($relationAlias);
        }
        
        // Добавляем критерии
        foreach ($criteria as $field => $value) {
            $qb->andWhere("{$alias}.{$field} = :{$field}")
               ->setParameter($field, $value);
        }
        
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        
        return $qb->getQuery()->getResult();
    }
}