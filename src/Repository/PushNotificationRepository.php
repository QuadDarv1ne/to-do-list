<?php

namespace App\Repository;

use App\Entity\PushNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushNotification>
 */
class PushNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushNotification::class);
    }

    /**
     * Получить непрочитанные уведомления для пользователя
     *
     * @return PushNotification[]
     */
    public function findUnreadForUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить последние уведомления
     *
     * @return PushNotification[]
     */
    public function findForUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество непрочитанных уведомлений
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Отметить все уведомления как прочитанные
     */
    public function markAllAsRead(User $user): int
    {
        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.isRead', ':isRead')
            ->set('p.readAt', ':readAt')
            ->where('p.user = :user')
            ->andWhere('p.isRead = :isReadOld')
            ->setParameter('user', $user)
            ->setParameter('isRead', true)
            ->setParameter('isReadOld', false)
            ->setParameter('readAt', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Удалить старые уведомления
     */
    public function removeOlderThan(User $user, \DateTime $date): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.user = :user')
            ->andWhere('p.createdAt < :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Получить уведомления по типу
     *
     * @return PushNotification[]
     */
    public function findByTypeForUser(User $user, string $type, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
