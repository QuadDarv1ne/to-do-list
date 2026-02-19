<?php

namespace App\Controller;

use App\Service\AdvancedAnalyticsService;
use App\Service\TagManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics/advanced')]
#[IsGranted('ROLE_USER')]
class AdvancedAnalyticsController extends AbstractController
{
    public function __construct(
        private AdvancedAnalyticsService $analyticsService,
        private TagManagementService $tagService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Advanced analytics dashboard
     */
    #[Route('', name: 'app_advanced_analytics', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        // Get all analytics data
        $predictions = $this->analyticsService->predictCompletionTime($user);
        $trends = $this->analyticsService->analyzeProductivityTrends($user, 12);
        $burnout = $this->analyticsService->calculateBurnoutRisk($user);
        $patterns = $this->analyticsService->analyzeTaskPatterns($user);
        $insights = $this->analyticsService->getPerformanceInsights($user);
        $tagCloud = $this->tagService->getTagCloud();

        return $this->render('analytics/advanced.html.twig', [
            'predictions' => $predictions,
            'trends' => $trends,
            'burnout' => $burnout,
            'patterns' => $patterns,
            'insights' => $insights,
            'tag_cloud' => $tagCloud,
        ]);
    }

    /**
     * Get completion time prediction
     */
    #[Route('/predict/{priority}', name: 'app_analytics_predict', methods: ['GET'])]
    public function predict(string $priority = 'medium'): JsonResponse
    {
        $user = $this->getUser();
        $prediction = $this->analyticsService->predictCompletionTime($user, $priority);

        return $this->json([
            'success' => true,
            'data' => $prediction,
        ]);
    }

    /**
     * Get productivity trends
     */
    #[Route('/trends', name: 'app_analytics_trends', methods: ['GET'])]
    public function trends(): JsonResponse
    {
        $user = $this->getUser();
        $trends = $this->analyticsService->analyzeProductivityTrends($user, 12);

        return $this->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get burnout risk assessment
     */
    #[Route('/burnout', name: 'app_analytics_burnout', methods: ['GET'])]
    public function burnout(): JsonResponse
    {
        $user = $this->getUser();
        $burnout = $this->analyticsService->calculateBurnoutRisk($user);

        return $this->json([
            'success' => true,
            'data' => $burnout,
        ]);
    }

    /**
     * Get task patterns
     */
    #[Route('/patterns', name: 'app_analytics_patterns', methods: ['GET'])]
    public function patterns(): JsonResponse
    {
        $user = $this->getUser();
        $patterns = $this->analyticsService->analyzeTaskPatterns($user);

        return $this->json([
            'success' => true,
            'data' => $patterns,
        ]);
    }

    /**
     * Get performance insights
     */
    #[Route('/insights', name: 'app_analytics_insights', methods: ['GET'])]
    public function insights(): JsonResponse
    {
        $user = $this->getUser();
        $insights = $this->analyticsService->getPerformanceInsights($user);

        return $this->json([
            'success' => true,
            'data' => $insights,
        ]);
    }

    /**
     * Get tag cloud
     */
    #[Route('/tags/cloud', name: 'app_analytics_tag_cloud', methods: ['GET'])]
    public function tagCloud(): JsonResponse
    {
        $tagCloud = $this->tagService->getTagCloud();

        return $this->json([
            'success' => true,
            'data' => $tagCloud,
        ]);
    }

    /**
     * Get tag statistics
     */
    #[Route('/tags/{id}/stats', name: 'app_analytics_tag_stats', methods: ['GET'])]
    public function tagStats(int $id): JsonResponse
    {
        $tag = $this->entityManager->getRepository(\App\Entity\Tag::class)->find($id);

        if (!$tag) {
            return $this->json([
                'success' => false,
                'message' => 'Tag not found',
            ], 404);
        }

        $stats = $this->tagService->getTagStatistics($tag);
        $related = $this->tagService->getRelatedTags($tag);

        return $this->json([
            'success' => true,
            'data' => [
                'tag' => [
                    'id' => $tag->getId(),
                    'name' => $tag->getName(),
                    'color' => $tag->getColor(),
                ],
                'statistics' => $stats,
                'related_tags' => $related,
            ],
        ]);
    }
}
