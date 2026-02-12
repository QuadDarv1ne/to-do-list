<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        TaskRepository $taskRepository,
        AnalyticsService $analyticsService
    ): Response {
        $user = $this->getUser();
        
        // Get quick stats from repository with caching
        $taskStats = $taskRepository->getQuickStats($user);
        
        // Get analytics data
        $analyticsData = $analyticsService->getDashboardData($user);
        
        return $this->render('dashboard/index.html.twig', [
            'task_stats' => $taskStats,
            'analytics_data' => $analyticsData,
            // Pass tag stats if available
            'tag_stats' => $analyticsData['tag_stats'] ?? [],
            'tag_completion_rates' => $analyticsData['tag_completion_rates'] ?? [],
            'categories' => $analyticsData['categories'] ?? [],
            'recent_tasks' => $analyticsData['recent_tasks'] ?? [],
            // Pass activity stats if user is admin
            'platform_activity_stats' => $analyticsData['platform_activity_stats'] ?? null,
            'user_activity_stats' => $analyticsData['user_activity_stats'] ?? null,
        ]);
    }
}