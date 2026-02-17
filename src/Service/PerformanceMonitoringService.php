<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for monitoring application performance and collecting metrics
 */
class PerformanceMonitoringService
{
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;
    private array $metrics = [];
    private array $timers = [];

    public function __construct(
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Start timing an operation
     */
    public function startTiming(string $operation): void
    {
        $startTime = microtime(true);
        $memoryStart = memory_get_usage();

        $this->timers[$operation] = [
            'start_time' => $startTime,
            'memory_start' => $memoryStart,
            'start_peak_memory' => memory_get_peak_usage()
        ];

        $this->logger->debug("Started timing operation: {$operation}");
    }

    /**
     * Stop timing an operation and record metrics
     */
    public function stopTiming(string $operation): array
    {
        if (!isset($this->timers[$operation])) {
            $this->logger->warning("No timer found for operation: {$operation}");
            return [];
        }

        $timer = $this->timers[$operation];
        $endTime = microtime(true);
        $memoryEnd = memory_get_usage();

        $executionTime = ($endTime - $timer['start_time']) * 1000; // Convert to milliseconds
        $memoryUsed = $memoryEnd - $timer['memory_start'];
        $peakMemoryUsed = memory_get_peak_usage() - $timer['start_peak_memory'];

        $metrics = [
            'operation' => $operation,
            'execution_time_ms' => round($executionTime, 2),
            'memory_used_bytes' => $memoryUsed,
            'peak_memory_used_bytes' => $peakMemoryUsed,
            'timestamp' => microtime(true)
        ];

        // Store metrics for reporting
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = [];
        }
        $this->metrics[$operation][] = $metrics;

        // Log performance data
        $this->logger->info("Operation performance", $metrics);

        // Clean up timer
        unset($this->timers[$operation]);

        return $metrics;
    }

    /**
     * Record custom metric
     */
    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $metric = [
            'name' => $metricName,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];

        if (!isset($this->metrics[$metricName])) {
            $this->metrics[$metricName] = [];
        }
        $this->metrics[$metricName][] = $metric;

        $this->logger->info("Recorded metric: {$metricName}", $metric);
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport(): array
    {
        $report = [
            'summary' => $this->getSummaryMetrics(),
            'detailed_metrics' => $this->getDetailedMetrics(),
            'recommendations' => $this->getRecommendations(),
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->parameterBag->get('kernel.environment')
        ];

        return $report;
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(): array
    {
        $summary = [
            'total_operations_timed' => 0,
            'total_memory_used_bytes' => 0,
            'average_execution_time_ms' => 0,
            'slowest_operation_ms' => 0,
            'fastest_operation_ms' => PHP_FLOAT_MAX,
            'operations_count' => []
        ];

        $totalExecutionTime = 0;
        $operationCount = 0;

        foreach ($this->metrics as $operation => $operationMetrics) {
            if (is_numeric($operation)) continue; // Skip non-operation entries

            $count = count($operationMetrics);
            $summary['operations_count'][$operation] = $count;
            $summary['total_operations_timed'] += $count;

            foreach ($operationMetrics as $metric) {
                if (isset($metric['execution_time_ms'])) {
                    $executionTime = $metric['execution_time_ms'];
                    $totalExecutionTime += $executionTime;
                    $operationCount++;

                    if ($executionTime > $summary['slowest_operation_ms']) {
                        $summary['slowest_operation_ms'] = $executionTime;
                        $summary['slowest_operation_name'] = $operation;
                    }

                    if ($executionTime < $summary['fastest_operation_ms']) {
                        $summary['fastest_operation_ms'] = $executionTime;
                        $summary['fastest_operation_name'] = $operation;
                    }
                }

                if (isset($metric['memory_used_bytes'])) {
                    $summary['total_memory_used_bytes'] += $metric['memory_used_bytes'];
                }
            }
        }

        if ($operationCount > 0) {
            $summary['average_execution_time_ms'] = round($totalExecutionTime / $operationCount, 2);
        } else {
            $summary['average_execution_time_ms'] = 0;
            $summary['fastest_operation_ms'] = 0;
        }

        return $summary;
    }

    /**
     * Get detailed metrics
     */
    private function getDetailedMetrics(): array
    {
        $detailedMetrics = [];

        foreach ($this->metrics as $operation => $operationMetrics) {
            if (is_numeric($operation)) continue; // Skip non-operation entries

            $executionTimes = array_filter(
                array_column($operationMetrics, 'execution_time_ms'),
                fn($time) => $time !== null
            );

            if (empty($executionTimes)) {
                continue;
            }

            $detailedMetrics[$operation] = [
                'count' => count($executionTimes),
                'avg_execution_time_ms' => round(array_sum($executionTimes) / count($executionTimes), 2),
                'min_execution_time_ms' => round(min($executionTimes), 2),
                'max_execution_time_ms' => round(max($executionTimes), 2),
                'total_execution_time_ms' => round(array_sum($executionTimes), 2),
                'last_recorded' => end($operationMetrics)['timestamp'] ?? null
            ];
        }

        return $detailedMetrics;
    }

    /**
     * Get performance recommendations
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        $detailedMetrics = $this->getDetailedMetrics();

        foreach ($detailedMetrics as $operation => $metrics) {
            // Recommend optimization for operations taking more than 500ms on average
            if ($metrics['avg_execution_time_ms'] > 500) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'message' => "Operation '{$operation}' has high average execution time ({$metrics['avg_execution_time_ms']}ms). Consider optimization.",
                    'operation' => $operation,
                    'current_avg_ms' => $metrics['avg_execution_time_ms']
                ];
            }

            // Recommend optimization for operations taking more than 100ms on average
            if ($metrics['avg_execution_time_ms'] > 100 && $metrics['avg_execution_time_ms'] <= 500) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => "Operation '{$operation}' has moderate average execution time ({$metrics['avg_execution_time_ms']}ms). Consider review.",
                    'operation' => $operation,
                    'current_avg_ms' => $metrics['avg_execution_time_ms']
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get metrics for a specific operation
     */
    public function getOperationMetrics(string $operation): array
    {
        return $this->metrics[$operation] ?? [];
    }

    /**
     * Clear metrics for a specific operation or all metrics
     */
    public function clearMetrics(?string $operation = null): void
    {
        if ($operation) {
            unset($this->metrics[$operation]);
            $this->logger->info("Cleared metrics for operation: {$operation}");
        } else {
            $this->metrics = [];
            $this->logger->info("Cleared all performance metrics");
        }
    }

    /**
     * Get slow operations (taking more than threshold milliseconds)
     */
    public function getSlowOperations(float $thresholdMs = 100): array
    {
        $slowOperations = [];

        foreach ($this->metrics as $operation => $operationMetrics) {
            if (is_numeric($operation)) continue;

            foreach ($operationMetrics as $metric) {
                if (isset($metric['execution_time_ms']) && $metric['execution_time_ms'] > $thresholdMs) {
                    $slowOperations[] = $metric;
                }
            }
        }

        // Sort by execution time descending
        usort($slowOperations, function ($a, $b) {
            return $b['execution_time_ms'] <=> $a['execution_time_ms'];
        });

        return $slowOperations;
    }

    /**
     * Get memory usage statistics
     */
    public function getMemoryStats(): array
    {
        return [
            'current_memory_usage' => memory_get_usage(),
            'peak_memory_usage' => memory_get_peak_usage(),
            'current_formatted' => $this->formatBytes(memory_get_usage()),
            'peak_formatted' => $this->formatBytes(memory_get_peak_usage())
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
