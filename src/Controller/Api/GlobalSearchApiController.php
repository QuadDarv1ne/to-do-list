<?php

namespace App\Controller\Api;

use App\Service\GlobalSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/search')]
#[IsGranted('ROLE_USER')]
class GlobalSearchApiController extends AbstractController
{
    public function __construct(
        private readonly GlobalSearchService $globalSearchService,
    ) {
    }

    #[Route('', name: 'api_global_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);
        $user = $this->getUser();

        if (empty($query)) {
            return $this->json([
                'error' => 'Query parameter "q" is required',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $results = $this->globalSearchService->search($query, $user, ['limit' => $limit]);

        return $this->json($results);
    }

    #[Route('/suggestions', name: 'api_global_search_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 5);

        if (empty($query)) {
            return $this->json([], JsonResponse::HTTP_OK);
        }

        $suggestions = $this->globalSearchService->getSuggestions($query, $limit);

        return $this->json($suggestions);
    }
}
