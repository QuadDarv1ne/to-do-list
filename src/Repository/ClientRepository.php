<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Find clients by manager
     */
    public function findByManager(User $manager): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.manager = :manager')
            ->setParameter('manager', $manager)
            ->orderBy('c.companyName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search clients by name
     */
    public function searchByName(string $query, ?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.companyName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.companyName', 'ASC')
            ->setMaxResults(10);

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get top clients by revenue
     */
    public function getTopClientsByRevenue(int $limit = 5, ?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.deals', 'd')
            ->select('c, SUM(CASE WHEN d.status = :status THEN d.amount ELSE 0 END) as total_revenue')
            ->setParameter('status', 'won')
            ->groupBy('c.id')
            ->orderBy('total_revenue', 'DESC')
            ->setMaxResults($limit);

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get clients by segment
     */
    public function findBySegment(string $segment, ?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.segment = :segment')
            ->setParameter('segment', $segment)
            ->orderBy('c.companyName', 'ASC');

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get clients by category
     */
    public function findByCategory(string $category, ?User $manager = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.category = :category')
            ->setParameter('category', $category)
            ->orderBy('c.companyName', 'ASC');

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get clients without recent contact
     */
    public function getClientsWithoutRecentContact(int $days = 30, ?User $manager = null): array
    {
        $date = new \DateTime("-{$days} days");
        
        $qb = $this->createQueryBuilder('c')
            ->where('c.lastContactAt < :date OR c.lastContactAt IS NULL')
            ->setParameter('date', $date)
            ->orderBy('c.lastContactAt', 'ASC');

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total clients count
     */
    public function getTotalCount(?User $manager = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($manager) {
            $qb->where('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get new clients for period
     */
    public function getNewClientsCount(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?User $manager = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($manager) {
            $qb->andWhere('c.manager = :manager')
               ->setParameter('manager', $manager);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
