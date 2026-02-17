<?php

namespace App\Controller;

use App\Service\PerformanceMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/performance')]
#[IsGranted('ROLE_ADMIN')]
class PerformanceController extends AbstractController
{
    public function __construct(
        private PerformanceMetricsService $metricsService
    ) {}

    #[Route('', name: 'app_performance_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $from = new \DateTime('first day of this month');
        $to = new \DateTime();

        $metrics = $this->metricsService->getUserMetrics($user, $from, $to);
        $trend = $this->metricsService->getPerformanceTrend($user, 4);

        return $this->render('performance/index.html.twig', [
            'metrics' => $metrics,
            'trend' => $trend
        ]);
    }

    #[Route('/api/metrics', name: 'app_performance_api_metrics')]
    public function apiMetrics(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $period = $request->query->get('period', 'month');

        $from = match($period) {
            'today' => new \DateTime('today'),
            'week' => new \DateTime('monday this week'),
            'month' => new \DateTime('first day of this month'),
            'year' => new \DateTime('first day of january this year'),
            default => new \DateTime('first day of this month')
        };
        $to = new \DateTime();

        $metrics = $this->metricsService->getUserMetrics($user, $from, $to);

        return $this->json($metrics);
    }

    #[Route('/api/trend', name: 'app_performance_api_trend')]
    public function apiTrend(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $weeks = (int)$request->query->get('weeks', 4);

        $trend = $this->metricsService->getPerformanceTrend($user, $weeks);

        return $this->json($trend);
    }

    #[Route('/export', name: 'app_performance_export')]
    public function export(Request $request): Response
    {
        $user = $this->getUser();
        $from = new \DateTime('first day of this month');
        $to = new \DateTime();

        $metrics = $this->metricsService->getUserMetrics($user, $from, $to);
        $csv = $this->metricsService->exportMetricsToCSV($metrics);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="performance.csv"'
        ]);
    }

    #[Route('/team', name: 'app_performance_team')]
    public function team(Request $request): JsonResponse
    {
        $userIds = $request->query->all('user_ids');
        $from = new \DateTime('first day of this month');
        $to = new \DateTime();

        $teamMetrics = $this->metricsService->getTeamMetrics($userIds, $from, $to);

        return $this->json($teamMetrics);
    }

    #[Route('/leaderboard', name: 'app_performance_leaderboard')]
    public function leaderboard(Request $request): JsonResponse
    {
        $userIds = $request->query->all('user_ids');
        $from = new \DateTime('first day of this month');
        $to = new \DateTime();
        $limit = (int)$request->query->get('limit', 10);

        $leaderboard = $this->metricsService->getLeaderboard($userIds, $from, $to, $limit);

        return $this->json($leaderboard);
    }
}
