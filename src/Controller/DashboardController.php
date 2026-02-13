<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\AnalyticsService;
use App\Service\QueryCacheService;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        TaskRepository $taskRepository,
        AnalyticsService $analyticsService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        $user = $this->getUser();
        
        // Get quick stats from repository with caching
        $taskStats = $taskRepository->getQuickStats($user);
        
        // Get analytics data
        $analyticsData = $analyticsService->getDashboardData($user);
        
        // Get performance metrics if user is admin and service is available
        $performanceMetrics = null;
        if ($this->isGranted('ROLE_ADMIN') && $performanceMonitor) {
            try {
                $performanceMetrics = $performanceMonitor->getPerformanceReport();
            } catch (\Exception $e) {
                // Log error but don't break the dashboard
                error_log('Error getting performance metrics: ' . $e->getMessage());
            }
        }
        
        // Prepare dashboard data with defaults
        $dashboardData = [
            'task_stats' => $taskStats,
            'analytics_data' => $analyticsData,
            'performance_metrics' => $performanceMetrics,
            // Pass tag stats if available
            'tag_stats' => $analyticsData['tag_stats'] ?? [],
            'tag_completion_rates' => $analyticsData['tag_completion_rates'] ?? [],
            'categories' => $analyticsData['categories'] ?? [],
            'recent_tasks' => $analyticsData['recent_tasks'] ?? [],
            // Pass activity stats if user is admin
            'platform_activity_stats' => $analyticsData['platform_activity_stats'] ?? null,
            'user_activity_stats' => $analyticsData['user_activity_stats'] ?? null,
        ];
        
        // Add additional data for enhanced user experience
        $dashboardData['dashboard_refresh_interval'] = 300000; // 5 minutes in milliseconds
        
        return $this->render('dashboard/index.html.twig', $dashboardData);
    }
    
    #[Route('/cache/clear', name: 'app_cache_clear', methods: ['POST'])]
    public function clearCache(QueryCacheService $cacheService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $cacheService->clear();
        
        $this->addFlash('success', 'Cache cleared successfully!');
        
        return $this->redirectToRoute('app_dashboard');
    }
}