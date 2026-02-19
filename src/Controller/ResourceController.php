<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Entity\Task;
use App\Repository\ResourceRepository;
use App\Repository\TaskRepository;
use App\Service\ResourceManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/resource')]
class ResourceController extends AbstractController
{
    public function __construct(
        private ResourceManagementService $resourceManagementService,
        private ResourceRepository $resourceRepository,
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_resource_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] $user, Request $request): Response
    {
        // Добавляем пагинацию
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        
        $qb = $this->resourceRepository->createQueryBuilder('r')
            ->orderBy('r.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $resources = $qb->getQuery()->getResult();
        $total = $this->resourceRepository->count([]);
        
        return $this->render('resource/index.html.twig', [
            'resources' => $resources,
            'page' => $page,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/create', name: 'app_resource_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            
            $resource = $this->resourceManagementService->createResource([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'description' => $data['description'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? '0.00',
                'capacity_per_week' => $data['capacity_per_week'] ?? 40,
                'status' => $data['status'] ?? 'available',
                'skills' => $data['skills'] ?? []
            ]);
            
            return $this->redirectToRoute('app_resource_index');
        }

        return $this->render('resource/create.html.twig');
    }

    #[Route('/{id}', name: 'app_resource_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(Resource $resource): Response
    {
        return $this->render('resource/show.html.twig', [
            'resource' => $resource,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_resource_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Resource $resource): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            
            $this->resourceManagementService->updateResource($resource, [
                'name' => $data['name'] ?? $resource->getName(),
                'email' => $data['email'] ?? $resource->getEmail(),
                'description' => $data['description'] ?? $resource->getDescription(),
                'hourly_rate' => $data['hourly_rate'] ?? $resource->getHourlyRate(),
                'capacity_per_week' => $data['capacity_per_week'] ?? $resource->getCapacityPerWeek(),
                'status' => $data['status'] ?? $resource->getStatus()
            ]);
            
            return $this->redirectToRoute('app_resource_show', ['id' => $resource->getId()]);
        }

        return $this->render('resource/edit.html.twig', [
            'resource' => $resource,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_resource_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Resource $resource): Response
    {
        if ($this->isCsrfTokenValid('delete'.$resource->getId(), $request->request->get('_token'))) {
            $this->resourceManagementService->deleteResource($resource);
        }

        return $this->redirectToRoute('app_resource_index');
    }

    #[Route('/availability', name: 'app_resource_availability', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailability(Request $request): JsonResponse
    {
        $from = new \DateTime($request->query->get('from', 'today'));
        $to = new \DateTime($request->query->get('to', '+1 month'));
        
        $resources = $this->resourceRepository->findAll();
        $availabilityData = [];

        foreach ($resources as $resource) {
            $availability = $this->resourceManagementService->getResourceAvailability($resource, $from, $to);
            
            $availabilityData[] = [
                'id' => $resource->getId(),
                'name' => $resource->getName(),
                'email' => $resource->getEmail(),
                'capacity' => $resource->getCapacityPerWeek(),
                'utilization' => $availability['utilization_percentage'],
                'available' => $availability['status'] === 'available' || $availability['status'] === 'underutilized',
                'status' => $availability['status']
            ];
        }

        return $this->json($availabilityData);
    }

    #[Route('/workload/{id}', name: 'app_resource_workload', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getResourceWorkload(int $id): JsonResponse
    {
        $resource = $this->resourceRepository->find($id);
        
        if (!$resource) {
            return $this->json(['error' => 'Resource not found'], 404);
        }

        $from = new \DateTime('-1 week');
        $to = new \DateTime('+1 month');
        
        $workloadData = $this->resourceManagementService->getResourceWorkload($resource, $from, $to);

        return $this->json($workloadData);
    }

    #[Route('/allocation', name: 'app_resource_allocation', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function allocateResource(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $taskId = $data['task_id'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $hours = $data['hours'] ?? 1;
        $dateStr = $data['date'] ?? 'today';
        
        if (!$taskId || !$resourceId) {
            return $this->json(['error' => 'Task ID and Resource ID are required'], 400);
        }

        $task = $this->taskRepository->find($taskId);
        $resource = $this->resourceRepository->find($resourceId);
        
        if (!$task || !$resource) {
            return $this->json(['error' => 'Task or Resource not found'], 404);
        }

        try {
            $allocation = $this->resourceManagementService->allocateResource($task, $resource, $hours, new \DateTime($dateStr));
            
            return $this->json([
                'success' => true,
                'message' => 'Resource allocated successfully',
                'allocation_id' => $allocation->getId(),
                'task_id' => $taskId,
                'resource_id' => $resourceId
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to allocate resource: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/forecast', name: 'app_resource_forecast', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function forecast(): JsonResponse
    {
        $resources = $this->resourceRepository->findAll();
        $resourceIds = array_map(fn($r) => $r->getId(), $resources);
        
        $forecastData = $this->resourceManagementService->getResourceForecast($resourceIds, 4);

        return $this->json($forecastData);
    }

    #[Route('/skills', name: 'app_resource_skills', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSkills(): JsonResponse
    {
        $resources = $this->resourceRepository->findAll();
        $resourceIds = array_map(fn($r) => $r->getId(), $resources);
        
        $skillsData = $this->resourceManagementService->getSkillMatrix($resourceIds);

        return $this->json($skillsData);
    }

    #[Route('/conflicts', name: 'app_resource_conflicts', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function getConflicts(Request $request): JsonResponse
    {
        $from = new \DateTime($request->query->get('from', 'today'));
        $to = new \DateTime($request->query->get('to', '+1 month'));
        
        $conflictsData = $this->resourceManagementService->getResourceConflicts($from, $to);

        return $this->json($conflictsData);
    }

    #[Route('/utilization-report', name: 'app_resource_utilization_report', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function utilizationReport(Request $request): JsonResponse
    {
        $from = new \DateTime($request->query->get('from', '-1 month'));
        $to = new \DateTime($request->query->get('to', 'now'));
        
        $resources = $this->resourceRepository->findAll();
        $resourceIds = array_map(fn($r) => $r->getId(), $resources);
        
        $reportData = $this->resourceManagementService->getUtilizationReport($resourceIds, $from, $to);

        return $this->json($reportData);
    }
}