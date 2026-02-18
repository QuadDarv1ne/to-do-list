<?php

namespace App\Controller;

use App\Service\PerformanceMetricsCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/metrics')]
#[IsGranted('ROLE_ADMIN')]
class MetricsController extends AbstractController
{
    public function __construct(
        private PerformanceMetricsCollector $metricsCollector
    ) {}

    /**
     * Metrics dashboard
     */
    #[Route('', name: 'app_metrics_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('metrics/dashboard.html.twig');
    }

    /**
     * Get metrics for specific endpoint
     */
    #[Route('/endpoint', name: 'app_metrics_endpoint', methods: ['GET'])]
    public function endpoint(string $path = '/', int $hours = 1): JsonResponse
    {
        $metrics = $this->metricsCollector->getAggregatedMetrics($path, $hours);
        
        return $this->json([
            'success' => true,
            'endpoint' => $path,
            'hours' => $hours,
            'metrics' => $metrics
        ]);
    }

    /**
     * Get current performance summary
     */
    #[Route('/summary', name: 'app_metrics_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $summary = $this->metricsCollector->getPerformanceSummary();
        
        return $this->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'app_metrics_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $metrics = $this->metricsCollector->getMetrics();
        
        $status = 'healthy';
        $issues = [];

        // Check memory usage
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $memoryUsagePercent = ($metrics['memory_peak'] / $memoryLimitBytes) * 100;

        if ($memoryUsagePercent > 90) {
            $status = 'critical';
            $issues[] = 'Memory usage critical: ' . round($memoryUsagePercent, 2) . '%';
        } elseif ($memoryUsagePercent > 75) {
            $status = 'warning';
            $issues[] = 'Memory usage high: ' . round($memoryUsagePercent, 2) . '%';
        }

        // Check request duration
        if ($metrics['request_duration'] > 5.0) {
            $status = 'critical';
            $issues[] = 'Request duration critical: ' . round($metrics['request_duration'], 2) . 's';
        } elseif ($metrics['request_duration'] > 2.0) {
            if ($status === 'healthy') {
                $status = 'warning';
            }
            $issues[] = 'Request duration high: ' . round($metrics['request_duration'], 2) . 's';
        }

        return $this->json([
            'status' => $status,
            'timestamp' => time(),
            'metrics' => [
                'memory_usage' => round($memoryUsagePercent, 2) . '%',
                'request_duration' => round($metrics['request_duration'], 3) . 's'
            ],
            'issues' => $issues
        ]);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;

        return match($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
}
