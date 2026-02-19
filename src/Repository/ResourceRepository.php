<?php

namespace App\Repository;

use App\Entity\Resource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resource>
 */
class ResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    /**
     * Find all resources with their skills eagerly loaded
     */
    public function findAllWithSkills(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.skills', 's')
            ->addSelect('s')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find available resources by date and skills
     */
    public function findAvailableByDateAndSkills(\DateTime $date, array $skillNames = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.skills', 's')
            ->addSelect('s')
            ->where('r.status = :status')
            ->setParameter('status', 'available');

        if (!empty($skillNames)) {
            $qb->andWhere('s.name IN (:skills)')
               ->setParameter('skills', $skillNames);
        }

        return $qb->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find resources with allocations in date range
     */
    public function findWithAllocations(\DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.allocations', 'a')
            ->addSelect('a')
            ->where('a.date BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
