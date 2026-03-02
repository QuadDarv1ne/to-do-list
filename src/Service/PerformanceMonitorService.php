<?php

namespace App\Service;

/**
 * Заглушка сервиса мониторинга производительности
 * Для устранения ошибок PHPStan
 */
class PerformanceMonitorService
{
    public function startTiming(string $operation): void
    {
    }

    public function stopTiming(string $operation): void
    {
    }

    public function logSlowQuery(string $query, float $duration): void
    {
    }

    public function getMetrics(): array
    {
        return [];
    }
}
