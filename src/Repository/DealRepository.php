<?php

namespace App\Repository;

use App\Entity\Deal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deal>
 */
class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    /**
     * Find all deals with optimized joins
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')->addSelect('c')
            ->leftJoin('d.manager', 'm')->addSelect('m')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find deals by manager with optimized joins
     */
    public function findByManager(User $manager): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')->addSelect('c')
            ->leftJoin('d.manager', 'm')->addSelect('m')
            ->where('d.manager = :manager')
            ->setParameter('manager', $manager)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active deals (in progress) with optimized joins
     */
    public function findActiveDeals(?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')->addSelect('c')
            ->leftJoin('d.manager', 'm')->addSelect('m')
            ->where('d.status = :status')
            ->setParameter('status', 'in_progress')
            ->orderBy('d.expectedCloseDate', 'ASC');

        if ($manager) {
            $qb->andWhere('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get deals by stage for funnel
     */
    public function getDealsByStage(?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.stage, COUNT(d.id) as count, SUM(d.amount) as total')
            ->where('d.status = :status')
            ->setParameter('status', 'in_progress')
            ->groupBy('d.stage');

        if ($manager) {
            $qb->andWhere('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total revenue for period
     */

    /**
     * Get deals count by status
     */
    public function getDealsCountByStatus(?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.status, COUNT(d.id) as count')
            ->groupBy('d.status');

        if ($manager) {
            $qb->where('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        $results = $qb->getQuery()->getResult();

        $counts = [
            'in_progress' => 0,
            'won' => 0,
            'lost' => 0,
            'postponed' => 0,
        ];

        foreach ($results as $result) {
            $counts[$result['status']] = $result['count'];
        }

        return $counts;
    }

    /**
     * Get overdue deals with optimized joins
     */
    public function getOverdueDeals(?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')->addSelect('c')
            ->leftJoin('d.manager', 'm')->addSelect('m')
            ->where('d.status = :status')
            ->andWhere('d.expectedCloseDate < :now')
            ->setParameter('status', 'in_progress')
            ->setParameter('now', new \DateTime())
            ->orderBy('d.expectedCloseDate', 'ASC');

        if ($manager) {
            $qb->andWhere('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get conversion rate (won / total closed)
     */
    /**
     * Get deals statistics by status (optimized)
     */
    public function getDealsStatsByStatus(?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.status, COUNT(d.id) as count')
            ->groupBy('d.status');

        if ($manager) {
            $qb->where('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        $results = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'in_progress' => 0,
            'won' => 0,
            'lost' => 0,
            'postponed' => 0,
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Get total revenue (won deals only)
     */
    public function getTotalRevenue(?User $manager = null): float
    {
        $qb = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->where('d.status = :status')
            ->setParameter('status', 'won');

        if ($manager) {
            $qb->andWhere('d.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
    }
}
