<?php

namespace App\Controller;

use App\Service\KnowledgeBaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/knowledge-base')]
class KnowledgeBaseController extends AbstractController
{
    public function __construct(
        private KnowledgeBaseService $knowledgeBaseService
    ) {}

    #[Route('/', name: 'app_knowledge_base_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // For now, just render a basic template
        return $this->render('knowledge_base/index.html.twig');
    }

    #[Route('/categories', name: 'app_knowledge_base_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $categories = $this->knowledgeBaseService->getCategories();
        
        return $this->json([
            'categories' => $categories
        ]);
    }

    #[Route('/create', name: 'app_knowledge_base_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $tags = $data['tags'] ?? [];
        
        if (empty($title) || empty($content)) {
            return $this->json([
                'error' => 'Title and content are required'
            ], 400);
        }
        
        $article = $this->knowledgeBaseService->createArticle($title, $content, $this->getUser(), $tags);
        
        return $this->json([
            'article' => $article
        ]);
    }

    #[Route('/search', name: 'app_knowledge_base_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $query = $request->query->get('q', '');
        $filters = $request->query->all('filters') ?: [];
        
        if (empty($query)) {
            return $this->json([
                'error' => 'Search query is required'
            ], 400);
        }
        
        $results = $this->knowledgeBaseService->searchArticles($query, $filters);
        
        return $this->json([
            'results' => $results
        ]);
    }

    #[Route('/popular', name: 'app_knowledge_base_popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $limit = (int)$request->query->get('limit', 10);
        
        $articles = $this->knowledgeBaseService->getPopularArticles($limit);
        
        return $this->json([
            'articles' => $articles
        ]);
    }

    #[Route('/recent', name: 'app_knowledge_base_recent', methods: ['GET'])]
    public function recent(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $limit = (int)$request->query->get('limit', 10);
        
        $articles = $this->knowledgeBaseService->getRecentArticles($limit);
        
        return $this->json([
            'articles' => $articles
        ]);
    }

    #[Route('/related/{id}', name: 'app_knowledge_base_related', methods: ['GET'])]
    public function related(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $limit = (int)$request->query->get('limit', 5);
        
        $articles = $this->knowledgeBaseService->getRelatedArticles($id, $limit);
        
        return $this->json([
            'articles' => $articles
        ]);
    }

    #[Route('/rate/{id}', name: 'app_knowledge_base_rate', methods: ['POST'])]
    public function rate(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $rating = (int)($data['rating'] ?? 0);
        
        if ($rating < 1 || $rating > 5) {
            return $this->json([
                'error' => 'Rating must be between 1 and 5'
            ], 400);
        }
        
        $this->knowledgeBaseService->rateArticle($id, $this->getUser(), $rating);
        
        return $this->json([
            'success' => true
        ]);
    }

    #[Route('/comment/{id}', name: 'app_knowledge_base_comment', methods: ['POST'])]
    public function comment(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $comment = $data['comment'] ?? '';
        
        if (empty($comment)) {
            return $this->json([
                'error' => 'Comment is required'
            ], 400);
        }
        
        $comment = $this->knowledgeBaseService->addComment($id, $this->getUser(), $comment);
        
        return $this->json([
            'comment' => $comment
        ]);
    }

    #[Route('/learning-paths', name: 'app_knowledge_base_learning_paths', methods: ['GET'])]
    public function learningPaths(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $paths = $this->knowledgeBaseService->getLearningPaths();
        
        return $this->json([
            'paths' => $paths
        ]);
    }

    #[Route('/learning-path/create', name: 'app_knowledge_base_learning_path_create', methods: ['POST'])]
    public function createLearningPath(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $articleIds = $data['article_ids'] ?? [];
        $description = $data['description'] ?? '';
        
        if (empty($name) || empty($articleIds)) {
            return $this->json([
                'error' => 'Name and article IDs are required'
            ], 400);
        }
        
        $path = $this->knowledgeBaseService->createLearningPath($name, $articleIds, $description);
        
        return $this->json([
            'path' => $path
        ]);
    }

    #[Route('/progress/{pathId}', name: 'app_knowledge_base_progress', methods: ['GET'])]
    public function progress(int $pathId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $progress = $this->knowledgeBaseService->getUserProgress($this->getUser(), $pathId);
        
        return $this->json([
            'progress' => $progress
        ]);
    }

    #[Route('/stats/{id}', name: 'app_knowledge_base_stats', methods: ['GET'])]
    public function stats(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $stats = $this->knowledgeBaseService->getArticleStats($id);
        
        return $this->json([
            'stats' => $stats
        ]);
    }

    #[Route('/export', name: 'app_knowledge_base_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $format = $request->query->get('format', 'pdf');
        $supportedFormats = ['pdf', 'docx', 'markdown'];
        
        if (!in_array($format, $supportedFormats)) {
            return $this->json([
                'error' => 'Unsupported format. Supported formats: ' . implode(', ', $supportedFormats)
            ], 400);
        }
        
        $export = $this->knowledgeBaseService->exportKnowledgeBase($format);
        
        $response = new Response($export);
        
        switch ($format) {
            case 'pdf':
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set('Content-Disposition', 'attachment; filename=knowledge_base.pdf');
                break;
            case 'docx':
                $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                $response->headers->set('Content-Disposition', 'attachment; filename=knowledge_base.docx');
                break;
            case 'markdown':
                $response->headers->set('Content-Type', 'text/markdown');
                $response->headers->set('Content-Disposition', 'attachment; filename=knowledge_base.md');
                break;
        }
        
        return $response;
    }

    #[Route('/import', name: 'app_knowledge_base_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $format = $request->query->get('format', 'markdown');
        $file = $request->files->get('file');
        
        if (!$file) {
            return $this->json([
                'error' => 'File is required'
            ], 400);
        }
        
        $importedCount = $this->knowledgeBaseService->importArticles($file->getPathname(), $format);
        
        return $this->json([
            'imported_count' => $importedCount
        ]);
    }

    #[Route('/quiz/create', name: 'app_knowledge_base_quiz_create', methods: ['POST'])]
    public function createQuiz(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $title = $data['title'] ?? '';
        $questions = $data['questions'] ?? [];
        
        if (empty($title) || empty($questions)) {
            return $this->json([
                'error' => 'Title and questions are required'
            ], 400);
        }
        
        $quiz = $this->knowledgeBaseService->createQuiz($title, $questions);
        
        return $this->json([
            'quiz' => $quiz
        ]);
    }

    #[Route('/quiz/take/{id}', name: 'app_knowledge_base_quiz_take', methods: ['POST'])]
    public function takeQuiz(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $answers = $data['answers'] ?? [];
        
        if (empty($answers)) {
            return $this->json([
                'error' => 'Answers are required'
            ], 400);
        }
        
        $result = $this->knowledgeBaseService->takeQuiz($id, $this->getUser(), $answers);
        
        return $this->json([
            'result' => $result
        ]);
    }

    #[Route('/certifications', name: 'app_knowledge_base_certifications', methods: ['GET'])]
    public function certifications(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $certifications = $this->knowledgeBaseService->getCertifications($this->getUser());
        
        return $this->json([
            'certifications' => $certifications
        ]);
    }
}