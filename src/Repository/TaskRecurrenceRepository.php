<?php

namespace App\Repository;

use App\Entity\TaskRecurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskRecurrence>
 */
class TaskRecurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskRecurrence::class);
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