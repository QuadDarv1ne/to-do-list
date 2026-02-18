<?php

namespace App\Controller;

use App\Service\BudgetManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/budget')]
class BudgetController extends AbstractController
{
    public function __construct(
        private BudgetManagementService $budgetManagementService
    ) {}

    #[Route('/', name: 'app_budget_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        // For now, just render a basic template
        return $this->render('budget/index.html.twig');
    }

    #[Route('/create', name: 'app_budget_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        
        $name = $data['name'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $options = $data['options'] ?? [];
        
        if (empty($name) || $amount <= 0) {
            return $this->json([
                'error' => 'Name and amount are required'
            ], 400);
        }
        
        $budget = $this->budgetManagementService->createBudget($name, $amount, $this->getUser(), $options);
        
        return $this->json([
            'success' => true,
            'budget' => $budget
        ]);
    }

    #[Route('/{id}/status', name: 'app_budget_status', methods: ['GET'])]
    public function status(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $status = $this->budgetManagementService->getBudgetStatus($id);
        
        return $this->json([
            'status' => $status
        ]);
    }

    #[Route('/{id}/breakdown', name: 'app_budget_breakdown', methods: ['GET'])]
    public function breakdown(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $breakdown = $this->budgetManagementService->getBudgetBreakdown($id);
        
        return $this->json([
            'breakdown' => $breakdown
        ]);
    }

    #[Route('/{id}/forecast', name: 'app_budget_forecast', methods: ['GET'])]
    public function forecast(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $forecast = $this->budgetManagementService->forecastBudget($id);
        
        return $this->json([
            'forecast' => $forecast
        ]);
    }

    #[Route('/{id}/alerts', name: 'app_budget_alerts', methods: ['GET'])]
    public function alerts(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $alerts = $this->budgetManagementService->getBudgetAlerts($id);
        
        return $this->json([
            'alerts' => $alerts
        ]);
    }

    #[Route('/{id}/roi', name: 'app_budget_roi', methods: ['GET'])]
    public function roi(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $roi = $this->budgetManagementService->getROIAnalysis($id);
        
        return $this->json([
            'roi' => $roi
        ]);
    }

    #[Route('/compare', name: 'app_budget_compare', methods: ['POST'])]
    public function compare(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        $budgetIds = $data['budget_ids'] ?? [];
        
        if (empty($budgetIds)) {
            return $this->json([
                'error' => 'At least one budget ID is required'
            ], 400);
        }
        
        $comparison = $this->budgetManagementService->compareBudgets($budgetIds);
        
        return $this->json([
            'comparison' => $comparison
        ]);
    }

    #[Route('/{id}/export.{format}', name: 'app_budget_export', methods: ['GET'])]
    public function export(int $id, string $format = 'csv'): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $supportedFormats = ['csv', 'pdf', 'excel'];
        
        if (!in_array($format, $supportedFormats)) {
            return $this->json([
                'error' => 'Unsupported format. Supported formats: ' . implode(', ', $supportedFormats)
            ], 400);
        }
        
        $report = $this->budgetManagementService->exportBudgetReport($id, $format);
        
        $response = new Response($report);
        
        switch ($format) {
            case 'csv':
                $response->headers->set('Content-Type', 'text/csv');
                $response->headers->set('Content-Disposition', "attachment; filename=budget_$id.csv");
                break;
            case 'pdf':
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set('Content-Disposition', "attachment; filename=budget_$id.pdf");
                break;
            case 'excel':
                $response->headers->set('Content-Type', 'application/vnd.ms-excel');
                $response->headers->set('Content-Disposition', "attachment; filename=budget_$id.xlsx");
                break;
        }
        
        return $response;
    }
}