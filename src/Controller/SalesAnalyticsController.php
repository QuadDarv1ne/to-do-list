<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics/sales')]
#[IsGranted('ROLE_USER')]
class SalesAnalyticsController extends AbstractController
{
    #[Route('/', name: 'app_sales_analytics', methods: ['GET'])]
    public function index(
        Request $request,
        TaskRepository $taskRepository,
        UserRepository $userRepository,
    ): Response {
        $user = $this->getUser();

        // Get filter parameters
        $period = $request->query->get('period', 'month'); // day, week, month, quarter, year
        $managerId = $request->query->get('manager');
        $categoryId = $request->query->get('category');

        // Calculate date range based on period
        $dateRange = $this->calculateDateRange($period);

        // Get sales data
        $salesData = $this->getSalesData(
            $taskRepository,
            $dateRange,
            $managerId,
            $categoryId,
            $user,
        );

        // Get active managers list for filter (optimized)
        $managers = $userRepository->findActiveUsers();

        // Get categories for filter
        $categories = $taskRepository->createQueryBuilder('t')
            ->select('DISTINCT c.id, c.name')
            ->leftJoin('t.category', 'c')
            ->where('c.id IS NOT NULL')
            ->getQuery()
            ->getResult();

        return $this->render('analytics/sales.html.twig', [
            'sales_data' => $salesData,
            'managers' => $managers,
            'categories' => $categories,
            'current_period' => $period,
            'current_manager' => $managerId,
            'current_category' => $categoryId,
            'date_range' => $dateRange,
        ]);
    }

    private function calculateDateRange(string $period): array
    {
        $now = new \DateTime();
        $start = clone $now;

        switch ($period) {
            case 'day':
                $start->modify('-1 day');

                break;
            case 'week':
                $start->modify('-7 days');

                break;
            case 'month':
                $start->modify('-30 days');

                break;
            case 'quarter':
                $start->modify('-90 days');

                break;
            case 'year':
                $start->modify('-365 days');

                break;
            default:
                $start->modify('-30 days');
        }

        return [
            'start' => $start,
            'end' => $now,
            'label' => $this->getPeriodLabel($period),
        ];
    }

    private function getPeriodLabel(string $period): string
    {
        return match($period) {
            'day' => 'За последний день',
            'week' => 'За последнюю неделю',
            'month' => 'За последний месяц',
            'quarter' => 'За последний квартал',
            'year' => 'За последний год',
            default => 'За последний месяц',
        };
    }

    private function getSalesData(
        TaskRepository $taskRepository,
        array $dateRange,
        ?int $managerId,
        ?int $categoryId,
        $user,
    ): array {
        $qb = $taskRepository->createQueryBuilder('t')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end']);

        // Filter by manager if specified
        if ($managerId) {
            $qb->andWhere('t.assignedUser = :manager')
               ->setParameter('manager', $managerId);
        } elseif (!$this->isGranted('ROLE_ADMIN')) {
            // Non-admin users see only their tasks
            $qb->andWhere('t.assignedUser = :user OR t.createdBy = :user')
               ->setParameter('user', $user);
        }

        // Filter by category if specified
        if ($categoryId) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $categoryId);
        }

        $tasks = $qb->getQuery()->getResult();

        // Calculate metrics
        $total = \count($tasks);
        $completed = \count(array_filter($tasks, fn ($t) => $t->isCompleted()));
        $inProgress = \count(array_filter($tasks, fn ($t) => $t->getStatus() === 'in_progress'));
        $pending = \count(array_filter($tasks, fn ($t) => $t->getStatus() === 'pending'));

        // Group by date for chart
        $dailyStats = [];
        foreach ($tasks as $task) {
            $date = $task->getCreatedAt()->format('Y-m-d');
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = ['total' => 0, 'completed' => 0];
            }
            $dailyStats[$date]['total']++;
            if ($task->isCompleted()) {
                $dailyStats[$date]['completed']++;
            }
        }

        // Sort by date
        ksort($dailyStats);

        // Group by manager
        $managerStats = [];
        foreach ($tasks as $task) {
            $manager = $task->getAssignedUser();
            if ($manager) {
                $managerId = $manager->getId();
                if (!isset($managerStats[$managerId])) {
                    $managerStats[$managerId] = [
                        'name' => $manager->getFullName(),
                        'total' => 0,
                        'completed' => 0,
                        'conversion' => 0,
                    ];
                }
                $managerStats[$managerId]['total']++;
                if ($task->isCompleted()) {
                    $managerStats[$managerId]['completed']++;
                }
            }
        }

        // Calculate conversion rates
        foreach ($managerStats as &$stats) {
            $stats['conversion'] = $stats['total'] > 0
                ? round(($stats['completed'] / $stats['total']) * 100, 1)
                : 0;
        }

        // Sort by total descending
        uasort($managerStats, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'conversion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'daily_stats' => $dailyStats,
            'manager_stats' => $managerStats,
            'top_managers' => \array_slice($managerStats, 0, 5, true),
        ];
    }
}
