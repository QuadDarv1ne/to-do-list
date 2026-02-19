<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * Get user spending comparison data
     */
    public function getUserSpendingComparison(): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.userId as user_id')
            ->addSelect('SUM(b.usedAmount) as total_spent')
            ->addSelect('SUM(b.amount) as total_budget')
            ->addSelect('COUNT(b.id) as budget_count')
            ->groupBy('b.userId')
            ->orderBy('total_spent', 'DESC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'user_id' => $row['user_id'],
                'total_spent' => (float) $row['total_spent'],
                'total_budget' => (float) $row['total_budget'],
                'budget_count' => (int) $row['budget_count'],
                'utilization_rate' => $row['total_budget'] > 0
                    ? round(($row['total_spent'] / $row['total_budget']) * 100, 2)
                    : 0,
            ];
        }, $results);
    }

    /**
     * Find active budgets by user
     */
    public function findActiveBudgetsByUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :userId')
            ->andWhere('b.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'active')
            ->orderBy('b.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired budgets by user
     */
    public function findExpiredBudgetsByUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :userId')
            ->andWhere('b.endDate < :now OR b.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'expired')
            ->orderBy('b.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find budgets near expiration (within 7 days)
     */
    public function findNearExpirationBudgetsByUser(int $userId): array
    {
        $sevenDaysFromNow = new \DateTime('+7 days');

        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :userId')
            ->andWhere('b.endDate BETWEEN :now AND :sevenDaysFromNow')
            ->andWhere('b.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->setParameter('sevenDaysFromNow', $sevenDaysFromNow)
            ->setParameter('status', 'active')
            ->orderBy('b.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find over budget items for user
     */
    public function findOverBudgetItemsByUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :userId')
            ->andWhere('b.usedAmount > b.amount')
            ->setParameter('userId', $userId)
            ->orderBy('b.usedAmount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Uncomment this method if you need custom queries
    /*
    public function findBySomething(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */
}
