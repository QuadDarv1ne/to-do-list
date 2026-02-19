<?php

namespace App\Service;

use App\Entity\KnowledgeBaseArticle;
use App\Entity\KnowledgeBaseCategory;
use App\Entity\User;
use App\Repository\KnowledgeBaseArticleRepository;
use App\Repository\KnowledgeBaseCategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class KnowledgeBaseService
{
    public function __construct(
        private KnowledgeBaseArticleRepository $articleRepository,
        private KnowledgeBaseCategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Create a new knowledge base article
     */
    public function createArticle(array $data, User $author): KnowledgeBaseArticle
    {
        $article = new KnowledgeBaseArticle();
        $article->setTitle($data['title']);
        $article->setContent($data['content']);
        $article->setSummary($data['summary'] ?? '');
        $article->setAuthor($author);
        $article->setStatus($data['status'] ?? 'draft');
        $article->setMetaDescription($data['meta_description'] ?? '');
        $article->setSlug($this->generateSlug($data['title']));

        // Set parent article if provided
        if (isset($data['parent_article_id'])) {
            $parentArticle = $this->articleRepository->find($data['parent_article_id']);
            if ($parentArticle) {
                $article->setParentArticle($parentArticle);
            }
        }

        // Add categories
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            foreach ($data['category_ids'] as $categoryId) {
                $category = $this->categoryRepository->find($categoryId);
                if ($category) {
                    $article->addCategory($category);
                }
            }
        }

        // Add tags
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            foreach ($data['tag_names'] as $tagName) {
                $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                if (!$tag) {
                    $tag = new \App\Entity\Tag();
                    $tag->setName($tagName);
                    $this->entityManager->persist($tag);
                }
                $article->addTag($tag);
            }
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    /**
     * Update an existing article
     */
    public function updateArticle(KnowledgeBaseArticle $article, array $data): KnowledgeBaseArticle
    {
        if (isset($data['title'])) {
            $article->setTitle($data['title']);
            $article->setSlug($this->generateSlug($data['title']));
        }
        
        if (isset($data['content'])) {
            $article->setContent($data['content']);
        }
        
        if (isset($data['summary'])) {
            $article->setSummary($data['summary']);
        }
        
        if (isset($data['status'])) {
            $article->setStatus($data['status']);
        }
        
        if (isset($data['meta_description'])) {
            $article->setMetaDescription($data['meta_description']);
        }

        // Update categories
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            // Remove existing categories
            foreach ($article->getCategories() as $category) {
                $article->removeCategory($category);
            }
            
            // Add new categories
            foreach ($data['category_ids'] as $categoryId) {
                $category = $this->categoryRepository->find($categoryId);
                if ($category) {
                    $article->addCategory($category);
                }
            }
        }

        // Update tags
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            // Remove existing tags
            foreach ($article->getTags() as $tag) {
                $article->removeTag($tag);
            }
            
            // Add new tags
            foreach ($data['tag_names'] as $tagName) {
                $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                if (!$tag) {
                    $tag = new \App\Entity\Tag();
                    $tag->setName($tagName);
                    $this->entityManager->persist($tag);
                }
                $article->addTag($tag);
            }
        }

        $this->entityManager->flush();

        return $article;
    }

    /**
     * Delete an article
     */
    public function deleteArticle(KnowledgeBaseArticle $article): void
    {
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    /**
     * Create a new category
     */
    public function createCategory(array $data): KnowledgeBaseCategory
    {
        $category = new KnowledgeBaseCategory();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? '');
        $category->setSlug($this->generateSlug($data['name']));
        $category->setSortOrder($data['sort_order'] ?? 0);

        // Set parent category if provided
        if (isset($data['parent_category_id'])) {
            $parentCategory = $this->categoryRepository->find($data['parent_category_id']);
            if ($parentCategory) {
                $category->setParentCategory($parentCategory);
            }
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * Update a category
     */
    public function updateCategory(KnowledgeBaseCategory $category, array $data): KnowledgeBaseCategory
    {
        if (isset($data['name'])) {
            $category->setName($data['name']);
            $category->setSlug($this->generateSlug($data['name']));
        }
        
        if (isset($data['description'])) {
            $category->setDescription($data['description']);
        }
        
        if (isset($data['sort_order'])) {
            $category->setSortOrder($data['sort_order']);
        }

        $this->entityManager->flush();

        return $category;
    }

    /**
     * Delete a category
     */
    public function deleteCategory(KnowledgeBaseCategory $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    /**
     * Get articles by various criteria
     */
    public function getArticles(
        ?string $status = null, 
        ?User $author = null, 
        ?int $categoryId = null, 
        ?string $tagName = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($author) {
            $qb->andWhere('a.author = :author')
               ->setParameter('author', $author);
        }

        if ($categoryId) {
            $qb->join('a.categories', 'cat')
               ->andWhere('cat.id = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        if ($tagName) {
            $qb->join('a.tags', 't')
               ->andWhere('t.name = :tagName')
               ->setParameter('tagName', $tagName);
        }

        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get published articles with pagination
     */
    public function getPublishedArticles(int $limit = 20, int $offset = 0): array
    {
        return $this->articleRepository->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total count of published articles
     */
    public function getPublishedArticlesCount(): int
    {
        return $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get article by slug
     */
    public function getArticleBySlug(string $slug): ?KnowledgeBaseArticle
    {
        return $this->articleRepository->findOneBy(['slug' => $slug, 'status' => 'published']);
    }

    /**
     * Increment article view count
     */
    public function incrementViewCount(KnowledgeBaseArticle $article): void
    {
        $article->incrementViewCount();
        $this->entityManager->flush();
    }

    /**
     * Search articles
     */
    public function searchArticles(string $query, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.title LIKE :query OR a.content LIKE :query OR a.summary LIKE :query')
            ->setParameter('status', 'published')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get trending articles
     */
    public function getTrendingArticles(int $limit = 10): array
    {
        return $this->articleRepository->findTrending($limit);
    }

    /**
     * Get recent articles
     */
    public function getRecentArticles(int $limit = 10): array
    {
        return $this->articleRepository->findRecent($limit);
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        return $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get category by slug
     */
    public function getCategoryBySlug(string $slug): ?KnowledgeBaseCategory
    {
        return $this->categoryRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * Get articles by category
     */
    public function getArticlesByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        return $this->articleRepository->createQueryBuilder('a')
            ->join('a.categories', 'c')
            ->andWhere('c.id = :categoryId')
            ->andWhere('a.status = :status')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('status', 'published')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get related articles
     */
    public function getRelatedArticles(KnowledgeBaseArticle $article, int $limit = 5): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->andWhere('a.id != :currentId')
            ->andWhere('a.status = :status')
            ->setParameter('currentId', $article->getId())
            ->setParameter('status', 'published');

        // Try to find articles with similar tags
        $tags = $article->getTags();
        if (count($tags) > 0) {
            $tagNames = [];
            foreach ($tags as $tag) {
                $tagNames[] = $tag->getName();
            }
            
            $qb->leftJoin('a.tags', 't')
               ->andWhere('t.name IN (:tagNames)')
               ->setParameter('tagNames', $tagNames);
        } else {
            // Fallback to same category
            $categories = $article->getCategories();
            if (count($categories) > 0) {
                $categoryIds = [];
                foreach ($categories as $category) {
                    $categoryIds[] = $category->getId();
                }
                
                $qb->leftJoin('a.categories', 'c')
                   ->andWhere('c.id IN (:categoryIds)')
                   ->setParameter('categoryIds', $categoryIds);
            }
        }

        $qb->orderBy('a.viewCount', 'DESC')
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Like an article
     */
    public function likeArticle(KnowledgeBaseArticle $article): void
    {
        $article->incrementLikeCount();
        $this->entityManager->flush();
    }

    /**
     * Dislike an article
     */
    public function dislikeArticle(KnowledgeBaseArticle $article): void
    {
        $article->incrementDislikeCount();
        $this->entityManager->flush();
    }

    /**
     * Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return $slug ?: 'untitled';
    }

    /**
     * Get knowledge base statistics
     */
    public function getStatistics(): array
    {
        $totalArticles = $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $publishedArticles = $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();

        $totalCategories = $this->categoryRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $mostPopularArticle = $this->articleRepository->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('a.viewCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_articles' => (int) $totalArticles,
            'published_articles' => (int) $publishedArticles,
            'total_categories' => (int) $totalCategories,
            'most_popular_article' => $mostPopularArticle ? [
                'id' => $mostPopularArticle->getId(),
                'title' => $mostPopularArticle->getTitle(),
                'view_count' => $mostPopularArticle->getViewCount()
            ] : null
        ];
    }
}