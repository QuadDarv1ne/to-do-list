<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\APIOptimizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/tasks')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private APIOptimizationService $apiOptimizer
    ) {}

    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request, #[CurrentUser] User $user): Response
    {
        $startTime = microtime(true);
        
        // Санитизация и валидация параметров
        $params = $this->apiOptimizer->sanitizeAPIParams($request, [
            'status' => ['type' => 'string', 'default' => null],
            'priority' => ['type' => 'string', 'default' => null],
            'search' => ['type' => 'string', 'max_length' => 100],
            'category_id' => ['type' => 'int', 'default' => null],
            'assigned_to_me' => ['type' => 'bool', 'default' => false],
            'created_by_me' => ['type' => 'bool', 'default' => false],
            'page' => ['type' => 'int', 'default' => 1],
            'limit' => ['type' => 'int', 'default' => 20]
        ]);

        // Проверка ETag для условного запроса
        $cacheKey = 'tasks_' . $user->getId() . '_' . md5(serialize($params));
        
        // Кэширование с оптимизацией
        $result = $this->apiOptimizer->cacheAPIResponse(
            'tasks_list',
            array_merge($params, ['user_id' => $user->getId()]),
            function() use ($params, $user) {
                return $this->fetchTasks($params, $user);
            },
            300 // 5 минут кэш
        );

        $executionTime = microtime(true) - $startTime;
        $dataSize = strlen(json_encode($result['data']));
        
        // Логирование производительности
        $this->apiOptimizer->logAPIPerformance('tasks_list', $executionTime, $dataSize);

        // Добавляем метаданные
        $response = $this->apiOptimizer->addResponseMetadata($result['data'], [
            'cached' => isset($result['cached_at']),
            'execution_time' => $executionTime,
            'cache_time' => $result['cached_at'] ?? null
        ]);

        return $this->apiOptimizer->createOptimizedResponse($response);
    }

    #[Route('/{id}', name: 'api_task_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(int $id, #[CurrentUser] User $user): Response
    {
        $startTime = microtime(true);

        $result = $this->apiOptimizer->cacheAPIResponse(
            'task_details',
            ['id' => $id, 'user_id' => $user->getId()],
            function() use ($id, $user) {
                return $this->fetchTaskDetails($id, $user);
            },
            600 // 10 минут кэш для деталей
        );

        if (!$result['data']) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $executionTime = microtime(true) - $startTime;
        $this->apiOptimizer->logAPIPerformance('task_show', $executionTime, strlen(json_encode($result['data'])));

        return $this->apiOptimizer->createOptimizedResponse($result['data']);
    }

    #[Route('/stats', name: 'api_tasks_stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function stats(#[CurrentUser] User $user): Response
    {
        $result = $this->apiOptimizer->cacheAPIResponse(
            'task_stats',
            ['user_id' => $user->getId()],
            function() use ($user) {
                return $this->fetchTaskStats($user);
            },
            180 // 3 минуты кэш для статистики
        );

        return $this->apiOptimizer->createOptimizedResponse($result['data']);
    }

    #[Route('/check-duplicate', name: 'api_task_check_duplicate', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function checkDuplicate(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $title = $request->query->get('title');
        
        if (!$title) {
            return $this->json(['exists' => false]);
        }

        // Быстрая проверка дубликатов
        $exists = $this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('LOWER(t.title) = LOWER(:title)')
            ->setParameter('user', $user)
            ->setParameter('title', $title)
            ->getQuery()
            ->getSingleScalarResult() > 0;

        return $this->json(['exists' => $exists]);
    }

    #[Route('/suggestions', name: 'api_task_suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function suggestions(Request $request, #[CurrentUser] User $user): Response
    {
        $query = $request->query->get('q', '');
        
        $result = $this->apiOptimizer->cacheAPIResponse(
            'task_suggestions',
            ['query' => $query, 'user_id' => $user->getId()],
            function() use ($query, $user) {
                return $this->fetchSuggestions($query, $user);
            },
            900 // 15 минут кэш для предложений
        );

        return $this->apiOptimizer->createOptimizedResponse($result['data']);
    }

    /**
     * Получение списка задач с оптимизированным запросом
     */
    private function fetchTasks(array $params, User $user): array
    {
        $pagination = $this->apiOptimizer->optimizePagination(
            new Request(['page' => $params['page'], 'limit' => $params['limit']])
        );

        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au, c, tags')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tags');

        // Фильтры доступа
        if ($params['assigned_to_me']) {
            $qb->andWhere('t.assignedUser = :user');
        } elseif ($params['created_by_me']) {
            $qb->andWhere('t.user = :user');
        } else {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user');
        }
        $qb->setParameter('user', $user);

        // Дополнительные фильтры
        if ($params['status']) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $params['status']);
        }

        if ($params['priority']) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $params['priority']);
        }

        if ($params['category_id']) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $params['category_id']);
        }

        if ($params['search']) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $params['search'] . '%');
        }

        $qb->orderBy('t.createdAt', 'DESC')
           ->setFirstResult($pagination['offset'])
           ->setMaxResults($pagination['limit']);

        $tasks = $qb->getQuery()->getResult();

        // Подсчет общего количества
        $totalQb = clone $qb;
        $total = $totalQb->select('COUNT(DISTINCT t.id)')
                        ->setFirstResult(0)
                        ->setMaxResults(null)
                        ->getQuery()
                        ->getSingleScalarResult();

        return [
            'tasks' => array_map([$this, 'serializeTask'], $tasks),
            'pagination' => [
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total' => (int) $total,
                'pages' => ceil($total / $pagination['limit'])
            ]
        ];
    }

    /**
     * Получение деталей задачи
     */
    private function fetchTaskDetails(int $id, User $user): ?array
    {
        $task = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au, c, tags, comments, attachments')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tags')
            ->leftJoin('t.comments', 'comments')
            ->leftJoin('t.attachments', 'attachments')
            ->where('t.id = :id')
            ->andWhere('t.user = :user OR t.assignedUser = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $task ? $this->serializeTaskDetails($task) : null;
    }

    /**
     * Получение статистики задач
     */
    private function fetchTaskStats(User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as count')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->groupBy('t.status');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Получение предложений для автодополнения
     */
    private function fetchSuggestions(string $query, User $user): array
    {
        if (strlen($query) < 2) {
            // Возвращаем недавние задачи
            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->select('t.id, t.title, t.priority, c.id as category_id')
                ->leftJoin('t.category', 'c')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        } else {
            // Поиск по запросу
            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->select('t.id, t.title, t.priority, c.id as category_id')
                ->leftJoin('t.category', 'c')
                ->where('t.user = :user')
                ->andWhere('t.title LIKE :query')
                ->setParameter('user', $user)
                ->setParameter('query', '%' . $query . '%')
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        return array_map(function($task) {
            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'priority' => $task['priority'],
                'category' => $task['category_id']
            ];
        }, $tasks);
    }

    /**
     * Сериализация задачи для API
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'created_at' => $task->getCreatedAt()->format('c'),
            'updated_at' => $task->getUpdatedAt()?->format('c'),
            'due_date' => $task->getDueDate()?->format('c'),
            'completed_at' => $task->getCompletedAt()?->format('c'),
            'user' => [
                'id' => $task->getUser()->getId(),
                'name' => $task->getUser()->getFullName()
            ],
            'assigned_user' => $task->getAssignedUser() ? [
                'id' => $task->getAssignedUser()->getId(),
                'name' => $task->getAssignedUser()->getFullName()
            ] : null,
            'category' => $task->getCategory() ? [
                'id' => $task->getCategory()->getId(),
                'name' => $task->getCategory()->getName()
            ] : null,
            'tags' => array_map(fn($tag) => [
                'id' => $tag->getId(),
                'name' => $tag->getName()
            ], $task->getTags()->toArray())
        ];
    }

    /**
     * Детальная сериализация задачи
     */
    private function serializeTaskDetails(Task $task): array
    {
        $basic = $this->serializeTask($task);
        
        $basic['comments'] = array_map(fn($comment) => [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'author' => $comment->getAuthor()->getFullName(),
            'created_at' => $comment->getCreatedAt()->format('c')
        ], $task->getComments()->toArray());

        $basic['attachments'] = array_map(fn($attachment) => [
            'id' => $attachment->getId(),
            'filename' => $attachment->getFilename(),
            'size' => $attachment->getSize(),
            'mime_type' => $attachment->getMimeType()
        ], $task->getAttachments()->toArray());

        return $basic;
    }
}