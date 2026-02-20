<?php

namespace App\Repository;

use App\Entity\Webhook;
use App\Entity\WebhookLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookLog>
 *
 * @method WebhookLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebhookLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method WebhookLog[]    findAll()
 * @method WebhookLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebhookLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookLog::class);
    }

    /**
     * Get logs for webhook
     *
     * @return WebhookLog[]
     */
    public function findByWebhook(int $webhookId, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.webhook = :webhook')
            ->setParameter('webhook', $webhookId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent logs for user
     *
     * @return WebhookLog[]
     */
    public function findRecentByUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.webhook', 'w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get failed logs for webhook
     *
     * @return WebhookLog[]
     */
    public function findFailedByWebhook(int $webhookId, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.webhook = :webhook')
            ->andWhere('l.isSuccess = false')
            ->setParameter('webhook', $webhookId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get success rate for webhook
     */
    public function getSuccessRate(int $webhookId, int $periodDays = 7): ?float
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) as total', 'SUM(CASE WHEN l.isSuccess = true THEN 1 ELSE 0 END) as successful')
            ->andWhere('l.webhook = :webhook')
            ->andWhere('l.createdAt >= :dateFrom')
            ->setParameter('webhook', $webhookId)
            ->setParameter('dateFrom', (new \DateTime())->modify("-{$periodDays} days"));

        $result = $qb->getQuery()->getSingleResult();

        if ($result['total'] == 0) {
            return null;
        }

        return round(($result['successful'] / $result['total']) * 100, 2);
    }

    /**
     * Get statistics for webhook
     */
    public function getStatistics(int $webhookId, int $periodDays = 7): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) as total')
            ->addSelect('SUM(CASE WHEN l.isSuccess = true THEN 1 ELSE 0 END) as successful')
            ->addSelect('SUM(CASE WHEN l.isSuccess = false THEN 1 ELSE 0 END) as failed')
            ->addSelect('AVG(l.responseTimeMs) as avg_response_time')
            ->andWhere('l.webhook = :webhook')
            ->andWhere('l.createdAt >= :dateFrom')
            ->setParameter('webhook', $webhookId)
            ->setParameter('dateFrom', (new \DateTime())->modify("-{$periodDays} days"));

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs(int $daysOld = 30): int
    {
        $dateFrom = (new \DateTime())->modify("-{$daysOld} days");

        $qb = $this->_em->createQueryBuilder();
        $qb->delete($this->_getEntityName(), 'l')
            ->where('l.createdAt < :dateFrom')
            ->setParameter('dateFrom', $dateFrom);

        return $qb->getQuery()->execute();
    }

    /**
     * Log webhook delivery
     */
    public function logDelivery(
        Webhook $webhook,
        string $event,
        array $payload,
        ?int $statusCode,
        ?int $responseTimeMs,
        bool $isSuccess,
        ?string $errorMessage = null,
        ?array $response = null
    ): WebhookLog {
        $log = new WebhookLog();
        $log->setWebhook($webhook);
        $log->setEvent($event);
        $log->setPayload($payload);
        $log->setStatusCode($statusCode);
        $log->setResponseTimeMs($responseTimeMs);
        $log->setIsSuccess($isSuccess);
        $log->setErrorMessage($errorMessage);
        $log->setResponse($response);

        $this->_em->persist($log);
        $this->_em->flush();

        // Update last triggered at
        $webhook->setLastTriggeredAt(new \DateTime());
        $this->_em->flush();

        return $log;
    }
}
