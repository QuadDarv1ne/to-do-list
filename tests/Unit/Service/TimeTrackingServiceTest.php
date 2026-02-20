<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\TaskTimeTracking;
use App\Entity\User;
use App\Repository\TaskTimeTrackingRepository;
use App\Service\TimeTrackingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TimeTrackingServiceTest extends TestCase
{
    private TaskTimeTrackingRepository|MockObject $timeTrackingRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private TimeTrackingService $service;

    protected function setUp(): void
    {
        $this->timeTrackingRepository = $this->createMock(TaskTimeTrackingRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new TimeTrackingService(
            $this->timeTrackingRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testStartTrackingCreatesSession(): void
    {
        $task = new Task();
        $user = new User();
        $description = 'Test tracking';

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(TaskTimeTracking::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->startTracking($task, $user, $description);

        $this->assertInstanceOf(TaskTimeTracking::class, $result);
        $this->assertTrue($result->isActive());
        $this->assertEquals($task, $result->getTask());
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals($description, $result->getDescription());
    }

    public function testStopTrackingUpdatesSession(): void
    {
        $tracking = new TaskTimeTracking();
        $tracking->start();
        
        // Small delay to ensure duration > 0
        usleep(10000); // 10ms

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->stopTracking($tracking);

        $this->assertInstanceOf(TaskTimeTracking::class, $result);
        $this->assertFalse($result->isActive());
        $this->assertGreaterThanOrEqual(0, $result->getDurationSeconds());
    }

    public function testGetTimeSpentReturnsZeroForNewTask(): void
    {
        $task = new Task();

        $this->timeTrackingRepository->expects($this->once())
            ->method('getTotalTimeByTask')
            ->with($task)
            ->willReturn(0);

        $result = $this->service->getTimeSpent($task);

        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    public function testGetTimeSpentReturnsCorrectValue(): void
    {
        $task = new Task();
        $expectedTime = 3600; // 1 hour

        $this->timeTrackingRepository->expects($this->once())
            ->method('getTotalTimeByTask')
            ->with($task)
            ->willReturn($expectedTime);

        $result = $this->service->getTimeSpent($task);

        $this->assertIsInt($result);
        $this->assertEquals($expectedTime, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('durationProvider')]
    public function testFormatDuration(int $seconds, string $expected): void
    {
        $result = $this->service->formatDuration($seconds);
        $this->assertEquals($expected, $result);
    }

    public static function durationProvider(): array
    {
        return [
            'seconds only' => [45, '45с'],
            'minutes and seconds' => [125, '2м 5с'],
            'hours minutes seconds' => [3665, '1ч 1м 5с'],
            'days hours minutes seconds' => [90065, '1д 1ч 1м 5с'],
            'zero' => [0, '0с'],
            'one hour' => [3600, '1ч'],
            'one day' => [86400, '1д'],
        ];
    }

    public function testGetStatisticsReturnsCorrectData(): void
    {
        $user = new User();
        $from = new \DateTime('-7 days');
        $to = new \DateTime();

        // Create mock tracking with proper task
        $task = new Task();
        $tracking1 = new TaskTimeTracking();
        $tracking1->setTask($task);
        $tracking1->setDurationSeconds(3600);
        $tracking1->setDateLogged(new \DateTimeImmutable());
        
        $tracking2 = new TaskTimeTracking();
        $tracking2->setTask($task);
        $tracking2->setDurationSeconds(1800);
        $tracking2->setDateLogged(new \DateTimeImmutable());

        $mockTrackings = [$tracking1, $tracking2];

        $this->timeTrackingRepository->expects($this->once())
            ->method('findByUserAndDateRange')
            ->with($user, $from, $to)
            ->willReturn($mockTrackings);

        $result = $this->service->getStatistics($user, $from, $to);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_time', $result);
        $this->assertArrayHasKey('tasks_tracked', $result);
        $this->assertArrayHasKey('by_day', $result);
        $this->assertArrayHasKey('by_category', $result);
    }

    public function testGetTodaySummary(): void
    {
        $user = new User();
        $today = new \DateTime('today');
        $now = new \DateTime();

        $this->timeTrackingRepository->expects($this->any())
            ->method('findByUserAndDateRange')
            ->with($user, $this->isInstanceOf(\DateTime::class), $this->isInstanceOf(\DateTime::class))
            ->willReturn([]);

        $this->timeTrackingRepository->expects($this->any())
            ->method('findOneActiveByUser')
            ->with($user)
            ->willReturn(null);

        $result = $this->service->getTodaySummary($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_time', $result);
        $this->assertArrayHasKey('active_session', $result);
    }

    public function testGetProductivityScoreReturnsValidRange(): void
    {
        $user = new User();
        
        $this->timeTrackingRepository->expects($this->any())
            ->method('findByUserAndDateRange')
            ->willReturn([]);

        $score = $this->service->getProductivityScore($user, 7);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testToggleTrackingStartsWhenNoActiveSession(): void
    {
        $task = new Task();
        $user = new User();

        $this->timeTrackingRepository->expects($this->once())
            ->method('findOneActiveByTaskAndUser')
            ->with($task, $user)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->toggleTracking($task, $user);

        $this->assertIsArray($result);
        $this->assertEquals('started', $result['action']);
        $this->assertArrayHasKey('tracking', $result);
    }

    public function testDeleteSessionStopsActiveTracking(): void
    {
        $tracking = new TaskTimeTracking();
        $tracking->start();

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($tracking);

        $result = $this->service->deleteSession($tracking);

        $this->assertTrue($result);
    }

    public function testUpdateSessionDescription(): void
    {
        $tracking = new TaskTimeTracking();
        $newDescription = 'Updated description';

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateSessionDescription($tracking, $newDescription);

        $this->assertInstanceOf(TaskTimeTracking::class, $result);
        $this->assertEquals($newDescription, $result->getDescription());
    }
}
