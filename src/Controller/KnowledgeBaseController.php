<?php

namespace App\Controller;

use App\Entity\KnowledgeBaseArticle;
use App\Entity\User;
use App\Repository\KnowledgeBaseArticleRepository;
use App\Repository\KnowledgeBaseCategoryRepository;
use App\Service\KnowledgeBaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/knowledge-base')]
class KnowledgeBaseController extends AbstractController
{
    public function __construct(
        private KnowledgeBaseService $knowledgeBaseService,
        private KnowledgeBaseArticleRepository $articleRepository,
        private KnowledgeBaseCategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_knowledge_base_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $offset = ($page - 1) * $limit;

        $articles = $this->knowledgeBaseService->getPublishedArticles($limit, $offset);
        $total = $this->knowledgeBaseService->getPublishedArticlesCount();

        $categories = $this->knowledgeBaseService->getCategories();

        return $this->render('knowledge_base/index.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/articles', name: 'app_knowledge_base_articles', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getArticles(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        $articles = $this->knowledgeBaseService->getPublishedArticles($limit, $offset);

        $formattedArticles = array_map(function ($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => $article->getSummary(),
                'category' => $article->getCategories()->first() ? $article->getCategories()->first()->getName() : 'Uncategorized',
                'author' => $article->getAuthor()->getFullName() ?? $article->getAuthor()->getEmail(),
                'date' => $article->getCreatedAt()->format('Y-m-d'),
                'tags' => array_map(fn ($tag) => $tag->getName(), $article->getTags()->toArray()),
                'views' => $article->getViewCount(),
                'status' => $article->getStatus(),
            ];
        }, $articles);

        return $this->json([
            'articles' => $formattedArticles,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $this->knowledgeBaseService->getPublishedArticlesCount(),
            ],
        ]);
    }

