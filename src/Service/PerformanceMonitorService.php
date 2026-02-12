<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for monitoring application performance and collecting metrics
 */
class PerformanceMonitorService
{
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;
    private array $metrics = [];
    private array $slowQueries = [];

    public function __construct(LoggerInterface $logger, ParameterBagInterface $parameterBag)
    {
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
    }
    
    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }
    
    public function clearSlowQueries(): void
    {
        $this->slowQueries = [];
    }

    /**
     * Start measuring execution time for a specific operation
     */
    public function startTimer(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    /**
     * Stop measuring execution time and log the results
     */
    public function stopTimer(string $operation): array
    {
        if (!isset($this->metrics[$operation])) {
            $this->logger->warning("Timer for operation '{$operation}' was not started");
            return [];
        }

        $startData = $this->metrics[$operation];
        $executionTime = microtime(true) - $startData['start_time'];
        $memoryUsed = memory_get_usage(true) - $startData['memory_start'];

        $result = [
            'execution_time' => round($executionTime * 1000, 2), // in milliseconds
            'memory_used' => $this->formatBytes($memoryUsed),
            'memory_used_bytes' => $memoryUsed
        ];

        // Log performance metrics
        $this->logger->info("Performance metric for {$operation}", [
            'execution_time_ms' => $result['execution_time'],
            'memory_used_bytes' => $result['memory_used_bytes']
        ]);

        // Clean up timer
        unset($this->metrics[$operation]);

        return $result;
    }

    /**
     * Collect and return application performance metrics
     */
    public function collectMetrics(): array
    {
        $environment = $this->parameterBag->get('kernel.environment');
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        return [
            'environment' => $environment,
            'current_memory_usage' => $this->formatBytes($memoryUsage),
            'current_memory_usage_bytes' => $memoryUsage,
            'peak_memory_usage' => $this->formatBytes($peakMemory),
            'peak_memory_usage_bytes' => $peakMemory,
            'uptime' => $this->getApplicationUptime(),
            'server_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'os' => PHP_OS_FAMILY
            ]
        ];
    }

    /**
     * Get application uptime
     */
    private function getApplicationUptime(): string
    {
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? time();
        $uptimeSeconds = time() - intval($startTime);
        
        $hours = floor($uptimeSeconds / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);
        $seconds = $uptimeSeconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
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

    /**
     * Monitor query performance
     */
    public function monitorQuery(string $query, callable $callback, string $source = 'unknown')
    {
        $this->startTimer("query_{$source}");
        try {
            $result = $callback();
            $metrics = $this->stopTimer("query_{$source}");
            
            // Log slow queries (threshold: 100ms)
            if ($metrics['execution_time'] > 100) {
                $slowQueryData = [
                    'query' => $query,
                    'source' => $source,
                    'execution_time_ms' => $metrics['execution_time'],
                    'memory_used_bytes' => $metrics['memory_used_bytes'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
                ];
                
                $this->logger->warning("Slow query detected", $slowQueryData);
                
                // Store slow query for reporting
                $this->slowQueries[] = $slowQueryData;
                
                // Keep only the last 50 slow queries to prevent memory issues
                if (count($this->slowQueries) > 50) {
                    $this->slowQueries = array_slice($this->slowQueries, -50);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->stopTimer("query_{$source}");
            throw $e;
        }
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport(): array
    {
        $metrics = $this->collectMetrics();
        
        // Add cache hit ratio if available
        $cacheInfo = [];
        if (class_exists('\Symfony\Component\Cache\Adapter\AdapterInterface')) {
            // Placeholder for cache statistics if cache adapter supports it
            $cacheInfo = [
                'cache_enabled' => true
            ];
        }
        
        return array_merge($metrics, [
            'cache_info' => $cacheInfo,
            'slow_queries' => $this->getSlowQueries(),
            'collection_timestamp' => date('Y-m-d H:i:s'),
            'report_type' => 'performance'
        ]);
    }
    
    /**
     * Get detailed performance metrics including database and cache statistics
     */
    public function getDetailedMetrics(): array
    {
        $basicMetrics = $this->collectMetrics();
        
        // Add additional metrics
        $additionalMetrics = [
            'database_info' => $this->getDatabaseMetrics(),
            'cache_info' => $this->getCacheMetrics(),
            'request_info' => $this->getRequestMetrics(),
            'system_load' => $this->getSystemLoad()
        ];
        
        return array_merge($basicMetrics, $additionalMetrics);
    }
    
    private function getDatabaseMetrics(): array
    {
        // Return basic database metrics
        return [
            'slow_query_count' => count($this->slowQueries),
            'slow_query_threshold' => '100ms',
        ];
    }
    
    private function getCacheMetrics(): array
    {
        // Return basic cache metrics
        return [
            'enabled' => true,
            'adapter' => 'symfony_cache',
        ];
    }
    
    private function getRequestMetrics(): array
    {
        // Return request-related metrics
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'console',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'localhost',
        ];
    }
    
    private function getSystemLoad(): array
    {
        // Return system load information
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        
        return [
            'load_avg_1min' => $load[0] ?? 0,
            'load_avg_5min' => $load[1] ?? 0,
            'load_avg_15min' => $load[2] ?? 0,
        ];
    }
}