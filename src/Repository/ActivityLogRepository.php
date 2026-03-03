<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Find activity logs for a specific task ordered by creation date
     */
    public function findByTask(Task $task): array
    {
        return $this->getCached(
            "activity.task.{$task->getId()}",
            fn () => $this->createQueryBuilder('al')
                ->andWhere('al.task = :task')
                ->setParameter('task', $task)
                ->orderBy('al.createdAt', 'DESC')
                ->getQuery()
                ->getResult(),
            300 // Cache for 5 minutes
        );
    }

    /**
     * Find activity logs by user
     */
    public function findByUser(User $user): array
    {
        return $this->getCached(
            "activity.user.{$user->getId()}",
            fn () => $this->createQueryBuilder('al')
                ->andWhere('al.user = :user')
                ->setParameter('user', $user)
                ->orderBy('al.createdAt', 'DESC')
                ->getQuery()
                ->getResult(),
            300 // Cache for 5 minutes
        );
    }

    /**
     * Find recent activity logs
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->getCached(
            "activity.recent.{$limit}",
            fn () => $this->createQueryBuilder('al')
                ->orderBy('al.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
            180 // Cache for 3 minutes
        );
    }

    /**
     * Log a user login event
     */
    public function logLoginEvent(User $user, ?string $ipAddress = null): void
    {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('login');
        $log->setEventType('login');
        $log->setDescription('User logged in' . ($ipAddress ? ' from IP: ' . $ipAddress : ''));
        // createdAt устанавливается автоматически через PrePersist

        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }

    /**
     * Log a user logout event
     */
    public function logLogoutEvent(User $user, ?string $ipAddress = null): void
    {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('logout');
        $log->setEventType('logout');
        $log->setDescription('User logged out' . ($ipAddress ? ' from IP: ' . $ipAddress : ''));
        // createdAt устанавливается автоматически через PrePersist

        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }

    /**
     * Get login events for a user
     */
    public function findLoginEventsForUser(User $user, int $limit = 10): array
    {
        return $this->getCached(
            "activity.login.user.{$user->getId()}.{$limit}",
            fn () => $this->createQueryBuilder('al')
                ->andWhere('al.user = :user')
                ->andWhere('al.eventType = :eventType')
                ->setParameter('user', $user)
                ->setParameter('eventType', 'login')
                ->orderBy('al.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
            300 // Cache for 5 minutes
        );
    }

    /**
     * Get recent login events
     */
    public function findRecentLoginEvents(int $limit = 10): array
    {
        return $this->getCached(
            "activity.login.recent.{$limit}",
            fn () => $this->createQueryBuilder('al')
                ->andWhere('al.eventType = :eventType')
                ->setParameter('eventType', 'login')
                ->orderBy('al.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
            180 // Cache for 3 minutes
        );
    }
}
