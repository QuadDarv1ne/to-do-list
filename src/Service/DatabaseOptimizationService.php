<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DatabaseOptimizationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Анализ и оптимизация запросов к базе данных
     */
    public function analyzeQueryPerformance(): array
    {
        $connection = $this->entityManager->getConnection();
        $results = [];

        try {
            // Получаем статистику медленных запросов (PostgreSQL)
            if ($connection->getDatabasePlatform()->getName() === 'postgresql') {
                $results['slow_queries'] = $this->getSlowQueriesPostgreSQL();
                $results['index_usage'] = $this->getIndexUsagePostgreSQL();
                $results['table_sizes'] = $this->getTableSizesPostgreSQL();
            }

            $results['connection_stats'] = $this->getConnectionStats();
            $results['recommendations'] = $this->generateRecommendations($results);

        } catch (\Exception $e) {
            $this->logger->error('Database analysis failed: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Получение медленных запросов для PostgreSQL
     */
    private function getSlowQueriesPostgreSQL(): array
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            // Проверяем, включен ли pg_stat_statements
            $stmt = $connection->prepare("
                SELECT EXISTS (
                    SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements'
                ) as enabled
            ");
            $result = $stmt->executeQuery();
            $enabled = $result->fetchAssociative()['enabled'];

            if (!$enabled) {
                return ['error' => 'pg_stat_statements extension not enabled'];
            }

            // Получаем топ медленных запросов
            $stmt = $connection->prepare("
                SELECT 
                    query,
                    calls,
                    total_exec_time,
                    mean_exec_time,
                    rows
                FROM pg_stat_statements 
                WHERE query NOT LIKE '%pg_stat_statements%'
                ORDER BY mean_exec_time DESC 
                LIMIT 10
            ");
            
            $result = $stmt->executeQuery();
            return $result->fetchAllAssociative();

        } catch (\Exception $e) {
            return ['error' => 'Could not fetch slow queries: ' . $e->getMessage()];
        }
    }

    /**
     * Анализ использования индексов
     */
    private function getIndexUsagePostgreSQL(): array
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            $stmt = $connection->prepare("
                SELECT 
                    schemaname,
                    tablename,
                    indexname,
                    idx_tup_read,
                    idx_tup_fetch,
                    idx_scan
                FROM pg_stat_user_indexes 
                WHERE idx_scan = 0
                ORDER BY schemaname, tablename
            ");
            
            $result = $stmt->executeQuery();
            return $result->fetchAllAssociative();

        } catch (\Exception $e) {
            return ['error' => 'Could not fetch index usage: ' . $e->getMessage()];
        }
    }

    /**
     * Получение размеров таблиц
     */
    private function getTableSizesPostgreSQL(): array
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            $stmt = $connection->prepare("
                SELECT 
                    schemaname,
                    tablename,
                    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
                FROM pg_tables 
                WHERE schemaname = 'public'
                ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
            ");
            
            $result = $stmt->executeQuery();
            return $result->fetchAllAssociative();

        } catch (\Exception $e) {
            return ['error' => 'Could not fetch table sizes: ' . $e->getMessage()];
        }
    }

    /**
     * Статистика подключений
     */
    private function getConnectionStats(): array
    {
        $connection = $this->entityManager->getConnection();
        
        try {
            $stmt = $connection->prepare("
                SELECT 
                    count(*) as total_connections,
                    count(*) FILTER (WHERE state = 'active') as active_connections,
                    count(*) FILTER (WHERE state = 'idle') as idle_connections
                FROM pg_stat_activity
            ");
            
            $result = $stmt->executeQuery();
            return $result->fetchAssociative();

        } catch (\Exception $e) {
            return ['error' => 'Could not fetch connection stats: ' . $e->getMessage()];
        }
    }

    /**
     * Генерация рекомендаций по оптимизации
     */
    private function generateRecommendations(array $analysisResults): array
    {
        $recommendations = [];

        // Рекомендации по медленным запросам
        if (isset($analysisResults['slow_queries']) && is_array($analysisResults['slow_queries'])) {
            foreach ($analysisResults['slow_queries'] as $query) {
                if (isset($query['mean_exec_time']) && $query['mean_exec_time'] > 1000) {
                    $recommendations[] = [
                        'type' => 'slow_query',
                        'priority' => 'high',
                        'message' => 'Запрос выполняется более 1 секунды в среднем',
                        'suggestion' => 'Рассмотрите добавление индексов или оптимизацию запроса'
                    ];
                }
            }
        }

        // Рекомендации по неиспользуемым индексам
        if (isset($analysisResults['index_usage']) && is_array($analysisResults['index_usage'])) {
            if (count($analysisResults['index_usage']) > 0) {
                $recommendations[] = [
                    'type' => 'unused_indexes',
                    'priority' => 'medium',
                    'message' => 'Найдены неиспользуемые индексы: ' . count($analysisResults['index_usage']),
                    'suggestion' => 'Рассмотрите удаление неиспользуемых индексов для экономии места'
                ];
            }
        }

        // Рекомендации по размерам таблиц
        if (isset($analysisResults['table_sizes']) && is_array($analysisResults['table_sizes'])) {
            foreach ($analysisResults['table_sizes'] as $table) {
                if (isset($table['size_bytes']) && $table['size_bytes'] > 100 * 1024 * 1024) { // > 100MB
                    $recommendations[] = [
                        'type' => 'large_table',
                        'priority' => 'medium',
                        'message' => "Таблица {$table['tablename']} занимает {$table['size']}",
                        'suggestion' => 'Рассмотрите архивирование старых данных или партиционирование'
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Оптимизация таблиц (VACUUM и ANALYZE)
     */
    public function optimizeTables(): array
    {
        $connection = $this->entityManager->getConnection();
        $results = [];

        try {
            // Получаем список всех таблиц
            $stmt = $connection->prepare("
                SELECT tablename 
                FROM pg_tables 
                WHERE schemaname = 'public'
            ");
            
            $tablesResult = $stmt->executeQuery();
            $tables = $tablesResult->fetchAllAssociative();

            foreach ($tables as $table) {
                $tableName = $table['tablename'];
                
                try {
                    // VACUUM ANALYZE для каждой таблицы
                    $connection->executeStatement("VACUUM ANALYZE {$tableName}");
                    $results[$tableName] = 'optimized';
                    
                } catch (\Exception $e) {
                    $results[$tableName] = 'error: ' . $e->getMessage();
                    $this->logger->warning("Failed to optimize table {$tableName}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Table optimization failed: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Создание недостающих индексов для часто используемых запросов
     */
    public function createOptimalIndexes(): array
    {
        $connection = $this->entityManager->getConnection();
        $results = [];

        $indexes = [
            // Индексы для задач
            'idx_task_user_status' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_user_status ON task (user_id, status)',
            'idx_task_assigned_status' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_assigned_status ON task (assigned_user_id, status)',
            'idx_task_deadline' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_deadline ON task (deadline) WHERE deadline IS NOT NULL',
            'idx_task_priority_status' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_priority_status ON task (priority, status)',
            'idx_task_created_at' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_created_at ON task (created_at DESC)',
            
            // Индексы для комментариев
            'idx_comment_task_created' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_comment_task_created ON comment (task_id, created_at DESC)',
            
            // Индексы для уведомлений
            'idx_notification_user_read' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notification_user_read ON notification (user_id, is_read, created_at DESC)',
            
            // Индексы для активности
            'idx_activity_user_created' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_user_created ON activity_log (user_id, created_at DESC)',
        ];

        foreach ($indexes as $indexName => $sql) {
            try {
                $connection->executeStatement($sql);
                $results[$indexName] = 'created';
                $this->logger->info("Created index: {$indexName}");
                
            } catch (\Exception $e) {
                $results[$indexName] = 'error: ' . $e->getMessage();
                $this->logger->warning("Failed to create index {$indexName}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Очистка старых данных
     */
    public function cleanupOldData(int $daysToKeep = 365): array
    {
        $connection = $this->entityManager->getConnection();
        $results = [];
        $cutoffDate = new \DateTime("-{$daysToKeep} days");

        try {
            // Очистка старых логов активности
            $stmt = $connection->prepare("
                DELETE FROM activity_log 
                WHERE created_at < :cutoff_date
            ");
            $stmt->bindValue('cutoff_date', $cutoffDate->format('Y-m-d H:i:s'));
            $deletedLogs = $stmt->executeStatement();
            $results['activity_logs_deleted'] = $deletedLogs;

            // Очистка прочитанных уведомлений старше 90 дней
            $notificationCutoff = new \DateTime('-90 days');
            $stmt = $connection->prepare("
                DELETE FROM notification 
                WHERE is_read = true AND created_at < :cutoff_date
            ");
            $stmt->bindValue('cutoff_date', $notificationCutoff->format('Y-m-d H:i:s'));
            $deletedNotifications = $stmt->executeStatement();
            $results['notifications_deleted'] = $deletedNotifications;

            $this->logger->info("Cleanup completed: {$deletedLogs} logs, {$deletedNotifications} notifications deleted");

        } catch (\Exception $e) {
            $this->logger->error('Data cleanup failed: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Полная оптимизация базы данных
     */
    public function performFullOptimization(): array
    {
        $results = [
            'analysis' => $this->analyzeQueryPerformance(),
            'table_optimization' => $this->optimizeTables(),
            'index_creation' => $this->createOptimalIndexes(),
            'data_cleanup' => $this->cleanupOldData(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logger->info('Full database optimization completed');
        return $results;
    }
}