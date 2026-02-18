<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\AnalyticsService;
use App\Service\QueryCacheService;
use App\Service\PerformanceMonitorService;
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
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        $user = $this->getUser();
        
        // Get goals and habits data
        $activeGoals = $goalRepository->findActiveGoalsByUser($user);
        $activeHabits = $habitRepository->findActiveByUser($user);
        
        // Calculate goals stats
        $goalsStats = [
            'total' => count($activeGoals),
            'avg_progress' => 0,
            'on_track' => 0,
            'at_risk' => 0,
        ];
        
        if (count($activeGoals) > 0) {
            $totalProgress = 0;
            foreach ($activeGoals as $goal) {
                $progress = $goal->getProgress();
                $totalProgress += $progress;
                
                $daysRemaining = $goal->getDaysRemaining();
                if ($progress >= 70 || $daysRemaining > 7) {
                    $goalsStats['on_track']++;
                } else {
                    $goalsStats['at_risk']++;
                }
            }
            $goalsStats['avg_progress'] = round($totalProgress / count($activeGoals));
        }
        
        // Calculate habits stats
        $habitsStats = [
            'total' => count($activeHabits),
            'avg_streak' => 0,
            'completed_today' => 0,
            'total_logs' => 0,
        ];
        
        if (count($activeHabits) > 0) {
            $totalStreak = 0;
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            
            foreach ($activeHabits as $habit) {
                $totalStreak += $habit->getCurrentStreak();
                $habitsStats['total_logs'] += count($habit->getLogs());
                
                // Check if completed today
                foreach ($habit->getLogs() as $log) {
                    $logDate = clone $log->getDate();
                    $logDate->setTime(0, 0, 0);
                    if ($logDate == $today) {
                        $habitsStats['completed_today']++;
                        break;
                    }
                }
            }
            $habitsStats['avg_streak'] = round($totalStreak / count($activeHabits));
        }
        
        // Check if user prefers modern theme
        $useModernTheme = $this->getParameter('app.use_modern_theme') ?? false;
        
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
        
        // Get CRM data
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $manager = $isAdmin ? null : $user;
        
        $activeDeals = $dealRepository->findActiveDeals($manager);
        $dealsByStage = $dealRepository->getDealsByStage($manager);
        $dealsCountByStatus = $dealRepository->getDealsCountByStatus($manager);
        $overdueDeals = $dealRepository->getOverdueDeals($manager);
        
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        $monthRevenue = $dealRepository->getTotalRevenue($startOfMonth, $endOfMonth, $manager);
        
        $topClients = $clientRepository->getTopClientsByRevenue(5, $manager);
        $totalClients = $clientRepository->getTotalCount($manager);
        $newClientsThisMonth = $clientRepository->getNewClientsCount($startOfMonth, $endOfMonth, $manager);
        
        // Prepare dashboard data with defaults
        $dashboardData = [
            'task_stats' => $taskStats,
            'analytics_data' => $analyticsData,
            'performance_metrics' => $performanceMetrics,
            'crm_metrics' => $crmMetrics,
            'goals_stats' => $goalsStats,
            'habits_stats' => $habitsStats,
            'active_goals' => array_slice($activeGoals, 0, 3),
            'active_habits' => array_slice($activeHabits, 0, 4),
            // CRM data
            'active_deals' => array_slice($activeDeals, 0, 5),
            'deals_by_stage' => $dealsByStage,
            'deals_count_by_status' => $dealsCountByStatus,
            'overdue_deals_count' => count($overdueDeals),
            'month_revenue' => $monthRevenue,
            'top_clients' => $topClients,
            'total_clients' => $totalClients,
            'new_clients_this_month' => $newClientsThisMonth,
            // Pass tag stats if available
            'tag_stats' => $analyticsData['tag_stats'] ?? [],
            'tag_completion_rates' => $analyticsData['tag_completion_rates'] ?? [],
            'categories' => $analyticsData['categories'] ?? [],
            'recent_tasks' => $analyticsData['recent_tasks'] ?? [],
            // Pass activity stats if user is admin
            'platform_activity_stats' => $analyticsData['platform_activity_stats'] ?? null,
            'user_activity_stats' => $analyticsData['user_activity_stats'] ?? null,
        ];
        
        // Add additional data for enhanced user experience
        $dashboardData['dashboard_refresh_interval'] = 300000; // 5 minutes in milliseconds
        
        // Use modern theme if enabled
        $template = $useModernTheme ? 'dashboard/index_modern.html.twig' : 'dashboard/index.html.twig';
        
        return $this->render($template, $dashboardData);
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
