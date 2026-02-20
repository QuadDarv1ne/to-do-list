<?php

namespace App\Repository;

use App\Entity\NotificationTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationTemplate>
 */
class NotificationTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTemplate::class);
    }

    public function findActiveByChannel(string $channel): array
    {
        return $this->createQueryBuilder('nt')
            ->where('nt.channel = :channel')
            ->andWhere('nt.isActive = true')
            ->setParameter('channel', $channel)
            ->orderBy('nt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByKey(string $key): ?NotificationTemplate
    {
        return $this->findOneBy(['key' => $key, 'isActive' => true]);
    }

    public function findTemplateForNotification(string $notificationKey, string $channel): ?NotificationTemplate
    {
        return $this->createQueryBuilder('nt')
            ->where('nt.key = :key')
            ->andWhere('nt.channel = :channel')
            ->andWhere('nt.isActive = true')
            ->setParameter('key', $notificationKey)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult();
    }
}