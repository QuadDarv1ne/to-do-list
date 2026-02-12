<?php

namespace App\Repository;

use App\Entity\TaskNotification;
use App\Repository\Traits\CachedRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskNotification>
 *
 * @method TaskNotification|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaskNotification|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaskNotification[]    findAll()
 * @method TaskNotification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskNotificationRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskNotification::class);
    }

    /**
     * Find notifications by recipient
     */
    public function findByRecipient($recipient)
    {
        return $this->createQueryBuilder('tn')
            ->andWhere('tn.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('tn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find notifications for a user
     */
    public function findForUser($user)
    {
        return $this->createQueryBuilder('tn')
            ->andWhere('tn.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('tn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Count unread notifications for a recipient
     */
    public function countUnreadByRecipient($recipient)
    {
        return $this->createQueryBuilder('tn')
            ->select('COUNT(tn.id)')
            ->andWhere('tn.recipient = :recipient')
            ->andWhere('tn.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}