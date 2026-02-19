<?php

namespace App\Controller;

use App\Service\QuickSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quick-search')]
#[IsGranted('ROLE_USER')]
class QuickSearchController extends AbstractController
{
    public function __construct(
        private QuickSearchService $searchService,
    ) {
    }

    /**
     * Quick search API
     */
    #[Route('', name: 'app_search_quick', methods: ['GET'])]
    public function quick(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        if (\strlen($query) < 2) {
            return $this->json([
                'results' => [],
                'message' => 'Введите минимум 2 символа',
            ]);
        }

        $results = $this->searchService->search($query, $user);

        return $this->json([
            'results' => [
                'tasks' => array_map(fn ($t) => [
                    'id' => $t->getId(),
                    'title' => $t->getTitle(),
                    'status' => $t->getStatus(),
                    'priority' => $t->getPriority(),
                    'url' => $this->generateUrl('app_task_show', ['id' => $t->getId()]),
                ], $results['tasks']),
                'users' => array_map(fn ($u) => [
                    'id' => $u->getId(),
                    'name' => $u->getFullName(),
                    'email' => $u->getEmail(),
                    'avatar' => $u->getAvatarUrl(),
                ], $results['users']),
                'tags' => array_map(fn ($t) => [
                    'id' => $t->getId(),
                    'name' => $t->getName(),
                ], $results['tags']),
                'commands' => $results['commands'],
            ],
            'query' => $query,
        ]);
    }

    /**
     * Get search suggestions
     */
    #[Route('/suggestions', name: 'app_search_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        $suggestions = $this->searchService->getSuggestions($query, $user);

        return $this->json(['suggestions' => $suggestions]);
    }
}
