<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\AnalyticsService;
use App\Service\PerformanceMonitorService;
use App\Service\QueryCacheService;
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
        AnalyticsService $analyticsService,
        \App\Repository\GoalRepository $goalRepository,
        \App\Repository\HabitRepository $habitRepository,
        \App\Repository\DealRepository $dealRepository,
        \App\Repository\ClientRepository $clientRepository,
        \App\Repository\ActivityLogRepository $activityLogRepository,
        QueryCacheService $cacheService,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        $user = $this->getUser();

        // Cache dashboard data for 2 minutes
        $cacheKey = 'dashboard_data_' . $user->getId();
        $dashboardData = $cacheService->cacheQuery($cacheKey, function () use (
            $user,
            $taskRepository,
            $analyticsService,
            $goalRepository,
            $habitRepository,
            $dealRepository,
            $clientRepository,
            $activityLogRepository,
            $performanceMonitor
        ) {
            return $this->loadDashboardData(
                $user,
                $taskRepository,
                $analyticsService,
                $goalRepository,
                $habitRepository,
                $dealRepository,
                $clientRepository,
                $activityLogRepository,
                $performanceMonitor,
            );
        }, 120); // 2 minutes cache

        return $this->render('dashboard/index.html.twig', $dashboardData);
    }

    /**
     * Load all dashboard data (extracted for caching)
     */
    private function loadDashboardData(
        $user,
        TaskRepository $taskRepository,
        AnalyticsService $analyticsService,
        $goalRepository,
        $habitRepository,
        $dealRepository,
        $clientRepository,
        $activityLogRepository,
        ?PerformanceMonitorService $performanceMonitor,
    ): array {

        // Get goals and habits data (limit to 3 and 4 respectively for performance)
        $activeGoals = $goalRepository->findActiveGoalsByUser($user);
        $activeHabits = $habitRepository->findActiveByUser($user);

        // Calculate goals stats efficiently
        $goalsCount = \count($activeGoals);
        $goalsStats = [
            'total' => $goalsCount,
            'avg_progress' => 0,
            'on_track' => 0,
            'at_risk' => 0,
        ];

        if ($goalsCount > 0) {
            $totalProgress = 0;
            foreach ($activeGoals as $goal) {
                $progress = $goal->getProgress();
                $totalProgress += $progress;
                $goalsStats[$progress >= 70 || $goal->getDaysRemaining() > 7 ? 'on_track' : 'at_risk']++;
            }
            $goalsStats['avg_progress'] = round($totalProgress / $goalsCount);
        }

        // Calculate habits stats efficiently
        $habitsCount = \count($activeHabits);
        $habitsStats = [
            'total' => $habitsCount,
            'avg_streak' => 0,
            'completed_today' => 0,
            'total_logs' => 0,
        ];

        if ($habitsCount > 0) {
            $totalStreak = 0;
            $today = (new \DateTime())->setTime(0, 0, 0);

            foreach ($activeHabits as $habit) {
                $totalStreak += $habit->getCurrentStreak();
                $logs = $habit->getLogs();
                $habitsStats['total_logs'] += \count($logs);

                // Check if completed today (optimized)
                foreach ($logs as $log) {
                    if ($log->getDate()->setTime(0, 0, 0) == $today) {
                        $habitsStats['completed_today']++;

                        break;
                    }
                }
            }
            $habitsStats['avg_streak'] = round($totalStreak / $habitsCount);
        }

        // Get quick stats from repository with caching
        $taskStats = $taskRepository->getQuickStats($user);

        // Get analytics data
        $analyticsData = $analyticsService->getDashboardData($user);

        // Get performance metrics if user is admin and service is available
        $performanceMetrics = null;
        if ($this->isGranted('ROLE_ADMIN') && $performanceMonitor) {
            try {
                $performanceMetrics = $performanceMonitor->getPerformanceReport();
            } catch (\Exception $e) {
                // Log error but don't break the dashboard
                error_log('Error getting performance metrics: ' . $e->getMessage());
            }
        }

        // Calculate CRM-specific metrics
        $crmMetrics = $this->calculateCRMMetrics($taskStats, $analyticsData);

        // Get CRM data (optimized with single date calculation)
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $manager = $isAdmin ? null : $user;
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');

        // Fetch CRM data in parallel-ready structure
        $activeDeals = $dealRepository->findActiveDeals($manager);
        $dealsByStage = $dealRepository->getDealsByStage($manager);
        $dealsCountByStatus = $dealRepository->getDealsCountByStatus($manager);
        $overdueDeals = $dealRepository->getOverdueDeals($manager);
        $monthRevenue = $dealRepository->getTotalRevenueForPeriod($startOfMonth, $endOfMonth, $manager);
        $topClients = $clientRepository->getTopClientsByRevenue(5, $manager);
        $totalClients = $clientRepository->getTotalCount($manager);
        $newClientsThisMonth = $clientRepository->getNewClientsCount($startOfMonth, $endOfMonth, $manager);

        // Get recent activity logs
        $recentActivities = $activityLogRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Prepare dashboard data with defaults
        $dashboardData = [
            'task_stats' => $taskStats,
            'analytics_data' => $analyticsData,
            'performance_metrics' => $performanceMetrics,
            'crm_metrics' => $crmMetrics,
            'goals_stats' => $goalsStats,
            'habits_stats' => $habitsStats,
            'active_goals' => \array_slice($activeGoals, 0, 3),
            'active_habits' => \array_slice($activeHabits, 0, 4),
            // CRM data
            'active_deals' => \array_slice($activeDeals, 0, 5),
            'deals_by_stage' => $dealsByStage,
            'deals_count_by_status' => $dealsCountByStatus,
            'overdue_deals_count' => \count($overdueDeals),
            'month_revenue' => $monthRevenue,
            'top_clients' => $topClients,
            'total_clients' => $totalClients,
            'new_clients_this_month' => $newClientsThisMonth,
            // Pass tag stats if available
            'tag_stats' => $analyticsData['tag_stats'] ?? [],
            'tag_completion_rates' => $analyticsData['tag_completion_rates'] ?? [],
            'categories' => $analyticsData['categories'] ?? [],
            'recent_tasks' => $analyticsData['recent_tasks'] ?? [],
            'recent_activities' => $recentActivities,
            // Pass activity stats if user is admin
            'platform_activity_stats' => $analyticsData['platform_activity_stats'] ?? null,
            'user_activity_stats' => $analyticsData['user_activity_stats'] ?? null,
        ];

        // Add additional data for enhanced user experience
        $dashboardData['dashboard_refresh_interval'] = 300000; // 5 minutes in milliseconds

        return $dashboardData;
    }

    /**
     * Calculate CRM-specific metrics for sales analytics
     */
    private function calculateCRMMetrics(array $taskStats, array $analyticsData): array
    {
        $total = $taskStats['total'] ?? 0;
        $completed = $taskStats['completed'] ?? 0;
        $inProgress = $taskStats['in_progress'] ?? 0;
        $pending = $taskStats['pending'] ?? 0;

        // Conversion rate (completed / total)
        $conversionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        // Success rate trend (comparing with previous period)
        $previousCompleted = $analyticsData['previous_completed'] ?? $completed;
        $previousTotal = $analyticsData['previous_total'] ?? $total;
        $previousRate = $previousTotal > 0 ? ($previousCompleted / $previousTotal) * 100 : 0;
        $rateTrend = $conversionRate - $previousRate;

        // Average deal cycle (days from creation to completion)
        $avgCycleDays = $analyticsData['avg_completion_days'] ?? 7;

        // Active pipeline value (in-progress tasks as "deals")
        $pipelineValue = $inProgress;

        // Win rate (completed vs total closed - completed + cancelled)
        $cancelled = $taskStats['cancelled'] ?? 0;
        $totalClosed = $completed + $cancelled;
        $winRate = $totalClosed > 0 ? round(($completed / $totalClosed) * 100, 1) : 0;

        return [
            'conversion_rate' => $conversionRate,
            'conversion_trend' => $rateTrend,
            'avg_cycle_days' => $avgCycleDays,
            'pipeline_value' => $pipelineValue,
            'win_rate' => $winRate,
            'total_deals' => $total,
            'active_deals' => $inProgress,
            'won_deals' => $completed,
            'pending_deals' => $pending,
        ];
    }

    #[Route('/cache/clear', name: 'app_cache_clear', methods: ['POST'])]
    public function clearCache(QueryCacheService $cacheService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $cacheService->clear();

        $this->addFlash('success', 'Cache cleared successfully!');

        return $this->redirectToRoute('app_dashboard');
    }
}
