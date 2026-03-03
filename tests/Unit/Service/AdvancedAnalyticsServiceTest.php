<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\AdvancedAnalyticsService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class AdvancedAnalyticsServiceTest extends TestCase
{
    private TaskRepository $taskRepository;
    private CacheItemPoolInterface $cache;
    private AdvancedAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('hasItem')->willReturn(false);
        $this->analyticsService = new AdvancedAnalyticsService(
            $this->taskRepository,
            $this->cache
        );
    }

    public function testGetDashboardReturnsStructuredData(): void
    {
        $user = $this->createMock(User::class);
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-02-28');

        // Мокируем все вызовы репозитория чтобы избежать циклов
        $this->taskRepository->method('countByStatus')
            ->willReturn(0);
        
        $this->taskRepository->method('findByAssignedUser')
            ->willReturn([]);
        
        $this->taskRepository->method('findByUserAndStatus')
            ->willReturn([]);
        
        $this->taskRepository->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock([]));

        $dashboard = $this->analyticsService->getDashboard($user, $from, $to);

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('overview', $dashboard);
        $this->assertArrayHasKey('trends', $dashboard);
        $this->assertArrayHasKey('predictions', $dashboard);
        $this->assertArrayHasKey('insights', $dashboard);
        $this->assertArrayHasKey('comparisons', $dashboard);
    }

    public function testGetOverviewMetricsCalculatesCompletionRate(): void
    {
        $user = $this->createMock(User::class);
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-01-31');

        // Мокируем данные репозитория
        $this->taskRepository->method('countByStatus')
            ->willReturnMap([
                [$user, false, null, 100], // total tasks
                [$user, true, null, 60],   // completed tasks
                [$user, false, 'in_progress', 25], // in progress
            ]);

        $this->taskRepository->method('findByAssignedUser')
            ->willReturn([]);

        $this->taskRepository->method('findByUserAndStatus')
            ->willReturn([]);

        // Используем рефлексию для вызова приватного метода
        $reflection = new \ReflectionClass(AdvancedAnalyticsService::class);
        $method = $reflection->getMethod('getOverviewMetrics');
        $method->setAccessible(true);

        $metrics = $method->invoke($this->analyticsService, $user, $from, $to);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_tasks', $metrics);
        $this->assertArrayHasKey('completed_tasks', $metrics);
        $this->assertArrayHasKey('completion_rate', $metrics);
        $this->assertEquals(100, $metrics['total_tasks']);
        $this->assertEquals(60, $metrics['completed_tasks']);
        $this->assertEquals(60, $metrics['completion_rate']);
    }

    public function testCalculateAverageCompletionTimeWithEmptyTasks(): void
    {
        $reflection = new \ReflectionClass(AdvancedAnalyticsService::class);
        $method = $reflection->getMethod('calculateAverageCompletionTime');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyticsService, []);

        $this->assertEquals(0, $result);
    }

    public function testCalculateQualityScoreWithEmptyTasks(): void
    {
        $user = $this->createMock(User::class);
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-01-31');

        $reflection = new \ReflectionClass(AdvancedAnalyticsService::class);
        $method = $reflection->getMethod('calculateQualityScore');
        $method->setAccessible(true);

        $this->taskRepository->method('findByUserAndStatus')
            ->willReturn([]);

        $result = $method->invoke($this->analyticsService, $user, $from, $to);

        $this->assertEquals(0, $result);
    }

    public function testGetPredictionsReturnsStructuredData(): void
    {
        $user = $this->createMock(User::class);

        $reflection = new \ReflectionClass(AdvancedAnalyticsService::class);
        $method = $reflection->getMethod('getPredictions');
        $method->setAccessible(true);

        $predictions = $method->invoke($this->analyticsService, $user);

        $this->assertIsArray($predictions);
        $this->assertArrayHasKey('next_week_completion', $predictions);
        $this->assertArrayHasKey('burnout_risk', $predictions);
        $this->assertArrayHasKey('capacity_utilization', $predictions);
    }

    public function testGetForecastReturnsDailyForecasts(): void
    {
        $user = $this->createMock(User::class);

        $this->taskRepository->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock([]));

        $forecast = $this->analyticsService->getForecast($user, 7);

        $this->assertIsArray($forecast);
        $this->assertCount(7, $forecast);
        
        foreach ($forecast as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('predicted_completions', $day);
            $this->assertArrayHasKey('confidence_interval', $day);
        }
    }

    public function testCalculateBurnoutRiskReturnsStructuredData(): void
    {
        $user = $this->createMock(User::class);

        $this->taskRepository->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(['COUNT' => [['num' => 5]]]));

        $risk = $this->analyticsService->calculateBurnoutRisk($user);

        $this->assertIsArray($risk);
        $this->assertArrayHasKey('risk_level', $risk);
        $this->assertArrayHasKey('risk_score', $risk);
        $this->assertArrayHasKey('factors', $risk);
        $this->assertArrayHasKey('recommendations', $risk);
        $this->assertContains($risk['risk_level'], ['low', 'medium', 'high']);
    }

    public function testAnalyzeProductivityTrendsReturnsMonthlyData(): void
    {
        $user = $this->createMock(User::class);

        $this->taskRepository->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock([
                ['completed' => 10, 'avg_time' => 86400],
                ['completed' => 15, 'avg_time' => 172800],
            ]));

        $trends = $this->analyticsService->analyzeProductivityTrends($user, 2);

        $this->assertIsArray($trends);
        $this->assertCount(2, $trends);
        
        foreach ($trends as $month) {
            $this->assertArrayHasKey('month', $month);
            $this->assertArrayHasKey('productivity_score', $month);
            $this->assertArrayHasKey('tasks_completed', $month);
        }
    }

    public function testAnalyzeTaskPatternsReturnsStructuredData(): void
    {
        $user = $this->createMock(User::class);

        $this->taskRepository->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock([]));

        $patterns = $this->analyticsService->analyzeTaskPatterns($user);

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('most_productive_time', $patterns);
        $this->assertArrayHasKey('least_productive_time', $patterns);
        $this->assertArrayHasKey('task_distribution', $patterns);
        $this->assertArrayHasKey('completion_patterns', $patterns);
        $this->assertArrayHasKey('procrastination_score', $patterns);
    }
    
    public function testGetDashboardUsesCache(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-01-31');
        
        // Мокаем кэш с попаданием
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(['cached' => 'data']);
        
        $this->cache->method('hasItem')->willReturn(true);
        $this->cache->method('getItem')->willReturn($cacheItem);
        
        $dashboard = $this->analyticsService->getDashboard($user, $from, $to);
        
        $this->assertEquals(['cached' => 'data'], $dashboard);
    }

    private function createQueryBuilderMock(array $results): \PHPUnit\Framework\MockObject\MockObject
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $query->method('getResult')
            ->willReturn($results);
        $query->method('getOneOrNullResult')
            ->willReturn($results[0] ?? null);
        $query->method('getSingleScalarResult')
            ->willReturn($results[0]['num'] ?? 0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }
}
