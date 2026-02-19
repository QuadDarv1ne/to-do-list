<?php

namespace App\Controller;

use App\Service\SmartSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/search')]
#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    public function __construct(
        private SmartSearchService $searchService,
    ) {
    }

    /**
     * Search page
     */
    #[Route('', name: 'app_search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $results = [];

        if ($query) {
            $user = $this->getUser();
            $results = $this->searchService->search($query, $user);
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * Quick search API
     */
    #[Route('/api/quick', name: 'app_search_api_quick', methods: ['GET'])]
    public function quickSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        if (\strlen($query) < 2) {
            return $this->json([]);
        }

        $results = $this->searchService->search($query, $user, ['limit' => 5]);

        return $this->json($results);
    }

    /**
     * Search suggestions
     */
    #[Route('/api/suggestions', name: 'app_search_api_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        $suggestions = $this->searchService->getSuggestions($query, $user);

        return $this->json($suggestions);
    }

    /**
     * Advanced search
     */
    #[Route('/advanced', name: 'app_search_advanced', methods: ['GET', 'POST'])]
    public function advanced(Request $request): Response
    {
        $user = $this->getUser();
        $results = [];

        if ($request->isMethod('POST')) {
            $filters = $request->request->all();
            $results = $this->searchService->advancedSearch($filters, $user);
        }

        return $this->render('search/advanced.html.twig', [
            'results' => $results,
        ]);
    }

    /**
     * Save search
     */
    #[Route('/save', name: 'app_search_save', methods: ['POST'])]
    public function saveSearch(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $saved = $this->searchService->saveSearch(
            $data['name'],
            $data['filters'],
            $user,
        );

        return $this->json([
            'success' => true,
            'search' => $saved,
        ]);
    }

    /**
     * Get saved searches
     */
    #[Route('/saved', name: 'app_search_saved', methods: ['GET'])]
    public function savedSearches(): Response
    {
        $user = $this->getUser();
        $searches = $this->searchService->getSavedSearches($user);

        return $this->render('search/saved.html.twig', [
            'searches' => $searches,
        ]);
    }
}
