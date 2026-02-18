<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/knowledge-base')]
class KnowledgeBaseController extends AbstractController
{
    #[Route('', name: 'app_knowledge_base_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('knowledge_base/index.html.twig', [
            'articles' => [], // Placeholder - would load actual articles
        ]);
    }

    #[Route('/articles', name: 'app_knowledge_base_articles', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getArticles(): JsonResponse
    {
        $articles = [
            [
                'id' => 1,
                'title' => 'Getting Started with Task Management',
                'summary' => 'Learn how to create and manage tasks effectively',
                'category' => 'Guides',
                'author' => 'Admin User',
                'date' => '2026-01-15',
                'tags' => ['tasks', 'beginner', 'guide'],
                'views' => 124
            ],
            [
                'id' => 2,
                'title' => 'Advanced Resource Allocation Techniques',
                'summary' => 'Optimize your resource allocation with these advanced techniques',
                'category' => 'Best Practices',
                'author' => 'Manager User',
                'date' => '2026-01-20',
                'tags' => ['resources', 'optimization', 'best-practices'],
                'views' => 89
            ],
            [
                'id' => 3,
                'title' => 'Budget Planning Strategies',
                'summary' => 'Effective strategies for planning and managing project budgets',
                'category' => 'Finance',
                'author' => 'Finance User',
                'date' => '2026-01-25',
                'tags' => ['budget', 'finance', 'planning'],
                'views' => 67
            ]
        ];

        return $this->json($articles);
    }

    #[Route('/categories', name: 'app_knowledge_base_categories', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCategories(): JsonResponse
    {
        $categories = [
            [
                'id' => 1,
                'name' => 'Guides',
                'description' => 'Step-by-step guides for common tasks',
                'article_count' => 12
            ],
            [
                'id' => 2,
                'name' => 'Best Practices',
                'description' => 'Industry best practices and tips',
                'article_count' => 8
            ],
            [
                'id' => 3,
                'name' => 'Troubleshooting',
                'description' => 'Solutions to common issues',
                'article_count' => 5
            ],
            [
                'id' => 4,
                'name' => 'Finance',
                'description' => 'Financial planning and management',
                'article_count' => 7
            ]
        ];

        return $this->json($categories);
    }

    #[Route('/search', name: 'app_knowledge_base_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $category = $request->query->get('category', '');
        $tags = $request->query->get('tags', '');

        // Simulate search results based on query
        $results = [
            [
                'id' => 1,
                'title' => 'Getting Started with Task Management',
                'summary' => 'Learn how to create and manage tasks effectively',
                'category' => 'Guides',
                'author' => 'Admin User',
                'date' => '2026-01-15',
                'relevance' => 0.95
            ],
            [
                'id' => 2,
                'title' => 'Advanced Resource Allocation Techniques',
                'summary' => 'Optimize your resource allocation with these advanced techniques',
                'category' => 'Best Practices',
                'author' => 'Manager User',
                'date' => '2026-01-20',
                'relevance' => 0.87
            ]
        ];

        // Filter results based on category if specified
        if ($category) {
            $results = array_filter($results, function($item) use ($category) {
                return stripos($item['category'], $category) !== false;
            });
        }

        return $this->json([
            'query' => $query,
            'results' => array_values($results),
            'total_results' => count($results)
        ]);
    }

    #[Route('/learning-paths', name: 'app_knowledge_base_learning_paths', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getLearningPaths(): JsonResponse
    {
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
                    ['id' => 5, 'title' => 'Reporting and Analytics']
                ],
                'estimated_duration' => '2 weeks',
                'difficulty' => 'Beginner'
            ],
            [
                'id' => 2,
                'title' => 'Advanced Resource Optimization',
                'description' => 'Master advanced techniques for optimizing resource allocation',
                'modules' => [
                    ['id' => 6, 'title' => 'Understanding Capacity Planning'],
                    ['id' => 7, 'title' => 'Load Balancing Techniques'],
                    ['id' => 8, 'title' => 'Forecasting Demand'],
                    ['id' => 9, 'title' => 'Performance Monitoring']
                ],
                'estimated_duration' => '1 week',
                'difficulty' => 'Intermediate'
            ]
        ];

        return $this->json($paths);
    }

    #[Route('/article/{id}', name: 'app_knowledge_base_article', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getArticle(int $id): JsonResponse
    {
        $article = [
            'id' => $id,
            'title' => 'Sample Article Title',
            'content' => '# Sample Article Content
            
This is a sample article content that would provide detailed information about the topic.

## Section 1
Here is some content for the first section of the article.

## Section 2
Here is content for the second section of the article.

### Subsection
This is a subsection with more detailed information.',
            'category' => 'Guides',
            'author' => 'Admin User',
            'date' => '2026-01-15',
            'tags' => ['sample', 'guide', 'help'],
            'related_articles' => [
                ['id' => 2, 'title' => 'Related Article 1'],
                ['id' => 3, 'title' => 'Related Article 2']
            ]
        ];

        return $this->json($article);
    }

    #[Route('/trending', name: 'app_knowledge_base_trending', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTrendingArticles(): JsonResponse
    {
        $trending = [
            [
                'id' => 1,
                'title' => 'New Feature: Advanced Reporting',
                'summary' => 'Learn how to use our new advanced reporting features',
                'views' => 156,
                'week' => 124
            ],
            [
                'id' => 2,
                'title' => 'Best Practices for Remote Team Collaboration',
                'summary' => 'Tips and tricks for managing remote teams effectively',
                'views' => 98,
                'week' => 87
            ],
            [
                'id' => 3,
                'title' => 'Integrating Third-Party Tools',
                'summary' => 'How to connect external tools to enhance productivity',
                'views' => 76,
                'week' => 65
            ]
        ];

        return $this->json($trending);
    }

    #[Route('/recent-updates', name: 'app_knowledge_base_recent_updates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getRecentUpdates(): JsonResponse
    {
        $updates = [
            [
                'id' => 1,
                'title' => 'Updated Security Guidelines',
                'summary' => 'New security measures and best practices',
                'date' => '2026-02-10',
                'type' => 'update'
            ],
            [
                'id' => 2,
                'title' => 'New Integration Available',
                'summary' => 'Added support for popular third-party tools',
                'date' => '2026-02-08',
                'type' => 'feature'
            ],
            [
                'id' => 3,
                'title' => 'API Documentation Updated',
                'summary' => 'Enhanced documentation for developers',
                'date' => '2026-02-05',
                'type' => 'documentation'
            ]
        ];

        return $this->json($updates);
    }
}