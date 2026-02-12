<?php

namespace App\Controller;

use App\Service\AnalyticsService;
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
    public function dashboard(AnalyticsService $analyticsService): Response
    {
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        return $this->render('analytics/dashboard.html.twig', [
            'analytics' => $analytics
        ]);
    }

    #[Route('/api/data', name: 'app_analytics_data', methods: ['GET'])]
    public function getAnalyticsData(AnalyticsService $analyticsService): JsonResponse
    {
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        return $this->json($analytics);
    }

    #[Route('/export/csv', name: 'app_analytics_export_csv', methods: ['GET'])]
    public function exportCsv(AnalyticsService $analyticsService): Response
    {
        $user = $this->getUser();
        $csvData = $analyticsService->exportAnalyticsToCsv($user);

        $response = new Response($csvData);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="analytics-export.csv"');

        return $response;
    }

    #[Route('/api/completion-trend', name: 'app_analytics_completion_trend', methods: ['GET'])]
    public function getCompletionTrend(AnalyticsService $analyticsService): JsonResponse
    {
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        return $this->json([
            'daily_completion' => $analytics['productivity_trends']['daily_completion'],
            'weekly_completion' => $analytics['productivity_trends']['weekly_completion'],
            'trend' => $analytics['productivity_trends']['trend_analysis']
        ]);
    }

    #[Route('/api/category-breakdown', name: 'app_analytics_category_breakdown', methods: ['GET'])]
    public function getCategoryBreakdown(AnalyticsService $analyticsService): JsonResponse
    {
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        return $this->json($analytics['category_analysis']);
    }

    #[Route('/api/priority-analysis', name: 'app_analytics_priority_analysis', methods: ['GET'])]
    public function getPriorityAnalysis(AnalyticsService $analyticsService): JsonResponse
    {
        $user = $this->getUser();
        $analytics = $analyticsService->getUserTaskAnalytics($user);

        return $this->json($analytics['priority_analysis']);
    }

    #[Route('/compare-periods', name: 'app_analytics_compare_periods', methods: ['GET'])]
    public function comparePeriods(
        Request $request,
        AnalyticsService $analyticsService
    ): JsonResponse {
        $user = $this->getUser();
        $period1 = $request->query->get('period1', 'this_month');
        $period2 = $request->query->get('period2', 'last_month');

        $comparison = $analyticsService->getPeriodComparison($user, $period1, $period2);

        return $this->json($comparison);
    }
}