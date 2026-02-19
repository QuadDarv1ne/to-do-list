<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskTimeTracking;
use App\Service\TimeTrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class TimeTrackingServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private TimeTrackingService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new TimeTrackingService($this->entityManager);
    }

    public function testStartTrackingCreatesSession(): void
    {
        $task = new Task();
        $user = new User();
        
        // Используем рефлексию для проверки внутренних методов
        $result = $this->service->startTracking($task, $user);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('task_id', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('started_at', $result);
        $this->assertEquals('active', $result['status']);
    }

    public function testGetTimeSpentReturnsZeroForNewTask(): void
    {
        $task = new Task();
        
        $result = $this->service->getTimeSpent($task);
        
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    public function testFormatDurationHours(): void
    {
        $seconds = 3665; // 1 час 1 минута 5 секунд
        
        $result = $this->service->formatDuration($seconds);
        
        $this->assertEquals('1ч 1м 5с', $result);
    }

    public function testFormatDurationMinutes(): void
    {
        $seconds = 125; // 2 минуты 5 секунд
        
        $result = $this->service->formatDuration($seconds);
        
        $this->assertEquals('2м 5с', $result);
    }

    public function testFormatDurationSeconds(): void
    {
        $seconds = 45;
        
        $result = $this->service->formatDuration($seconds);
        
        $this->assertEquals('45с', $result);
    }

    public function testFormatDurationDays(): void
    {
        $seconds = 90065; // 1 день 1 час 1 минута 5 секунд
        
        $result = $this->service->formatDuration($seconds);
        
        $this->assertEquals('1д 1ч 1м 5с', $result);
    }
}
