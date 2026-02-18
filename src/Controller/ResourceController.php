<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/resource')]
class ResourceController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private TaskRepository $taskRepository
    ) {
    }

    #[Route('', name: 'app_resource_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        // Добавляем пагинацию
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.fullName', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $resources = $qb->getQuery()->getResult();
        $total = $this->userRepository->count(['isActive' => true]);
        
        return $this->render('resource/index.html.twig', [
            'resources' => $resources,
            'page' => $page,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/availability', name: 'app_resource_availability', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailability(): JsonResponse
    {
        // Оптимизация: загружаем пользователей с количеством задач одним запросом
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.fullName, u.email, u.roles, COUNT(t.id) as taskCount')
            ->leftJoin('u.assignedTasks', 't')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('u.id')
            ->setMaxResults(100);
        
        $results = $qb->getQuery()->getResult();
        $availabilityData = [];

        foreach ($results as $result) {
            $capacity = 40;
            $taskCount = $result['taskCount'] ?? 0;
            $utilization = $taskCount > 0 ? min(100, ($taskCount * 8 / $capacity) * 100) : 0;

            $availabilityData[] = [
                'id' => $result['id'],
                'name' => $result['fullName'],
                'email' => $result['email'],
                'role' => is_array($result['roles']) ? ($result['roles'][0] ?? 'USER') : 'USER',
                'capacity' => $capacity,
                'utilization' => round($utilization, 2),
                'available' => $utilization < 80
            ];
        }

        return $this->json($availabilityData);
    }

    #[Route('/workload/{id}', name: 'app_resource_workload', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getResourceWorkload(int $id): JsonResponse
    {
        $resource = $this->userRepository->find($id);
        
        if (!$resource) {
            return $this->json(['error' => 'Resource not found'], 404);
        }

        $assignedTasks = $this->taskRepository->findBy(['assignedUser' => $resource]);
        $workloadData = [
            'id' => $resource->getId(),
            'name' => $resource->getFullName(),
            'email' => $resource->getEmail(),
            'tasks_count' => count($assignedTasks),
            'tasks' => [],
        ];

        foreach ($assignedTasks as $task) {
            $workloadData['tasks'][] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'priority' => $task->getPriority(),
                'status' => $task->getStatus(),
                'dueDate' => $task->getDueDate()?->format('Y-m-d') ?? null,
                'totalTimeSpent' => $task->getTotalTimeSpent(),
            ];
        }

        return $this->json($workloadData);
    }

    #[Route('/allocation', name: 'app_resource_allocation', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function allocateResource(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $taskId = $data['task_id'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        
        if (!$taskId || !$resourceId) {
            return $this->json(['error' => 'Task ID and Resource ID are required'], 400);
        }

        $task = $this->taskRepository->find($taskId);
        $resource = $this->userRepository->find($resourceId);
        
        if (!$task || !$resource) {
            return $this->json(['error' => 'Task or Resource not found'], 404);
        }

        // Assign the task to the resource
        $task->setAssignedUser($resource);
        
        // Here you would typically persist the changes to the database
        // $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Resource allocated successfully',
            'task_id' => $taskId,
            'resource_id' => $resourceId
        ]);
    }

    #[Route('/forecast', name: 'app_resource_forecast', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function forecast(): JsonResponse
    {
        // Оптимизация: загружаем данные одним запросом с JOIN
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.fullName, u.email, SUM(t.estimatedHours) as totalHours, COUNT(t.id) as taskCount')
            ->leftJoin('u.assignedTasks', 't', 'WITH', 't.status != :completed AND t.dueDate >= :now')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('completed', 'completed')
            ->setParameter('now', new \DateTime())
            ->groupBy('u.id')
            ->setMaxResults(100);
        
        $results = $qb->getQuery()->getResult();
        $forecastData = [];

        foreach ($results as $result) {
            $forecastData[] = [
                'id' => $result['id'],
                'name' => $result['fullName'],
                'email' => $result['email'],
                'total_upcoming_hours' => (float)($result['totalHours'] ?? 0),
                'upcoming_tasks_count' => (int)($result['taskCount'] ?? 0),
            ];
        }

        return $this->json($forecastData);
    }

    #[Route('/skills', name: 'app_resource_skills', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSkills(): JsonResponse
    {
        // Оптимизация: выбираем только нужные поля
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.fullName, u.email, u.position, u.department')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->setMaxResults(100);
        
        $results = $qb->getQuery()->getResult();
        $skillsData = [];

        foreach ($results as $result) {
            $skillsData[] = [
                'id' => $result['id'],
                'name' => $result['fullName'],
                'email' => $result['email'],
                'position' => $result['position'] ?? '',
                'department' => $result['department'] ?? '',
            ];
        }

        return $this->json($skillsData);
    }
}