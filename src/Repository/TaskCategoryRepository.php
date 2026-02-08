<?php

namespace App\Repository;

use App\Entity\TaskCategory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskCategory>
 */
class TaskCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskCategory::class);
    }

    /**
     * Find categories by owner user
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('tc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Add more methods as needed
}