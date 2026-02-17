<?php

namespace App\Controller;

use App\Service\AdvancedReportingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reports')]
class ReportingController extends AbstractController
{
    public function __construct(
        private AdvancedReportingService $reportingService
    ) {}

    #[Route('', name: 'app_reports_index')]
    public function index(): Response
    {
        $predefinedReports = $this->reportingService->getPredefinedReports();

        return $this->render('reports/index.html.twig', [
            'predefined_reports' => $predefinedReports
        ]);
    }

    #[Route('/generate', name: 'app_reports_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $config = $request->request->all();
        $user = $this->getUser();

        $report = $this->reportingService->generateCustomReport($config, $user);

        return $this->json($report);
    }

    #[Route('/predefined/{key}', name: 'app_reports_predefined')]
    public function predefined(string $key): JsonResponse
    {
        $predefinedReports = $this->reportingService->getPredefinedReports();
        
        if (!isset($predefinedReports[$key])) {
            return $this->json(['error' => 'Report not found'], 404);
        }

        $config = $predefinedReports[$key];
        $user = $this->getUser();

        $report = $this->reportingService->generateCustomReport($config, $user);

        return $this->json($report);
    }

    #[Route('/export/pdf', name: 'app_reports_export_pdf', methods: ['POST'])]
    public function exportPDF(Request $request): Response
    {
        $config = $request->request->all();
        $user = $this->getUser();

        $report = $this->reportingService->generateCustomReport($config, $user);
        $pdf = $this->reportingService->exportToPDF($report);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="report.pdf"'
        ]);
    }

    #[Route('/export/excel', name: 'app_reports_export_excel', methods: ['POST'])]
    public function exportExcel(Request $request): Response
    {
        $config = $request->request->all();
        $user = $this->getUser();

        $report = $this->reportingService->generateCustomReport($config, $user);
        $excel = $this->reportingService->exportToExcel($report);

        return new Response($excel, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="report.xlsx"'
        ]);
    }

    #[Route('/schedule', name: 'app_reports_schedule', methods: ['POST'])]
    public function schedule(Request $request): JsonResponse
    {
        $config = $request->request->get('config');
        $frequency = $request->request->get('frequency', 'daily');
        $user = $this->getUser();

        $scheduled = $this->reportingService->scheduleReport($config, $user, $frequency);

        return $this->json($scheduled);
    }
}
