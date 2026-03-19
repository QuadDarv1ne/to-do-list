<?php

namespace App\Tests\Unit\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @covers \App\Service\AuditLogService
 */
class AuditLogServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $em;
    private RequestStack|MockObject $requestStack;
    private TokenStorageInterface|MockObject $tokenStorage;
    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->auditLogService = new AuditLogService(
            $this->em,
            $this->requestStack,
            $this->tokenStorage,
        );
    }

    public function testLogCreate(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $task = new \App\Entity\Task();
        $task->setTitle('Test Task');
        $task->setUser($user);

        // Reflection для установки ID
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->em->expects(self::once())
            ->method('persist')
            ->with($this->isInstanceOf(AuditLog::class));

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logCreate($task, ['title' => 'Test Task']);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('create', $auditLog->getAction());
        $this->assertEquals(\App\Entity\Task::class, $auditLog->getEntityClass());
        $this->assertEquals('123', $auditLog->getEntityId());
    }

    public function testLogUpdate(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $task = new \App\Entity\Task();
        
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 456);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $oldValues = ['priority' => 'low', 'status' => 'pending'];
        $newValues = ['priority' => 'high', 'status' => 'in_progress'];

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logUpdate($task, $oldValues, $newValues, 'Test reason');

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('update', $auditLog->getAction());
        $this->assertEquals('Test reason', $auditLog->getReason());
        $this->assertEquals($oldValues, $auditLog->getOldValues());
        $this->assertEquals($newValues, $auditLog->getNewValues());
    }

    public function testLogDelete(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $task = new \App\Entity\Task();

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 789);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logDelete($task, ['title' => 'Deleted Task']);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('delete', $auditLog->getAction());
    }

    public function testLogLogin(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setUsername('loginuser');
        $user->setFirstName('Login');
        $user->setLastName('User');

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 999);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Test Browser 1.0');

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logLogin($user);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('login', $auditLog->getAction());
        $this->assertEquals('user@example.com', $auditLog->getUserEmail());
        $this->assertEquals('192.168.1.1', $auditLog->getIpAddress());
    }

    public function testLogLogout(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('logout@example.com');
        $user->setUsername('logoutuser');

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 888);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logLogout($user);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('logout', $auditLog->getAction());
    }

    public function testLogExport(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('export@example.com');
        $user->setUsername('exportuser');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logExport('tasks_csv', 150);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('export', $auditLog->getAction());
        $this->assertEquals('Export', $auditLog->getEntityClass());
        $this->assertEquals('tasks_csv', $auditLog->getEntityId());
    }

    public function testLogSettingsChange(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('settings@example.com');
        $user->setUsername('settingsuser');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logSettingsChange('timezone', 'UTC', 'Europe/Moscow');

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('settings_change', $auditLog->getAction());
        $this->assertEquals('Settings', $auditLog->getEntityClass());
        $this->assertEquals('timezone', $auditLog->getEntityId());
    }

    public function testLogPermissionChange(): void
    {
        // Arrange
        $adminUser = new User();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setUsername('admin');
        $adminUser->setFirstName('Admin');
        $adminUser->setLastName('User');
        
        $targetUser = new User();
        $targetUser->setEmail('target@example.com');
        $targetUser->setUsername('targetuser');

        $reflection = new \ReflectionClass($targetUser);
        $property = $reflection->getProperty('id');
        $property->setValue($targetUser, 777);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($adminUser);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logPermissionChange($targetUser, 'ROLE_USER', 'ROLE_MANAGER');

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('permission_change', $auditLog->getAction());
        $this->assertEquals('777', $auditLog->getEntityId());
        $this->assertStringContainsString('ROLE_USER → ROLE_MANAGER', $auditLog->getReason());
    }

    public function testLogWithoutUser(): void
    {
        // Arrange
        $task = new \App\Entity\Task();
        
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 111);

        // Нет пользователя (null token)
        $this->tokenStorage
            ->method('getToken')
            ->willReturn(null);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $auditLog = $this->auditLogService->logCreate($task, ['title' => 'Anonymous Task']);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertNull($auditLog->getUser());
    }

    public function testLogBatch(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('batch@example.com');
        $user->setUsername('batchuser');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $operations = [
            [
                'entityClass' => \App\Entity\Task::class,
                'entityId' => 1,
                'action' => 'create',
                'changes' => ['title' => 'Task 1'],
            ],
            [
                'entityClass' => \App\Entity\Task::class,
                'entityId' => 2,
                'action' => 'update',
                'changes' => ['priority' => 'high'],
            ],
        ];

        $this->em->expects(self::exactly(2))
            ->method('persist');

        $this->em->expects(self::exactly(2))
            ->method('flush');

        // Act
        $this->auditLogService->logBatch($operations);

        // Assert
        // Ожидается что persist и flush будут вызваны 2 раза
        $this->assertTrue(true);
    }
}
