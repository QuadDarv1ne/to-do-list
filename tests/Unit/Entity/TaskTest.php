<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Task;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    private Task $task;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setUsername('testuser');
        $this->user->setEmail('test@example.com');
        
        $this->task = new Task();
        $this->task->setUser($this->user);
        $this->task->setAssignedUser($this->user);
    }

    public function testTaskCreation(): void
    {
        $this->assertInstanceOf(Task::class, $this->task);
        $this->assertNull($this->task->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->task->getCreatedAt());
    }

    public function testSetTitle(): void
    {
        $this->task->setTitle('Test Task');
        $this->assertEquals('Test Task', $this->task->getTitle());
        $this->assertEquals('Test Task', $this->task->getName()); // Alias test
    }

    public function testSetStatus(): void
    {
        $this->task->setStatus('in_progress');
        $this->assertEquals('in_progress', $this->task->getStatus());
        $this->assertTrue($this->task->isInProgress());
        $this->assertFalse($this->task->isCompleted());
        $this->assertFalse($this->task->isPending());
    }

    public function testSetStatusToCompleted(): void
    {
        $this->task->setStatus('completed');
        $this->assertTrue($this->task->isCompleted());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->task->getCompletedAt());
    }

    public function testSetPriority(): void
    {
        $this->task->setPriority('high');
        $this->assertEquals('high', $this->task->getPriority());
        $this->assertEquals('Высокий', $this->task->getPriorityLabel());
    }

    public function testIsOverdue(): void
    {
        $pastDate = new \DateTime('-1 day');
        $this->task->setDueDate($pastDate);
        $this->task->setStatus('pending');
        $this->assertTrue($this->task->isOverdue());
        
        $this->task->setStatus('completed');
        $this->assertFalse($this->task->isOverdue());
    }

    public function testCanStart(): void
    {
        $this->assertTrue($this->task->canStart());
    }

    public function testGetCompletionTimeInHours(): void
    {
        $this->task->setCreatedAt(new \DateTime('2024-01-01 10:00:00'));
        $this->task->setStatus('completed');
        $this->task->setCompletedAt(new \DateTime('2024-01-01 12:30:00'));
        
        $hours = $this->task->getCompletionTimeInHours();
        $this->assertEquals(2.5, $hours);
    }

    public function testIsCompletedLate(): void
    {
        $this->task->setDueDate(new \DateTime('2024-01-01 10:00:00'));
        $this->task->setCompletedAt(new \DateTime('2024-01-01 12:00:00'));
        $this->task->setStatus('completed');
        
        $this->assertTrue($this->task->isCompletedLate());
    }
}
