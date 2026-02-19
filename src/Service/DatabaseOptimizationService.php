<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class DatabaseOptimizationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Оптимизация запросов с батчингом
     */
    public function batchProcess(array $entities, int $batchSize = 100): void
    {
        $count = 0;
        
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
            
            if (++$count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(); // Освобождаем память
            }
        }
        
        // Обрабатываем оставшиеся сущности
        if ($count % $batchSize !== 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /**
     * Массовое обновление через DQL
     */
    public function bulkUpdate(string $entityClass, array $criteria, array $updates): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update($entityClass, 'e');
        
        foreach ($updates as $field => $value) {
            $qb->set("e.{$field}", $qb->expr()->literal($value));
        }
        
        $whereConditions = [];
        foreach ($criteria as $field => $value) {
            $paramName = "param_{$field}";
            $whereConditions[] = $qb->expr()->eq("e.{$field}", ":{$paramName}");
            $qb->setParameter($paramName, $value);
        }
        
        if (!empty($whereConditions)) {
            $qb->where($qb->expr()->andX(...$whereConditions));
        }
        
        return $qb->getQuery()->execute();
    }

    /**
     * Массовое удаление через DQL
     */
    public function bulkDelete(string $entityClass, array $criteria): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($entityClass, 'e');
        
        $whereConditions = [];
        foreach ($criteria as $field => $value) {
            $paramName = "param_{$field}";
            $whereConditions[] = $qb->expr()->eq("e.{$field}", ":{$paramName}");
            $qb->setParameter($paramName, $value);
        }
        
        if (!empty($whereConditions)) {
            $qb->where($qb->expr()->andX(...$whereConditions));
        }
        
        return $qb->getQuery()->execute();
    }

    /**
     * Анализ медленных запросов
     */
    public function analyzeSlowQueries(): array
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            // Для PostgreSQL
            $sql = "
                SELECT query, mean_time, calls, total_time
                FROM pg_stat_statements 
                WHERE mean_time > 100 
                ORDER BY mean_time DESC 
                LIMIT 10
            ";
            
            $result = $connection->executeQuery($sql);
            return $result->fetchAllAssociative();
            
        } catch (\Exception $e) {
            $this->logger->warning('Не удалось получить статистику запросов: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Оптимизация индексов
     */
    public function suggestIndexes(): array
    {
        $connection = $this->entityManager->getConnection();
        $suggestions = [];
        
        try {
            // Анализ неиспользуемых индексов
            $sql = "
                SELECT schemaname, tablename, indexname, idx_tup_read, idx_tup_fetch
                FROM pg_stat_user_indexes 
                WHERE idx_tup_read = 0 AND idx_tup_fetch = 0
            ";
            
            $unusedIndexes = $connection->executeQuery($sql)->fetchAllAssociative();
            
            if (!empty($unusedIndexes)) {
                $suggestions['unused_indexes'] = $unusedIndexes;
            }
            
            // Анализ таблиц без индексов на часто используемых полях
            $sql = "
                SELECT tablename, attname, n_distinct, correlation
                FROM pg_stats 
                WHERE schemaname = 'public' 
                AND n_distinct > 100 
                AND correlation < 0.1
            ";
            
            $candidatesForIndex = $connection->executeQuery($sql)->fetchAllAssociative();
            
            if (!empty($candidatesForIndex)) {
                $suggestions['index_candidates'] = $candidatesForIndex;
            }
            
        } catch (\Exception $e) {
            $this->logger->warning('Не удалось проанализировать индексы: ' . $e->getMessage());
        }
        
        return $suggestions;
    }

    /**
     * Очистка старых данных
     */
    public function cleanupOldData(): array
    {
        $results = [];
        
        // Очистка старых логов активности (старше 90 дней)
        $count = $this->bulkDelete(
            'App\Entity\ActivityLog',
            ['createdAt' => new \DateTime('-90 days')]
        );
        $results['activity_logs'] = $count;
        
        // Очистка завершенных задач старше года
        $count = $this->bulkDelete(
            'App\Entity\Task',
            [
                'status' => 'completed',
                'completedAt' => new \DateTime('-1 year')
            ]
        );
        $results['old_completed_tasks'] = $count;
        
        // Очистка неактивных уведомлений старше 30 дней
        $count = $this->bulkDelete(
            'App\Entity\Notification',
            [
                'isRead' => true,
                'createdAt' => new \DateTime('-30 days')
            ]
        );
        $results['old_notifications'] = $count;
        
        return $results;
    }

    /**
     * Статистика использования БД
     */
    public function getDatabaseStats(): array
    {
        $connection = $this->entityManager->getConnection();
        $stats = [];
        
        try {
            // Размер таблиц
            $sql = "
                SELECT 
                    schemaname,
                    tablename,
                    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
                FROM pg_tables 
                WHERE schemaname = 'public'
                ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
            ";
            
            $stats['table_sizes'] = $connection->executeQuery($sql)->fetchAllAssociative();
            
            // Статистика подключений
            $sql = "
                SELECT 
                    state,
                    count(*) as connections
                FROM pg_stat_activity 
                WHERE datname = current_database()
                GROUP BY state
            ";
            
            $stats['connections'] = $connection->executeQuery($sql)->fetchAllAssociative();
            
            // Статистика кэша
            $sql = "
                SELECT 
                    sum(heap_blks_read) as heap_read,
                    sum(heap_blks_hit) as heap_hit,
                    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) as cache_hit_ratio
                FROM pg_statio_user_tables
            ";
            
            $cacheStats = $connection->executeQuery($sql)->fetchAssociative();
            $stats['cache_hit_ratio'] = round($cacheStats['cache_hit_ratio'] * 100, 2);
            
        } catch (\Exception $e) {
            $this->logger->error('Ошибка получения статистики БД: ' . $e->getMessage());
        }
        
        return $stats;
    }

    /**
     * Оптимизация таблиц
     */
    public function optimizeTables(): void
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            // Для PostgreSQL - VACUUM ANALYZE
            $sql = "
                SELECT tablename 
                FROM pg_tables 
                WHERE schemaname = 'public'
            ";
            
            $tables = $connection->executeQuery($sql)->fetchFirstColumn();
            
            foreach ($tables as $table) {
                $connection->executeStatement("VACUUM ANALYZE {$table}");
                $this->logger->info("Оптимизирована таблица: {$table}");
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Ошибка оптимизации таблиц: ' . $e->getMessage());
        }
    }

    /**
     * Проверка целостности данных
     */
    public function checkDataIntegrity(): array
    {
        $issues = [];
        
        try {
            // Проверка задач без пользователей
            $qb = $this->entityManager->createQueryBuilder();
            $orphanedTasks = $qb->select('COUNT(t.id)')
                ->from('App\Entity\Task', 't')
                ->where('t.user IS NULL')
                ->getQuery()
                ->getSingleScalarResult();
            
            if ($orphanedTasks > 0) {
                $issues[] = "Найдено {$orphanedTasks} задач без пользователей";
            }
            
            // Проверка комментариев без задач
            $qb = $this->entityManager->createQueryBuilder();
            $orphanedComments = $qb->select('COUNT(c.id)')
                ->from('App\Entity\Comment', 'c')
                ->where('c.task IS NULL')
                ->getQuery()
                ->getSingleScalarResult();
            
            if ($orphanedComments > 0) {
                $issues[] = "Найдено {$orphanedComments} комментариев без задач";
            }
            
        } catch (\Exception $e) {
            $issues[] = 'Ошибка проверки целостности: ' . $e->getMessage();
        }
        
        return $issues;
    }
}