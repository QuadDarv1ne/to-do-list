<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\ResourceManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/resources')]
class ResourceController extends AbstractController
{
    public function __construct(
        private ResourceManagementService $resourceManagementService,
        private UserRepository $userRepository,
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_resource_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        // For now, just render a basic template
        return $this->render('resource/index.html.twig');
    }

    #[Route('/availability', name: 'app_resource_availability', methods: ['GET'])]
    public function availability(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $userId = (int)$request->query->get('user_id');
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime();
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime('+1 month');
        
        // Get user by ID
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        $availability = $this->resourceManagementService->getResourceAvailability($user, $from, $to);
        
        return $this->json([
            'availability' => $availability
        ]);
    }

    #[Route('/workload', name: 'app_resource_workload', methods: ['GET'])]
    public function workload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $userId = (int)$request->query->get('user_id');
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime();
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime('+1 month');
        
        // Get user by ID
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        $workload = $this->resourceManagementService->getResourceWorkload($user, $from, $to);
        
        return $this->json([
            'workload' => $workload
        ]);
    }

    #[Route('/balance', name: 'app_resource_balance', methods: ['POST'])]
    public function balance(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        $userIds = $data['user_ids'] ?? [];
        
        if (empty($userIds)) {
            return $this->json(['error' => 'At least one user ID is required'], 400);
        }
        
        $balanced = $this->resourceManagementService->balanceTeamWorkload($userIds);
        
        return $this->json([
            'balance' => $balanced
        ]);
    }

    #[Route('/utilization', name: 'app_resource_utilization', methods: ['GET'])]
    public function utilization(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $userIds = $request->query->all('user_ids') ?: [];
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime();
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime('+1 month');
        
        if (empty($userIds)) {
            return $this->json(['error' => 'At least one user ID is required'], 400);
        }
        
        $utilization = $this->resourceManagementService->getUtilizationReport($userIds, $from, $to);
        
        return $this->json([
            'utilization' => $utilization
        ]);
    }

    #[Route('/pool/create', name: 'app_resource_pool_create', methods: ['POST'])]
    public function createPool(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $userIds = $data['user_ids'] ?? [];
        $skills = $data['skills'] ?? [];
        
        if (empty($name) || empty($userIds)) {
            return $this->json(['error' => 'Name and user IDs are required'], 400);
        }
        
        $pool = $this->resourceManagementService->createResourcePool($name, $userIds, $skills);
        
        return $this->json([
            'pool' => $pool
        ]);
    }

    #[Route('/pools', name: 'app_resource_pools', methods: ['GET'])]
    public function getPools(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $pools = $this->resourceManagementService->getResourcePools();
        
        return $this->json([
            'pools' => $pools
        ]);
    }

    #[Route('/forecast', name: 'app_resource_forecast', methods: ['GET'])]
    public function forecast(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $userIds = $request->query->all('user_ids') ?: [];
        $weeks = (int)$request->query->get('weeks', 4);
        
        if (empty($userIds)) {
            return $this->json(['error' => 'At least one user ID is required'], 400);
        }
        
        $forecast = $this->resourceManagementService->getResourceForecast($userIds, $weeks);
        
        return $this->json([
            'forecast' => $forecast
        ]);
    }

    #[Route('/skill-matrix', name: 'app_resource_skill_matrix', methods: ['GET'])]
    public function skillMatrix(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $userIds = $request->query->all('user_ids') ?: [];
        
        if (empty($userIds)) {
            return $this->json(['error' => 'At least one user ID is required'], 400);
        }
        
        $matrix = $this->resourceManagementService->getSkillMatrix($userIds);
        
        return $this->json([
            'skill_matrix' => $matrix
        ]);
    }

    #[Route('/efficiency/{id}', name: 'app_resource_efficiency', methods: ['GET'])]
    public function efficiency(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime('-1 month');
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime();
        
        // Get user by ID
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        $efficiency = $this->resourceManagementService->getResourceEfficiency($user, $from, $to);
        
        return $this->json([
            'efficiency' => $efficiency
        ]);
    }

    #[Route('/allocate', name: 'app_resource_allocate', methods: ['POST'])]
    public function allocate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        $taskId = $data['task_id'] ?? 0;
        $userId = $data['user_id'] ?? 0;
        $hours = (float)($data['hours'] ?? 0);
        $date = $data['date'] ? new \DateTime($data['date']) : new \DateTime();
        
        if (!$taskId || !$userId || $hours <= 0) {
            return $this->json(['error' => 'Task ID, User ID, and Hours are required'], 400);
        }
        
        // Get task and user by ID
        $task = $this->taskRepository->find($taskId);
        $user = $this->userRepository->find($userId);
        
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        $allocation = $this->resourceManagementService->allocateResource($task, $user, $hours, $date);
        
        return $this->json([
            'allocation' => $allocation
        ]);
    }
}