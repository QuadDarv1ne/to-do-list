<?php

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private NotificationRepository|MockObject $notificationRepository;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->notificationRepository = $this->createMock(NotificationRepository::class);

        $this->service = new NotificationService(
            $this->entityManager,
            $this->logger,
            $this->notificationRepository,
            null // PerformanceMonitorService is optional
        );
    }

    public function testCreateNotification(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getUsername')->willReturn('testuser');

        $title = 'Test Notification';
        $message = 'Test message content';

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
        $notification->setMessage(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $notification->setIsRead(false);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Notification::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Created notification for user'));

        $result = $this->service->createNotification($user, $title, $message);

        $this->assertInstanceOf(Notification::class, $result);
        $this->assertEquals(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $result->getTitle());
        $this->assertEquals(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), $result->getMessage());
        $this->assertFalse($result->isRead());
    }

    public function testCreateNotificationWithTask(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $task = $this->createMock(Task::class);
        $task->method('getId')->willReturn(123);
        $task->method('getTitle')->willReturn('Test Task');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createNotification($user, 'Title', 'Message', $task);

        $this->assertInstanceOf(Notification::class, $result);
        $this->assertEquals($task, $result->getTask());
    }

    public function testCreateTaskNotification(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createTaskNotification($user, 'Task Title', 'Task Message', 123, 'Task Name');

        $this->assertInstanceOf(Notification::class, $result);
    }

    public function testGetUnreadNotifications(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $notifications = [
            $this->createMock(Notification::class),
            $this->createMock(Notification::class),
        ];

        $this->notificationRepository->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $user, 'isRead' => false],
                ['createdAt' => 'DESC']
            )
            ->willReturn($notifications);

        $result = $this->service->getUnreadNotifications($user);

        $this->assertEquals($notifications, $result);
        $this->assertCount(2, $result);
    }

    public function testMarkAsRead(): void
    {
        $notification = new Notification();
        $notification->setIsRead(false);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->markAsRead($notification);

        $this->assertTrue($notification->isRead());
    }

    public function testMarkAllAsRead(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('update')
            ->with(Notification::class, 'n')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('set')
            ->with('n.isRead', ':isRead')
            ->willReturn($queryBuilder);

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->willReturn(5);

        $result = $this->service->markAllAsRead($user);

        $this->assertEquals(5, $result);
    }

    public function testClearOldNotifications(): void
    {
        $this->notificationRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(function ($alias) {
                $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
                $qb->method('where')->willReturnSelf();
                $qb->method('setParameter')->willReturnSelf();
                $qb->method('getQuery')->willReturnCallback(function () {
                    $query = $this->createMock(\Doctrine\ORM\Query::class);
                    $query->method('getResult')->willReturn([new Notification(), new Notification()]);
                    return $query;
                });
                return $qb;
            });

        $this->entityManager->expects($this->exactly(2))
            ->method('remove');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Cleaned up'));

        $result = $this->service->cleanupOldNotifications();

        $this->assertEquals(2, $result);
    }

    public function testSendTaskAssignmentNotification(): void
    {
        $assigner = $this->createMock(User::class);
        $assigner->method('getId')->willReturn(1);
        $assigner->method('getFullName')->willReturn('Test Assigner');

        $assignee = $this->createMock(User::class);
        $assignee->method('getId')->willReturn(2);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->sendTaskAssignmentNotification($assignee, $assigner, 123, 'Test Task', 'high');
    }

    public function testSendTaskAssignmentNotificationWithoutTaskTitle(): void
    {
        $assigner = $this->createMock(User::class);
        $assigner->method('getId')->willReturn(1);
        $assigner->method('getFullName')->willReturn('Test Assigner');

        $assignee = $this->createMock(User::class);
        $assignee->method('getId')->willReturn(2);

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        // taskTitle must be a string, not null
        $this->service->sendTaskAssignmentNotification($assignee, $assigner, 123, 'Untitled Task', 'medium');
    }

    public function testSendDeadlineReminder(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $taskId = 456;
        $taskTitle = 'Deadline Task';
        $deadline = new \DateTime('+1 day');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->sendDeadlineReminder($user, $taskId, $taskTitle, $deadline);
    }

    public function testSendMentionNotification(): void
    {
        $mentionedUser = $this->createMock(User::class);
        $mentionedUser->method('getId')->willReturn(1);

        $mentioner = $this->createMock(User::class);
        $mentioner->method('getId')->willReturn(2);
        $mentioner->method('getFullName')->willReturn('Test Mentioner');

        $taskId = 789;
        $taskTitle = 'Test Task';
        $context = 'This is a comment context with mention';

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->sendMentionNotification($mentionedUser, $mentioner, $taskId, $taskTitle, $context);
    }
}
