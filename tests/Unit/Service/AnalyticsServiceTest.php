<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\AnalyticsService;
use App\Service\PerformanceMonitoringService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AnalyticsServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private TaskRepository|MockObject $taskRepository;
    private PerformanceMonitoringService|MockObject $performanceMonitor;
    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->performanceMonitor = $this->createMock(PerformanceMonitoringService::class);

        $this->analyticsService = new AnalyticsService(
            $this->entityManager,
            $this->logger,
            $this->taskRepository,
            $this->performanceMonitor,
        );
    }

    public function testGetUserTaskAnalyticsReturnsCompleteData(): void
    {
        $user = $this->createMock(User::class);

        // Mock performance monitoring
        $this->performanceMonitor
            ->method('startTiming')
            ->willReturnCallback(fn () => null);
        $this->performanceMonitor
            ->method('stopTiming')
            ->willReturnCallback(fn (): array => []);

        // Mock entity manager для getOverviewStats
        $queryBuilder = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleResult')
            ->willReturn([
                'total_tasks' => 10,
                'completed_tasks' => 5,
                'overdue_tasks' => 2,
                'pending_tasks' => 3,
            ]);
        
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $analytics = $this->analyticsService->getUserTaskAnalytics($user);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('overview', $analytics);
        $this->assertArrayHasKey('completion_rates', $analytics);
        $this->assertArrayHasKey('productivity_trends', $analytics);
        $this->assertArrayHasKey('category_analysis', $analytics);
        $this->assertArrayHasKey('priority_analysis', $analytics);
        $this->assertArrayHasKey('time_analysis', $analytics);
        $this->assertArrayHasKey('performance_metrics', $analytics);
        $this->assertArrayHasKey('prediction_analysis', $analytics);
        $this->assertArrayHasKey('dependency_analysis', $analytics);
    }

    public function testGetDashboardDataReturnsAllWidgets(): void
    {
        $user = $this->createMock(User::class);

        $this->performanceMonitor
            ->method('startTiming')
            ->willReturnCallback(fn () => null);
        $this->performanceMonitor
            ->method('stopTiming')
            ->willReturnCallback(fn (): array => []);

        $this->taskRepository
            ->method('getQuickStats')
            ->with($user)
            ->willReturn([
                'recent_tasks' => [],
            ]);

        $dashboardData = $this->analyticsService->getDashboardData($user);

        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('quickStats', $dashboardData);
        $this->assertArrayHasKey('overview', $dashboardData);
        $this->assertArrayHasKey('completionRates', $dashboardData);
        $this->assertArrayHasKey('productivityTrends', $dashboardData);
        $this->assertArrayHasKey('priorityAnalysis', $dashboardData);
        $this->assertArrayHasKey('categoryAnalysis', $dashboardData);
        $this->assertArrayHasKey('recent_tasks', $dashboardData);
    }

    public function testExportAnalyticsToCsvReturnsValidCsv(): void
    {
        $user = $this->createMock(User::class);

        $this->performanceMonitor
            ->method('startTiming')
            ->willReturnCallback(fn () => null);
        $this->performanceMonitor
            ->method('stopTiming')
            ->willReturnCallback(fn (): array => []);

        $csv = $this->analyticsService->exportAnalyticsToCsv($user);

        $this->assertIsString($csv);
        $this->assertStringContainsString('Метрика,Значение', $csv);
        $this->assertStringContainsString('Всего задач', $csv);
        $this->assertStringContainsString('Завершено', $csv);
        $this->assertStringContainsString('Процент завершения', $csv);
    }

    public function testGetPeriodComparisonReturnsComparativeMetrics(): void
    {
        $user = $this->createMock(User::class);

        $this->performanceMonitor
            ->method('startTiming')
            ->willReturnCallback(fn () => null);
        $this->performanceMonitor
            ->method('stopTiming')
            ->willReturnCallback(fn (): array => []);

        $comparison = $this->analyticsService->getPeriodComparison($user, 'this_month', 'last_month');

        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('period1', $comparison);
        $this->assertArrayHasKey('period2', $comparison);
        $this->assertArrayHasKey('differences', $comparison);
        $this->assertArrayHasKey('trend', $comparison);
    }

    public function testGetSystemPerformanceMetricsDelegatesToPerformanceMonitor(): void
    {
        $this->performanceMonitor
            ->method('getPerformanceReport')
            ->willReturn([
                'avg_response_time' => 0.15,
                'slow_queries' => [],
            ]);

        $metrics = $this->analyticsService->getSystemPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertNotEmpty($metrics);
    }
}
