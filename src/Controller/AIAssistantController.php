<?php

namespace App\Controller;

use App\Service\AIAssistantService;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai')]
class AIAssistantController extends AbstractController
{
    public function __construct(
        private AIAssistantService $aiService,
        private TaskRepository $taskRepository
    ) {}

    #[Route('/suggest-title', name: 'app_ai_suggest_title', methods: ['POST'])]
    public function suggestTitle(Request $request): JsonResponse
    {
        $description = $request->request->get('description', '');
        $suggestions = $this->aiService->suggestTitle($description);
        
        return $this->json($suggestions);
    }

    #[Route('/autocomplete-description', name: 'app_ai_autocomplete_description', methods: ['POST'])]
    public function autoCompleteDescription(Request $request): JsonResponse
    {
        $partial = $request->request->get('partial', '');
        $suggestions = $this->aiService->autoCompleteDescription($partial);
        
        return $this->json($suggestions);
    }

    #[Route('/suggest-priority/{id}', name: 'app_ai_suggest_priority')]
    public function suggestPriority(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $priority = $this->aiService->suggestPriority($task);
        
        return $this->json(['priority' => $priority]);
    }

    #[Route('/suggest-deadline/{id}', name: 'app_ai_suggest_deadline')]
    public function suggestDeadline(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $deadline = $this->aiService->suggestDeadline($task);
        
        return $this->json(['deadline' => $deadline->format('Y-m-d H:i:s')]);
    }

    #[Route('/suggest-tags/{id}', name: 'app_ai_suggest_tags')]
    public function suggestTags(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $tags = $this->aiService->suggestTags($task);
        
        return $this->json(['tags' => $tags]);
    }

    #[Route('/generate-checklist/{id}', name: 'app_ai_generate_checklist')]
    public function generateChecklist(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $checklist = $this->aiService->generateChecklist($task);
        
        return $this->json(['checklist' => $checklist]);
    }

    #[Route('/analyze-sentiment/{id}', name: 'app_ai_analyze_sentiment')]
    public function analyzeSentiment(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $sentiment = $this->aiService->analyzeSentiment($task);
        
        return $this->json($sentiment);
    }

    #[Route('/predict-time/{id}', name: 'app_ai_predict_time')]
    public function predictTime(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $prediction = $this->aiService->predictCompletionTime($task);
        
        return $this->json($prediction);
    }
}
