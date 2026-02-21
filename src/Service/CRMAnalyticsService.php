<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use Doctrine\ORM\EntityManagerInterface;

class CRMAnalyticsService
{
    public function __construct(
        private DealRepository $dealRepository,
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get CRM dashboard data
     */
    public function getDashboardData(?User $manager = null): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        $startOfPrevMonth = new \DateTime('first day of last month');
        $endOfPrevMonth = new \DateTime('last day of last month');

        // Revenue
        $currentRevenue = $this->dealRepository->getTotalRevenueForPeriod($startOfMonth, $endOfMonth, $manager);
        $previousRevenue = $this->dealRepository->getTotalRevenueForPeriod($startOfPrevMonth, $endOfPrevMonth, $manager);
        $revenueTrend = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        // Deals
        $activeDeals = $this->dealRepository->findActiveDeals($manager);
        $dealsByStage = $this->dealRepository->getDealsByStage($manager);
        $dealsCountByStatus = $this->dealRepository->getDealsCountByStatus($manager);
        $overdueDeals = $this->dealRepository->getOverdueDeals($manager);

        // Conversion
        $conversionRate = $this->dealRepository->getConversionRate($startOfMonth, $endOfMonth, $manager);
        $prevConversionRate = $this->dealRepository->getConversionRate($startOfPrevMonth, $endOfPrevMonth, $manager);
        $conversionTrend = $conversionRate - $prevConversionRate;

        // Clients
        $topClients = $this->clientRepository->getTopClientsByRevenue(5, $manager);
        $totalClients = $this->clientRepository->getTotalCount($manager);
        $newClientsThisMonth = $this->clientRepository->getNewClientsCount($startOfMonth, $endOfMonth, $manager);

        return [
            'revenue' => [
                'current' => $currentRevenue,
                'previous' => $previousRevenue,
                'trend' => $revenueTrend,
            ],
            'deals' => [
                'active' => $activeDeals,
                'by_stage' => $dealsByStage,
                'by_status' => $dealsCountByStatus,
                'overdue' => $overdueDeals,
                'overdue_count' => \count($overdueDeals),
            ],
            'conversion' => [
                'rate' => $conversionRate,
                'trend' => $conversionTrend,
            ],
            'clients' => [
                'top' => $topClients,
                'total' => $totalClients,
                'new_this_month' => $newClientsThisMonth,
            ],
        ];
    }

    /**
     * Get sales funnel data with conversion rates
     */
    public function getSalesFunnelData(?User $manager = null): array
    {
        $dealsByStage = $this->dealRepository->getDealsByStage($manager);

        $funnel = [];
        $prevCount = 0;

        foreach ($dealsByStage as $stage) {
            $conversionRate = $prevCount > 0 ? ($stage['count'] / $prevCount) * 100 : 100;

            $funnel[] = [
                'stage' => $stage['stage'],
                'count' => $stage['count'],
                'total' => $stage['total'],
                'conversion_rate' => $conversionRate,
            ];

            $prevCount = $stage['count'];
        }

        return $funnel;
    }

    /**
     * Get manager performance data
     */
    public function getManagerPerformance(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Получаем всех менеджеров с их сделками
        $qb->select('
            m.id as manager_id,
            m.username,
            m.firstName,
            m.lastName,
            COUNT(DISTINCT d.id) as total_deals,
            SUM(CASE WHEN d.status = :won THEN 1 ELSE 0 END) as won_deals,
            SUM(CASE WHEN d.status = :lost THEN 1 ELSE 0 END) as lost_deals,
            SUM(CASE WHEN d.status = :in_progress THEN 1 ELSE 0 END) as active_deals,
            COALESCE(SUM(CASE WHEN d.status = :won THEN d.amount ELSE 0 END), 0) as total_revenue
        ')
            ->from(User::class, 'm')
            ->leftJoin('m.deals', 'd')
            ->where('d.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('won', 'won')
            ->setParameter('lost', 'lost')
            ->setParameter('in_progress', 'in_progress')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('m.id', 'm.username', 'm.firstName', 'm.lastName')
            ->orderBy('total_revenue', 'DESC');

        $results = $qb->getQuery()->getResult();

        $managers = [];
        foreach ($results as $row) {
            $totalDeals = (int) $row['total_deals'];
            $wonDeals = (int) $row['won_deals'];
            $lostDeals = (int) $row['lost_deals'];

            $winRate = $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 2) : 0;
            $avgDealAmount = $wonDeals > 0 ? round((float) $row['total_revenue'] / $wonDeals, 2) : 0;

            $managers[] = [
                'manager_id' => $row['manager_id'],
                'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')) ?: $row['username'],
                'total_deals' => $totalDeals,
                'won_deals' => $wonDeals,
                'lost_deals' => $lostDeals,
                'active_deals' => (int) $row['active_deals'],
                'total_revenue' => (float) $row['total_revenue'],
                'win_rate' => $winRate,
                'avg_deal_amount' => $avgDealAmount,
            ];
        }

        // Рассчитываем средние показатели
        $totalManagers = \count($managers);
        $avgMetrics = [];
        if ($totalManagers > 0) {
            $avgMetrics = [
                'avg_deals_per_manager' => round(array_sum(array_column($managers, 'total_deals')) / $totalManagers, 2),
                'avg_revenue_per_manager' => round(array_sum(array_column($managers, 'total_revenue')) / $totalManagers, 2),
                'avg_win_rate' => round(array_sum(array_column($managers, 'win_rate')) / $totalManagers, 2),
            ];
        }

        // Находим лучшего менеджера
        $topManager = !empty($managers) ? reset($managers) : null;

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'managers' => $managers,
            'summary' => [
                'total_managers' => $totalManagers,
                'total_deals' => array_sum(array_column($managers, 'total_deals')),
                'total_won' => array_sum(array_column($managers, 'won_deals')),
                'total_lost' => array_sum(array_column($managers, 'lost_deals')),
                'total_revenue' => array_sum(array_column($managers, 'total_revenue')),
            ],
            'averages' => $avgMetrics,
            'top_manager' => $topManager,
        ];
    }

    /**
     * Get client activity statistics
     */
    public function getClientActivityStats(?User $manager = null): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');

        $newClients = $this->clientRepository->getNewClientsCount($startOfMonth, $endOfMonth, $manager);
        $inactiveClients = $this->clientRepository->getClientsWithoutRecentContact(30, $manager);

        return [
            'new_clients' => $newClients,
            'inactive_clients' => \count($inactiveClients),
            'total_clients' => $this->clientRepository->getTotalCount($manager),
        ];
    }

    /**
     * Calculate average deal cycle time
     */
    public function getAverageDealCycle(?User $manager = null): float
    {
        // This would require additional repository method to calculate
        // average time from creation to close for won deals
        return 0;
    }

    /**
     * Get win rate percentage
     */
    public function getWinRate(\DateTime $startDate, \DateTime $endDate, ?User $manager = null): float
    {
        return $this->dealRepository->getConversionRate($startDate, $endDate, $manager);
    }
}
