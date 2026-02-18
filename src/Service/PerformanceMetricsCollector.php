<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Performance Metrics Collector - сбор метрик производительности (Spotify-style)
 */
class PerformanceMetricsCollector
{
    private array $metrics = [];
    private array $timers = [];
    private float $requestStartTime;

    public function __construct(
        private LoggerInterface $logger,
        private CacheInterface $cache
    ) {
        $this->requestStartTime = microtime(true);
    }

    /**
     * Start timing operation
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    /**
     * Stop timing operation and record metric
     */
    public function stopTimer(string $name): float
    {
        if (!isset($this->timers[$name])) {
            return 0.0;
        }

        $duration = microtime(true) - $this->timers[$name]['start'];
        $memoryUsed = memory_get_usage(true) - $this->timers[$name]['memory_start'];

        $this->recordMetric($name, [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'timestamp' => time()
        ]);

        unset($this->timers[$name]);

        return $duration;
    }

    /**
     * Record custom metric
     */
    public function recordMetric(string $name, mixed $value): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = [];
        }

        $this->metrics[$name][] = $value;
    }

    /**
     * Increment counter metric
     */
    public function incrementCounter(string $name, int $value = 1): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = 0;
        }

        $this->metrics[$name] += $value;
    }

    /**
     * Get all collected metrics
     */
    public function getMetrics(): array
    {
        return [
            'request_duration' => microtime(true) - $this->requestStartTime,
            'memory_peak' => memory_get_peak_usage(true),
            'memory_current' => memory_get_usage(true),
            'custom_metrics' => $this->metrics,
            'timestamp' => time()
        ];
    }

    /**
     * Get metric statistics
     */
    public function getMetricStats(string $name): ?array
    {
        if (!isset($this->metrics[$name]) || empty($this->metrics[$name])) {
            return null;
        }

        $values = array_column($this->metrics[$name], 'duration');
        
        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);

        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / $count,
            'median' => $values[(int)($count / 2)],
            'p95' => $values[(int)($count * 0.95)],
            'p99' => $values[(int)($count * 0.99)]
        ];
    }

    /**
     * Store metrics for analysis
     */
    public function storeMetrics(string $endpoint): void
    {
        $metrics = $this->getMetrics();
        
        // Store in cache for last 1 hour
        $cacheKey = 'metrics_' . md5($endpoint) . '_' . date('YmdH');
        
        try {
            $existing = $this->cache->get($cacheKey, fn() => []);
            $existing[] = $metrics;
            
            // Keep only last 1000 entries
            if (count($existing) > 1000) {
                $existing = array_slice($existing, -1000);
            }
            
            $this->cache->set($cacheKey, $existing);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store metrics', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);
        }
    }

    /**
     * Get aggregated metrics for endpoint
     */
    public function getAggregatedMetrics(string $endpoint, int $hours = 1): array
    {
        $aggregated = [
            'total_requests' => 0,
            'avg_duration' => 0,
            'max_duration' => 0,
            'avg_memory' => 0,
            'max_memory' => 0
        ];

        $durations = [];
        $memories = [];

        for ($i = 0; $i < $hours; $i++) {
            $hour = date('YmdH', strtotime("-$i hours"));
            $cacheKey = 'metrics_' . md5($endpoint) . '_' . $hour;

            try {
                $metrics = $this->cache->get($cacheKey, fn() => []);
                
                foreach ($metrics as $metric) {
                    $aggregated['total_requests']++;
                    $durations[] = $metric['request_duration'];
                    $memories[] = $metric['memory_peak'];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!empty($durations)) {
            $aggregated['avg_duration'] = array_sum($durations) / count($durations);
            $aggregated['max_duration'] = max($durations);
            $aggregated['min_duration'] = min($durations);
        }

        if (!empty($memories)) {
            $aggregated['avg_memory'] = array_sum($memories) / count($memories);
            $aggregated['max_memory'] = max($memories);
        }

        return $aggregated;
    }

    /**
     * Log slow operations
     */
    public function logSlowOperation(string $operation, float $duration, float $threshold = 1.0): void
    {
        if ($duration > $threshold) {
            $this->logger->warning('Slow operation detected', [
                'operation' => $operation,
                'duration' => $duration,
                'threshold' => $threshold,
                'memory' => memory_get_usage(true)
            ]);
        }
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): array
    {
        $metrics = $this->getMetrics();
        
        return [
            'request_time' => round($metrics['request_duration'] * 1000, 2) . 'ms',
            'memory_used' => $this->formatBytes($metrics['memory_current']),
            'memory_peak' => $this->formatBytes($metrics['memory_peak']),
            'metrics_count' => count($this->metrics),
            'status' => $this->getPerformanceStatus($metrics['request_duration'])
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function getPerformanceStatus(float $duration): string
    {
        return match(true) {
            $duration < 0.1 => 'excellent',
            $duration < 0.5 => 'good',
            $duration < 1.0 => 'acceptable',
            $duration < 3.0 => 'slow',
            default => 'critical'
        };
    }
}
