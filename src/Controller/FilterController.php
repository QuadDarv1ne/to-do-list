<?php

namespace App\Controller;

use App\Service\TaskFilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filters')]
#[IsGranted('ROLE_USER')]
class FilterController extends AbstractController
{
    public function __construct(
        private TaskFilterService $filterService,
    ) {
    }

    /**
     * Filters page
     */
    #[Route('', name: 'app_filters', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $filters = $this->filterService->getPredefinedFilters();
        $counts = $this->filterService->getAllFilterCounts($user);
        $customFilters = $this->filterService->getUserCustomFilters($user);

        return $this->render('filters/index.html.twig', [
            'filters' => $filters,
            'counts' => $counts,
            'custom_filters' => $customFilters,
        ]);
    }

    /**
     * Apply filter
     */
    #[Route('/apply/{key}', name: 'app_filters_apply', methods: ['GET'])]
    public function apply(string $key): Response
    {
        $user = $this->getUser();
        $tasks = $this->filterService->applyFilter($key, $user);
        $filters = $this->filterService->getPredefinedFilters();
        $filterInfo = $filters[$key] ?? null;

        return $this->render('filters/results.html.twig', [
            'tasks' => $tasks,
            'filter_key' => $key,
            'filter_info' => $filterInfo,
        ]);
    }

    /**
     * Get filter count
     */
    #[Route('/count/{key}', name: 'app_filters_count', methods: ['GET'])]
    public function count(string $key): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->filterService->getFilterCount($key, $user);

        return $this->json(['count' => $count]);
    }

    /**
     * Get all counts
     */
    #[Route('/counts', name: 'app_filters_counts', methods: ['GET'])]
    public function allCounts(): JsonResponse
    {
        $user = $this->getUser();
        $counts = $this->filterService->getAllFilterCounts($user);

        return $this->json($counts);
    }

    /**
     * Create custom filter
     */
    #[Route('/custom', name: 'app_filters_custom_create', methods: ['POST'])]
    public function createCustom(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $filter = $this->filterService->createCustomFilter(
            $data['name'],
            $data['filter'],
            $user,
        );

        return $this->json([
            'success' => true,
            'filter' => $filter,
        ]);
    }
}
