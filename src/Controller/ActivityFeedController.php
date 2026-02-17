<?php

namespace App\Controller;

use App\Service\ActivityFeedService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity')]
#[IsGranted('ROLE_USER')]
class ActivityFeedController extends AbstractController
{
    public function __construct(
        private ActivityFeedService $activityFeedService
    ) {}

    /**
     * Activity feed page
     */
    #[Route('', name: 'app_activity_feed', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $feed = $this->activityFeedService->getUserFeed($user, 50);

        return $this->render('activity/index.html.twig', [
            'feed' => $feed
        ]);
    }

    /**
     * Team activity feed
     */
    #[Route('/team', name: 'app_activity_team', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function team(): Response
    {
        $feed = $this->activityFeedService->getTeamFeed(100);

        return $this->render('activity/team.html.twig', [
            'feed' => $feed
        ]);
    }

    /**
     * Get activity feed as JSON
     */
    #[Route('/api/feed', name: 'app_activity_api_feed', methods: ['GET'])]
    public function apiFeed(): JsonResponse
    {
        $user = $this->getUser();
        $feed = $this->activityFeedService->getUserFeed($user, 20);

        return $this->json($feed);
    }

    /**
     * Activity statistics
     */
    #[Route('/stats', name: 'app_activity_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $user = $this->getUser();
        $from = new \DateTime('-30 days');
        $to = new \DateTime();

        $stats = $this->activityFeedService->getActivityStats($user, $from, $to);
        $mostActive = $this->activityFeedService->getMostActiveUsers(30, 10);

        return $this->render('activity/stats.html.twig', [
            'stats' => $stats,
            'most_active' => $mostActive,
            'from' => $from,
            'to' => $to
        ]);
    }
}
