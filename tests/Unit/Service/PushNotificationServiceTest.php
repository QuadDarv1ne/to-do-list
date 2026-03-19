<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\PushNotificationService
 */
class PushNotificationServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $em;
    private LoggerInterface|MockObject $logger;
    private SerializerInterface|MockObject $serializer;
    private PushNotificationService $pushService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->pushService = new PushNotificationService(
            $this->em,
            $this->logger,
            $this->serializer,
        );
    }

    public function testSendCreatesNotification(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        $this->logger->expects(self::once())
            ->method('info');

        // Act
        $notification = $this->pushService->send(
            $user,
            'test.type',
            'Test Title',
            'Test Message',
            '/test/url',
            ['key' => 'value'],
            'database'
        );

        // Assert
        $this->assertInstanceOf(\App\Entity\PushNotification::class, $notification);
        $this->assertEquals('test.type', $notification->getType());
        $this->assertEquals('Test Title', $notification->getTitle());
        $this->assertEquals('Test Message', $notification->getMessage());
        $this->assertEquals('/test/url', $notification->getActionUrl());
        $this->assertEquals(['key' => 'value'], $notification->getData());
    }

    public function testSendTaskCreated(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $task = new Task();
        $task->setTitle('Test Task');

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $notification = $this->pushService->sendTaskCreated($user, $task);

        // Assert
        $this->assertInstanceOf(\App\Entity\PushNotification::class, $notification);
        $this->assertEquals('task.created', $notification->getType());
        $this->assertStringContainsString('📝', $notification->getTitle());
    }

    public function testSendTaskUpdated(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $task = new Task();
        $task->setTitle('Test Task');

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $notification = $this->pushService->sendTaskUpdated($user, $task, 'изменён приоритет');

        // Assert
        $this->assertInstanceOf(\App\Entity\PushNotification::class, $notification);
        $this->assertEquals('task.updated', $notification->getType());
        $this->assertStringContainsString('изменён приоритет', $notification->getMessage());
    }

    public function testSendMention(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setUsername('user');

        $mentionedBy = new User();
        $mentionedBy->setEmail('other@example.com');
        $mentionedBy->setFirstName('John');
        $mentionedBy->setLastName('Doe');

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $notification = $this->pushService->sendMention($user, $mentionedBy, 'in comment');

        // Assert
        $this->assertInstanceOf(\App\Entity\PushNotification::class, $notification);
        $this->assertEquals('mention', $notification->getType());
        $this->assertStringContainsString('John Doe', $notification->getMessage());
    }

    public function testSendComment(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setUsername('user');

        $task = new Task();
        $task->setTitle('Test Task');

        $author = new User();
        $author->setFirstName('Jane');
        $author->setLastName('Smith');

        $this->em->expects(self::once())
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $notification = $this->pushService->sendComment($user, $task, $author);

        // Assert
        $this->assertInstanceOf(\App\Entity\PushNotification::class, $notification);
        $this->assertEquals('comment.created', $notification->getType());
        $this->assertStringContainsString('Jane Smith', $notification->getMessage());
    }

    public function testGetUnreadCount(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $repo = $this->createMock(\App\Repository\PushNotificationRepository::class);
        $repo->expects(self::once())
            ->method('countUnreadForUser')
            ->with($user)
            ->willReturn(5);

        $this->em->expects(self::once())
            ->method('getRepository')
            ->willReturn($repo);

        // Act
        $count = $this->pushService->getUnreadCount($user);

        // Assert
        $this->assertEquals(5, $count);
    }

    public function testMarkAllAsRead(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $repo = $this->createMock(\App\Repository\PushNotificationRepository::class);
        $repo->expects(self::once())
            ->method('markAllAsRead')
            ->with($user)
            ->willReturn(10);

        $this->em->expects(self::once())
            ->method('getRepository')
            ->willReturn($repo);

        // Act
        $count = $this->pushService->markAllAsRead($user);

        // Assert
        $this->assertEquals(10, $count);
    }

    public function testCleanupOldNotifications(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $repo = $this->createMock(\App\Repository\PushNotificationRepository::class);
        $repo->expects(self::once())
            ->method('removeOlderThan')
            ->with($user, $this->isInstanceOf(\DateTime::class))
            ->willReturn(5);

        $this->em->expects(self::once())
            ->method('getRepository')
            ->willReturn($repo);

        // Act
        $deleted = $this->pushService->cleanupOldNotifications($user, 30);

        // Assert
        $this->assertEquals(5, $deleted);
    }

    public function testSerializeForWebSocket(): void
    {
        // Arrange
        $notification = new \App\Entity\PushNotification();
        $notification->setType('task.created');
        $notification->setTitle('Test');
        $notification->setMessage('Test message');
        $notification->setData(['task_id' => 123]);

        // Используем Reflection для установки ID
        $reflection = new \ReflectionClass($notification);
        $property = $reflection->getProperty('id');
        $property->setValue($notification, 1);

        $this->serializer->expects(self::once())
            ->method('serialize')
            ->willReturn('{"id":1,"type":"task.created"}');

        // Act
        $json = $this->pushService->serializeForWebSocket($notification);

        // Assert
        $this->assertEquals('{"id":1,"type":"task.created"}', $json);
    }
}
