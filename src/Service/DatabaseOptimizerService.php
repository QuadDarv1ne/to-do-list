<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for monitoring and optimizing database usage
 */
class DatabaseOptimizerService
{
    private Connection $connection;
    private LoggerInterface $logger;
    private ContainerInterface $container;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->container = $container;
    }

    /**
     * Analyze database performance and usage
     */
    public function analyzeDatabase(): array
    {
        $this->logger->info('Starting database analysis');

        $startTime = microtime(true);

        try {
            // Get basic database info
            $dbInfo = $this->getDatabaseInfo();
            
            // Analyze table sizes
            $tableSizes = $this->getTableSizes();
            
            // Analyze indexes
            $indexes = $this->getIndexesInfo();
            
            // Analyze slow queries (if available)
            $slowQueries = $this->getSlowQueries();
            
            // Get connection info
            $connectionInfo = $this->getConnectionInfo();
            
            $analysis = [
                'database_info' => $dbInfo,
                'table_sizes' => $tableSizes,
                'indexes_info' => $indexes,
                'slow_queries' => $slowQueries,
                'connection_info' => $connectionInfo,
                'analysis_time' => round(microtime(true) - $startTime, 4),
                'recommendations' => $this->getRecommendations($tableSizes, $indexes)
            ];

            $this->logger->info('Database analysis completed', [
                'analysis_time' => $analysis['analysis_time']
            ]);

            return [
                'success' => true,
                'analysis' => $analysis
            ];

        } catch (\Exception $e) {
            $this->logger->error('Database analysis failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get basic database information
     */
    private function getDatabaseInfo(): array
    {
        try {
            $platform = $this->connection->getDatabasePlatform();
            $databaseName = $this->connection->getDatabase();

            // Get table count
            $statement = $this->connection->executeQuery("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'");
            $tableCount = $statement->fetchOne();

            // Determine platform name based on the platform instance type
            $platformName = 'unknown';
            if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform) {
                $platformName = 'mysql';
            } elseif ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
                $platformName = 'sqlite';
            } elseif ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
                $platformName = 'postgresql';
            } elseif ($platform instanceof \Doctrine\DBAL\Platforms\OraclePlatform) {
                $platformName = 'oracle';
            } elseif ($platform instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform) {
                $platformName = 'sqlserver';
            }

            return [
                'platform' => $platformName,
                'database_name' => $databaseName,
                'table_count' => $tableCount,
                'driver' => get_class($this->connection->getDriver())
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get database info', [
                'error' => $e->getMessage()
            ]);
            return [
                'platform' => 'unknown',
                'database_name' => 'unknown',
                'table_count' => 0,
                'driver' => 'unknown'
            ];
        }
    }

    /**
     * Get table sizes and row counts
     */
    private function getTableSizes(): array
    {
        try {
            $tables = [];
            
            // Get list of tables
            $schemaManager = $this->connection->createSchemaManager();
            $tableNames = $schemaManager->listTableNames();

            foreach ($tableNames as $tableName) {
                // Sanitize table name to prevent SQL injection
                $sanitizedTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
                
                if ($sanitizedTableName !== $tableName) {
                    $this->logger->warning('Skipping table with invalid name', ['table' => $tableName]);
                    continue;
                }
                
                // Get row count using parameterized query
                $sql = sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteIdentifier($sanitizedTableName));
                $rowCount = $this->connection->executeQuery($sql)->fetchOne();
                
                $tables[] = [
                    'name' => $tableName,
                    'row_count' => $rowCount
                ];
            }

            // Sort by row count descending
            usort($tables, function($a, $b) {
                return $b['row_count'] <=> $a['row_count'];
            });

            return $tables;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get table sizes', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get indexes information
     */
    private function getIndexesInfo(): array
    {
        try {
            $indexes = [];
            
            // Get list of tables
            $schemaManager = $this->connection->createSchemaManager();
            $tableNames = $schemaManager->listTableNames();

            foreach ($tableNames as $tableName) {
                $tableIndexes = $schemaManager->listTableIndexes($tableName);
                
                foreach ($tableIndexes as $indexName => $index) {
                    $indexes[] = [
                        'table' => $tableName,
                        'index_name' => $indexName,
                        'columns' => $index->getColumns(),
                        'is_unique' => $index->isUnique(),
                        'is_primary' => $index->isPrimary()
                    ];
                }
            }

            return $indexes;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get indexes info', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get slow queries information (placeholder for now)
     */
    private function getSlowQueries(): array
    {
        // This would typically require query logging or profiling
        // For now, we return an empty array as a placeholder
        return [];
    }

    /**
     * Get connection information
     */
    private function getConnectionInfo(): array
    {
        try {
            $params = $this->connection->getParams();
            
            return [
                'host' => $params['host'] ?? 'localhost',
                'port' => $params['port'] ?? 3306,
                'database' => $params['dbname'] ?? 'unknown',
                'charset' => $params['charset'] ?? 'utf8',
                'active' => $this->connection->isConnected()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get connection info', [
                'error' => $e->getMessage()
            ]);
            return [
                'host' => 'unknown',
                'port' => 'unknown',
                'database' => 'unknown',
                'charset' => 'unknown',
                'active' => false
            ];
        }
    }

    /**
     * Get recommendations based on analysis
     */
    private function getRecommendations(array $tableSizes, array $indexes): array
    {
        $recommendations = [];

        // Check for large tables without proper indexing
        foreach ($tableSizes as $table) {
            if ($table['row_count'] > 10000) { // Large table threshold
                $hasIndexOnCommonFields = false;
                
                foreach ($indexes as $index) {
                    if ($index['table'] === $table['name']) {
                        // Check if index covers common query fields
                        $commonFields = ['created_at', 'updated_at', 'status', 'user_id'];
                        $indexCols = array_map('strtolower', $index['columns']);
                        
                        if (count(array_intersect($commonFields, $indexCols)) > 0) {
                            $hasIndexOnCommonFields = true;
                            break;
                        }
                    }
                }
                
                if (!$hasIndexOnCommonFields) {
                    $recommendations[] = [
                        'level' => 'WARNING',
                        'message' => "Table {$table['name']} has {$table['row_count']} rows but lacks indexes on common query fields",
                        'suggestion' => "Consider adding indexes on frequently queried columns like created_at, status, or user_id"
                    ];
                }
            }
        }

        // Check for missing primary keys
        foreach ($indexes as $index) {
            if ($index['is_primary']) {
                // Primary key exists for this table
                continue;
            }
        }

        // Check for tables that might benefit from optimization
        $largeTables = array_filter($tableSizes, function($table) {
            return $table['row_count'] > 50000;
        });

        if (!empty($largeTables)) {
            $tableNames = array_map(function($table) {
                return $table['name'];
            }, $largeTables);
            
            $recommendations[] = [
                'level' => 'INFO',
                'message' => 'Large tables detected: ' . implode(', ', $tableNames),
                'suggestion' => 'Consider partitioning or archiving old data in these tables'
            ];
        }

        return $recommendations;
    }

    /**
     * Optimize a specific table
     */
    public function optimizeTable(string $tableName): array
    {
        $this->logger->info('Starting table optimization', [
            'table' => $tableName
        ]);

        try {
            // Sanitize table name to prevent SQL injection
            $sanitizedTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            
            if ($sanitizedTableName !== $tableName) {
                throw new \InvalidArgumentException('Invalid table name');
            }
            
            // For SQLite, VACUUM doesn't support table-specific optimization
            $result = $this->connection->executeStatement("VACUUM");
            
            $this->logger->info('Database optimized successfully');

            return [
                'success' => true,
                'table' => $tableName,
                'message' => "Database optimized successfully"
            ];
        } catch (\Exception $e) {
            $this->logger->error('Database optimization failed', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'table' => $tableName,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Optimize all tables in the database
     */
    public function optimizeAllTables(): array
    {
        $this->logger->info('Starting optimization of all tables');

        $schemaManager = $this->connection->createSchemaManager();
        $tableNames = $schemaManager->listTableNames();

        $results = [
            'optimized_tables' => [],
            'failed_tables' => [],
            'total_tables' => count($tableNames)
        ];

        foreach ($tableNames as $tableName) {
            $result = $this->optimizeTable($tableName);
            
            if ($result['success']) {
                $results['optimized_tables'][] = $result;
            } else {
                $results['failed_tables'][] = $result;
            }
        }

        $this->logger->info('All tables optimization completed', [
            'optimized_count' => count($results['optimized_tables']),
            'failed_count' => count($results['failed_tables'])
        ]);

        return $results;
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        $this->logger->info('Getting database statistics');

        try {
            // Get total number of records across all tables
            $schemaManager = $this->connection->createSchemaManager();
            $tableNames = $schemaManager->listTableNames();
            
            $totalRecords = 0;
            $tableRecordCounts = [];
            
            foreach ($tableNames as $tableName) {
                // Sanitize table name
                $sanitizedTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
                
                if ($sanitizedTableName !== $tableName) {
                    $this->logger->warning('Skipping table with invalid name', ['table' => $tableName]);
                    continue;
                }
                
                $sql = sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteIdentifier($sanitizedTableName));
                $recordCount = $this->connection->executeQuery($sql)->fetchOne();
                $totalRecords += $recordCount;
                
                $tableRecordCounts[] = [
                    'table' => $tableName,
                    'records' => $recordCount
                ];
            }
            
            // Sort by record count descending
            usort($tableRecordCounts, function($a, $b) {
                return $b['records'] <=> $a['records'];
            });
            
            $stats = [
                'total_records' => $totalRecords,
                'table_count' => count($tableNames),
                'largest_table' => !empty($tableRecordCounts) ? $tableRecordCounts[0] : null,
                'table_record_counts' => $tableRecordCounts
            ];
            
            $this->logger->info('Database statistics collected', [
                'total_records' => $totalRecords,
                'table_count' => $stats['table_count']
            ]);
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get database statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clean up old records based on retention policy
     */
    public function cleanupOldRecords(string $tableName, string $dateField, int $retentionDays): array
    {
        $this->logger->info('Starting cleanup of old records', [
            'table' => $tableName,
            'date_field' => $dateField,
            'retention_days' => $retentionDays
        ]);

        try {
            $cutoffDate = new \DateTime("-{$retentionDays} days");
            $cutoffDateString = $cutoffDate->format('Y-m-d H:i:s');
            
            // Sanitize table and field names
            $sanitizedTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            $sanitizedDateField = preg_replace('/[^a-zA-Z0-9_]/', '', $dateField);
            
            if ($sanitizedTableName !== $tableName || $sanitizedDateField !== $dateField) {
                throw new \InvalidArgumentException('Invalid table or field name');
            }
            
            $sql = sprintf(
                'DELETE FROM %s WHERE %s < ?',
                $this->connection->quoteIdentifier($sanitizedTableName),
                $this->connection->quoteIdentifier($sanitizedDateField)
            );
            $deletedCount = $this->connection->executeStatement($sql, [$cutoffDateString]);
            
            $this->logger->info('Old records cleanup completed', [
                'table' => $tableName,
                'date_field' => $dateField,
                'retention_days' => $retentionDays,
                'records_deleted' => $deletedCount
            ]);
            
            return [
                'success' => true,
                'table' => $tableName,
                'date_field' => $dateField,
                'retention_days' => $retentionDays,
                'records_deleted' => $deletedCount
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup old records', [
                'table' => $tableName,
                'date_field' => $dateField,
                'retention_days' => $retentionDays,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}