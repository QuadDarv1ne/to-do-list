<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskCategory;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = new Task();
        
        $this->assertNull($task->getId());
        $this->assertEquals('pending', $task->getStatus());
        $this->assertEquals('medium', $task->getPriority());
    }

    public function testTaskTitleCanBeSet(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        
        $this->assertEquals('Test Task', $task->getTitle());
    }

    public function testTaskTitleCannotBeEmpty(): void
    {
        $task = new Task();
        
        $this->expectException(\InvalidArgumentException::class);
        $task->setTitle('');
    }

    public function testTaskStatusCanBeChanged(): void
    {
        $task = new Task();
        
        $task->setStatus('in_progress');
        $this->assertEquals('in_progress', $task->getStatus());
        
        $task->setStatus('completed');
        $this->assertEquals('completed', $task->getStatus());
    }

    public function testTaskStatusMustBeValid(): void
    {
        $task = new Task();
        
        $this->expectException(\InvalidArgumentException::class);
        $task->setStatus('invalid_status');
    }

    public function testTaskPriorityCanBeSet(): void
    {
        $task = new Task();
        
        $task->setPriority('high');
        $this->assertEquals('high', $task->getPriority());
        
        $task->setPriority('urgent');
        $this->assertEquals('urgent', $task->getPriority());
    }

    public function testTaskPriorityMustBeValid(): void
    {
        $task = new Task();
        
        $this->expectException(\InvalidArgumentException::class);
        $task->setPriority('invalid_priority');
    }

    public function testTaskCanBeAssignedToUser(): void
    {
        $task = new Task();
        $user = new User();
        $user->setUsername('testuser');
        
        $task->setAssignedUser($user);
        
        $this->assertSame($user, $task->getAssignedUser());
    }

    public function testTaskCanHaveCategory(): void
    {
        $task = new Task();
        $category = new TaskCategory();
        $category->setName('Development');
        
        $task->setCategory($category);
        
        $this->assertSame($category, $task->getCategory());
    }

    public function testTaskCompletedAtIsSetOnCompletion(): void
    {
        $task = new Task();
        $task->setStatus('completed');
        
        $this->assertInstanceOf(\DateTimeInterface::class, $task->getCompletedAt());
    }

    public function testTaskIsOverdue(): void
    {
        $task = new Task();
        $task->setDueDate(new \DateTime('-1 day'));
        $task->setStatus('pending');
        
        $this->assertTrue($task->isOverdue());
    }

    public function testTaskIsNotOverdueWhenCompleted(): void
    {
        $task = new Task();
        $task->setDueDate(new \DateTime('-1 day'));
        $task->setStatus('completed');
        
        $this->assertFalse($task->isOverdue());
    }

    public function testTaskIsNotOverdueWhenDueDateInFuture(): void
    {
        $task = new Task();
        $task->setDueDate(new \DateTime('+1 day'));
        $task->setStatus('pending');
        
        $this->assertFalse($task->isOverdue());
    }
}
