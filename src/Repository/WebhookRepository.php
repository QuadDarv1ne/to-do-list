<?php

namespace App\Repository;

use App\Entity\Webhook;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Webhook>
 *
 * @method Webhook|null find($id, $lockMode = null, $lockVersion = null)
 * @method Webhook|null findOneBy(array $criteria, array $orderBy = null)
 * @method Webhook[]    findAll()
 * @method Webhook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webhook::class);
    }

    /**
     * Get active webhooks for user
     *
     * @return Webhook[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get webhooks by event
     *
     * @return array<array{url: string, secret: string|null, events: array}>
     */
    public function findByEvent(string $event): array
    {
        $webhooks = $this->createQueryBuilder('w')
            ->andWhere('w.isActive = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($webhooks as $webhook) {
            /** @var Webhook $webhook */
            if ($webhook->hasEvent($event)) {
                $result[] = [
                    'id' => $webhook->getId(),
                    'url' => $webhook->getUrl(),
                    'secret' => $webhook->getSecret(),
                    'events' => $webhook->getEvents(),
                ];
            }
        }

        return $result;
    }

    /**
     * Get webhooks by user and event
     *
     * @return array<array{url: string, secret: string|null, events: array}>
     */
    public function findByUserAndEvent(User $user, string $event): array
    {
        $webhooks = $this->findActiveByUser($user);

        $result = [];
        foreach ($webhooks as $webhook) {
            /** @var Webhook $webhook */
            if ($webhook->hasEvent($event)) {
                $result[] = [
                    'id' => $webhook->getId(),
                    'url' => $webhook->getUrl(),
                    'secret' => $webhook->getSecret(),
                    'events' => $webhook->getEvents(),
                ];
            }
        }

        return $result;
    }

    /**
     * Get all webhooks with statistics
     *
     * @return Webhook[]
     */
    public function findAllWithStats(): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.logs', 'l')
            ->addSelect('COUNT(l.id) as HIDDEN total_logs')
            ->addSelect('SUM(CASE WHEN l.isSuccess = true THEN 1 ELSE 0 END) as HIDDEN successful_logs')
            ->addSelect('SUM(CASE WHEN l.isSuccess = false THEN 1 ELSE 0 END) as HIDDEN failed_logs')
            ->groupBy('w.id')
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Update last triggered timestamp
     */
    public function updateLastTriggered(Webhook $webhook): void
    {
        $webhook->setLastTriggeredAt(new \DateTime());
        $this->_em->flush();
    }

    /**
     * Count webhooks by user
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
