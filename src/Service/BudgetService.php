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
}