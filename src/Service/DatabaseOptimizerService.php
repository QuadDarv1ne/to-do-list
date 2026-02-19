<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Database Optimization Service
 * 
 * Оптимизация запросов, индексы, анализ производительности БД
 */
class DatabaseOptimizerService
{
    private array $queryLog = [];
    private array $optimizationSuggestions = [];
    private bool $debugMode = false;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
        $this->debugMode = $_ENV['APP_DEBUG'] ?? false;
    }

    /**
     * Анализ и оптимизация таблицы
     */
    public function analyzeTable(string $tableName): array
    {
        $connection = $this->em->getConnection();
        
        try {
            // Получаем статистику таблицы
            $stats = $this->getTableStats($tableName, $connection);
            
            // Проверяем индексы
            $indexes = $this->analyzeIndexes($tableName, $connection);
            
            // Проверяем размер таблицы
            $size = $this->getTableSize($tableName, $connection);
            
            // Получаем рекомендации
            $recommendations = $this->getRecommendations($stats, $indexes);

            return [
                'table' => $tableName,
                'stats' => $stats,
                'indexes' => $indexes,
                'size' => $size,
                'recommendations' => $recommendations,
                'optimized_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to analyze table', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Создание индекса для улучшения производительности
     */
    public function createIndex(
        string $tableName,
        string $indexName,
        array $columns,
        bool $unique = false
    ): bool {
        try {
            $connection = $this->em->getConnection();
            
            $sql = sprintf(
                'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
                $unique ? 'UNIQUE ' : '',
                $indexName,
                $tableName,
                implode(', ', $columns)
            );
            
            $connection->executeStatement($sql);
            
            $this->logger->info('Index created', [
                'table' => $tableName,
                'index' => $indexName,
                'columns' => $columns
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create index', [
                'table' => $tableName,
                'index' => $indexName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Оптимизация медленных запросов
     */
    public function optimizeQuery(string $query): array
    {
        $suggestions = [];
        $queryUpper = strtoupper($query);

        // Проверка на SELECT *
        if (strpos($queryUpper, 'SELECT *') !== false) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Избегайте SELECT *, указывайте конкретные колонки',
                'impact' => 'high'
            ];
        }

        // Проверка на отсутствие WHERE
        if (strpos($queryUpper, 'SELECT') !== false && 
            strpos($queryUpper, 'WHERE') === false &&
            strpos($queryUpper, 'JOIN') === false) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Запрос без WHERE может вернуть много данных',
                'impact' => 'medium'
            ];
        }

        // Проверка на LIKE с ведущим %
        if (preg_match("/LIKE\s+['\"]%/", $query)) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'LIKE с ведущим % не использует индексы',
                'impact' => 'high'
            ];
        }

        // Проверка на отсутствие LIMIT
        if (strpos($queryUpper, 'SELECT') !== false && 
            strpos($queryUpper, 'LIMIT') === false &&
            strpos($queryUpper, 'COUNT') === false) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Рассмотрите добавление LIMIT для ограничения результатов',
                'impact' => 'low'
            ];
        }

        // Проверка на подзапросы в WHERE
        if (preg_match("/WHERE\s+.*\s+IN\s*\(\s*SELECT/", $queryUpper)) {
            $suggestions[] = [
                'type' => 'suggestion',
                'message' => 'Рассмотрите замену подзапроса на JOIN',
                'impact' => 'medium'
            ];
        }

        return [
            'query' => $query,
            'suggestions' => $suggestions,
            'score' => max(0, 100 - count($suggestions) * 15)
        ];
    }

    /**
     * Массовое создание индексов для частых запросов
     */
    public function createRecommendedIndexes(): array
    {
        $indexes = [
            // Tasks table
            [
                'table' => 'tasks',
                'name' => 'idx_tasks_user_status',
                'columns' => ['user_id', 'status'],
                'unique' => false
            ],
            [
                'table' => 'tasks',
                'name' => 'idx_tasks_due_date',
                'columns' => ['due_date'],
                'unique' => false
            ],
            [
                'table' => 'tasks',
                'name' => 'idx_tasks_priority',
                'columns' => ['priority'],
                'unique' => false
            ],
            [
                'table' => 'tasks',
                'name' => 'idx_tasks_created_at',
                'columns' => ['created_at'],
                'unique' => false
            ],
            
            // Users table
            [
                'table' => 'users',
                'name' => 'idx_users_email',
                'columns' => ['email'],
                'unique' => true
            ],
            
            // Comments table
            [
                'table' => 'comments',
                'name' => 'idx_comments_task',
                'columns' => ['task_id'],
                'unique' => false
            ],
            
            // Activity logs
            [
                'table' => 'activity_logs',
                'name' => 'idx_activity_user',
                'columns' => ['user_id'],
                'unique' => false
            ],
            [
                'table' => 'activity_logs',
                'name' => 'idx_activity_created',
                'columns' => ['created_at'],
                'unique' => false
            ]
        ];

        $results = [];
        foreach ($indexes as $index) {
            $success = $this->createIndex(
                $index['table'],
                $index['name'],
                $index['columns'],
                $index['unique'] ?? false
            );
            
            $results[] = [
                'table' => $index['table'],
                'index' => $index['name'],
                'success' => $success
            ];
        }

        return $results;
    }

    /**
     * Очистка старых данных
     */
    public function cleanupOldData(
        string $tableName,
        string $dateColumn,
        int $daysToKeep
    ): int {
        try {
            $connection = $this->em->getConnection();
            
            $cutoffDate = (new \DateTime())
                ->modify("-{$daysToKeep} days")
                ->format('Y-m-d H:i:s');
            
            $sql = sprintf(
                'DELETE FROM %s WHERE %s < :cutoff_date',
                $tableName,
                $dateColumn
            );
            
            $deleted = $connection->executeStatement($sql, [
                'cutoff_date' => $cutoffDate
            ]);
            
            $this->logger->info('Old data cleaned up', [
                'table' => $tableName,
                'deleted_rows' => $deleted,
                'cutoff_date' => $cutoffDate
            ]);
            
            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup old data', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Оптимизация таблицы (VACUUM для SQLite, OPTIMIZE для MySQL)
     */
    public function optimizeTableStorage(string $tableName): bool
    {
        try {
            $connection = $this->em->getConnection();
            $platform = $connection->getDatabasePlatform()->getName();
            
            switch ($platform) {
                case 'sqlite':
                    $connection->executeStatement('VACUUM ' . $tableName);
                    break;
                    
                case 'mysql':
                    $connection->executeStatement('OPTIMIZE TABLE ' . $tableName);
                    break;
                    
                case 'postgresql':
                    $connection->executeStatement('VACUUM ANALYZE ' . $tableName);
                    break;
            }
            
            $this->logger->info('Table storage optimized', ['table' => $tableName]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to optimize table storage', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Получение статистики таблицы
     */
    private function getTableStats(string $tableName, Connection $connection): array
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$tableName}";
            $count = $connection->fetchOne($sql);
            
            return [
                'row_count' => (int)$count,
                'last_analyzed' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Анализ индексов таблицы
     */
    private function analyzeIndexes(string $tableName, Connection $connection): array
    {
        try {
            $platform = $connection->getDatabasePlatform()->getName();
            
            switch ($platform) {
                case 'sqlite':
                    $sql = "PRAGMA index_list({$tableName})";
                    break;
                    
                case 'mysql':
                    $sql = "SHOW INDEX FROM {$tableName}";
                    break;
                    
                case 'postgresql':
                    $sql = "SELECT indexname, indexdef FROM pg_indexes WHERE tablename = '{$tableName}'";
                    break;
                    
                default:
                    return [];
            }
            
            return $connection->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Получение размера таблицы
     */
    private function getTableSize(string $tableName, Connection $connection): array
    {
        try {
            $platform = $connection->getDatabasePlatform()->getName();
            
            switch ($platform) {
                case 'sqlite':
                    // SQLite не предоставляет точный размер таблицы
                    return ['size' => 'N/A', 'note' => 'Use database file size'];
                    
                case 'mysql':
                    $sql = "SELECT 
                        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                        FROM information_schema.tables 
                        WHERE table_name = '{$tableName}'";
                    $size = $connection->fetchOne($sql);
                    return ['size_mb' => (float)$size];
                    
                case 'postgresql':
                    $sql = "SELECT pg_size_pretty(pg_total_relation_size('{$tableName}')) as size";
                    $size = $connection->fetchOne($sql);
                    return ['size' => $size];
                    
                default:
                    return [];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Получение рекомендаций на основе анализа
     */
    private function getRecommendations(array $stats, array $indexes): array
    {
        $recommendations = [];

        // Если нет индексов
        if (empty($indexes)) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => 'Отсутствуют индексы. Создайте индексы для часто используемых колонок.',
                'action' => 'create_indexes'
            ];
        }

        // Если много записей но мало индексов
        if (($stats['row_count'] ?? 0) > 10000 && count($indexes) < 3) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Большая таблица с малым количеством индексов',
                'action' => 'add_indexes'
            ];
        }

        return $recommendations;
    }

    /**
     * Логирование запроса для анализа
     */
    public function logQuery(string $query, float $executionTime): void
    {
        if (!$this->debugMode) {
            return;
        }

        $this->queryLog[] = [
            'query' => $query,
            'time' => $executionTime,
            'timestamp' => microtime(true)
        ];

        // Логгируем медленные запросы
        if ($executionTime > 1.0) {
            $this->logger->warning('Slow query detected', [
                'query' => substr($query, 0, 200),
                'time' => $executionTime . 's'
            ]);
        }
    }

    /**
     * Получение статистики запросов
     */
    public function getQueryStats(): array
    {
        if (empty($this->queryLog)) {
            return ['total_queries' => 0];
        }

        $times = array_column($this->queryLog, 'time');
        
        return [
            'total_queries' => count($this->queryLog),
            'avg_time' => round(array_sum($times) / count($times), 4),
            'max_time' => round(max($times), 4),
            'min_time' => round(min($times), 4),
            'slow_queries' => count(array_filter($times, fn($t) => $t > 1.0))
        ];
    }
}
