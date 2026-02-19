<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Мониторинг производительности запросов к БД
 */
class QueryPerformanceMonitor
{
    private array $queryLog = [];

    private array $slowQueries = [];

    private float $slowQueryThreshold = 0.1; // 100ms

    public function __construct(
        private LoggerInterface $logger,
        private ?Profiler $profiler = null,
    ) {
    }

    /**
     * Начало отслеживания запроса
     */
    public function startQuery(string $sql, array $params = []): string
    {
        $queryId = uniqid('query_', true);

        $this->queryLog[$queryId] = [
            'sql' => $sql,
            'params' => $params,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];

        return $queryId;
    }

    /**
     * Завершение отслеживания запроса
     */
    public function endQuery(string $queryId, int $resultCount = 0): void
    {
        if (!isset($this->queryLog[$queryId])) {
            return;
        }

        $query = &$this->queryLog[$queryId];
        $query['end_time'] = microtime(true);
        $query['end_memory'] = memory_get_usage(true);
        $query['duration'] = $query['end_time'] - $query['start_time'];
        $query['memory_used'] = $query['end_memory'] - $query['start_memory'];
        $query['result_count'] = $resultCount;

        // Проверяем медленные запросы
        if ($query['duration'] > $this->slowQueryThreshold) {
            $this->slowQueries[] = $query;

            $this->logger->warning('Slow query detected', [
                'duration' => round($query['duration'] * 1000, 2) . 'ms',
                'sql' => substr($query['sql'], 0, 200),
                'result_count' => $resultCount,
            ]);
        }
    }

    /**
     * Получение статистики запросов
     */
    public function getStatistics(): array
    {
        $totalQueries = \count($this->queryLog);
        $totalDuration = 0;
        $totalMemory = 0;

        foreach ($this->queryLog as $query) {
            if (isset($query['duration'])) {
                $totalDuration += $query['duration'];
                $totalMemory += $query['memory_used'] ?? 0;
            }
        }

        return [
            'total_queries' => $totalQueries,
            'slow_queries' => \count($this->slowQueries),
            'total_duration' => round($totalDuration * 1000, 2) . 'ms',
            'avg_duration' => $totalQueries > 0 ? round(($totalDuration / $totalQueries) * 1000, 2) . 'ms' : '0ms',
            'total_memory' => $this->formatBytes($totalMemory),
            'slow_query_threshold' => round($this->slowQueryThreshold * 1000, 2) . 'ms',
        ];
    }

    /**
     * Получение медленных запросов
     */
    public function getSlowQueries(): array
    {
        return array_map(function ($query) {
            return [
                'sql' => substr($query['sql'], 0, 200),
                'duration' => round($query['duration'] * 1000, 2) . 'ms',
                'result_count' => $query['result_count'] ?? 0,
                'memory' => $this->formatBytes($query['memory_used'] ?? 0),
            ];
        }, $this->slowQueries);
    }

    /**
     * Сброс статистики
     */
    public function reset(): void
    {
        $this->queryLog = [];
        $this->slowQueries = [];
    }

    /**
     * Форматирование байтов
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Анализ N+1 проблем
     */
    public function detectNPlusOne(): array
    {
        $patterns = [];

        foreach ($this->queryLog as $query) {
            if (!isset($query['sql'])) {
                continue;
            }

            // Упрощаем SQL для поиска паттернов
            $normalized = preg_replace('/\\d+/', '?', $query['sql']);
            $normalized = preg_replace('/\\s+/', ' ', $normalized);

            if (!isset($patterns[$normalized])) {
                $patterns[$normalized] = 0;
            }
            $patterns[$normalized]++;
        }

        // Находим повторяющиеся запросы (потенциальные N+1)
        $nPlusOne = [];
        foreach ($patterns as $sql => $count) {
            if ($count > 5) { // Более 5 одинаковых запросов
                $nPlusOne[] = [
                    'sql' => substr($sql, 0, 200),
                    'count' => $count,
                    'suggestion' => 'Consider using JOIN or batch loading',
                ];
            }
        }

        return $nPlusOne;
    }

    /**
     * Генерация отчета
     */
    public function generateReport(): array
    {
        return [
            'statistics' => $this->getStatistics(),
            'slow_queries' => $this->getSlowQueries(),
            'n_plus_one' => $this->detectNPlusOne(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
