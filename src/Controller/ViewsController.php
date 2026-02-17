<?php

namespace App\Controller;

use App\Service\AdvancedFilterViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/views')]
class ViewsController extends AbstractController
{
    public function __construct(
        private AdvancedFilterViewService $viewService
    ) {}

    #[Route('', name: 'app_views_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $views = $this->viewService->getUserViews($user);
        $defaultView = $this->viewService->getDefaultView($user);

        return $this->render('views/index.html.twig', [
            'views' => $views,
            'default_view' => $defaultView
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

        $view = $this->viewService->createCustomView($name, $filters, $columns, $user);

        return $this->json($view);
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
            'Content-Disposition' => "attachment; filename=\"view.$extension\""
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
        $this->viewService->shareView($id, $userIds);

        return $this->json(['success' => true]);
    }

    #[Route('/duplicate/{id}', name: 'app_views_duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        $user = $this->getUser();
        $view = $this->viewService->duplicateView($id, $user);

        return $this->json($view);
    }
}
