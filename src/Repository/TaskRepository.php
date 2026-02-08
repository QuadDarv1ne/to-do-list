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
    
    /**
     * Find tasks with deadlines approaching
     */
    public function findUpcomingDeadlines(\DateTimeImmutable $beforeDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deadline IS NOT NULL')
            ->andWhere('t.deadline <= :beforeDate')
            ->andWhere('t.isDone = :isDone')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('isDone', false)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks assigned to user or created by user
     */
    public function findByAssignedToOrCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
            ->andWhere('au = :user OR cu = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks by search query
     */
    public function findBySearchQuery(string $searchQuery): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
            ->where('LOWER(t.name) LIKE :search')
            ->orWhere('LOWER(t.description) LIKE :search')
            ->orWhere('LOWER(au.firstName) LIKE :search')
            ->orWhere('LOWER(au.lastName) LIKE :search')
            ->orWhere('LOWER(cu.firstName) LIKE :search')
            ->orWhere('LOWER(cu.lastName) LIKE :search')
            ->orWhere('LOWER(t.priority) LIKE :search')
            ->orWhere('t.isDone = :doneStatus')
            ->setParameter('search', '%' . strtolower($searchQuery) . '%')
            ->setParameter('doneStatus', $searchQuery === 'completed' || $searchQuery === 'выполнено' || $searchQuery === 'done');
            
        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find tasks by search query for specific user
     */
    public function findBySearchQueryAndUser(string $searchQuery, User $user): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.createdBy', 'cu')
            ->andWhere('(au = :user OR cu = :user)')
            ->andWhere('(
                LOWER(t.name) LIKE :search OR
                LOWER(t.description) LIKE :search OR
                LOWER(au.firstName) LIKE :search OR
                LOWER(au.lastName) LIKE :search OR
                LOWER(cu.firstName) LIKE :search OR
                LOWER(cu.lastName) LIKE :search OR
                LOWER(t.priority) LIKE :search OR
                t.isDone = :doneStatus
            )')
            ->setParameter('user', $user)
            ->setParameter('search', '%' . strtolower($searchQuery) . '%')
            ->setParameter('doneStatus', $searchQuery === 'completed' || $searchQuery === 'выполнено' || $searchQuery === 'done');
            
        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}