<?php

namespace App\Controller;

use App\Service\TaskTemplateLibraryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/templates')]
class TemplateController extends AbstractController
{
    public function __construct(
        private TaskTemplateLibraryService $templateService
    ) {}

    #[Route('', name: 'app_templates_index')]
    public function index(): Response
    {
        $templates = $this->templateService->getAllTemplates();
        $categories = $this->templateService->getCategories();
        $user = $this->getUser();
        $customTemplates = $this->templateService->getUserCustomTemplates($user);

        return $this->render('templates/index.html.twig', [
            'templates' => $templates,
            'categories' => $categories,
            'custom_templates' => $customTemplates
        ]);
    }

    #[Route('/api/all', name: 'app_templates_api_all')]
    public function apiAll(): JsonResponse
    {
        $templates = $this->templateService->getAllTemplates();
        return $this->json($templates);
    }

    #[Route('/api/{key}', name: 'app_templates_api_get')]
    public function apiGet(string $key): JsonResponse
    {
        $template = $this->templateService->getTemplate($key);
        
        if (!$template) {
            return $this->json(['error' => 'Template not found'], 404);
        }

        return $this->json($template);
    }

    #[Route('/create-from/{key}', name: 'app_templates_create_from', methods: ['POST'])]
    public function createFrom(string $key, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $overrides = $request->request->all();

        try {
            $task = $this->templateService->createFromTemplate($key, $user, $overrides);
            
            return $this->json([
                'success' => true,
                'task_id' => $task->getId(),
                'redirect' => $this->generateUrl('app_task_show', ['id' => $task->getId()])
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/category/{category}', name: 'app_templates_by_category')]
    public function byCategory(string $category): JsonResponse
    {
        $templates = $this->templateService->getTemplatesByCategory($category);
        return $this->json($templates);
    }

    #[Route('/search', name: 'app_templates_search')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $templates = $this->templateService->searchTemplates($query);
        
        return $this->json($templates);
    }

    #[Route('/custom/create', name: 'app_templates_custom_create', methods: ['POST'])]
    public function createCustom(Request $request): JsonResponse
    {
        $name = $request->request->get('name');
        $template = $request->request->get('template');
        $user = $this->getUser();

        $customTemplate = $this->templateService->createCustomTemplate($name, $template, $user);

        return $this->json($customTemplate);
    }

    #[Route('/popular', name: 'app_templates_popular')]
    public function popular(): JsonResponse
    {
        $templates = $this->templateService->getPopularTemplates(5);
        return $this->json($templates);
    }
}
