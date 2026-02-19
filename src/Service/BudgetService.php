<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\User;
use App\Repository\BudgetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class BudgetService
{
    public function __construct(
        private BudgetRepository $budgetRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getUserBudgets(User $user): array
    {
        return $this->budgetRepository->findBy(['userId' => $user->getId()]);
    }

    public function getActiveBudgets(User $user): array
    {
        return $this->budgetRepository->findActiveBudgetsByUser($user->getId());
    }

    public function getExpiredBudgets(User $user): array
    {
        return $this->budgetRepository->findExpiredBudgetsByUser($user->getId());
    }

    public function getBudgetSummary(User $user): array
    {
        $budgets = $this->getUserBudgets($user);
        $totalBudgeted = 0;
        $totalSpent = 0;
        $activeCount = 0;
        $expiredCount = 0;

        foreach ($budgets as $budget) {
            $totalBudgeted += (float)$budget->getAmount();
            $totalSpent += (float)$budget->getUsedAmount();

            if ($budget->isActive()) {
                $activeCount++;
            } else {
                $expiredCount++;
            }
        }

        return [
            'total_budgeted' => $totalBudgeted,
            'total_spent' => $totalSpent,
            'remaining' => $totalBudgeted - $totalSpent,
            'active_count' => $activeCount,
            'expired_count' => $expiredCount,
            'spending_percentage' => $totalBudgeted > 0 ? round(($totalSpent / $totalBudgeted) * 100, 2) : 0,
        ];
    }

    public function isBudgetOverSpent(Budget $budget): bool
    {
        return $budget->isOverBudget();
    }

    public function calculateBudgetHealth(Budget $budget): string
    {
        $percentage = $budget->getPercentageUsed();

        if ($percentage >= 90) {
            return 'critical';
        } elseif ($percentage >= 75) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'caution';
        } else {
            return 'healthy';
        }
    }

    public function exportBudgetData(array $budgets): string
    {
        $csv = "Title,Description,Amount,Used Amount,Start Date,End Date,Status,Currency,Remaining Amount,Percentage Used,Over Budget\n";
        
        foreach ($budgets as $budget) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%.2f","%s"' . "\n",
                $budget->getTitle(),
                $budget->getDescription() ?? '',
                $budget->getAmount(),
                $budget->getUsedAmount(),
                $budget->getStartDate()->format('Y-m-d'),
                $budget->getEndDate()?->format('Y-m-d') ?? '',
                $budget->getStatus(),
                $budget->getCurrency(),
                $budget->getRemainingAmount(),
                $budget->getPercentageUsed(),
                $budget->isOverBudget() ? 'Yes' : 'No'
            );
        }
        
        return $csv;
    }

    public function createBudget(array $data, User $user): Budget
    {
        $budget = new Budget();
        $budget->setTitle($data['title']);
        $budget->setDescription($data['description'] ?? '');
        $budget->setAmount($data['amount']);
        $budget->setUsedAmount($data['used_amount'] ?? '0');
        $budget->setStartDate(new \DateTimeImmutable($data['start_date']));
        $budget->setEndDate(new \DateTimeImmutable($data['end_date']));
        $budget->setStatus($data['status'] ?? 'active');
        $budget->setCurrency($data['currency'] ?? 'USD');
        $budget->setUserId($user->getId());

        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }

    public function updateBudget(Budget $budget, array $data): Budget
    {
        $budget->setTitle($data['title'] ?? $budget->getTitle());
        $budget->setDescription($data['description'] ?? $budget->getDescription());
        $budget->setAmount($data['amount'] ?? $budget->getAmount());
        $budget->setUsedAmount($data['used_amount'] ?? $budget->getUsedAmount());
        $budget->setStartDate(new \DateTimeImmutable($data['start_date'] ?? $budget->getStartDate()->format('Y-m-d')));
        $budget->setEndDate(new \DateTimeImmutable($data['end_date'] ?? $budget->getEndDate()?->format('Y-m-d') ?? 'now'));
        $budget->setStatus($data['status'] ?? $budget->getStatus());
        $budget->setCurrency($data['currency'] ?? $budget->getCurrency());
        $budget->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $budget;
    }

    public function deleteBudget(Budget $budget): void
    {
        $this->entityManager->remove($budget);
        $this->entityManager->flush();
    }

    public function getBudgetById(int $id, User $user): ?Budget
    {
        $budget = $this->budgetRepository->find($id);
        if ($budget && $budget->getUserId() === $user->getId()) {
            return $budget;
        }

        return null;
    }
}