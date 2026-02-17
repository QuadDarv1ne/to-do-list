<?php

namespace App\Controller;

use App\Service\MobileAPIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mobile')]
class MobileAPIController extends AbstractController
{
    public function __construct(
        private MobileAPIService $mobileService
    ) {}

    #[Route('/dashboard', name: 'app_mobile_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $user = $this->getUser();
        $dashboard = $this->mobileService->getMobileDashboard($user);
        
        return $this->json($dashboard);
    }

    #[Route('/tasks/quick-create', name: 'app_mobile_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        
        $task = $this->mobileService->quickCreateTask($user, $data);
        
        return $this->json($task);
    }

    #[Route('/tasks/{id}/quick-update', name: 'app_mobile_quick_update', methods: ['PATCH'])]
    public function quickUpdate(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? 'pending';
        
        $task = $this->mobileService->quickUpdateStatus($id, $status);
        
        return $this->json($task);
    }

    #[Route('/tasks/{id}', name: 'app_mobile_task_details', methods: ['GET'])]
    public function taskDetails(int $id): JsonResponse
    {
        $details = $this->mobileService->getTaskDetails($id);
        
        return $this->json($details);
    }

    #[Route('/tasks/search', name: 'app_mobile_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $query = $request->query->get('q', '');
        $limit = (int)$request->query->get('limit', 20);
        
        $tasks = $this->mobileService->searchTasks($user, $query, $limit);
        
        return $this->json($tasks);
    }

    #[Route('/tasks/filter', name: 'app_mobile_filter', methods: ['POST'])]
    public function filter(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = json_decode($request->getContent(), true);
        $page = (int)$request->query->get('page', 1);
        $limit = (int)$request->query->get('limit', 20);
        
        $result = $this->mobileService->getFilteredTasks($user, $filters, $page, $limit);
        
        return $this->json($result);
    }

    #[Route('/sync', name: 'app_mobile_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $changes = json_decode($request->getContent(), true);
        
        $result = $this->mobileService->syncOfflineChanges($user, $changes);
        
        return $this->json($result);
    }

    #[Route('/config', name: 'app_mobile_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        $config = $this->mobileService->getAppConfig();
        
        return $this->json($config);
    }

    #[Route('/device/register', name: 'app_mobile_register_device', methods: ['POST'])]
    public function registerDevice(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $deviceData = json_decode($request->getContent(), true);
        
        $result = $this->mobileService->registerDevice($user, $deviceData);
        
        return $this->json($result);
    }

    #[Route('/widget/{type}', name: 'app_mobile_widget', methods: ['GET'])]
    public function widget(string $type): JsonResponse
    {
        $user = $this->getUser();
        $data = $this->mobileService->getWidgetData($user, $type);
        
        return $this->json($data);
    }
}
