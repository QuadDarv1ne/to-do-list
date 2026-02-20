<?php

namespace App\Repository;

use App\Entity\Mention;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mention>
 */
class MentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mention::class);
    }

    /**
     * @return Mention[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->select('m, u, ub')
            ->leftJoin('m.mentionedByUser', 'ub')
            ->leftJoin('m.mentionedUser', 'u')
            ->andWhere('m.mentionedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndUser(int $id, User $user): ?Mention
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.mentionedUser = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnread(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.mentionedUser = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Mention $mention, bool $flush = true): void
    {
        $this->getEntityManager()->persist($mention);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Mention $mention, bool $flush = true): void
    {
        $this->getEntityManager()->remove($mention);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
