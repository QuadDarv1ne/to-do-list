<?php

namespace App\Tests\Unit\Domain\Task\Event;

use App\Domain\Task\Event\TaskCompleted;
use App\Domain\Task\Event\TaskStatusChanged;
use App\Domain\Task\ValueObject\TaskId;
use App\Domain\Task\ValueObject\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskCompletedTest extends TestCase
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
        $taskId = $this->createTaskId(999);
        $completedByUserId = 42;
        $completedAt = new \DateTimeImmutable('2026-02-21 15:30:00');

        $event = TaskCompleted::create($taskId, $completedByUserId, $completedAt);

        $this->assertEquals($taskId, $event->getTaskId());
        $this->assertEquals($completedByUserId, $event->getCompletedByUserId());
        $this->assertEquals($completedAt, $event->getCompletedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testGetEventName(): void
    {
        $event = TaskCompleted::create(
            $this->createTaskId(1),
            1,
            new \DateTimeImmutable()
        );

        $this->assertEquals('task.completed', $event->getEventName());
    }

    public function testToArray(): void
    {
        $completedAt = new \DateTimeImmutable('2026-02-21 15:30:00');
        $event = TaskCompleted::create(
            $this->createTaskId(123),
            456,
            $completedAt
        );

        $array = $event->toArray();

        $this->assertEquals(123, $array['task_id']);
        $this->assertEquals(456, $array['completed_by_user_id']);
        $this->assertEquals($completedAt->format(\DateTimeInterface::ATOM), $array['completed_at']);
        $this->assertArrayHasKey('occurred_at', $array);
    }
}

class TaskStatusChangedTest extends TestCase
{
    public function testCreateEvent(): void
    {
        $taskId = new TaskId(555);
        $oldStatus = TaskStatus::from('pending');
        $newStatus = TaskStatus::from('in_progress');
        $changedByUserId = 7;

        $event = TaskStatusChanged::create($taskId, $oldStatus, $newStatus, $changedByUserId);

        $this->assertEquals($taskId, $event->getTaskId());
        $this->assertEquals($oldStatus, $event->getOldStatus());
        $this->assertEquals($newStatus, $event->getNewStatus());
        $this->assertEquals($changedByUserId, $event->getChangedByUserId());
    }

    public function testGetEventName(): void
    {
        $event = TaskStatusChanged::create(
            new TaskId(1),
            TaskStatus::from('pending'),
            TaskStatus::from('completed'),
            1
        );

        $this->assertEquals('task.status_changed', $event->getEventName());
    }

    public function testToArray(): void
    {
        $event = TaskStatusChanged::create(
            new TaskId(789),
            TaskStatus::from('in_progress'),
            TaskStatus::from('completed'),
            99
        );

        $array = $event->toArray();

        $this->assertEquals(789, $array['task_id']);
        $this->assertEquals('in_progress', $array['old_status']);
        $this->assertEquals('completed', $array['new_status']);
        $this->assertEquals(99, $array['changed_by_user_id']);
        $this->assertArrayHasKey('occurred_at', $array);
    }

    public function testStatusValueObject(): void
    {
        $oldStatus = TaskStatus::from('pending');
        $newStatus = TaskStatus::from('completed');

        $this->assertEquals('pending', $oldStatus->value);
        $this->assertEquals('completed', $newStatus->value);
    }
}
