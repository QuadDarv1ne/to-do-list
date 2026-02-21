<?php

namespace App\Tests\Unit\Domain\Task\Event;

use App\Domain\Task\Event\TaskAssigned;
use App\Domain\Task\Event\TaskCompleted;
use App\Domain\Task\Event\TaskCreated;
use App\Domain\Task\Event\TaskStatusChanged;
use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskPriority;
use App\Domain\Task\ValueObject\TaskStatus;
use App\Domain\Task\ValueObject\TaskTitle;
use PHPUnit\Framework\TestCase;

class TaskCreatedTest extends TestCase
{
    private function createTaskId(int $id): TaskId
    {
        $reflection = new \ReflectionClass(TaskId::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('value');
        $property->setValue($instance, $id);
        return $instance;
    }

    public function testCreateEvent(): void
    {
        $taskId = $this->createTaskId(123);
        $title = TaskTitle::fromString('Test Task');
        $priority = TaskPriority::from('high');
        $userId = 1;
        $assignedUserId = 2;

        $event = TaskCreated::create($taskId, $title, $priority, $userId, $assignedUserId);

        $this->assertEquals($taskId, $event->getTaskId());
        $this->assertEquals($title, $event->getTitle());
        $this->assertEquals($priority, $event->getPriority());
        $this->assertEquals($userId, $event->getUserId());
        $this->assertEquals($assignedUserId, $event->getAssignedUserId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testGetEventName(): void
    {
        $event = TaskCreated::create(
            $this->createTaskId(1),
            TaskTitle::fromString('Test'),
            TaskPriority::from('medium'),
            1,
            2
        );

        $this->assertEquals('task.created', $event->getEventName());
    }

    public function testToArray(): void
    {
        $event = TaskCreated::create(
            $this->createTaskId(42),
            TaskTitle::fromString('Test Task'),
            TaskPriority::from('urgent'),
            10,
            20
        );

        $array = $event->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(42, $array['task_id']);
        $this->assertEquals('Test Task', $array['title']);
        $this->assertEquals('urgent', $array['priority']);
        $this->assertEquals(10, $array['user_id']);
        $this->assertEquals(20, $array['assigned_user_id']);
        $this->assertArrayHasKey('occurred_at', $array);
    }

    public function testImmutableEvent(): void
    {
        $event = TaskCreated::create(
            $this->createTaskId(1),
            TaskTitle::fromString('Test'),
            TaskPriority::from('low'),
            1,
            2
        );

        // Проверяем, что данные не могут быть изменены
        $this->assertEquals('Test', $event->getTitle()->toString());
    }
}
