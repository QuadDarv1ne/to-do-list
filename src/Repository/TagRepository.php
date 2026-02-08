<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
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
        
        $result = $qb->select('
                t.id, 
                t.name, 
                t.color,
                COUNT(task.id) as totalTasks,
                SUM(CASE WHEN task.status = \'completed\' THEN 1 ELSE 0 END) as completedTasks,
                CASE 
                    WHEN COUNT(task.id) > 0 THEN 
                        ROUND((SUM(CASE WHEN task.status = \'completed\' THEN 1 ELSE 0 END) * 100.0) / COUNT(task.id), 2)
                    ELSE 0 
                END as completionRate
            ')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id, t.name, t.color')
            ->orderBy('completionRate', 'DESC')
            ->getQuery()
            ->getResult();
            
        return $result;
    }
}
