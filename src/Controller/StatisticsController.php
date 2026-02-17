<?php

namespace App\Controller;

use App\Service\TaskStatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/statistics')]
#[IsGranted('ROLE_USER')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private TaskStatisticsService $statisticsService
    ) {}

    /**
     * Statistics dashboard
     */
    #[Route('', name: 'app_statistics', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        $from = new \DateTime($request->query->get('from', '-30 days'));
        $to = new \DateTime($request->query->get('to', 'now'));

        $stats = $this->statisticsService->getComprehensiveStats($user, $from, $to);

        return $this->render('statistics/index.html.twig', [
            'stats' => $stats,
            'from' => $from,
            'to' => $to
        ]);
    }

    /**
     * Get statistics as JSON
     */
    #[Route('/api/data', name: 'app_statistics_api', methods: ['GET'])]
    public function apiData(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        $from = new \DateTime($request->query->get('from', '-30 days'));
        $to = new \DateTime($request->query->get('to', 'now'));

        $stats = $this->statisticsService->getComprehensiveStats($user, $from, $to);

        return $this->json($stats);
    }

    /**
     * Compare periods
     */
    #[Route('/compare', name: 'app_statistics_compare', methods: ['GET'])]
    public function compare(Request $request): Response
    {
        $user = $this->getUser();

        $period1Start = new \DateTime($request->query->get('p1_start', '-60 days'));
        $period1End = new \DateTime($request->query->get('p1_end', '-30 days'));
        $period2Start = new \DateTime($request->query->get('p2_start', '-30 days'));
        $period2End = new \DateTime($request->query->get('p2_end', 'now'));

        $comparison = $this->statisticsService->comparePeriods(
            $user,
            $period1Start,
            $period1End,
            $period2Start,
            $period2End
        );

        return $this->render('statistics/compare.html.twig', [
            'comparison' => $comparison
        ]);
    }

    /**
     * Export statistics
     */
    #[Route('/export', name: 'app_statistics_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $user = $this->getUser();
        
        $from = new \DateTime($request->query->get('from', '-30 days'));
        $to = new \DateTime($request->query->get('to', 'now'));

        $stats = $this->statisticsService->getComprehensiveStats($user, $from, $to);
        $csv = $this->statisticsService->exportToCSV($stats);

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="statistics.csv"');

        return $response;
    }
}
