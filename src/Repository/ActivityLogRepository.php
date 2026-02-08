<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find activity logs for a specific task ordered by creation date
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.task = :task')
            ->setParameter('task', $task)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activity logs by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.user = :user')
            ->setParameter('user', $user)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent activity logs
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}