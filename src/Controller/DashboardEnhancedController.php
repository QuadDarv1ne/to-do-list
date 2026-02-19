<?php

namespace App\Controller;

use App\Service\DashboardStatisticsService;
use App\Service\PerformanceOptimizerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Улучшенный контроллер дашборда со статистикой
 */
#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardEnhancedController extends AbstractController
{
    public function __construct(
        private DashboardStatisticsService $statsService,
        private PerformanceOptimizerService $optimizer,
    ) {
    }

    #[Route('/enhanced', name: 'app_dashboard_enhanced', methods: ['GET'])]
    public function enhanced(): Response
    {
        $user = $this->getUser();

        // Кэширование статистики
        $cacheKey = 'dashboard_enhanced_' . $user->getId();

        $stats = $this->optimizer->cacheQuery($cacheKey, function () use ($user) {
            return $this->statsService->getDashboardStats($user);
        }, 300); // 5 минут

        return $this->render('dashboard/enhanced.html.twig', [
            'stats' => $stats,
            'page_title' => 'Панель управления',
        ]);
    }

    /**
     * API для получения статистики дашборда
     */
    #[Route('/api/stats', name: 'app_dashboard_api_stats', methods: ['GET'])]
    public function apiStats(): Response
    {
        $user = $this->getUser();

        $cacheKey = 'dashboard_api_stats_' . $user->getId();

        $stats = $this->optimizer->cacheQuery($cacheKey, function () use ($user) {
            return $this->statsService->getDashboardStats($user);
        }, 60);

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * API для данных диаграмм
     */
    #[Route('/api/chart-data', name: 'app_dashboard_api_chart', methods: ['GET'])]
    public function chartData(): Response
    {
        $user = $this->getUser();

        $cacheKey = 'dashboard_chart_' . $user->getId();

        $chartData = $this->optimizer->cacheQuery($cacheKey, function () use ($user) {
            return [
                'pie' => $this->statsService->getPieChartData($user),
                'line' => $this->statsService->getLineChartData($user),
            ];
        }, 300);

        return $this->json([
            'success' => true,
            'data' => $chartData,
        ]);
    }
}
