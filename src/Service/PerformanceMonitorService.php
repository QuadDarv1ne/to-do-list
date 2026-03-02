<?php

namespace App\Service;

/**
 * Сервис мониторинга производительности
 */
class PerformanceMonitorService
{
    private array $timings = [];

    private array $slowQueries = [];

    public function startTiming(string $operation): void
    {
        $this->timings[$operation] = microtime(true);
    }

    public function stopTiming(string $operation): void
    {
        if (isset($this->timings[$operation])) {
            unset($this->timings[$operation]);
        }
    }

    public function logSlowQuery(string $query, float $duration): void
    {
        $this->slowQueries[] = [
            'query' => $query,
            'duration' => $duration,
            'time' => new \DateTime(),
        ];
    }

    public function getMetrics(): array
    {
        return [
            'active_timings' => \count($this->timings),
            'slow_queries' => \count($this->slowQueries),
        ];
    }

    public function getPerformanceReport(): array
    {
        return [
            'active_timings' => \count($this->timings),
            'slow_queries_count' => \count($this->slowQueries),
            'slow_queries' => \array_slice($this->slowQueries, 0, 10),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }
}
