<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\PerformanceOptimizerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * REST API Controller для задач
 *
 * @see docs/API_DOCUMENTATION.md для полной документации
 */
#[Route('/api/tasks')]
#[IsGranted('ROLE_USER')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PerformanceOptimizerService $optimizer,
    ) {
    }

    /**
     * GET /api/tasks
     * Получить список задач с пагинацией и фильтрами
     */
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    public function list(Request $request, TaskRepository $taskRepo): JsonResponse
    {
        $user = $this->getUser();

        // Параметры запроса
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $search = $request->query->get('search');
        $dueDate = $request->query->get('due_date');

        // Кэширование запроса
        $cacheKey = \sprintf(
            'api_tasks_%d_%d_%s_%s_%s_%s',
            $user->getId(),
            $page,
            $status ?? 'all',
            $priority ?? 'all',
            $search ?? '',
            $dueDate ?? '',
        );

        $result = $this->optimizer->cacheQuery($cacheKey, function () use (
            $taskRepo,
            $user,
            $page,
            $limit,
            $status,
            $priority,
            $search,
            $dueDate
        ) {
            $query = $taskRepo->createQueryBuilder('t')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->orderBy('t.createdAt', 'DESC');

            // Фильтры
            if ($status) {
                $query->andWhere('t.status = :status')
                    ->setParameter('status', $status);
            }

            if ($priority) {
                $query->andWhere('t.priority = :priority')
                    ->setParameter('priority', $priority);
            }

            if ($search) {
                $query->andWhere('t.title LIKE :search OR t.description LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            if ($dueDate) {
                $query->andWhere('t.dueDate = :dueDate')
                    ->setParameter('dueDate', $dueDate);
            }

            // Пагинация
            $offset = ($page - 1) * $limit;
            $query->setFirstResult($offset)
                ->setMaxResults($limit);

            $tasks = $query->getQuery()->getResult();

            // Общее количество для пагинации
            $totalQuery = $taskRepo->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user')
                ->setParameter('user', $user);

            if ($status) {
                $totalQuery->andWhere('t.status = :status')
                    ->setParameter('status', $status);
            }

            $total = (int) $totalQuery->getQuery()->getSingleScalarResult();

            return [
                'tasks' => $tasks,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ];
        }, 60); // Кэш на 1 минуту

        return $this->json([
            'success' => true,
            'data' => $result['tasks'],
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'pages' => $result['pages'],
            ],
        ]);
    }

    /**
     * GET /api/tasks/{id}
     * Получить конкретную задачу
     */
    #[Route('/{id}', name: 'api_tasks_get', methods: ['GET'])]
    public function get(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $task);

        return $this->json([
            'success' => true,
            'data' => $task,
        ], context: ['groups' => ['task:read']]);
    }

    /**
     * POST /api/tasks
     * Создать новую задачу
     */
    #[Route('', name: 'api_tasks_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['title'])) {
            return $this->json([
                'success' => false,
                'error' => 'Title is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate priority
        $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, $allowedPriorities)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid priority. Allowed values: ' . implode(', ', $allowedPriorities),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate status
        $allowedStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $status = $data['status'] ?? 'pending';
        if (!in_array($status, $allowedStatuses)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid status. Allowed values: ' . implode(', ', $allowedStatuses),
            ], Response::HTTP_BAD_REQUEST);
        }

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setPriority($priority);
        $task->setStatus($status);
        $task->setUser($this->getUser());

        if (isset($data['due_date'])) {
            try {
                $task->setDueDate(new \DateTimeImmutable($data['due_date']));
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid date format for due_date',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->em->persist($task);
        $this->em->flush();

        // Инвалидация кэша
        $this->optimizer->invalidateByTags(['user_tasks']);

        return $this->json([
            'success' => true,
            'data' => $task,
            'message' => 'Task created successfully',
        ], Response::HTTP_CREATED, [], ['groups' => ['task:read']]);
    }

    /**
     * PUT /api/tasks/{id}
     * Обновить задачу
     */
    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'])]
    public function update(Request $request, Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $task);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Обновление полей
        if (isset($data['title'])) {
            if (empty($data['title'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Title cannot be empty',
                ], Response::HTTP_BAD_REQUEST);
            }
            $task->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }

        if (isset($data['priority'])) {
            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($data['priority'], $allowedPriorities)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid priority. Allowed values: ' . implode(', ', $allowedPriorities),
                ], Response::HTTP_BAD_REQUEST);
            }
            $task->setPriority($data['priority']);
        }

        if (isset($data['status'])) {
            $allowedStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($data['status'], $allowedStatuses)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid status. Allowed values: ' . implode(', ', $allowedStatuses),
                ], Response::HTTP_BAD_REQUEST);
            }
            $task->setStatus($data['status']);
        }

        if (isset($data['due_date'])) {
            try {
                $task->setDueDate(new \DateTimeImmutable($data['due_date']));
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid date format for due_date',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $task->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        // Инвалидация кэша
        $this->optimizer->invalidateByTags(['user_tasks', 'task_' . $task->getId()]);

        return $this->json([
            'success' => true,
            'data' => $task,
            'message' => 'Task updated successfully',
        ], context: ['groups' => ['task:read']]);
    }

    /**
     * PATCH /api/tasks/{id}/toggle
     * Переключить статус задачи
     */
    #[Route('/{id}/toggle', name: 'api_tasks_toggle', methods: ['PATCH'])]
    public function toggle(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $task);

        $newStatus = $task->getStatus() === 'completed' ? 'pending' : 'completed';
        $task->setStatus($newStatus);
        $task->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $task->getId(),
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? $task->getCompletedAt() : null,
            ],
            'message' => 'Task status toggled',
        ]);
    }

    /**
     * DELETE /api/tasks/{id}
     * Удалить задачу
     */
    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'])]
    public function delete(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $task);

        $taskId = $task->getId();
        $this->em->remove($task);
        $this->em->flush();

        // Инвалидация кэша
        $this->optimizer->invalidateByTags(['user_tasks', 'task_' . $taskId]);

        return $this->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * GET /api/tasks/statistics
     * Получить статистику задач
     */
    #[Route('/statistics', name: 'api_tasks_statistics', methods: ['GET'])]
    public function statistics(TaskRepository $taskRepo): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = 'api_tasks_stats_' . $user->getId();

        $stats = $this->optimizer->cacheQuery($cacheKey, function () use ($taskRepo, $user) {
            $qb = $taskRepo->createQueryBuilder('t')
                ->select(
                    'COUNT(t.id) as total',
                    'SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed',
                    'SUM(CASE WHEN t.status = :pending THEN 1 ELSE 0 END) as pending',
                    'SUM(CASE WHEN t.status = :in_progress THEN 1 ELSE 0 END) as in_progress',
                    'SUM(CASE WHEN t.priority = :high THEN 1 ELSE 0 END) as high_priority',
                    'SUM(CASE WHEN t.dueDate < :today AND t.status != :completed THEN 1 ELSE 0 END) as overdue',
                )
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('pending', 'pending')
                ->setParameter('in_progress', 'in_progress')
                ->setParameter('high', 'high')
                ->setParameter('today', new \DateTime());

            return $qb->getQuery()->getSingleResult();
        }, 300); // 5 минут

        return $this->json([
            'success' => true,
            'data' => [
                'total' => (int) ($stats['total'] ?? 0),
                'completed' => (int) ($stats['completed'] ?? 0),
                'pending' => (int) ($stats['pending'] ?? 0),
                'in_progress' => (int) ($stats['in_progress'] ?? 0),
                'high_priority' => (int) ($stats['high_priority'] ?? 0),
                'overdue' => (int) ($stats['overdue'] ?? 0),
            ],
        ]);
    }

    /**
     * POST /api/tasks/bulk-update
     * Массовое обновление задач
     */
    #[Route('/bulk-update', name: 'api_tasks_bulk_update', methods: ['POST'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['task_ids']) || empty($data['changes'])) {
            return $this->json([
                'success' => false,
                'error' => 'task_ids and changes are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $taskIds = $data['task_ids'];
        $changes = $data['changes'];

        // Validate changes before applying
        if (isset($changes['status'])) {
            $allowedStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($changes['status'], $allowedStatuses)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid status. Allowed values: ' . implode(', ', $allowedStatuses),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($changes['priority'])) {
            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($changes['priority'], $allowedPriorities)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid priority. Allowed values: ' . implode(', ', $allowedPriorities),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $updated = 0;
        $tasks = $this->em->getRepository(Task::class)->findBy(['id' => $taskIds]);

        foreach ($tasks as $task) {
            $this->denyAccessUnlessGranted('edit', $task);

            if (isset($changes['status'])) {
                $task->setStatus($changes['status']);
            }

            if (isset($changes['priority'])) {
                $task->setPriority($changes['priority']);
            }

            $updated++;
        }

        $this->em->flush();

        // Инвалидация кэша
        $this->optimizer->invalidateByTags(['user_tasks']);

        return $this->json([
            'success' => true,
            'data' => ['updated' => $updated],
            'message' => "{$updated} tasks updated",
        ]);
    }
}
