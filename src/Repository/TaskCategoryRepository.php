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
        $cacheKey = 'task_categories_for_user_' . $user->getId();
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($user) {
                    return $this->performFindByUser($user);
                },
                ['user_id' => $user->getId()],
                300 // 5 minutes cache
            );
        }
        
        return $this->performFindByUser($user);
    }
    
    /**
     * Internal method to find categories by user
     */
    private function performFindByUser($user)
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
        $cacheKey = 'task_category_' . $category_id . '_for_user_' . $user->getId();
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($category_id, $user) {
                    return $this->performFindOneByUser($category_id, $user);
                },
                ['category_id' => $category_id, 'user_id' => $user->getId()],
                600 // 10 minutes cache
            );
        }
        
        return $this->performFindOneByUser($category_id, $user);
    }
    
    /**
     * Internal method to find one category by user
     */
    private function performFindOneByUser($category_id, $user)
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