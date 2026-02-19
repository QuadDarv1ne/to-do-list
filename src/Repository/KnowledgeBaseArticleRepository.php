<?php

namespace App\Repository;

use App\Entity\KnowledgeBaseArticle;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KnowledgeBaseArticle>
 */
class KnowledgeBaseArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KnowledgeBaseArticle::class);
    }

    /**
     * Find published articles
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('kba')
            ->andWhere('kba.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('kba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles by author
     */
    public function findByAuthor(User $author): array
    {
        return $this->createQueryBuilder('kba')
            ->andWhere('kba.author = :author')
            ->setParameter('author', $author)
            ->orderBy('kba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles by category
     */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('kba')
            ->join('kba.categories', 'category')
            ->andWhere('category.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('kba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles by tag
     */
    public function findByTag(string $tagName): array
    {
        return $this->createQueryBuilder('kba')
            ->join('kba.tags', 'tag')
            ->andWhere('tag.name = :tagName')
            ->setParameter('tagName', $tagName)
            ->orderBy('kba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trending articles (most viewed in last week)
     */
    public function findTrending(int $limit = 10): array
    {
        return $this->createQueryBuilder('kba')
            ->andWhere('kba.status = :status')
            ->andWhere('kba.createdAt > :date')
            ->setParameter('status', 'published')
            ->setParameter('date', new \DateTime('-1 week'))
            ->orderBy('kba.viewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent articles
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('kba')
            ->andWhere('kba.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('kba.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search articles by title and content
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('kba')
            ->andWhere('kba.status = :status')
            ->andWhere('kba.title LIKE :query OR kba.content LIKE :query OR kba.summary LIKE :query')
            ->setParameter('status', 'published')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('kba.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
