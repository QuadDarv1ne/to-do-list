<?php

namespace App\CacheWarmer;

use App\Service\PerformanceMonitorService;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Cache warmer for performance monitoring service
 */
class PerformanceCacheWarmer implements CacheWarmerInterface
{
    private PerformanceMonitorService $performanceMonitor;

    public function __construct(PerformanceMonitorService $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Warms up the cache.
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        // Initialize performance monitoring metrics
        $this->performanceMonitor->collectMetrics();
        
        // Pre-populate common aggregate metrics keys
        $operations = [
            'task_controller_index',
            'task_controller_show', 
            'task_controller_edit',
            'analytics_controller_dashboard',
            'dashboard_controller_index',
            'user_controller_index',
            'tag_controller_index',
            'task_category_controller_index'
        ];
        
        foreach ($operations as $operation) {
            // Initialize aggregate metrics structure for common operations
            $this->performanceMonitor->startTimer($operation);
            // Just start and immediately stop to initialize the structure
            $this->performanceMonitor->stopTimer($operation);
        }

        // Return list of classes to preload
        return [];
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true for this warmer
     */
    public function isOptional(): bool
    {
        // This warmer is optional - if it fails, the cache will still work
        return true;
    }
}