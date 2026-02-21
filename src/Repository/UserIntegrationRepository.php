<?php

namespace App\Repository;

use App\Entity\UserIntegration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserIntegration>
 *
 * @method UserIntegration|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserIntegration|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserIntegration[]    findAll()
 * @method UserIntegration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserIntegrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserIntegration::class);
    }

    /**
     * Найти интеграцию пользователя по типу
     */
    public function findByUserAndType(int $userId, string $type): ?UserIntegration
    {
        return $this->findOneBy([
            'user' => $userId,
            'integrationType' => $type,
        ]);
    }

    /**
     * Получить все активные интеграции пользователя
     *
     * @return UserIntegration[]
     */
    public function findActiveByUser(int $userId): array
    {
        return $this->findBy([
            'user' => $userId,
            'isActive' => true,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Получить все интеграции по типу
     *
     * @return UserIntegration[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy([
            'integrationType' => $type,
            'isActive' => true,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Проверить, есть ли у пользователя активная интеграция
     */
    public function hasActiveIntegration(int $userId, string $type): bool
    {
        $result = $this->createQueryBuilder('ui')
            ->select('COUNT(ui.id)')
            ->where('ui.user = :userId')
            ->andWhere('ui.integrationType = :type')
            ->andWhere('ui.isActive = :active')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result['1'] > 0;
    }

    /**
     * Обновить токены интеграции
     */
    public function updateTokens(int $id, ?string $accessToken, ?string $refreshToken = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $qb = $this->createQueryBuilder('ui');
        $qb->update()
            ->set('ui.accessToken', ':accessToken')
            ->set('ui.refreshToken', ':refreshToken')
            ->set('ui.tokenExpiresAt', ':expiresAt')
            ->set('ui.updatedAt', ':updatedAt')
            ->where('ui.id = :id')
            ->setParameter('id', $id)
            ->setParameter('accessToken', $accessToken)
            ->setParameter('refreshToken', $refreshToken)
            ->setParameter('expiresAt', $expiresAt)
            ->setParameter('updatedAt', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Обновить время последней синхронизации
     */
    public function updateLastSync(int $id): void
    {
        $qb = $this->createQueryBuilder('ui');
        $qb->update()
            ->set('ui.lastSyncAt', ':lastSyncAt')
            ->where('ui.id = :id')
            ->setParameter('id', $id)
            ->setParameter('lastSyncAt', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Получить статистику интеграций
     */
    public function getIntegrationStats(): array
    {
        $qb = $this->createQueryBuilder('ui');

        return $qb->select('
            ui.integrationType as type,
            COUNT(ui.id) as total,
            SUM(CASE WHEN ui.isActive = true THEN 1 ELSE 0 END) as active,
            MAX(ui.lastSyncAt) as last_sync
        ')
            ->groupBy('ui.integrationType')
            ->getQuery()
            ->getResult();
    }
}
