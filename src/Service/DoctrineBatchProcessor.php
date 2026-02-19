<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine Batch Processor - оптимизация массовых операций
 * Используется для обработки больших объемов данных без переполнения памяти
 */
class DoctrineBatchProcessor
{
    private const DEFAULT_BATCH_SIZE = 50;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Batch insert - массовая вставка с автоматическим flush
     */
    public function batchInsert(array $entities, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $count = 0;
        
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
            $count++;
            
            if ($count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        
        // Flush remaining entities
        if ($count % $batchSize !== 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        
        return $count;
    }

    /**
     * Batch update - массовое обновление с оптимизацией памяти
     */
    public function batchUpdate(callable $callback, string $entityClass, array $criteria = [], int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        
        foreach ($criteria as $field => $value) {
            // Валидация имени поля
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                continue;
            }
            
            $qb->andWhere("e.$field = :$field")
               ->setParameter($field, $value);
        }
        
        $query = $qb->getQuery();
        $iterableResult = $query->toIterable();
        
        $count = 0;
        foreach ($iterableResult as $entity) {
            $callback($entity);
            $count++;
            
            if ($count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        
        if ($count % $batchSize !== 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        
        return $count;
    }

    /**
     * Batch delete - массовое удаление
     */
    public function batchDelete(string $entityClass, array $ids, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $count = 0;
        
        $chunks = array_chunk($ids, $batchSize);
        
        foreach ($chunks as $chunk) {
            $qb = $repository->createQueryBuilder('e');
            $qb->delete()
               ->where('e.id IN (:ids)')
               ->setParameter('ids', $chunk);
            
            $count += $qb->getQuery()->execute();
        }
        
        return $count;
    }

    /**
     * Process large result set with iterator
     */
    public function processLargeResultSet(string $entityClass, callable $processor, array $criteria = [], int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        
        foreach ($criteria as $field => $value) {
            // Валидация имени поля
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                continue;
            }
            
            $qb->andWhere("e.$field = :$field")
               ->setParameter($field, $value);
        }
        
        $query = $qb->getQuery();
        $iterableResult = $query->toIterable();
        
        $count = 0;
        foreach ($iterableResult as $entity) {
            $processor($entity);
            $count++;
            
            if ($count % $batchSize === 0) {
                $this->entityManager->clear();
            }
        }
        
        $this->entityManager->clear();
        
        return $count;
    }
}
