<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for monitoring API performance
 */
class ApiPerformanceMonitorService
{
    private LoggerInterface $logger;
    private Connection $connection;
    private ContainerInterface $container;
    private array $metrics;
    private array $timings;

    public function __construct(
        LoggerInterface $logger,
        Connection $connection,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->container = $container;
        $this->metrics = [];
        $this->timings = [];
    }

    /**
     * Start monitoring a request
     */
    public function startMonitoring(Request $request): string
    {
        $requestId = uniqid('api_monitor_', true);
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->timings[$requestId] = [
            'start_time' => $startTime,
            'start_memory' => $startMemory,
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'request_route' => $request->attributes->get('_route', 'unknown'),
            'request_ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent', ''),
        ];
        
        $this->logger->info('API request monitoring started', [
            'request_id' => $requestId,
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod()
        ]);
        
        return $requestId;
    }

    /**
     * Stop monitoring a request and collect metrics
     */
    public function stopMonitoring(string $requestId, Response $response): ?array
    {
        if (!isset($this->timings[$requestId])) {
            return null;
        }
        
        $timing = $this->timings[$requestId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metric = [
            'request_id' => $requestId,
            'method' => $timing['request_method'],
            'uri' => $timing['request_uri'],
            'route' => $timing['request_route'],
            'ip_address' => $timing['request_ip'],
            'user_agent' => $timing['user_agent'],
            'response_status' => $response->getStatusCode(),
            'execution_time_ms' => round(($endTime - $timing['start_time']) * 1000, 2),
            'memory_used_bytes' => $endMemory - $timing['start_memory'],
            'memory_used_formatted' => $this->formatBytes($endMemory - $timing['start_memory']),
            'peak_memory_formatted' => $this->formatBytes($endMemory),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        // Store the metric
        $this->metrics[] = $metric;
        
        // Log if response time is slow
        if ($metric['execution_time_ms'] > 500) { // More than 500ms
            $this->logger->warning('Slow API request detected', [
                'request_id' => $requestId,
                'uri' => $timing['request_uri'],
                'execution_time_ms' => $metric['execution_time_ms'],
                'status_code' => $response->getStatusCode()
            ]);
        }
        
        // Log if memory usage is high
        if (($endMemory - $timing['start_memory']) > 10 * 1024 * 1024) { // More than 10MB
            $this->logger->warning('High memory usage in API request', [
                'request_id' => $requestId,
                'uri' => $timing['request_uri'],
                'memory_used' => $this->formatBytes($endMemory - $timing['start_memory'])
            ]);
        }
        
        unset($this->timings[$requestId]);
        
        return $metric;
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(array $filters = []): array
    {
        $filteredMetrics = $this->metrics;
        
        // Apply filters
        if (isset($filters['min_execution_time'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['execution_time_ms'] >= $filters['min_execution_time'];
            });
        }
        
        if (isset($filters['max_execution_time'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['execution_time_ms'] <= $filters['max_execution_time'];
            });
        }
        
        if (isset($filters['status_code'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['response_status'] == $filters['status_code'];
            });
        }
        
        if (isset($filters['route'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['route'] == $filters['route'];
            });
        }
        
        if (isset($filters['date_from'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['timestamp'] >= $filters['date_from'];
            });
        }
        
        if (isset($filters['date_to'])) {
            $filteredMetrics = array_filter($filteredMetrics, function($metric) use ($filters) {
                return $metric['timestamp'] <= $filters['date_to'];
            });
        }
        
        return array_values($filteredMetrics);
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(array $filters = []): array
    {
        $metrics = $this->getMetrics($filters);
        
        if (empty($metrics)) {
            return [
                'total_requests' => 0,
                'average_response_time_ms' => 0,
                'min_response_time_ms' => 0,
                'max_response_time_ms' => 0,
                'average_memory_used_bytes' => 0,
                'total_errors' => 0,
                'error_rate_percent' => 0,
                'top_slow_endpoints' => [],
                'requests_per_minute' => 0
            ];
        }
        
        $totalRequests = count($metrics);
        $responseTimes = array_column($metrics, 'execution_time_ms');
        $memoryUsage = array_column($metrics, 'memory_used_bytes');
        $errors = array_filter($metrics, function($metric) {
            return $metric['response_status'] >= 400;
        });
        
        // Calculate top slow endpoints
        $endpointTimes = [];
        foreach ($metrics as $metric) {
            $endpoint = $metric['route'];
            if (!isset($endpointTimes[$endpoint])) {
                $endpointTimes[$endpoint] = ['times' => [], 'count' => 0];
            }
            $endpointTimes[$endpoint]['times'][] = $metric['execution_time_ms'];
            $endpointTimes[$endpoint]['count']++;
        }
        
        $avgEndpointTimes = [];
        foreach ($endpointTimes as $endpoint => $data) {
            $avgEndpointTimes[$endpoint] = [
                'average_time' => array_sum($data['times']) / count($data['times']),
                'count' => $data['count']
            ];
        }
        
        arsort($avgEndpointTimes);
        $topSlowEndpoints = array_slice($avgEndpointTimes, 0, 5, true);
        
        return [
            'total_requests' => $totalRequests,
            'average_response_time_ms' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'min_response_time_ms' => round(min($responseTimes), 2),
            'max_response_time_ms' => round(max($responseTimes), 2),
            'average_memory_used_bytes' => round(array_sum($memoryUsage) / count($memoryUsage)),
            'total_errors' => count($errors),
            'error_rate_percent' => round((count($errors) / $totalRequests) * 100, 2),
            'top_slow_endpoints' => $topSlowEndpoints,
            'requests_per_minute' => $this->calculateRequestsPerMinute($metrics)
        ];
    }

    /**
     * Calculate requests per minute
     */
    private function calculateRequestsPerMinute(array $metrics): float
    {
        if (empty($metrics)) {
            return 0;
        }
        
        // Find the time range
        $timestamps = array_column($metrics, 'timestamp');
        sort($timestamps);
        
        $firstTime = strtotime($timestamps[0]);
        $lastTime = strtotime(end($timestamps));
        
        if ($lastTime <= $firstTime) {
            return count($metrics); // If all requests happened at the same time
        }
        
        $minutesDiff = ($lastTime - $firstTime) / 60;
        if ($minutesDiff <= 0) {
            return count($metrics);
        }
        
        return round(count($metrics) / $minutesDiff, 2);
    }

    /**
     * Clear collected metrics
     */
    public function clearMetrics(): void
    {
        $this->metrics = [];
        $this->timings = [];
        
        $this->logger->info('API performance metrics cleared');
    }

    /**
     * Get slowest requests
     */
    public function getSlowestRequests(int $limit = 10): array
    {
        usort($this->metrics, function($a, $b) {
            return $b['execution_time_ms'] <=> $a['execution_time_ms'];
        });
        
        return array_slice($this->metrics, 0, $limit);
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
     * Identify potential bottlenecks
     */
    public function identifyBottlenecks(): array
    {
        $bottlenecks = [];
        
        // Check for endpoints with consistently slow response times
        $slowEndpoints = array_filter($this->getPerformanceSummary()['top_slow_endpoints'], function($data) {
            return $data['average_time'] > 500; // More than 500ms average
        });
        
        if (!empty($slowEndpoints)) {
            $bottlenecks['slow_endpoints'] = [
                'description' => 'Endpoints with high average response time (>500ms)',
                'endpoints' => $slowEndpoints
            ];
        }
        
        // Check for endpoints with high error rates
        $allMetrics = $this->getMetrics();
        $endpointErrors = [];
        
        foreach ($allMetrics as $metric) {
            $endpoint = $metric['route'];
            if (!isset($endpointErrors[$endpoint])) {
                $endpointErrors[$endpoint] = ['total' => 0, 'errors' => 0];
            }
            $endpointErrors[$endpoint]['total']++;
            if ($metric['response_status'] >= 500) {
                $endpointErrors[$endpoint]['errors']++;
            }
        }
        
        $highErrorEndpoints = [];
        foreach ($endpointErrors as $endpoint => $data) {
            if ($data['total'] > 5) { // Only consider endpoints with more than 5 requests
                $errorRate = ($data['errors'] / $data['total']) * 100;
                if ($errorRate > 10) { // More than 10% error rate
                    $highErrorEndpoints[$endpoint] = [
                        'error_rate_percent' => round($errorRate, 2),
                        'total_requests' => $data['total'],
                        'error_count' => $data['errors']
                    ];
                }
            }
        }
        
        if (!empty($highErrorEndpoints)) {
            $bottlenecks['high_error_endpoints'] = [
                'description' => 'Endpoints with high error rates (>10%)',
                'endpoints' => $highErrorEndpoints
            ];
        }
        
        return $bottlenecks;
    }
}
