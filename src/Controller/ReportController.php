<?php

namespace App\Controller;

use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
    ) {
    }

    #[Route('', name: 'app_reports_index', methods: ['GET'])]
    public function index(): Response
    {
        $predefinedReports = $this->reportService->getPredefinedReports();

        return $this->render('reports/index.html.twig', [
            'predefined_reports' => $predefinedReports,
        ]);
    }

    #[Route('/productivity', name: 'app_reports_productivity', methods: ['GET'])]
    public function productivity(Request $request): Response
    {
        $user = $this->getUser();

        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportService->generateProductivityReport($user, $startDate, $endDate);

        return $this->render('reports/productivity.html.twig', [
            'report' => $report,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    #[Route('/team', name: 'app_reports_team', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function team(Request $request): Response
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportService->generateTeamReport($startDate, $endDate);

        return $this->render('reports/team.html.twig', [
            'report' => $report,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    #[Route('/overdue', name: 'app_reports_overdue', methods: ['GET'])]
    public function overdue(): Response
    {
        $report = $this->reportService->generateOverdueReport();

        return $this->render('reports/overdue.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/predefined/{key}', name: 'app_reports_predefined', methods: ['GET'])]
    public function predefined(string $key): JsonResponse
    {
        $predefinedReports = $this->reportService->getPredefinedReports();

        if (!isset($predefinedReports[$key])) {
            return $this->json(['error' => 'Report not found'], 404);
        }

        $config = $predefinedReports[$key];
        $user = $this->getUser();

        $report = $this->reportService->generateCustomReport($config, $user);

        return $this->json($report);
    }

    #[Route('/productivity/export', name: 'app_reports_productivity_export', methods: ['GET'])]
    public function exportProductivity(Request $request): Response
    {
        $user = $this->getUser();

        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportService->generateProductivityReport($user, $startDate, $endDate);

        return $this->json($report);
    }

    #[Route('/team/export', name: 'app_reports_team_export', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function exportTeam(Request $request): Response
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportService->generateTeamReport($startDate, $endDate);

        return $this->json($report);
    }
}
