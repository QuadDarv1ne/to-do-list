<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\AdvancedSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/tasks')]
#[IsGranted('ROLE_USER')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private AdvancedSearchService $searchService
    ) {}
    
    /**
     * Get all tasks for current user
     */
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        
        $criteria = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'category' => $request->query->get('category'),
            'query' => $request->query->get('q'),
            'sort_by' => $request->query->get('sort_by', 'createdAt'),
            'sort_order' => $request->query->get('sort_order', 'DESC'),
        ];
        
        $tasks = $this->searchService->search($user, $criteria);
        
        // Pagination
        $total = count($tasks);
        $tasks = array_slice($tasks, ($page - 1) * $limit, $limit);
        
        return $this->json([
            'success' => true,
            'data' => array_map(fn($task) => $this->serializeTask($task), $tasks),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get single task
     */
    #[Route('/{id}', name: 'api_task_show', methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $task);
        
        return $this->json([
            'success' => true,
            'data' => $this->serializeTask($task, true)
        ]);
    }
    
    /**
     * Create new task
     */
    #[Route('', name: 'api_task_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['title']) || empty(trim($data['title']))) {
            return $this->json([
                'success' => false,
                'error' => 'Title is required'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setStatus($data['status'] ?? 'pending');
        $task->setPriority($data['priority'] ?? 'medium');
        $task->setUser($this->getUser());
        
        if (isset($data['deadline'])) {
            try {
                $task->setDeadline(new \DateTime($data['deadline']));
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid deadline format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'data' => $this->serializeTask($task),
            'message' => 'Task created successfully'
        ], Response::HTTP_CREATED);
    }
    
    /**
     * Update task
     */
    #[Route('/{id}', name: 'api_task_update', methods: ['PUT', 'PATCH'])]
    public function update(Task $task, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $task);
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['title'])) {
            $task->setTitle($data['title']);
        }
        
        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        
        if (isset($data['status'])) {
            $task->setStatus($data['status']);
        }
        
        if (isset($data['priority'])) {
            $task->setPriority($data['priority']);
        }
        
        if (isset($data['deadline'])) {
            try {
                $task->setDeadline($data['deadline'] ? new \DateTime($data['deadline']) : null);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid deadline format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'data' => $this->serializeTask($task),
            'message' => 'Task updated successfully'
        ]);
    }
    
    /**
     * Delete task
     */
    #[Route('/{id}', name: 'api_task_delete', methods: ['DELETE'])]
    public function delete(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $task);
        
        $this->entityManager->remove($task);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }
    
    /**
     * Get task statistics
     */
    #[Route('/stats/summary', name: 'api_task_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();
        $stats = $this->taskRepository->getQuickStats($user);
        
        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    /**
     * Search tasks
     */
    #[Route('/search', name: 'api_task_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([
                'success' => false,
                'error' => 'Query must be at least 2 characters'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $suggestions = $this->searchService->getSuggestions($user, $query, 10);
        
        return $this->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }
    
    /**
     * Serialize task to array
     */
    private function serializeTask(Task $task, bool $detailed = false): array
    {
        $data = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'created_at' => $task->getCreatedAt()?->format('c'),
            'updated_at' => $task->getUpdatedAt()?->format('c'),
            'deadline' => $task->getDeadline()?->format('c'),
        ];
        
        if ($detailed) {
            $data['category'] = $task->getCategory() ? [
                'id' => $task->getCategory()->getId(),
                'name' => $task->getCategory()->getName()
            ] : null;
            
            $data['assigned_user'] = $task->getAssignedUser() ? [
                'id' => $task->getAssignedUser()->getId(),
                'name' => $task->getAssignedUser()->getFullName(),
                'email' => $task->getAssignedUser()->getEmail()
            ] : null;
            
            $data['creator'] = [
                'id' => $task->getUser()->getId(),
                'name' => $task->getUser()->getFullName(),
                'email' => $task->getUser()->getEmail()
            ];
            
            $data['tags'] = array_map(fn($tag) => [
                'id' => $tag->getId(),
                'name' => $tag->getName()
            ], $task->getTags()->toArray());
        }
        
        return $data;
    }
}
