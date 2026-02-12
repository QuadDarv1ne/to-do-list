<?php

namespace App\Repository;

use App\Entity\TaskCategory;
use App\Repository\Traits\CachedRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskCategory>
 *
 * @method TaskCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaskCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaskCategory[]    findAll()
 * @method TaskCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskCategoryRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskCategory::class);
    }

    public function findByUser($user)
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('tc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUser($category_id, $user)
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.id = :id')
            ->andWhere('tc.user = :user')
            ->setParameter('id', $category_id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return TaskCategory[] Returns an array of TaskCategory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TaskCategory
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}