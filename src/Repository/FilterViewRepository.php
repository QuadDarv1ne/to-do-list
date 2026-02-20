<?php

namespace App\Repository;

use App\Entity\FilterView;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FilterView>
 *
 * @method FilterView|null find($id, $lockMode = null, $lockVersion = null)
 * @method FilterView|null findOneBy(array $criteria, array $orderBy = null)
 * @method FilterView[]    findAll()
 * @method FilterView[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FilterViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilterView::class);
    }

    /**
     * @return FilterView[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndId(User $user, int $id): ?FilterView
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDefaultView(User $user): ?FilterView
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.isDefault = true')
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FilterView[]
     */
    public function findSharedWithUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.sharedWithUsers', 'u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FilterView[]
     */
    public function findGlobalViews(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isShared = true')
            ->andWhere('f.user IS NULL')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(FilterView $filterView, bool $flush = true): void
    {
        $this->getEntityManager()->persist($filterView);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilterView $filterView, bool $flush = true): void
    {
        $this->getEntityManager()->remove($filterView);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
