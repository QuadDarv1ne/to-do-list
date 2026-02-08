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
    public function countByStatus(?User $user = null, ?bool $isDone = null, ?string $status = null): int
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
        
        if ($status !== null) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
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
            ->where('(
                LOWER(t.name) LIKE :search OR
                LOWER(t.description) LIKE :search OR
                LOWER(au.firstName) LIKE :search OR
                LOWER(au.lastName) LIKE :search OR
                LOWER(cu.firstName) LIKE :search OR
                LOWER(cu.lastName) LIKE :search OR
                LOWER(t.priority) LIKE :search OR
                t.isDone = :doneStatus
            )')
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
    
    /**
     * Find tasks by user and status within date range
     */
    public function findByUserAndStatus(User $user, bool $isDone, \DateTime $fromDate, \DateTime $toDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('(t.assignedUser = :user OR t.createdBy = :user)')
            ->andWhere('t.isDone = :isDone')
            ->andWhere('t.createdAt BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('isDone', $isDone)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Search tasks by various criteria
     */
    public function searchTasks(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c');

        if (!empty($criteria['search'])) {
            $qb->andWhere('LOWER(t.title) LIKE :search OR 
                          LOWER(t.description) LIKE :search OR 
                          LOWER(u.username) LIKE :search OR 
                          LOWER(au.username) LIKE :search OR 
                          LOWER(c.name) LIKE :search')
               ->setParameter('search', '%' . strtolower($criteria['search']) . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['user'])) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (!empty($criteria['startDate'])) {
            $qb->andWhere('t.dueDate >= :startDate')
               ->setParameter('startDate', $criteria['startDate']);
        }

        if (!empty($criteria['endDate'])) {
            $qb->andWhere('t.dueDate <= :endDate')
               ->setParameter('endDate', $criteria['endDate']);
        }
        
        // Advanced filtering options
        if (!empty($criteria['createdAfter'])) {
            $qb->andWhere('t.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['createdAfter']);
        }
        
        if (!empty($criteria['createdBefore'])) {
            $qb->andWhere('t.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['createdBefore']);
        }
        
        if (!empty($criteria['assignedToMe']) && $criteria['assignedToMe']) {
            $qb->andWhere('t.assignedUser = :currentUser')
               ->setParameter('currentUser', $criteria['assignedToMe']);
        }
        
        if (!empty($criteria['createdByMe']) && $criteria['createdByMe']) {
            $qb->andWhere('t.user = :currentUser')
               ->setParameter('currentUser', $criteria['createdByMe']);
        }
        
        if (!empty($criteria['overdue']) && $criteria['overdue']) {
            $qb->andWhere('t.dueDate < :now AND t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }
        
        if (!empty($criteria['tag'])) {
            $qb->join('t.tags', 'jt')
               ->andWhere('jt.id = :tagId')
               ->setParameter('tagId', $criteria['tag']);
        }
        
        if (!empty($criteria['hideCompleted']) && $criteria['hideCompleted']) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }
        
        if (!empty($criteria['sortBy'])) {
            $allowedSortFields = ['title', 'createdAt', 'dueDate', 'priority', 'status'];
            $direction = (!empty($criteria['sortDirection']) && strtoupper($criteria['sortDirection']) === 'ASC') ? 'ASC' : 'DESC';
            
            if ($criteria['sortBy'] === 'tag_count') {
                // Sort by tag count (tasks with more tags first)
                $qb->select('t, COUNT(tg.id) as HIDDEN tag_count')
                   ->leftJoin('t.tags', 'tg')
                   ->groupBy('t.id')
                   ->orderBy('tag_count', $direction)
                   ->addOrderBy('t.createdAt', 'DESC');
            } elseif (in_array($criteria['sortBy'], $allowedSortFields)) {
                $qb->orderBy('t.' . $criteria['sortBy'], $direction);
            } else {
                $qb->orderBy('t.createdAt', 'DESC');
            }
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }

        return $qb->getQuery()
                  ->getResult();
    }
    
    /**
     * Find tasks by tag
     */
    public function findByTag(int $tagId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.tags', 'tag')
            ->andWhere('tag.id = :tagId')
            ->setParameter('tagId', $tagId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find all tasks sorted by tag count (tasks with more tags first)
     */
    public function findAllSortedByTagCount(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t, COUNT(tg.id) as HIDDEN tag_count')
            ->leftJoin('t.tags', 'tg')
            ->groupBy('t.id')
            ->orderBy('tag_count', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Get task completion statistics by priority
     */
    public function getCompletionStatsByPriority(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.priority, COUNT(t.id) as total, SUM(CASE WHEN t.status = \'completed\' THEN 1 ELSE 0 END) as completed')
            ->groupBy('t.priority')
            ->orderBy('t.priority')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $item) {
            $stats[$item['priority']] = [
                'total' => (int)$item['total'],
                'completed' => (int)$item['completed'],
                'percentage' => $item['total'] > 0 ? round(($item['completed'] / $item['total']) * 100, 2) : 0
            ];
        }
        
        return $stats;
    }
}