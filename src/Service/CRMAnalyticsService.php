<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;

class CRMAnalyticsService
{
    public function __construct(
        private DealRepository $dealRepository,
        private ClientRepository $clientRepository,
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
        // This would require additional repository methods
        // For now, return empty array
        return [];
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
