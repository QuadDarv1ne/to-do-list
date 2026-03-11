<?php

namespace App\Repository;

use App\Entity\SocialAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialAccount>
 */
class SocialAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialAccount::class);
    }

    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.provider = :provider')
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getResult();
    }

    public function findByProviderId(string $provider, string $providerId): ?SocialAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'providerId' => $providerId,
        ]);
    }

    public function countByProvider(string $provider): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.provider = :provider')
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
