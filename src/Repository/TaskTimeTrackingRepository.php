<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskTimeTracking;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTimeTracking>
 */
class TaskTimeTrackingRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskTimeTracking::class);
    }

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
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
     *
     * @return TaskTimeTracking[]
     */
    public function findByUser(User $user, array $orderBy = [], ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('ttt')
            ->andWhere('ttt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ttt.dateLogged', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Find active session by user
     */
    public function findOneActiveByUser(User $user): ?TaskTimeTracking
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->orderBy('ttt.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active session by task and user
     */
    public function findOneActiveByTaskAndUser(Task $task, User $user): ?TaskTimeTracking
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.task = :task')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.isActive = :isActive')
            ->setParameter('task', $task)
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active sessions by user
     *
     * @return TaskTimeTracking[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find time tracking records by user and date range
     *
     * @return TaskTimeTracking[]
     */
    public function findByUserAndDateRange(User $user, \DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('ttt')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.dateLogged >= :from')
            ->andWhere('ttt.dateLogged <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('ttt.dateLogged', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total time spent on a task (in seconds)
     */
    public function getTotalTimeByTask(Task $task): int
    {
        $result = $this->createQueryBuilder('ttt')
            ->select('SUM(ttt.durationSeconds) as totalSeconds')
            ->andWhere('ttt.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result['totalSeconds'] ?? 0);
    }

    /**
     * Calculate total time spent by user in date range
     */
    public function getTotalTimeByUser(User $user, \DateTime $from, \DateTime $to): int
    {
        $result = $this->createQueryBuilder('ttt')
            ->select('SUM(ttt.durationSeconds) as totalSeconds')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.dateLogged >= :from')
            ->andWhere('ttt.dateLogged <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result['totalSeconds'] ?? 0);
    }

    /**
     * Get time tracking statistics by category
     */
    public function getStatisticsByCategory(User $user, \DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('ttt')
            ->select('c.name as category, SUM(ttt.durationSeconds) as totalSeconds')
            ->join('ttt.task', 't')
            ->leftJoin('t.category', 'c')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.dateLogged >= :from')
            ->andWhere('ttt.dateLogged <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('c.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get time tracking statistics by day
     */
    public function getStatisticsByDay(User $user, \DateTime $from, \DateTime $to): array
    {
        $results = $this->createQueryBuilder('ttt')
            ->select('DATE(ttt.dateLogged) as date, SUM(ttt.durationSeconds) as totalSeconds')
            ->andWhere('ttt.user = :user')
            ->andWhere('ttt.dateLogged >= :from')
            ->andWhere('ttt.dateLogged <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('DATE(ttt.dateLogged)')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($results as $result) {
            $byDay[$result['date']] = (int) $result['totalSeconds'];
        }

        return $byDay;
    }
}
