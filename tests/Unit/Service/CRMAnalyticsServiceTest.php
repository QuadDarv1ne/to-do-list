<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use App\Service\CRMAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CRMAnalyticsServiceTest extends TestCase
{
    private DealRepository $dealRepository;

    private ClientRepository $clientRepository;

    private EntityManagerInterface $entityManager;

    private CRMAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->dealRepository = $this->createMock(DealRepository::class);
        $this->clientRepository = $this->createMock(ClientRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->analyticsService = new CRMAnalyticsService(
            $this->dealRepository,
            $this->clientRepository,
            $this->entityManager,
        );
    }

    public function testGetDashboardDataReturnsStructuredData(): void
    {
        $manager = $this->createMock(User::class);

        // Мокируем данные репозиториев
        $this->dealRepository->method('getTotalRevenueForPeriod')
            ->willReturnOnConsecutiveCalls(150000.0, 120000.0);

        $this->dealRepository->method('findActiveDeals')
            ->willReturn([]); // Возвращаем массив вместо int

        $this->dealRepository->method('getDealsByStage')
            ->willReturn([
                ['stage' => 'new', 'count' => 10, 'total' => 50],
                ['stage' => 'contacted', 'count' => 8, 'total' => 40],
                ['stage' => 'proposal', 'count' => 5, 'total' => 30],
                ['stage' => 'won', 'count' => 3, 'total' => 20],
            ]);

        $this->dealRepository->method('getDealsCountByStatus')
            ->willReturn([
                'new' => 10,
                'in_progress' => 15,
                'won' => 5,
                'lost' => 3,
            ]);

        $this->dealRepository->method('getOverdueDeals')
            ->willReturn([]);

        $this->dealRepository->method('getConversionRate')
            ->willReturnOnConsecutiveCalls(25.5, 20.0);

        $this->clientRepository->method('getTopClientsByRevenue')
            ->willReturn([]);

        $this->clientRepository->method('getTotalCount')
            ->willReturn(100);

        $this->clientRepository->method('getNewClientsCount')
            ->willReturn(15);

        $dashboard = $this->analyticsService->getDashboardData($manager);

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('revenue', $dashboard);
        $this->assertArrayHasKey('deals', $dashboard);
        $this->assertArrayHasKey('conversion', $dashboard);
        $this->assertArrayHasKey('clients', $dashboard);

        // Проверяем revenue trend (25% рост)
        $this->assertEquals(25.0, $dashboard['revenue']['trend']);

        // Проверяем conversion trend (5.5% рост)
        $this->assertEquals(5.5, $dashboard['conversion']['trend']);
    }

    public function testGetSalesFunnelDataCalculatesConversion(): void
    {
        $manager = $this->createMock(User::class);

        $this->dealRepository->method('getDealsByStage')
            ->willReturn([
                ['stage' => 'new', 'count' => 100, 'total' => 100],
                ['stage' => 'contacted', 'count' => 80, 'total' => 100],
                ['stage' => 'proposal', 'count' => 60, 'total' => 100],
                ['stage' => 'won', 'count' => 40, 'total' => 100],
            ]);

        $funnel = $this->analyticsService->getSalesFunnelData($manager);

        $this->assertIsArray($funnel);
        $this->assertCount(4, $funnel);

        // Проверяем первую стадию (100% конверсия)
        $this->assertEquals(100, $funnel[0]['conversion_rate']);

        // Проверяем вторую стадию (80% конверсия)
        $this->assertEquals(80.0, $funnel[1]['conversion_rate']);

        // Проверяем третью стадию (75% конверсия)
        $this->assertEquals(75.0, $funnel[2]['conversion_rate']);
    }

    public function testGetManagerPerformanceReturnsDetailedMetrics(): void
    {
        $startDate = new \DateTime('2026-01-01');
        $endDate = new \DateTime('2026-01-31');

        $query = $this->createMock(\Doctrine\ORM\Query::class);

        // Мокаем результаты запроса
        $mockResults = [
            [
                'manager_id' => 1,
                'username' => 'manager1',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'total_deals' => 20,
                'won_deals' => 15,
                'lost_deals' => 3,
                'active_deals' => 2,
                'total_revenue' => 450000.0,
            ],
            [
                'manager_id' => 2,
                'username' => 'manager2',
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'total_deals' => 18,
                'won_deals' => 12,
                'lost_deals' => 4,
                'active_deals' => 2,
                'total_revenue' => 360000.0,
            ],
        ];

        $query->method('getResult')->willReturn($mockResults);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $performance = $this->analyticsService->getManagerPerformance($startDate, $endDate);

        $this->assertIsArray($performance);
        $this->assertArrayHasKey('period', $performance);
        $this->assertArrayHasKey('managers', $performance);
        $this->assertArrayHasKey('summary', $performance);
        $this->assertArrayHasKey('averages', $performance);
        $this->assertArrayHasKey('top_manager', $performance);

        // Проверяем количество менеджеров
        $this->assertCount(2, $performance['managers']);

        // Проверяем расчёт win rate для первого менеджера (75%)
        $this->assertEquals(75.0, $performance['managers'][0]['win_rate']);

        // Проверяем среднего менеджера
        $this->assertEquals(19.0, $performance['averages']['avg_deals_per_manager']);
    }

    public function testGetClientActivityStatsReturnsMetrics(): void
    {
        $manager = $this->createMock(User::class);

        $this->clientRepository->method('getNewClientsCount')
            ->willReturn(15);

        $this->clientRepository->method('getClientsWithoutRecentContact')
            ->willReturn([]);

        $this->clientRepository->method('getTotalCount')
            ->willReturn(100);

        $stats = $this->analyticsService->getClientActivityStats($manager);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('new_clients', $stats);
        $this->assertArrayHasKey('inactive_clients', $stats);
        $this->assertArrayHasKey('total_clients', $stats);

        $this->assertEquals(15, $stats['new_clients']);
        $this->assertEquals(0, $stats['inactive_clients']);
        $this->assertEquals(100, $stats['total_clients']);
    }

    public function testGetAverageDealCycleReturnsZero(): void
    {
        $manager = $this->createMock(User::class);

        $cycle = $this->analyticsService->getAverageDealCycle($manager);

        $this->assertEquals(0, $cycle);
    }

    public function testGetWinRateDelegatesToRepository(): void
    {
        $manager = $this->createMock(User::class);
        $startDate = new \DateTime('2026-01-01');
        $endDate = new \DateTime('2026-01-31');

        $this->dealRepository->method('getConversionRate')
            ->with($startDate, $endDate, $manager)
            ->willReturn(35.5);

        $winRate = $this->analyticsService->getWinRate($startDate, $endDate, $manager);

        $this->assertEquals(35.5, $winRate);
    }

    public function testGetDashboardDataWithoutManager(): void
    {
        $this->dealRepository->method('getTotalRevenueForPeriod')
            ->willReturn(0.0);

        $this->dealRepository->method('findActiveDeals')
            ->willReturn([]);

        $this->dealRepository->method('getDealsByStage')
            ->willReturn([]);

        $this->dealRepository->method('getDealsCountByStatus')
            ->willReturn([]);

        $this->dealRepository->method('getOverdueDeals')
            ->willReturn([]);

        $this->dealRepository->method('getConversionRate')
            ->willReturn(0.0);

        $this->clientRepository->method('getTopClientsByRevenue')
            ->willReturn([]);

        $this->clientRepository->method('getTotalCount')
            ->willReturn(0);

        $this->clientRepository->method('getNewClientsCount')
            ->willReturn(0);

        $dashboard = $this->analyticsService->getDashboardData(null);

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('revenue', $dashboard);
        $this->assertArrayHasKey('deals', $dashboard);
        $this->assertArrayHasKey('conversion', $dashboard);
        $this->assertArrayHasKey('clients', $dashboard);
    }

    public function testGetManagerPerformanceWithNoManagers(): void
    {
        $startDate = new \DateTime('2026-01-01');
        $endDate = new \DateTime('2026-01-31');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $performance = $this->analyticsService->getManagerPerformance($startDate, $endDate);

        $this->assertIsArray($performance);
        $this->assertArrayHasKey('period', $performance);
        $this->assertArrayHasKey('managers', $performance);
        $this->assertEmpty($performance['managers']);
        $this->assertEquals(0, $performance['summary']['total_managers']);
    }
}
