<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for monitoring and analyzing application performance
 */
class PerformanceMonitorService
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Monitor query performance for task-related operations
     */
    public function monitorTaskQueryPerformance(array $filters = []): array
    {
        $startTime = microtime(true);

        // Use the newly created indexes to optimize query performance
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id) as task_count')
           ->from('App\Entity\Task', 't');

        if (!empty($filters['user_id'])) {
            $qb->andWhere('t.user = :user_id OR t.assignedUser = :user_id')
               ->setParameter('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('t.dueDate >= :date_from')
               ->setParameter('date_from', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('t.dueDate <= :date_to')
               ->setParameter('date_to', $filters['date_to']);
        }

        $taskCount = $qb->getQuery()->getSingleScalarResult();

        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $this->logger->info('Task query performance monitored', [
            'execution_time_ms' => round($executionTime, 2),
            'task_count' => $taskCount,
            'filters' => $filters
        ]);

        return [
            'execution_time_ms' => round($executionTime, 2),
            'task_count' => $taskCount,
            'filters_applied' => count($filters),
            'timestamp' => new \DateTime()
        ];
    }

    /**
     * Get performance metrics for task search operations
     */
    public function getSearchPerformanceMetrics(): array
    {
        $metrics = [];

        // Measure performance with new indexes
        $startTime = microtime(true);
        $stmt = $this->connection->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE title LIKE ? 
            AND status = ?
        ");
        $result = $stmt->executeQuery(['%test%', 'pending']);
        $count = $result->fetchOne();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $metrics['title_status_search'] = [
            'execution_time_ms' => round($executionTime, 2),
            'result_count' => $count
        ];

        // Measure performance with composite index
        $startTime = microtime(true);
        $stmt = $this->connection->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE user_id = ? 
            AND status = ? 
            AND priority = ? 
            AND due_date >= ?
        ");
        $result = $stmt->executeQuery([1, 'pending', 'high', date('Y-m-d')]);
        $count = $result->fetchOne();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $metrics['complex_search'] = [
            'execution_time_ms' => round($executionTime, 2),
            'result_count' => $count
        ];

        $this->logger->info('Search performance metrics collected', $metrics);

        return $metrics;
    }

    /**
     * Monitor index effectiveness
     */
    public function getIndexEffectivenessReport(): array
    {
        $report = [];

        // Check if our new indexes are being used
        try {
            $stmt = $this->connection->prepare("
                EXPLAIN QUERY PLAN 
                SELECT COUNT(*) 
                FROM tasks 
                WHERE title LIKE ? 
                AND user_id = ?
                AND status = ?
            ");
            $result = $stmt->executeQuery(['%test%', 1, 'pending']);
            $plan = $result->fetchAllAssociative();

            $usesIndex = false;
            foreach ($plan as $row) {
                if (strpos(strtolower($row['detail'] ?? $row['QUERY PLAN'] ?? ''), 'idx_tasks_title') !== false ||
                    strpos(strtolower($row['detail'] ?? $row['QUERY PLAN'] ?? ''), 'idx_tasks_user_assigned_status') !== false) {
                    $usesIndex = true;
                    break;
                }
            }

            $report['index_utilization'] = [
                'query_uses_optimized_indexes' => $usesIndex,
                'indexes_available' => [
                    'idx_tasks_title',
                    'idx_tasks_description', 
                    'idx_tasks_user_assigned_status',
                    'idx_tasks_priority_due_date',
                    'idx_tasks_complex_search'
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Could not analyze index effectiveness: ' . $e->getMessage());
            $report['index_utilization'] = [
                'query_uses_optimized_indexes' => null,
                'error' => $e->getMessage()
            ];
        }

        return $report;
    }
}