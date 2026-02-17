<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    #[Route('/', name: 'app_analytics_dashboard')]
    public function dashboard(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_dashboard');
        }
        
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        try {
            return $this->render('analytics/dashboard.html.twig', [
                'analytics' => $analytics
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_dashboard');
            }
        }
    }

    #[Route('/api/data', name: 'app_analytics_data', methods: ['GET'])]
    public function getAnalyticsData(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_get_data');
        }
        
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        try {
            return $this->json($analytics);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_get_data');
            }
        }
    }

    #[Route('/export/csv', name: 'app_analytics_export_csv', methods: ['GET'])]
    public function exportCsv(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_export_csv');
        }
        
        $user = $this->getUser();
        $csvData = $analyticsService->exportAnalyticsToCsv($user);

        $response = new Response($csvData);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="analytics-export.csv"');

        try {
            return $response;
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_export_csv');
            }
        }
    }

    #[Route('/api/completion-trend', name: 'app_analytics_completion_trend', methods: ['GET'])]
    public function getCompletionTrend(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_completion_trend');
        }
        
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        try {
            return $this->json([
                'daily_completion' => $analytics['productivity_trends']['daily_completion'],
                'weekly_completion' => $analytics['productivity_trends']['weekly_completion'],
                'trend' => $analytics['productivity_trends']['trend_analysis']
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_completion_trend');
            }
        }
    }

    #[Route('/api/category-breakdown', name: 'app_analytics_category_breakdown', methods: ['GET'])]
    public function getCategoryBreakdown(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_category_breakdown');
        }
        
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        try {
            return $this->json($analytics['category_analysis']);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_category_breakdown');
            }
        }
    }

    #[Route('/api/priority-analysis', name: 'app_analytics_priority_analysis', methods: ['GET'])]
    public function getPriorityAnalysis(
        AnalyticsService $analyticsService, 
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_priority_analysis');
        }
        
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        try {
            return $this->json($analytics['priority_analysis']);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_priority_analysis');
            }
        }
    }

    #[Route('/compare-periods', name: 'app_analytics_compare_periods', methods: ['GET'])]
    public function comparePeriods(
        Request $request,
        AnalyticsService $analyticsService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('analytics_controller_compare_periods');
        }
        
        $user = $this->getUser();
        $period1 = $request->query->get('period1', 'this_month');
        $period2 = $request->query->get('period2', 'last_month');

        $comparison = $analyticsService->getPeriodComparison($user, $period1, $period2);

        try {
            return $this->json($comparison);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('analytics_controller_compare_periods');
            }
        }
    }
}
