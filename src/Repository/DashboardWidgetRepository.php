<?php

namespace App\Repository;

use App\Entity\DashboardWidget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DashboardWidget>
 */
class DashboardWidgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardWidget::class);
    }

    /**
     * @return DashboardWidget[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.user = :userId')
            ->andWhere('w.isActive = true')
            ->setParameter('userId', $userId)
            ->orderBy('w.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMaxPosition(int $userId): int
    {
        $result = $this->createQueryBuilder('w')
            ->select('MAX(w.position)')
            ->where('w.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result[1] ?? 0);
    }
}
