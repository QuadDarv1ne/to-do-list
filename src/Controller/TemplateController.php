<?php

namespace App\Controller;

use App\Entity\Task;
use App\Service\TemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/templates')]
#[IsGranted('ROLE_USER')]
class TemplateController extends AbstractController
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    /**
     * Templates page
     */
    #[Route('', name: 'app_templates', methods: ['GET'])]
    public function index(): Response
    {
        $predefined = $this->templateService->getPredefinedTemplates();
        $userTemplates = $this->templateService->getUserTemplates($this->getUser());
        $stats = $this->templateService->getTemplateStats();

        return $this->render('templates/index.html.twig', [
            'predefined' => $predefined,
            'user_templates' => $userTemplates,
            'stats' => $stats
        ]);
    }

    /**
     * Get template by key
     */
    #[Route('/get/{key}', name: 'app_templates_get', methods: ['GET'])]
    public function getTemplate(string $key): JsonResponse
    {
        $template = $this->templateService->getTemplate($key);

        if (!$template) {
            return $this->json(['error' => 'Template not found'], 404);
        }

        return $this->json($template);
    }

    /**
     * Create task from template
     */
    #[Route('/create/{key}', name: 'app_templates_create', methods: ['POST'])]
    public function createFromTemplate(string $key): Response
    {
        $template = $this->templateService->getTemplate($key);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден');
            return $this->redirectToRoute('app_templates');
        }

        // Redirect to task creation with template data
        return $this->redirectToRoute('app_task_new', [
            'template' => $key
        ]);
    }

    /**
     * Get all templates as JSON
     */
    #[Route('/api/list', name: 'app_templates_api_list', methods: ['GET'])]
    public function apiList(): JsonResponse
    {
        $predefined = $this->templateService->getPredefinedTemplates();
        $userTemplates = $this->templateService->getUserTemplates($this->getUser());

        return $this->json([
            'predefined' => $predefined,
            'user_templates' => $userTemplates
        ]);
    }
}
