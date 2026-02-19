<?php

namespace App\Controller;

use App\Service\ReportGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    public function __construct(
        private ReportGeneratorService $reportGenerator,
    ) {
    }

    /**
     * Reports dashboard
     */
    #[Route('', name: 'app_reports_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('reports/index.html.twig');
    }

    /**
     * Personal productivity report
     */
    #[Route('/productivity', name: 'app_reports_productivity', methods: ['GET'])]
    public function productivity(Request $request): Response
    {
        $user = $this->getUser();

        // Default to last 30 days
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        // Allow custom date range
        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportGenerator->generateProductivityReport($user, $startDate, $endDate);

        return $this->render('reports/productivity.html.twig', [
            'report' => $report,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Team performance report (managers and admins only)
     */
    #[Route('/team', name: 'app_reports_team', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function team(Request $request): Response
    {
        // Default to last 30 days
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        // Allow custom date range
        if ($request->query->has('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->has('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        $report = $this->reportGenerator->generateTeamReport($startDate, $endDate);

        return $this->render('reports/team.html.twig', [
            'report' => $report,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Overdue tasks report
     */
    #[Route('/overdue', name: 'app_reports_overdue', methods: ['GET'])]
    public function overdue(): Response
    {
        $report = $this->reportGenerator->generateOverdueReport();

        return $this->render('reports/overdue.html.twig', [
            'report' => $report,
        ]);
    }

    /**
     * Export productivity report as JSON
     */
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

        $report = $this->reportGenerator->generateProductivityReport($user, $startDate, $endDate);

        return $this->json($report);
    }

    /**
     * Export team report as JSON
     */
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

        $report = $this->reportGenerator->generateTeamReport($startDate, $endDate);

        return $this->json($report);
    }
}
