<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * @return Notification[]
     * Optimized with JOIN to preload related task data
     */
    public function findByUserUnread(int|User $user): array
    {
        return $this->getCached(
            "notifications.unread.user.{$user}",
            fn () => $this->createQueryBuilder('n')
                ->leftJoin('n.task', 't')->addSelect('t')
                ->andWhere('n.user = :user')
                ->andWhere('n.isRead = :isRead')
                ->setParameter('user', $user)
                ->setParameter('isRead', false)
                ->orderBy('n.createdAt', 'DESC')
                ->setMaxResults(100)
                ->getQuery()
                ->getResult(),
            180, // Cache for 3 minutes
        );
    }

    /**
     * @return Notification[]
     * Optimized with JOIN to preload related task data
     */
    public function findByUser(int|User $user): array
    {
        return $this->getCached(
            "notifications.user.{$user}.recent",
            fn () => $this->createQueryBuilder('n')
                ->leftJoin('n.task', 't')->addSelect('t')
                ->andWhere('n.user = :user')
                ->setParameter('user', $user)
                ->orderBy('n.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult(),
            300, // Cache for 5 minutes
        );
    }

    /**
     * Count unread notifications for a user (optimized query)
     */
    public function countUnreadByUser(int|User $user): int
    {
        $result = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$result;
    }

    /**
     * Get notifications created after a specific date for a user (optimized for SSE)
     * Optimized with JOIN to preload related task data
     *
     * @return Notification[]
     */
    public function findUnreadForUserSince(\DateTimeInterface $date, int|User $user): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.task', 't')->addSelect('t')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.createdAt > :date')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->setParameter('date', $date)
            ->orderBy('n.createdAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчитать уведомления старше даты
     */
    public function countOlderThan(\DateTime $date): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Удалить прочитанные уведомления старше даты
     */
    public function removeReadOlderThan(\DateTime $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.isRead = :isRead')
            ->andWhere('n.createdAt < :date')
            ->setParameter('isRead', true)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
