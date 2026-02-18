<?php

namespace App\Repository;

use App\Entity\Budget;
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

        return array_map(function($row) {
            return [
                'user_id' => $row['user_id'],
                'total_spent' => (float) $row['total_spent'],
                'total_budget' => (float) $row['total_budget'],
                'budget_count' => (int) $row['budget_count'],
                'utilization_rate' => $row['total_budget'] > 0 
                    ? round(($row['total_spent'] / $row['total_budget']) * 100, 2) 
                    : 0
            ];
        }, $results);
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