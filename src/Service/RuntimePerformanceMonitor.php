<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Мониторинг производительности во время выполнения
 */
class RuntimePerformanceMonitor
{
    private array $timers = [];
    private array $memorySnapshots = [];
    private float $requestStartTime;
    private int $requestStartMemory;

    public function __construct(
        private LoggerInterface $logger
    ) {
        $this->requestStartTime = microtime(true);
        $this->requestStartMemory = memory_get_usage(true);
    }

    /**
     * Начало замера времени
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    /**
     * Завершение замера времени
     */
    public function stopTimer(string $name): ?array
    {
        if (!isset($this->timers[$name])) {
            return null;
        }

        $timer = $this->timers[$name];
        $duration = microtime(true) - $timer['start'];
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];

        $result = [
            'duration' => $duration,
            'duration_ms' => round($duration * 1000, 2),
            'memory_used' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2)
        ];

        // Логируем медленные операции
        if ($duration > 0.5) {
            $this->logger->warning('Slow operation detected', [
                'operation' => $name,
                'duration' => $result['duration_ms'] . 'ms',
                'memory' => $result['memory_used_mb'] . 'MB'
            ]);
        }

        unset($this->timers[$name]);
        return $result;
    }

    /**
     * Снимок использования памяти
     */
    public function takeMemorySnapshot(string $label): void
    {
        $this->memorySnapshots[$label] = [
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Получение всех снимков памяти
     */
    public function getMemorySnapshots(): array
    {
        return $this->memorySnapshots;
    }

    /**
     * Получение текущей статистики
     */
    public function getCurrentStats(): array
    {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);

        return [
            'request_duration' => round(($currentTime - $this->requestStartTime) * 1000, 2) . 'ms',
            'memory_used' => round(($currentMemory - $this->requestStartMemory) / 1024 / 1024, 2) . 'MB',
            'current_memory' => round($currentMemory / 1024 / 1024, 2) . 'MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'active_timers' => count($this->timers)
        ];
    }

    /**
     * Проверка превышения лимитов
     */
    public function checkLimits(float $maxDuration = 5.0, int $maxMemoryMB = 128): array
    {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);
        
        $duration = $currentTime - $this->requestStartTime;
        $memoryMB = $currentMemory / 1024 / 1024;

        $warnings = [];

        if ($duration > $maxDuration) {
            $warnings[] = [
                'type' => 'duration',
                'message' => sprintf('Request duration %.2fs exceeds limit %.2fs', $duration, $maxDuration)
            ];
        }

        if ($memoryMB > $maxMemoryMB) {
            $warnings[] = [
                'type' => 'memory',
                'message' => sprintf('Memory usage %.2fMB exceeds limit %dMB', $memoryMB, $maxMemoryMB)
            ];
        }

        return $warnings;
    }

    /**
     * Генерация отчета о производительности
     */
    public function generateReport(): array
    {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);

        return [
            'request' => [
                'duration' => round(($currentTime - $this->requestStartTime) * 1000, 2) . 'ms',
                'memory_used' => round(($currentMemory - $this->requestStartMemory) / 1024 / 1024, 2) . 'MB'
            ],
            'memory' => [
                'current' => round($currentMemory / 1024 / 1024, 2) . 'MB',
                'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
                'limit' => ini_get('memory_limit')
            ],
            'snapshots' => $this->memorySnapshots,
            'active_timers' => array_keys($this->timers),
            'warnings' => $this->checkLimits()
        ];
    }

    /**
     * Сброс всех данных
     */
    public function reset(): void
    {
        $this->timers = [];
        $this->memorySnapshots = [];
        $this->requestStartTime = microtime(true);
        $this->requestStartMemory = memory_get_usage(true);
    }
}
