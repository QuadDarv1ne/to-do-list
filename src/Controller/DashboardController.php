<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\CommentRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\TaskRecurrenceRepository;
use App\Repository\TaskNotificationRepository;
use App\Repository\TaskCategoryRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, UserRepository $userRepository, CommentRepository $commentRepository, ActivityLogRepository $activityLogRepository, TaskRecurrenceRepository $taskRecurrenceRepository, TaskNotificationRepository $taskNotificationRepository, TaskCategoryRepository $categoryRepository, TagRepository $tagRepository): Response
    {
        $user = $this->getUser();
        
        // Получаем статистику для текущего пользователя
        if ($this->isGranted('ROLE_ADMIN')) {
            // Администратор видит общую статистику
            $totalTasks = $taskRepository->count([]);
            $completedTasks = $taskRepository->count(['status' => 'completed']);
            $inProgressTasks = $taskRepository->count(['status' => 'in_progress']);
            $pendingTasks = $taskRepository->count(['status' => 'pending']);
            
            // Дополнительная статистика для администратора
            $totalUsers = $userRepository->count([]);
            $activeUsers = $userRepository->count(['isActive' => true]);
            $totalComments = $commentRepository->count([]);
            
            // Статистика по приоритетам
            $lowPriorityTasks = $taskRepository->count(['priority' => 'low']);
            $mediumPriorityTasks = $taskRepository->count(['priority' => 'medium']);
            $highPriorityTasks = $taskRepository->count(['priority' => 'high']);
            
            // Статистика по пользователям
            $userStats = $userRepository->getStatistics();
            
            // Статистика по завершенности
            $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
            
            // Получаем задачи всех пользователей
            $recentTasks = $taskRepository->findBy([], ['createdAt' => 'DESC'], 5);
            
            // Получаем недавнюю активность
            $recentActivity = $activityLogRepository->findRecent(10);
            
            // Статистика по статусам для диаграммы
            $taskStats = [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'pending' => $pendingTasks,
                'low_priority' => $lowPriorityTasks,
                'medium_priority' => $mediumPriorityTasks,
                'high_priority' => $highPriorityTasks,
                'users' => $totalUsers,
                'active_users' => $activeUsers,
                'comments' => $totalComments,
                'completion_rate' => $completionRate,
            ];
        } else {
            // Обычный пользователь видит только свою статистику
            $totalTasks = $taskRepository->countByStatus($user);
            $completedTasks = $taskRepository->countByStatus($user, true);
            $inProgressTasks = $taskRepository->countByStatus($user, null, 'in_progress');
            $pendingTasks = $taskRepository->countByStatus($user, false);
            
            // Дополнительная статистика для пользователя
            $userCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
            $recentComments = $commentRepository->findByAuthor($user);
            
            // Получаем последние задачи пользователя
            $recentTasks = $taskRepository->findBy(['assignedUser' => $user], ['createdAt' => 'DESC'], 5);
            
            // Получаем недавнюю активность пользователя
            $recentActivity = $activityLogRepository->findByUser($user);
            
            $userStats = null;
            
            // Статистика по статусам для диаграммы
            $taskStats = [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'pending' => $pendingTasks,
                'completion_rate' => $userCompletionRate,
                'recent_comments' => $recentComments,
            ];
        }
        
        // Get task completion trends
        $completionTrends = $this->getTaskCompletionTrends($taskRepository, $user);
        
        // Get upcoming recurring tasks
        $upcomingRecurringTasks = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            $upcomingRecurringTasks = $taskRecurrenceRepository->findAll();
        } else {
            $upcomingRecurringTasks = $taskRecurrenceRepository->findByUser($user);
        }
        
        // Limit to first 5 upcoming recurring tasks
        $upcomingRecurringTasks = array_slice($upcomingRecurringTasks, 0, 5);
        
        // Get unread notifications count
        $unreadNotificationsCount = 0;
        if ($this->isGranted('ROLE_ADMIN')) {
            $unreadNotificationsCount = $taskNotificationRepository->count(['isRead' => false]);
        } else {
            $unreadNotificationsCount = $taskNotificationRepository->count([
                'recipient' => $user,
                'isRead' => false
            ]);
        }
        
        // Get recent notifications
        $recentNotifications = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            $recentNotifications = $taskNotificationRepository->findBy([], ['createdAt' => 'DESC'], 5);
        } else {
            $recentNotifications = $taskNotificationRepository->findBy([
                'recipient' => $user
            ], ['createdAt' => 'DESC'], 5);
        };

        // Get user's categories
        $categories = $categoryRepository->findByUser($user);
        
        // Get tag statistics
        $tagStats = $tagRepository->getTagUsageStats();
        
        // Get tag completion rates
        $tagCompletionRates = $tagRepository->getTagCompletionRates();
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'task_stats' => $taskStats,
            'recent_tasks' => $recentTasks,
            'user_stats' => $userStats,
            'recent_activity' => $recentActivity,
            'completion_trends' => $completionTrends,
            'upcoming_recurring_tasks' => $upcomingRecurringTasks,
            'unread_notifications_count' => $unreadNotificationsCount,
            'recent_notifications' => $recentNotifications,
            'categories' => $categories,
            'tag_stats' => $tagStats,
            'tag_completion_rates' => $tagCompletionRates,
        ]);
    }
    
    private function getTaskCompletionTrends(TaskRepository $taskRepository, $user)
    {
        $qb = $taskRepository->createQueryBuilder('t');
        
        if ($user->hasRole('ROLE_ADMIN')) {
            // For admin, get trends for all tasks
            $trends = $qb
                ->select('DATE(t.createdAt) as date, COUNT(t.id) as total, SUM(CASE WHEN t.status = \'completed\' THEN 1 ELSE 0 END) as completed')
                ->groupBy('DATE(t.createdAt)')
                ->orderBy('date', 'DESC')
                ->setMaxResults(30) // Last 30 days
                ->getQuery()
                ->getResult();
        } else {
            // For regular user, get trends for their tasks
            $trends = $qb
                ->select('DATE(t.createdAt) as date, COUNT(t.id) as total, SUM(CASE WHEN t.status = \'completed\' THEN 1 ELSE 0 END) as completed')
                ->andWhere('t.assignedUser = :user OR t.createdBy = :user')
                ->setParameter('user', $user)
                ->groupBy('DATE(t.createdAt)')
                ->orderBy('date', 'DESC')
                ->setMaxResults(30) // Last 30 days
                ->getQuery()
                ->getResult();
        }
        
        // Format data for chart
        $labels = [];
        $totalData = [];
        $completedData = [];
        
        foreach ($trends as $trend) {
            $labels[] = $trend['date'];
            $totalData[] = (int)$trend['total'];
            $completedData[] = (int)$trend['completed'];
        }
        
        return [
            'labels' => array_reverse($labels),
            'datasets' => [
                [
                    'label' => 'Всего задач',
                    'data' => array_reverse($totalData),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                ],
                [
                    'label' => 'Выполнено',
                    'data' => array_reverse($completedData),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                ],
            ],
        ];
    }
    
    #[Route('/api/completion-stats-by-priority', name: 'app_dashboard_completion_stats_by_priority', methods: ['GET'])]
    public function getCompletionStatsByPriority(TaskRepository $taskRepository): Response
    {
        $user = $this->getUser();
        
        $stats = $taskRepository->getCompletionStatsByPriority();
        
        return $this->json($stats);
    }
}