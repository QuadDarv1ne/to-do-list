<?php

namespace App\Repository;

use App\Entity\TaskRecurrence;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskRecurrence>
 */
class TaskRecurrenceRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskRecurrence::class);
    }

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Find all recurrences for a specific user
     */
    public function findByUser($user)
    {
        return $this->createQueryBuilder('tr')
            ->join('tr.task', 't')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find all recurrences
     */
    public function findAllRecurrences()
    {
        return $this->createQueryBuilder('tr')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find active recurrences that need to be processed (have not reached their end date or have no end date)
     */
    public function findActiveRecurrences()
    {
        return $this->createQueryBuilder('tr')
            ->where('tr.endDate IS NULL OR tr.endDate >= :today')
            ->setParameter('today', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find upcoming recurring tasks for a specific user
     */
    public function findUpcomingForUser($user, $limit = 5)
    {
        return $this->createQueryBuilder('tr')
            ->join('tr.task', 't')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('tr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
