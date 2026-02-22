<?php

namespace App\Controller;

use App\Service\AdvancedFilterViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/views')]
class ViewsController extends AbstractController
{
    public function __construct(
        private AdvancedFilterViewService $viewService,
    ) {
    }

    #[Route('', name: 'app_views_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $views = $this->viewService->getUserViews($user);
        $defaultView = $this->viewService->getDefaultView($user);

        return $this->render('views/index.html.twig', [
            'views' => $views,
            'default_view' => $defaultView,
        ]);
    }

    #[Route('/api/all', name: 'app_views_api_all')]
    public function apiAll(): JsonResponse
    {
        $user = $this->getUser();
        $views = $this->viewService->getUserViews($user);

        return $this->json($views);
    }

    #[Route('/api/apply/{key}', name: 'app_views_api_apply')]
    public function apiApply(string $key): JsonResponse
    {
        $user = $this->getUser();
        $tasks = $this->viewService->applyView($key, $user);

        return $this->json($tasks);
    }

    #[Route('/create', name: 'app_views_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $name = $request->request->get('name');
        $filters = $request->request->get('filters', []);
        $columns = $request->request->get('columns', []);
        $user = $this->getUser();

        // Валидация имени
        if (empty($name) || !is_string($name)) {
            return $this->json(['error' => 'View name is required'], 400);
        }
        
        if (strlen($name) > 255) {
            return $this->json(['error' => 'View name is too long (max 255 characters)'], 400);
        }
        
        // Валидация фильтров
        if (!is_array($filters)) {
            return $this->json(['error' => 'Filters must be an array'], 400);
        }
        
        // Валидация колонок
        if (!is_array($columns)) {
            return $this->json(['error' => 'Columns must be an array'], 400);
        }

        try {
            $view = $this->viewService->createCustomView($name, $filters, $columns, $user);
            return $this->json($view);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create view: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/export/{key}', name: 'app_views_export')]
    public function export(string $key, Request $request): Response
    {
        $user = $this->getUser();
        $format = $request->query->get('format', 'csv');

        $data = $this->viewService->exportView($key, $user, $format);

        $contentType = match($format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'excel' => 'application/vnd.ms-excel',
            default => 'text/plain'
        };

        $extension = match($format) {
            'csv' => 'csv',
            'json' => 'json',
            'excel' => 'xlsx',
            default => 'txt'
        };

        return new Response($data, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"view.$extension\"",
        ]);
    }

    #[Route('/set-default/{id}', name: 'app_views_set_default', methods: ['POST'])]
    public function setDefault(int $id): JsonResponse
    {
        $user = $this->getUser();
        $this->viewService->setDefaultView($id, $user);

        return $this->json(['success' => true]);
    }

    #[Route('/delete/{id}', name: 'app_views_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->viewService->deleteView($id);

        return $this->json(['success' => true]);
    }

    #[Route('/share/{id}', name: 'app_views_share', methods: ['POST'])]
    public function share(int $id, Request $request): JsonResponse
    {
        $userIds = $request->request->all('user_ids');
        
        // Валидация user_ids
        if (!is_array($userIds)) {
            return $this->json(['error' => 'User IDs must be an array'], 400);
        }
        
        // Проверка что все ID - числа
        foreach ($userIds as $userId) {
            if (!is_numeric($userId) || $userId <= 0) {
                return $this->json(['error' => 'Invalid user ID'], 400);
            }
        }
        
        try {
            $this->viewService->shareView($id, $userIds);
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to share view: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/duplicate/{id}', name: 'app_views_duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        $user = $this->getUser();
        $view = $this->viewService->duplicateView($id, $user);

        return $this->json($view);
    }
}