    #[Route('/article/create', name: 'app_knowledge_base_article_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function createArticle(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            $article = $this->knowledgeBaseService->createArticle([
                'title' => $data['title'],
                'content' => $data['content'],
                'summary' => $data['summary'] ?? '',
                'status' => $data['status'] ?? 'draft',
                'meta_description' => $data['meta_description'] ?? '',
                'category_ids' => $data['category_ids'] ?? [],
                'tag_names' => $data['tag_names'] ?? [],
            ], $user);

            return $this->redirectToRoute('app_knowledge_base_article_view', ['id' => $article->getId()]);
        }

        $categories = $this->knowledgeBaseService->getCategories();

        return $this->render('knowledge_base/article_create.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/article/{id}', name: 'app_knowledge_base_article_view', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function viewArticle(int $id, #[CurrentUser] User $user): Response
    {
        $article = $this->articleRepository->find($id);

        if (!$article || $article->getStatus() !== 'published') {
            throw $this->createNotFoundException('Article not found');
        }

        // Increment view count
        $this->knowledgeBaseService->incrementViewCount($article);

        $relatedArticles = $this->knowledgeBaseService->getRelatedArticles($article);

        return $this->render('knowledge_base/article_view.html.twig', [
            'article' => $article,
            'related_articles' => $relatedArticles,
        ]);
    }

    #[Route('/article/{id}/edit', name: 'app_knowledge_base_article_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function editArticle(Request $request, KnowledgeBaseArticle $article, #[CurrentUser] User $user): Response
    {
        // Check if user has permission to edit this article
        if ($article->getAuthor()->getId() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            $this->knowledgeBaseService->updateArticle($article, [
                'title' => $data['title'] ?? $article->getTitle(),
                'content' => $data['content'] ?? $article->getContent(),
                'summary' => $data['summary'] ?? $article->getSummary(),
                'status' => $data['status'] ?? $article->getStatus(),
                'meta_description' => $data['meta_description'] ?? $article->getMetaDescription(),
                'category_ids' => $data['category_ids'] ?? [],
                'tag_names' => $data['tag_names'] ?? [],
            ]);

            return $this->redirectToRoute('app_knowledge_base_article_view', ['id' => $article->getId()]);
        }

        $categories = $this->knowledgeBaseService->getCategories();

        return $this->render('knowledge_base/article_edit.html.twig', [
            'article' => $article,
            'categories' => $categories,
        ]);
    }

    #[Route('/article/{id}/delete', name: 'app_knowledge_base_article_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function deleteArticle(Request $request, KnowledgeBaseArticle $article, #[CurrentUser] User $user): Response
    {
        // Check if user has permission to delete this article
        if ($article->getAuthor()->getId() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $this->knowledgeBaseService->deleteArticle($article);
        }

        return $this->redirectToRoute('app_knowledge_base_index');
    }

    #[Route('/categories', name: 'app_knowledge_base_categories', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCategories(): JsonResponse
    {
        $categories = $this->knowledgeBaseService->getCategories();

        $formattedCategories = array_map(function ($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'article_count' => \count($category->getArticles()),
            ];
        }, $categories);

        return $this->json($formattedCategories);
    }

    #[Route('/search', name: 'app_knowledge_base_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        if (empty($query)) {
            return $this->json(['articles' => [], 'total' => 0]);
        }

        $articles = $this->knowledgeBaseService->searchArticles($query, $limit, $offset);
        $total = \count($this->knowledgeBaseService->searchArticles($query)); // Simplified count

        $formattedResults = array_map(function ($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => $article->getSummary(),
                'author' => $article->getAuthor()->getFullName() ?? $article->getAuthor()->getEmail(),
                'date' => $article->getCreatedAt()->format('Y-m-d'),
                'views' => $article->getViewCount(),
            ];
        }, $articles);

        return $this->json([
            'query' => $query,
            'results' => $formattedResults,
            'total_results' => $total,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    #[Route('/learning-paths', name: 'app_knowledge_base_learning_paths', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getLearningPaths(): JsonResponse
    {
        // This is a simplified implementation - in a real app, you'd have LearningPath entities
        $paths = [
            [
                'id' => 1,
                'title' => 'Project Management Fundamentals',
                'description' => 'Learn the basics of project management with our platform',
                'modules' => [
                    ['id' => 1, 'title' => 'Introduction to Project Management'],
                    ['id' => 2, 'title' => 'Task Creation and Assignment'],
                    ['id' => 3, 'title' => 'Resource Management'],
                    ['id' => 4, 'title' => 'Budget Planning'],
                    ['id' => 5, 'title' => 'Reporting and Analytics'],
                ],
                'estimated_duration' => '2 weeks',
                'difficulty' => 'Beginner',
            ],
            [
                'id' => 2,
                'title' => 'Advanced Resource Optimization',
                'description' => 'Master advanced techniques for optimizing resource allocation',
                'modules' => [
                    ['id' => 6, 'title' => 'Understanding Capacity Planning'],
                    ['id' => 7, 'title' => 'Load Balancing Techniques'],
                    ['id' => 8, 'title' => 'Forecasting Demand'],
                    ['id' => 9, 'title' => 'Performance Monitoring'],
                ],
                'estimated_duration' => '1 week',
                'difficulty' => 'Intermediate',
            ],
        ];

        return $this->json($paths);
    }

    #[Route('/trending', name: 'app_knowledge_base_trending', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTrendingArticles(): JsonResponse
    {
        $articles = $this->knowledgeBaseService->getTrendingArticles(10);

        $formattedArticles = array_map(function ($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => $article->getSummary(),
                'views' => $article->getViewCount(),
                'date' => $article->getCreatedAt()->format('Y-m-d'),
            ];
        }, $articles);

        return $this->json($formattedArticles);
    }

    #[Route('/recent-updates', name: 'app_knowledge_base_recent_updates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getRecentUpdates(): JsonResponse
    {
        $articles = $this->knowledgeBaseService->getRecentArticles(10);

        $formattedUpdates = array_map(function ($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => $article->getSummary(),
                'date' => $article->getCreatedAt()->format('Y-m-d'),
                'type' => 'article',
            ];
        }, $articles);

        return $this->json($formattedUpdates);
    }

    #[Route('/category/{id}/articles', name: 'app_knowledge_base_category_articles', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getArticlesByCategory(int $id, Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        $articles = $this->knowledgeBaseService->getArticlesByCategory($id, $limit, $offset);
        $total = \count($this->articleRepository->findByCategory($id));

        $formattedArticles = array_map(function ($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => $article->getSummary(),
                'author' => $article->getAuthor()->getFullName() ?? $article->getAuthor()->getEmail(),
                'date' => $article->getCreatedAt()->format('Y-m-d'),
                'views' => $article->getViewCount(),
            ];
        }, $articles);

        return $this->json([
            'articles' => $formattedArticles,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ]);
    }

    #[Route('/article/{id}/like', name: 'app_knowledge_base_article_like', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function likeArticle(KnowledgeBaseArticle $article): JsonResponse
    {
        $this->knowledgeBaseService->likeArticle($article);

        return $this->json([
            'success' => true,
            'likes' => $article->getLikeCount(),
        ]);
    }

    #[Route('/article/{id}/dislike', name: 'app_knowledge_base_article_dislike', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function dislikeArticle(KnowledgeBaseArticle $article): JsonResponse
    {
        $this->knowledgeBaseService->dislikeArticle($article);

        return $this->json([
            'success' => true,
            'dislikes' => $article->getDislikeCount(),
        ]);
    }

    #[Route('/statistics', name: 'app_knowledge_base_statistics', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getStatistics(): JsonResponse
    {
        $stats = $this->knowledgeBaseService->getStatistics();

        return $this->json($stats);
    }
}
