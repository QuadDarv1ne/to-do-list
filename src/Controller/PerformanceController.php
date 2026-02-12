<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/performance')]
#[IsGranted('ROLE_ADMIN')]
class PerformanceController extends AbstractController
{
    private PerformanceMonitorService $performanceMonitor;
    private AnalyticsService $analyticsService;

    public function __construct(
        PerformanceMonitorService $performanceMonitor,
        AnalyticsService $analyticsService
    ) {
        $this->performanceMonitor = $performanceMonitor;
        $this->analyticsService = $analyticsService;
    }

    #[Route('/', name: 'app_performance_index', methods: ['GET'])]
    public function index(): Response
    {
        $performanceData = $this->performanceMonitor->getPerformanceReport();
        
        return $this->render('performance/index.html.twig', [
            'performance_data' => $performanceData
        ]);
    }

    #[Route('/metrics', name: 'app_performance_metrics', methods: ['GET'])]
    public function getMetrics(): JsonResponse
    {
        $performanceData = $this->performanceMonitor->getPerformanceReport();
        
        return $this->json($performanceData);
    }

    #[Route('/system', name: 'app_performance_system', methods: ['GET'])]
    public function getSystemMetrics(): JsonResponse
    {
        $systemMetrics = $this->analyticsService->getSystemPerformanceMetrics();
        
        return $this->json($systemMetrics);
    }

    #[Route('/health', name: 'app_performance_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $performanceData = $this->performanceMonitor->collectMetrics();
        
        // Determine health status based on metrics
        $healthStatus = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => $performanceData,
            'checks' => [
                'memory_usage_normal' => $performanceData['current_memory_usage_bytes'] < 500 * 1024 * 1024, // Less than 500MB
                'environment' => $performanceData['environment']
            ]
        ];

        // Adjust status if any issues are detected
        if ($performanceData['current_memory_usage_bytes'] > 1000 * 1024 * 1024) { // More than 1GB
            $healthStatus['status'] = 'critical';
        } elseif ($performanceData['current_memory_usage_bytes'] > 750 * 1024 * 1024) { // More than 750MB
            $healthStatus['status'] = 'warning';
        }

        return $this->json($healthStatus);
    }
}