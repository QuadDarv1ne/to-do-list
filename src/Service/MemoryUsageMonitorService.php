<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for monitoring and analyzing memory usage
 */
class MemoryUsageMonitorService
{
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private array $memorySnapshots;
    private array $trackingPoints;

    public function __construct(
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->memorySnapshots = [];
        $this->trackingPoints = [];
    }

    /**
     * Take a snapshot of current memory usage
     */
    public function takeMemorySnapshot(string $label = 'snapshot'): array
    {
        $requestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $executionTime = $requestTimeFloat ? (microtime(true) - $requestTimeFloat) : 0;
        
        $memoryUsage = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'execution_time_since_start' => $executionTime,
            'formatted_memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'formatted_memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
        ];

        $this->memorySnapshots[] = $memoryUsage;

        $this->logger->info('Memory snapshot taken', [
            'label' => $label,
            'memory_usage' => $memoryUsage['formatted_memory_usage'],
            'memory_peak' => $memoryUsage['formatted_memory_peak']
        ]);

        return $memoryUsage;
    }

    /**
     * Track memory usage at specific point
     */
    public function trackMemoryPoint(string $pointName, string $context = ''): void
    {
        $this->trackingPoints[$pointName] = [
            'context' => $context,
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'formatted_memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'formatted_memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'timestamp' => microtime(true)
        ];

        $this->logger->info('Memory tracked at point', [
            'point' => $pointName,
            'context' => $context,
            'memory_usage' => $this->trackingPoints[$pointName]['formatted_memory_usage']
        ]);
    }

    /**
     * Get memory usage comparison between two snapshots
     */
    public function getMemoryComparison(string $fromLabel, string $toLabel): ?array
    {
        $fromSnapshot = null;
        $toSnapshot = null;

        foreach ($this->memorySnapshots as $snapshot) {
            if ($snapshot['label'] === $fromLabel) {
                $fromSnapshot = $snapshot;
            }
            if ($snapshot['label'] === $toLabel) {
                $toSnapshot = $snapshot;
            }
        }

        if (!$fromSnapshot || !$toSnapshot) {
            return null;
        }

        $memoryDiff = $toSnapshot['memory_usage_bytes'] - $fromSnapshot['memory_usage_bytes'];
        $peakDiff = $toSnapshot['memory_peak_bytes'] - $fromSnapshot['memory_peak_bytes'];

        $comparison = [
            'from_snapshot' => $fromSnapshot,
            'to_snapshot' => $toSnapshot,
            'memory_difference_bytes' => $memoryDiff,
            'memory_difference_formatted' => $this->formatBytes(abs($memoryDiff)),
            'direction' => $memoryDiff > 0 ? 'increased' : ($memoryDiff < 0 ? 'decreased' : 'unchanged'),
            'peak_difference_bytes' => $peakDiff,
            'peak_difference_formatted' => $this->formatBytes(abs($peakDiff)),
            'peak_direction' => $peakDiff > 0 ? 'increased' : ($peakDiff < 0 ? 'decreased' : 'unchanged'),
        ];

        return $comparison;
    }

    /**
     * Get memory usage analysis
     */
    public function getMemoryAnalysis(): array
    {
        if (empty($this->memorySnapshots)) {
            return [
                'error' => 'No memory snapshots available'
            ];
        }

        $usages = array_column($this->memorySnapshots, 'memory_usage_bytes');
        $peaks = array_column($this->memorySnapshots, 'memory_peak_bytes');
        $minUsage = min($usages);
        $maxUsage = max($usages);
        $minPeak = min($peaks);
        $maxPeak = max($peaks);

        $analysis = [
            'total_snapshots' => count($this->memorySnapshots),
            'min_memory_usage_bytes' => $minUsage,
            'max_memory_usage_bytes' => $maxUsage,
            'min_memory_usage_formatted' => $this->formatBytes($minUsage),
            'max_memory_usage_formatted' => $this->formatBytes($maxUsage),
            'min_memory_peak_bytes' => $minPeak,
            'max_memory_peak_bytes' => $maxPeak,
            'min_memory_peak_formatted' => $this->formatBytes($minPeak),
            'max_memory_peak_formatted' => $this->formatBytes($maxPeak),
            'average_memory_usage_bytes' => (int)(array_sum($usages) / count($usages)),
            'average_memory_usage_formatted' => $this->formatBytes((int)(array_sum($usages) / count($usages))),
            'current_memory_limit' => ini_get('memory_limit'),
            'snapshots' => $this->memorySnapshots
        ];

        return $analysis;
    }

    /**
     * Get memory tracking points analysis
     */
    public function getTrackingPointsAnalysis(): array
    {
        if (empty($this->trackingPoints)) {
            return [
                'error' => 'No memory tracking points available'
            ];
        }

        $analysis = [
            'total_points' => count($this->trackingPoints),
            'tracking_points' => $this->trackingPoints,
            'usage_comparison' => $this->compareTrackingPoints()
        ];

        return $analysis;
    }

    /**
     * Compare memory usage between tracking points
     */
    private function compareTrackingPoints(): array
    {
        $points = array_keys($this->trackingPoints);
        $comparisons = [];

        for ($i = 1; $i < count($points); $i++) {
            $prevPoint = $points[$i - 1];
            $currPoint = $points[$i];

            $prevUsage = $this->trackingPoints[$prevPoint]['memory_usage_bytes'];
            $currUsage = $this->trackingPoints[$currPoint]['memory_usage_bytes'];

            $diff = $currUsage - $prevUsage;
            $diffFormatted = $this->formatBytes(abs($diff));

            $comparisons[] = [
                'from_point' => $prevPoint,
                'to_point' => $currPoint,
                'difference_bytes' => $diff,
                'difference_formatted' => $diffFormatted,
                'direction' => $diff > 0 ? 'increased' : ($diff < 0 ? 'decreased' : 'unchanged')
            ];
        }

        return $comparisons;
    }

    /**
     * Detect potential memory leaks
     */
    public function detectPotentialLeaks(int $thresholdIncreasePercent = 50): array
    {
        $leakSuspects = [];
        
        // Check snapshots for unusual increases
        for ($i = 1; $i < count($this->memorySnapshots); $i++) {
            $prev = $this->memorySnapshots[$i - 1];
            $curr = $this->memorySnapshots[$i];
            
            if ($prev['memory_usage_bytes'] > 0) {
                $increasePercent = (($curr['memory_usage_bytes'] - $prev['memory_usage_bytes']) / $prev['memory_usage_bytes']) * 100;
                
                if ($increasePercent > $thresholdIncreasePercent) {
                    $leakSuspects[] = [
                        'from_snapshot' => $prev['label'],
                        'to_snapshot' => $curr['label'],
                        'increase_percent' => round($increasePercent, 2),
                        'from_memory' => $prev['formatted_memory_usage'],
                        'to_memory' => $curr['formatted_memory_usage']
                    ];
                }
            }
        }
        
        // Check tracking points
        $trackingComparisons = $this->compareTrackingPoints();
        foreach ($trackingComparisons as $comp) {
            if ($comp['difference_bytes'] > 0) {
                $increasePercent = (($comp['difference_bytes']) / $this->trackingPoints[$comp['from_point']]['memory_usage_bytes']) * 100;
                
                if ($increasePercent > $thresholdIncreasePercent) {
                    $leakSuspects[] = [
                        'from_point' => $comp['from_point'],
                        'to_point' => $comp['to_point'],
                        'increase_percent' => round($increasePercent, 2),
                        'from_memory' => $this->trackingPoints[$comp['from_point']]['formatted_memory_usage'],
                        'to_memory' => $this->trackingPoints[$comp['to_point']]['formatted_memory_usage']
                    ];
                }
            }
        }
        
        return $leakSuspects;
    }

    /**
     * Clear all memory snapshots
     */
    public function clearSnapshots(): void
    {
        $this->memorySnapshots = [];
        $this->trackingPoints = [];
        
        $this->logger->info('Memory snapshots and tracking points cleared');
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
     * Get memory usage trend
     */
    public function getMemoryTrend(): array
    {
        if (count($this->memorySnapshots) < 2) {
            return [
                'error' => 'Need at least 2 snapshots to calculate trend'
            ];
        }

        $timestamps = array_column($this->memorySnapshots, 'timestamp');
        $memories = array_column($this->memorySnapshots, 'memory_usage_bytes');

        // Calculate linear regression
        $n = count($timestamps);
        $sumX = array_sum($timestamps);
        $sumY = array_sum($memories);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $timestamps[$i] * $memories[$i];
            $sumXX += $timestamps[$i] * $timestamps[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $slopePerSecond = $slope; // slope is already per microsecond, convert to per second

        $trend = [
            'slope_per_second' => $slopePerSecond,
            'slope_direction' => $slopePerSecond > 0 ? 'increasing' : ($slopePerSecond < 0 ? 'decreasing' : 'stable'),
            'slope_description' => $this->describeSlope($slopePerSecond),
            'total_samples' => $n,
            'first_sample' => $this->memorySnapshots[0],
            'last_sample' => $this->memorySnapshots[$n - 1]
        ];

        return $trend;
    }

    /**
     * Describe slope in human-readable form
     */
    private function describeSlope(float $slope): string
    {
        $absSlope = abs($slope * 1000000); // Convert to bytes per second
        
        if ($absSlope < 1024) {
            return $slope > 0 ? 'Slowly increasing' : 'Slowly decreasing';
        } elseif ($absSlope < 1024 * 1024) {
            return $slope > 0 ? 'Moderately increasing' : 'Moderately decreasing';
        } else {
            return $slope > 0 ? 'Rapidly increasing' : 'Rapidly decreasing';
        }
    }
}
