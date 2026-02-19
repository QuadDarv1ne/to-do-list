<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 *
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Find comments for a specific task ordered by creation date
     * Optimized with JOIN to preload author data
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find comments by author
     * Optimized with JOIN to preload task data
     */
    public function findByAuthor(User $author): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.task', 't')->addSelect('t')
            ->andWhere('c.author = :author')
            ->setParameter('author', $author)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count comments for a specific task
     */
    public function countByTask(Task $task): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();
    }
    

}
