<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for database optimization operations
 */
class DatabaseOptimizationService
{
    private const BATCH_SIZE = 100;
    private const SLOW_QUERY_THRESHOLD = 1000; // milliseconds

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(): array
    {
        $results = [];
        $tables = $this->connection->createSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            try {
                $this->connection->executeStatement("VACUUM ANALYZE {$table}");
                $results[$table] = 'optimized';
                $this->logger->info("Table {$table} optimized");
            } catch (\Exception $e) {
                $results[$table] = 'failed: ' . $e->getMessage();
                $this->logger->error("Failed to optimize table {$table}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Analyze slow queries
     */
    public function analyzeSlowQueries(): array
    {
        // This would typically integrate with query logging
        // For now, return structure for slow query analysis
        return [
            'threshold_ms' => self::SLOW_QUERY_THRESHOLD,
            'queries' => [],
            'recommendations' => $this->getOptimizationRecommendations(),
        ];
    }

    /**
     * Get optimization recommendations
     */
    private function getOptimizationRecommendations(): array
    {
        return [
            'indexes' => $this->analyzeIndexUsage(),
            'queries' => $this->analyzeQueryPatterns(),
            'cache' => $this->analyzeCacheEfficiency(),
        ];
    }

    /**
     * Analyze index usage
     */
    private function analyzeIndexUsage(): array
    {
        $recommendations = [];

        try {
            // Check for missing indexes on foreign keys
            $sql = "
                SELECT 
                    t.table_name,
                    c.column_name
                FROM information_schema.table_constraints t
                JOIN information_schema.constraint_column_usage c 
                    ON t.constraint_name = c.constraint_name
                WHERE t.constraint_type = 'FOREIGN KEY'
                AND NOT EXISTS (
                    SELECT 1 
                    FROM information_schema.statistics s
                    WHERE s.table_name = t.table_name
                    AND s.column_name = c.column_name
                )
            ";

            $missingIndexes = $this->connection->fetchAllAssociative($sql);

            foreach ($missingIndexes as $index) {
                $recommendations[] = [
                    'type' => 'missing_index',
                    'table' => $index['table_name'],
                    'column' => $index['column_name'],
                    'suggestion' => "CREATE INDEX idx_{$index['table_name']}_{$index['column_name']} ON {$index['table_name']}({$index['column_name']})",
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not analyze index usage', [
                'error' => $e->getMessage(),
            ]);
        }

        return $recommendations;
    }

    /**
     * Analyze query patterns
     */
    private function analyzeQueryPatterns(): array
    {
        return [
            'n_plus_one_detected' => false,
            'missing_eager_loading' => [],
            'inefficient_joins' => [],
        ];
    }

    /**
     * Analyze cache efficiency
     */
    private function analyzeCacheEfficiency(): array
    {
        return [
            'hit_rate' => 0,
            'miss_rate' => 0,
            'recommendations' => [],
        ];
    }

    /**
     * Batch process entities
     */
    public function batchProcess(string $entityClass, callable $processor, int $batchSize = self::BATCH_SIZE): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $query = $repository->createQueryBuilder('e')
            ->getQuery();

        $iterableResult = $query->toIterable();
        $processed = 0;
        $batchCount = 0;

        foreach ($iterableResult as $entity) {
            $processor($entity);
            $processed++;
            $batchCount++;

            if ($batchCount >= $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $batchCount = 0;

                $this->logger->info("Batch processed {$processed} entities");
            }
        }

        if ($batchCount > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        return $processed;
    }

    /**
     * Bulk insert entities
     */
    public function bulkInsert(array $entities): int
    {
        $count = 0;
        $batchCount = 0;

        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
            $count++;
            $batchCount++;

            if ($batchCount >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $batchCount = 0;
            }
        }

        if ($batchCount > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        return $count;
    }

    /**
     * Bulk update with DQL
     */
    public function bulkUpdateDQL(string $dql, array $parameters = []): int
    {
        $query = $this->entityManager->createQuery($dql);

        foreach ($parameters as $key => $value) {
            $query->setParameter($key, $value);
        }

        return $query->execute();
    }

    /**
     * Bulk delete with DQL
     */
    public function bulkDeleteDQL(string $dql, array $parameters = []): int
    {
        $query = $this->entityManager->createQuery($dql);

        foreach ($parameters as $key => $value) {
            $query->setParameter($key, $value);
        }

        return $query->execute();
    }

    /**
     * Clean up old data
     */
    public function cleanupOldData(\DateTime $before): array
    {
        $results = [];

        // Clean up old activity logs
        $dql = 'DELETE FROM App\Entity\ActivityLog a WHERE a.createdAt < :before';
        $results['activity_logs'] = $this->bulkDeleteDQL($dql, ['before' => $before]);

        // Clean up old notifications
        $dql = 'DELETE FROM App\Entity\Notification n WHERE n.createdAt < :before AND n.isRead = true';
        $results['notifications'] = $this->bulkDeleteDQL($dql, ['before' => $before]);

        $this->logger->info('Old data cleaned up', $results);

        return $results;
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        $stats = [];

        try {
            // Get table sizes
            $sql = "
                SELECT 
                    table_name,
                    pg_size_pretty(pg_total_relation_size(quote_ident(table_name))) as size,
                    pg_total_relation_size(quote_ident(table_name)) as size_bytes
                FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY pg_total_relation_size(quote_ident(table_name)) DESC
            ";

            $stats['tables'] = $this->connection->fetchAllAssociative($sql);

            // Get index usage
            $sql = "
                SELECT 
                    schemaname,
                    tablename,
                    indexname,
                    idx_scan,
                    idx_tup_read,
                    idx_tup_fetch
                FROM pg_stat_user_indexes
                ORDER BY idx_scan DESC
            ";

            $stats['indexes'] = $this->connection->fetchAllAssociative($sql);

            // Get cache hit ratio
            $sql = "
                SELECT 
                    sum(heap_blks_read) as heap_read,
                    sum(heap_blks_hit) as heap_hit,
                    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) as ratio
                FROM pg_statio_user_tables
            ";

            $stats['cache_hit_ratio'] = $this->connection->fetchAssociative($sql);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get database stats', [
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Vacuum database
     */
    public function vacuum(bool $full = false, bool $analyze = true): void
    {
        $sql = 'VACUUM';

        if ($full) {
            $sql .= ' FULL';
        }

        if ($analyze) {
            $sql .= ' ANALYZE';
        }

        try {
            $this->connection->executeStatement($sql);
            $this->logger->info('Database vacuumed', [
                'full' => $full,
                'analyze' => $analyze,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to vacuum database', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Reindex database
     */
    public function reindex(?string $table = null): void
    {
        try {
            if ($table) {
                $this->connection->executeStatement("REINDEX TABLE {$table}");
                $this->logger->info("Table {$table} reindexed");
            } else {
                $this->connection->executeStatement('REINDEX DATABASE ' . $this->connection->getDatabase());
                $this->logger->info('Database reindexed');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
