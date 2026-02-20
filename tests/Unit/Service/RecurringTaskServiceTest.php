<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\TaskRecurrence;
use App\Entity\User;
use App\Repository\TaskRecurrenceRepository;
use App\Repository\TaskRepository;
use App\Service\RecurringTaskService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecurringTaskServiceTest extends TestCase
{
    private TaskRecurrenceRepository|MockObject $recurrenceRepository;
    private TaskRepository|MockObject $taskRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private RecurringTaskService $service;

    protected function setUp(): void
    {
        $this->recurrenceRepository = $this->createMock(TaskRecurrenceRepository::class);
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new RecurringTaskService(
            $this->recurrenceRepository,
            $this->taskRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testCreateRecurringCreatesRecord(): void
    {
        $template = new Task();
        $user = new User();
        $frequency = 'daily';
        $interval = 1;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(TaskRecurrence::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Recurring task created'));

        $result = $this->service->createRecurring($template, $user, $frequency, $interval);

        $this->assertInstanceOf(TaskRecurrence::class, $result);
        $this->assertEquals($frequency, $result->getFrequency());
        $this->assertEquals($interval, $result->getInterval());
    }

    public function testGetPatternsReturnsAllTypes(): void
    {
        $patterns = $this->service->getPatterns();

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('daily', $patterns);
        $this->assertArrayHasKey('weekly', $patterns);
        $this->assertArrayHasKey('monthly', $patterns);
        $this->assertArrayHasKey('yearly', $patterns);
        $this->assertArrayHasKey('custom', $patterns);

        foreach ($patterns as $pattern) {
            $this->assertArrayHasKey('name', $pattern);
            $this->assertArrayHasKey('description', $pattern);
            $this->assertArrayHasKey('icon', $pattern);
        }
    }

    public function testGetDaysOfWeekOptionsReturnsSevenDays(): void
    {
        $days = $this->service->getDaysOfWeekOptions();

        $this->assertIsArray($days);
        $this->assertCount(7, $days);
        $this->assertEquals('Понедельник', $days[1]);
        $this->assertEquals('Воскресенье', $days[7]);
    }

    public function testGetDaysOfMonthOptionsReturnsThirtyOneDays(): void
    {
        $days = $this->service->getDaysOfMonthOptions();

        $this->assertIsArray($days);
        $this->assertCount(31, $days);
        $this->assertArrayHasKey(1, $days);
        $this->assertArrayHasKey(31, $days);
    }

    public function testSkipWeekendMovesSaturdayToFriday(): void
    {
        // Saturday (2026-02-21)
        $saturday = new \DateTimeImmutable('2026-02-21');
        
        $result = $this->service->skipWeekend($saturday);
        
        $this->assertEquals('2026-02-20', $result->format('Y-m-d'));
    }

    public function testSkipWeekendMovesSundayToMonday(): void
    {
        // Sunday (2026-02-22)
        $sunday = new \DateTimeImmutable('2026-02-22');
        
        $result = $this->service->skipWeekend($sunday);
        
        $this->assertEquals('2026-02-23', $result->format('Y-m-d'));
    }

    public function testSkipWeekendReturnsSameDayForWeekday(): void
    {
        // Wednesday (2026-02-18)
        $wednesday = new \DateTimeImmutable('2026-02-18');
        
        $result = $this->service->skipWeekend($wednesday);
        
        $this->assertEquals('2026-02-18', $result->format('Y-m-d'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frequencyProvider')]
    public function testCalculateNextDateForAllFrequencies(
        string $frequency,
        string $expectedDate
    ): void {
        $from = new \DateTimeImmutable('2026-02-20');
        $interval = 1;

        $recurrence = new TaskRecurrence();
        $recurrence->setFrequency($frequency);
        $recurrence->setInterval($interval);
        $recurrence->setLastGenerated($from);

        $this->recurrenceRepository->expects($this->once())
            ->method('findActiveRecurrences')
            ->willReturn([$recurrence]);

        // Mock the task creation process
        $template = new Task();
        $this->taskRepository->expects($this->any())
            ->method('find')
            ->willReturn($template);

        $this->entityManager->expects($this->any())
            ->method('persist');

        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->service->processRecurringTasks();

        $this->assertEquals(
            $expectedDate,
            $recurrence->getLastGenerated()->format('Y-m-d')
        );
    }

    public static function frequencyProvider(): array
    {
        return [
            'daily' => ['daily', '2026-02-20'],
            'weekly' => ['weekly', '2026-02-20'],
            'monthly' => ['monthly', '2026-02-20'],
            'yearly' => ['yearly', '2026-02-20'],
        ];
    }

    public function testGetStatisticsReturnsCorrectData(): void
    {
        $user = new User();
        $recurrence1 = new TaskRecurrence();
        $recurrence1->setFrequency('daily');
        $recurrence2 = new TaskRecurrence();
        $recurrence2->setFrequency('weekly');

        $this->recurrenceRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn([$recurrence1, $recurrence2]);

        $stats = $this->service->getStatistics($user);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('inactive', $stats);
        $this->assertArrayHasKey('by_frequency', $stats);
        
        $this->assertEquals(2, $stats['total']);
        $this->assertArrayHasKey('daily', $stats['by_frequency']);
        $this->assertArrayHasKey('weekly', $stats['by_frequency']);
    }

    public function testDeleteRecurringRemovesRecord(): void
    {
        $recurrence = new TaskRecurrence();

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($recurrence);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Recurring task deleted'));

        $result = $this->service->deleteRecurring($recurrence);

        $this->assertTrue($result);
    }

    public function testUpdateRecurringUpdatesFields(): void
    {
        $recurrence = new TaskRecurrence();
        $recurrence->setFrequency('daily');
        $recurrence->setInterval(1);

        $newFrequency = 'weekly';
        $newInterval = 2;

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateRecurring(
            $recurrence,
            $newFrequency,
            $newInterval
        );

        $this->assertInstanceOf(TaskRecurrence::class, $result);
        $this->assertEquals($newFrequency, $result->getFrequency());
        $this->assertEquals($newInterval, $result->getInterval());
    }

    public function testGetUserRecurringTasksDelegatesToRepository(): void
    {
        $user = new User();
        $expectedRecurrences = [
            $this->createMock(TaskRecurrence::class),
            $this->createMock(TaskRecurrence::class),
        ];

        $this->recurrenceRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($expectedRecurrences);

        $result = $this->service->getUserRecurringTasks($user);

        $this->assertEquals($expectedRecurrences, $result);
    }

    public function testGetUpcomingRecurringTasksDelegatesToRepository(): void
    {
        $user = new User();
        $limit = 5;
        $expectedRecurrences = [
            $this->createMock(TaskRecurrence::class),
        ];

        $this->recurrenceRepository->expects($this->once())
            ->method('findUpcomingForUser')
            ->with($user, $limit)
            ->willReturn($expectedRecurrences);

        $result = $this->service->getUpcomingRecurringTasks($user, $limit);

        $this->assertEquals($expectedRecurrences, $result);
    }
}
