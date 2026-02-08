<?php

namespace App\Repository;

use App\Entity\TaskTimeTracking;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTimeTracking>
 */
class TaskTimeTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskTimeTracking::class);
    }

    /**
     * Find time tracking records for a specific task
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.task = :task')
            ->setParameter('task', $task)
            ->orderBy('ttt.dateLogged', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find time tracking records for a specific user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ttt.dateLogged', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total time spent on a task
     */
    public function getTotalTimeForTask(Task $task): int
    {
        // This would need to be adjusted based on how time is stored
        $result = $this->createQueryBuilder('ttt')
            ->select('SUM(HOUR(ttt.timeSpent) * 3600 + MINUTE(ttt.timeSpent) * 60 + SECOND(ttt.timeSpent)) as totalSeconds')
            ->andWhere('ttt.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }
}