<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Count tasks by status and optionally by user
     */
    public function countByStatus(?User $user = null, ?bool $isDone = null): int
    {
        $qb = $this->createQueryBuilder('t');
        
        if ($user !== null) {
            $qb->andWhere('t.assignedUser = :user')
               ->setParameter('user', $user);
        }
        
        if ($isDone !== null) {
            $qb->andWhere('t.isDone = :isDone')
               ->setParameter('isDone', $isDone);
        }
        
        return $qb->select('COUNT(t.id)')
                  ->getQuery()
                  ->getSingleScalarResult();
    }

    /**
     * Find tasks assigned to a specific user
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all tasks ordered by creation date
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}