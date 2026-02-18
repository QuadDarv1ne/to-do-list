<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Service\BudgetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/budget')]
class BudgetController extends AbstractController
{
    public function __construct(
        private BudgetService $budgetService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_budget_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user): Response
    {
        $budgets = $this->budgetService->getUserBudgets($user);
        
        return $this->render('budget/index.html.twig', [
            'budgets' => $budgets,
        ]);
    }

    #[Route('/create', name: 'app_budget_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            
            $budget = new Budget();
            $budget->setTitle($data['title'] ?? '');
            $budget->setDescription($data['description'] ?? '');
            $budget->setAmount($data['amount'] ?? 0);
            $budget->setUsedAmount($data['used_amount'] ?? 0);
            $budget->setStartDate(new \DateTime($data['start_date'] ?? 'now'));
            $budget->setEndDate(new \DateTime($data['end_date'] ?? '+1 month'));
            $budget->setStatus($data['status'] ?? 'active');
            $budget->setCurrency($data['currency'] ?? 'USD');
            $budget->setUserId($user->getId());

            $this->entityManager->persist($budget);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_budget_index');
        }

        return $this->render('budget/create.html.twig');
    }

    #[Route('/{id}', name: 'app_budget_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(Budget $budget, #[CurrentUser] User $user): Response
    {
        // Ensure user can only access their own budgets
        if ($budget->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('budget/show.html.twig', [
            'budget' => $budget,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_budget_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Budget $budget, #[CurrentUser] User $user): Response
    {
        // Ensure user can only edit their own budgets
        if ($budget->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            
            $budget->setTitle($data['title'] ?? $budget->getTitle());
            $budget->setDescription($data['description'] ?? $budget->getDescription());
            $budget->setAmount($data['amount'] ?? $budget->getAmount());
            $budget->setUsedAmount($data['used_amount'] ?? $budget->getUsedAmount());
            $budget->setStartDate(new \DateTime($data['start_date'] ?? $budget->getStartDate()->format('Y-m-d')));
            $budget->setEndDate(new \DateTime($data['end_date'] ?? $budget->getEndDate()->format('Y-m-d')));
            $budget->setStatus($data['status'] ?? $budget->getStatus());
            $budget->setCurrency($data['currency'] ?? $budget->getCurrency());

            $this->entityManager->flush();

            return $this->redirectToRoute('app_budget_show', ['id' => $budget->getId()]);
        }

        return $this->render('budget/edit.html.twig', [
            'budget' => $budget,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_budget_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Budget $budget, #[CurrentUser] User $user): Response
    {
        // Ensure user can only delete their own budgets
        if ($budget->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        
        if ($this->isCsrfTokenValid('delete'.$budget->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($budget);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_budget_index');
    }

    #[Route('/api/user-comparison', name: 'app_budget_user_comparison', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getUserComparison(BudgetRepository $budgetRepository): JsonResponse
    {
        $comparisonData = $budgetRepository->getUserSpendingComparison();
        
        return $this->json($comparisonData);
    }

    #[Route('/api/export', name: 'app_budget_export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(#[CurrentUser] User $user): Response
    {
        $budgets = $this->budgetService->getUserBudgets($user);
        $exportData = $this->budgetService->exportBudgetData($budgets);

        $response = new Response($exportData);
        $response->headers->set('Content-Type', 'application/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="budgets_export.csv"');

        return $response;
    }
}