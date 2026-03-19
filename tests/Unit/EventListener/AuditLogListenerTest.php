<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Task;
use App\Entity\User;
use App\EventListener\AuditLogListener;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\EventListener\AuditLogListener
 */
class AuditLogListenerTest extends TestCase
{
    private AuditLogService|MockObject $auditLogService;
    private AuditLogListener $listener;

    protected function setUp(): void
    {
        $this->auditLogService = $this->createMock(AuditLogService::class);
        $this->listener = new AuditLogListener($this->auditLogService);
    }

    public function testPostPersistLogsCreate(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');

        $task = new Task();
        $task->setTitle('Test Task');
        $task->setUser($user);

        // Reflection для установки ID
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostPersistEventArgs($task, $em);

        $this->auditLogService
            ->expects(self::once())
            ->method('logCreate')
            ->with($task, self::isArray());

        // Act
        $this->listener->postPersist($eventArgs);
    }

    public function testPostPersistSkipsAuditLog(): void
    {
        // Arrange
        $auditLog = new \App\Entity\AuditLog();
        $auditLog->setEntityClass('Test');
        $auditLog->setEntityId('1');
        $auditLog->setAction('test');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostPersistEventArgs($auditLog, $em);

        $this->auditLogService
            ->expects(self::never())
            ->method('logCreate');

        // Act
        $this->listener->postPersist($eventArgs);
    }

    public function testPostPersistSkipsEntityWithoutId(): void
    {
        // Arrange
        $entity = new class {
            public function getName(): string
            {
                return 'Test';
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostPersistEventArgs($entity, $em);

        $this->auditLogService
            ->expects(self::never())
            ->method('logCreate');

        // Act
        $this->listener->postPersist($eventArgs);
    }

    public function testPostUpdateLogsUpdate(): void
    {
        // Arrange
        $task = new Task();
        $task->setTitle('Test Task');

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 456);

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);
        $meta = new \Doctrine\ORM\Mapping\ClassMetadata(Task::class);

        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($meta);

        // Изменения: priority изменился с low на high
        $changes = [
            'priority' => ['low', 'high'],
            'status' => ['pending', 'in_progress'],
        ];

        $uow->method('getEntityChangeSet')->willReturn($changes);

        $eventArgs = new PostUpdateEventArgs($task, $em);

        $this->auditLogService
            ->expects(self::once())
            ->method('logUpdate')
            ->with(
                $task,
                ['priority' => 'low', 'status' => 'pending'],
                ['priority' => 'high', 'status' => 'in_progress'],
            );

        // Act
        $this->listener->postUpdate($eventArgs);
    }

    public function testPostUpdateSkipsIfNoChanges(): void
    {
        // Arrange
        $task = new Task();

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);

        $em->method('getUnitOfWork')->willReturn($uow);
        $uow->method('getEntityChangeSet')->willReturn([]);

        $eventArgs = new PostUpdateEventArgs($task, $em);

        $this->auditLogService
            ->expects(self::never())
            ->method('logUpdate');

        // Act
        $this->listener->postUpdate($eventArgs);
    }

    public function testPostUpdateSkipsTimestampFields(): void
    {
        // Arrange
        $task = new Task();

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 789);

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);
        $meta = new \Doctrine\ORM\Mapping\ClassMetadata(Task::class);

        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($meta);

        // Только updatedAt изменился
        $changes = [
            'updatedAt' => [new \DateTime(), new \DateTime()],
        ];

        $uow->method('getEntityChangeSet')->willReturn($changes);

        $eventArgs = new PostUpdateEventArgs($task, $em);

        $this->auditLogService
            ->expects(self::never())
            ->method('logUpdate');

        // Act
        $this->listener->postUpdate($eventArgs);
    }

    public function testPostRemoveLogsDelete(): void
    {
        // Arrange
        $task = new Task();
        $task->setTitle('Task to delete');

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 999);

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostRemoveEventArgs($task, $em);

        $this->auditLogService
            ->expects(self::once())
            ->method('logDelete')
            ->with($task, self::isArray());

        // Act
        $this->listener->postRemove($eventArgs);
    }

    public function testPostRemoveSkipsAuditLog(): void
    {
        // Arrange
        $auditLog = new \App\Entity\AuditLog();
        $auditLog->setEntityClass('Test');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostRemoveEventArgs($auditLog, $em);

        $this->auditLogService
            ->expects(self::never())
            ->method('logDelete');

        // Act
        $this->listener->postRemove($eventArgs);
    }

    public function testGetEntityDataExcludesSensitiveFields(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFullName('Test User');
        // Password не должен быть включён

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 111);

        $em = $this->createMock(EntityManagerInterface::class);
        $eventArgs = new PostPersistEventArgs($user, $em);

        $this->auditLogService
            ->expects(self::once())
            ->method('logCreate')
            ->with($user, function ($data) {
                // Проверяем что password не включён
                $this->assertArrayNotHasKey('password', $data);
                $this->assertArrayHasKey('email', $data);
                $this->assertArrayHasKey('fullName', $data);
                return true;
            });

        // Act
        $this->listener->postPersist($eventArgs);
    }
}
