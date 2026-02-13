<?php

namespace App\Repository;

use App\Entity\Tag;
use App\Repository\Traits\CachedRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    //    /**
    //     * @return Tag[] Returns an array of Tag objects
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

    //    public function findOneBySomeField($value): ?Tag
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    
    /**
     * Get tag usage statistics
     */
    public function getTagUsageStats(): array
    {
        $qb = $this->createQueryBuilder('t');
        
        $result = $qb->select('t.id, t.name, t.color, COUNT(task.id) as taskCount')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id, t.name, t.color')
            ->orderBy('taskCount', 'DESC')
            ->getQuery()
            ->getResult();
            
        return $result;
    }
    
    /**
     * Get tag completion rates
     */
    public function getTagCompletionRates(): array
    {
        $qb = $this->createQueryBuilder('t');
        
        $rawResult = $qb->select('
                t.id, 
                t.name, 
                t.color,
                COUNT(task.id) as totalTasks,
                SUM(CASE WHEN task.status = \'completed\' THEN 1 ELSE 0 END) as completedTasks,
                CASE 
                    WHEN COUNT(task.id) > 0 THEN 
                        (SUM(CASE WHEN task.status = \'completed\' THEN 1 ELSE 0 END) * 100.0) / COUNT(task.id)
                    ELSE 0 
                END as completionRate
            ')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id, t.name, t.color')
            ->orderBy('completionRate', 'DESC')
            ->getQuery()
            ->getResult();
            
        // Round the completion rate in PHP
        $result = [];
        foreach ($rawResult as $item) {
            $item['completionRate'] = round($item['completionRate'], 2);
            $result[] = $item;
        }
        
        return $result;
    }
    
    public function findByUser($user): array
    {
        $cacheKey = 'tags_by_user_' . $user->getId();
        
        if ($this->cacheService) {
            return $this->cachedQuery(
                $cacheKey,
                function() use ($user) {
                    return $this->createQueryBuilder('t')
                        ->andWhere('t.user = :user')
                        ->setParameter('user', $user)
                        ->orderBy('t.name', 'ASC')
                        ->getQuery()
                        ->getResult();
                },
                ['user_id' => $user->getId()],
                300 // 5 minutes cache
            );
        }
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
