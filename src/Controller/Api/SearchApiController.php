<?php

namespace App\Controller\Api;

use App\Service\MeilisearchService;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API для поиска
 */
#[Route('/api/search')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Search')]
class SearchApiController extends AbstractController
{
    #[Route('', name: 'api_search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/search',
        summary: 'Глобальный поиск',
        description: 'Поиск по задачам, пользователям и сделкам',
        tags: ['Search'],
    )]
    #[OA\Parameter(
        name: 'q',
        in: 'query',
        description: 'Поисковый запрос',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'type',
        in: 'query',
        description: 'Тип поиска (tasks, users, deals, all)',
        schema: new OA\Schema(type: 'string', enum: ['tasks', 'users', 'deals', 'all']),
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Количество результатов',
        schema: new OA\Schema(type: 'integer', default: 20),
    )]
    #[OA\Response(
        response: 200,
        description: 'Результаты поиска',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'query', type: 'string'),
                new OA\Property(property: 'results', type: 'object'),
                new OA\Property(property: 'total', type: 'integer'),
            ],
        ),
    )]
    public function search(
        Request $request,
        MeilisearchService $searchService,
        TaskRepository $taskRepo,
        UserRepository $userRepo,
    ): JsonResponse {
        $query = $request->query->get('q', '');
        $type = $request->query->get('type', 'all');
        $limit = (int) $request->query->get('limit', 20);

        if (empty($query)) {
            return $this->json([
                'success' => false,
                'error' => 'Query parameter "q" is required',
            ], 400);
        }

        $results = [];

        if ($type === 'all' || $type === 'tasks') {
            // Meilisearch поиск
            $meiliResults = $searchService->searchTasks($query, [], $limit);

            if (!empty($meiliResults)) {
                $results['tasks'] = $meiliResults;
            } else {
                // Fallback на Doctrine поиск
                $results['tasks'] = $taskRepo->searchTasks([
                    'search' => $query,
                    'user' => $this->getUser(),
                ]);
            }
        }

        if ($type === 'all' || $type === 'users') {
            $meiliResults = $searchService->searchUsers($query, $limit);

            if (!empty($meiliResults)) {
                $results['users'] = $meiliResults;
            } else {
                // Fallback на Doctrine поиск
                $results['users'] = $userRepo->searchUsers($query);
            }
        }

        if ($type === 'all' || $type === 'deals') {
            $meiliResults = $searchService->searchDeals($query, [], $limit);

            if (!empty($meiliResults)) {
                $results['deals'] = $meiliResults;
            } else {
                // Fallback на Doctrine поиск
                $results['deals'] = []; // TODO: добавить DealRepository
            }
        }

        $total = array_sum(array_map('count', $results));

        return $this->json([
            'success' => true,
            'query' => $query,
            'results' => $results,
            'total' => $total,
        ]);
    }

    #[Route('/suggestions', name: 'api_search_suggestions', methods: ['GET'])]
    #[OA\Get(
        path: '/api/search/suggestions',
        summary: 'Поисковые подсказки',
        description: 'Возвращает подсказки для автодополнения',
        tags: ['Search'],
    )]
    #[OA\Parameter(
        name: 'q',
        in: 'query',
        description: 'Часть запроса',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Подсказки',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'suggestions', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'text', type: 'string'),
                        new OA\Property(property: 'type', type: 'string'),
                        new OA\Property(property: 'url', type: 'string'),
                    ],
                )),
            ],
        ),
    )]
    public function suggestions(
        Request $request,
        MeilisearchService $searchService,
        TaskRepository $taskRepo,
    ): JsonResponse {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 5);

        if (strlen($query) < 2) {
            return $this->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        $suggestions = [];

        // Поиск задач
        $tasks = $taskRepo->searchTasks([
            'search' => $query,
            'user' => $this->getUser(),
        ]);

        foreach (array_slice($tasks, 0, $limit) as $task) {
            $suggestions[] = [
                'text' => $task->getTitle(),
                'type' => 'task',
                'url' => '/tasks/' . $task->getId(),
            ];
        }

        return $this->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/stats', name: 'api_search_stats', methods: ['GET'])]
    #[OA\Get(
        path: '/api/search/stats',
        summary: 'Статистика поиска',
        tags: ['Search'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Статистика Meilisearch',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'available', type: 'boolean'),
                new OA\Property(property: 'stats', type: 'object'),
            ],
        ),
    )]
    public function stats(MeilisearchService $searchService): JsonResponse
    {
        $stats = $searchService->getStats();

        return $this->json([
            'success' => true,
            'available' => $stats['available'] ?? false,
            'stats' => $stats,
        ]);
    }
}
