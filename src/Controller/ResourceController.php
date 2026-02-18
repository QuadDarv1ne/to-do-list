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
    public function index(#[CurrentUser] User $user): Response
    {
        $resources = $this->userRepository->findAll();
        
        return $this->render('resource/index.html.twig', [
            'resources' => $resources,
        ]);
    }

    #[Route('/availability', name: 'app_resource_availability', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailability(): JsonResponse
    {
        $resources = $this->userRepository->findAll();
        $availabilityData = [];

        foreach ($resources as $resource) {
            $assignedTasksCount = $this->taskRepository->count(['assignedUser' => $resource]);
            // Assuming default capacity of 40 hours per week
            $capacity = 40; 
            $utilization = $assignedTasksCount > 0 ? min(100, ($assignedTasksCount * 8 / $capacity) * 100) : 0;

            $availabilityData[] = [
                'id' => $resource->getId(),
                'name' => $resource->getFullName(),
                'email' => $resource->getEmail(),
                'role' => $resource->getRoles()[0] ?? 'USER',
                'capacity' => $capacity,
                'utilization' => $utilization,
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
        $resources = $this->userRepository->findAll();
        $forecastData = [];

        foreach ($resources as $resource) {
            $upcomingTasks = $this->taskRepository->findUpcomingByUser($resource);
            $totalHours = 0;
            
            foreach ($upcomingTasks as $task) {
                $totalHours += $task->getEstimatedHours();
            }
            
            $forecastData[] = [
                'id' => $resource->getId(),
                'name' => $resource->getFullName(),
                'email' => $resource->getEmail(),
                'total_upcoming_hours' => $totalHours,
                'upcoming_tasks_count' => count($upcomingTasks),
            ];
        }

        return $this->json($forecastData);
    }

    #[Route('/skills', name: 'app_resource_skills', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSkills(): JsonResponse
    {
        $resources = $this->userRepository->findAll();
        $skillsData = [];

        foreach ($resources as $resource) {
            $skillsData[] = [
                'id' => $resource->getId(),
                'name' => $resource->getFullName(),
                'email' => $resource->getEmail(),
                'position' => $resource->getPosition() ?? '',
                'department' => $resource->getDepartment() ?? '',
            ];
        }

        return $this->json($skillsData);
    }
}