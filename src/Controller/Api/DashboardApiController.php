<?php

namespace App\Controller\Api;

use App\Repository\ActivityLogRepository;
use App\Repository\TaskRepository;
use App\Service\QueryCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardApiController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private ActivityLogRepository $activityLogRepository,
        private QueryCacheService $cacheService,
    ) {
    }

    #[Route('/recent-tasks', name: 'api_dashboard_recent_tasks', methods: ['GET'])]
    public function getRecentTasks(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = min(10, max(1, $request->query->getInt('limit', 5)));

        // Cache for 1 minute
        $cacheKey = "dashboard_recent_tasks_{$user->getId()}_{$limit}";
        $tasks = $this->cacheService->cacheQuery($cacheKey, function () use ($user, $limit) {
            return $this->taskRepository->createQueryBuilder('t')
                ->leftJoin('t.category', 'c')
                ->addSelect('c')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->setParameter('user', $user)
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }, 60);

        $data = array_map(function ($task) {
            return [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'category' => $task->getCategory()?->getName(),
                'createdAt' => $task->getCreatedAt()->format('c'),
                'dueDate' => $task->getDueDate()?->format('c'),
            ];
        }, $tasks);

        return $this->json($data);
    }

    #[Route('/statistics', name: 'api_dashboard_statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        $user = $this->getUser();

        // Cache for 2 minutes
        $cacheKey = "dashboard_statistics_{$user->getId()}";
        $stats = $this->cacheService->cacheQuery($cacheKey, function () use ($user) {
            return $this->taskRepository->getQuickStats($user);
        }, 120);

        return $this->json($stats);
    }

    #[Route('/activity', name: 'api_dashboard_activity', methods: ['GET'])]
    public function getActivity(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = min(20, max(1, $request->query->getInt('limit', 10)));

        // Cache for 30 seconds
        $cacheKey = "dashboard_activity_{$user->getId()}_{$limit}";
        $activities = $this->cacheService->cacheQuery($cacheKey, function () use ($user, $limit) {
            return $this->activityLogRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->setParameter('user', $user)
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }, 30);

        $data = array_map(function ($activity) {
            return [
                'type' => $activity->getAction(),
                'text' => $activity->getDescription(),
                'timestamp' => $activity->getCreatedAt()->format('c'),
            ];
        }, $activities);

        return $this->json($data);
    }

    #[Route('/recent-activity', name: 'api_dashboard_recent_activity', methods: ['GET'])]
    public function getRecentActivity(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = min(10, max(1, $request->query->getInt('limit', 5)));

        // Cache for 1 minute
        $cacheKey = "dashboard_recent_activity_{$user->getId()}_{$limit}";
        $activities = $this->cacheService->cacheQuery($cacheKey, function () use ($user, $limit) {
            return $this->activityLogRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->setParameter('user', $user)
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }, 60);

        $data = array_map(function ($activity) {
            return [
                'id' => $activity->getId(),
                'type' => $activity->getAction(),
                'description' => $activity->getDescription(),
                'createdAt' => $activity->getCreatedAt()->format('c'),
            ];
        }, $activities);

        return $this->json($data);
    }

    #[Route('/weekly-stats', name: 'api_dashboard_weekly_stats', methods: ['GET'])]
    public function getWeeklyStats(): JsonResponse
    {
        $user = $this->getUser();

        // Cache for 5 minutes
        $cacheKey = "dashboard_weekly_stats_{$user->getId()}";
        $stats = $this->cacheService->cacheQuery($cacheKey, function () use ($user) {
            $startOfWeek = new \DateTime('monday this week');
            $endOfWeek = new \DateTime('sunday this week');

            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $startOfWeek)
                ->setParameter('end', $endOfWeek)
                ->getQuery()
                ->getResult();

            $completed = array_filter($tasks, fn ($t) => $t->getStatus() === 'completed');
            $total = \count($tasks);
            $completedCount = \count($completed);

            return [
                'total' => $total,
                'completed' => $completedCount,
                'productivity' => $total > 0 ? round(($completedCount / $total) * 100) : 0,
                'streak' => $this->calculateStreak($user),
            ];
        }, 300);

        return $this->json($stats);
    }

    #[Route('/chart-data', name: 'api_dashboard_chart_data', methods: ['GET'])]
    public function getChartData(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $period = $request->query->get('period', 'week');

        // Cache for 5 minutes
        $cacheKey = "dashboard_chart_data_{$user->getId()}_{$period}";
        $data = $this->cacheService->cacheQuery($cacheKey, function () use ($user, $period) {
            $days = $period === 'month' ? 30 : 7;
            $labels = [];
            $taskData = [];
            $completedData = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = new \DateTime("-{$i} days");
                $nextDate = (clone $date)->modify('+1 day');

                $labels[] = $date->format('d.m');

                $tasks = $this->taskRepository->createQueryBuilder('t')
                    ->where('t.user = :user OR t.assignedUser = :user')
                    ->andWhere('t.createdAt BETWEEN :start AND :end')
                    ->setParameter('user', $user)
                    ->setParameter('start', $date)
                    ->setParameter('end', $nextDate)
                    ->getQuery()
                    ->getResult();

                $completed = array_filter($tasks, fn ($t) => $t->getStatus() === 'completed');

                $taskData[] = \count($tasks);
                $completedData[] = \count($completed);
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Создано задач',
                        'data' => $taskData,
                        'borderColor' => 'rgb(102, 126, 234)',
                        'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Завершено',
                        'data' => $completedData,
                        'borderColor' => 'rgb(40, 167, 69)',
                        'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                        'tension' => 0.4,
                    ],
                ],
            ];
        }, 300);

        return $this->json($data);
    }

    /**
     * Calculate user's current streak
     */
    private function calculateStreak($user): int
    {
        $streak = 0;
        $currentDate = new \DateTime();
        $currentDate->setTime(0, 0, 0); // Начало дня

        // Проверяем последовательные дни назад от сегодня
        for ($i = 0; $i < 365; $i++) {
            $startOfDay = (clone $currentDate)->modify("-{$i} days");
            $endOfDay = (clone $startOfDay)->setTime(23, 59, 59);

            $tasksCompleted = $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.status = :completed')
                ->andWhere('t.completedAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay)
                ->getQuery()
                ->getSingleScalarResult();

            if ($tasksCompleted > 0) {
                $streak++;
            } else {
                // Прерываем серию при первом дне без задач
                break;
            }
        }

        return $streak;
    }
}
