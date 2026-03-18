<?php

namespace App\Tests\Unit\Service;

use App\Entity\DashboardWidget;
use App\Entity\User;
use App\Repository\DashboardWidgetRepository;
use App\Repository\TaskRepository;
use App\Service\DashboardWidgetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\DashboardWidgetService
 */
class DashboardWidgetServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $em;
    private TaskRepository|MockObject $taskRepo;
    private DashboardWidgetRepository|MockObject $widgetRepo;
    private DashboardWidgetService $widgetService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->taskRepo = $this->createMock(TaskRepository::class);
        $this->widgetRepo = $this->createMock(DashboardWidgetRepository::class);

        $this->widgetService = new DashboardWidgetService(
            $this->em,
            $this->taskRepo,
            $this->widgetRepo,
        );
    }

    public function testGetUserWidgets(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $widgets = [
            new DashboardWidget(),
            new DashboardWidget(),
        ];

        $this->widgetRepo->expects(self::once())
            ->method('findBy')
            ->with(
                ['user' => $user, 'isActive' => true],
                ['position' => 'ASC']
            )
            ->willReturn($widgets);

        // Act
        $result = $this->widgetService->getUserWidgets($user);

        // Assert
        $this->assertCount(2, $result);
    }

    public function testGetWidgetDataForStatsOverview(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('stats_overview');
        $widget->setTitle('Общая статистика');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $taskRepoResult = [
            'total_tasks' => 100,
            'completed_tasks' => 60,
            'pending_tasks' => 30,
            'in_progress_tasks' => 10,
            'overdue_tasks' => 5,
        ];

        // Создаём mock для TaskRepository с методом performGetDashboardStats
        $taskRepoMock = $this->createMock(TaskRepository::class);
        $taskRepoMock->method('performGetDashboardStats')
            ->willReturn($taskRepoResult);

        $widgetService = new DashboardWidgetService(
            $this->em,
            $taskRepoMock,
            $this->widgetRepo,
        );

        // Act
        $data = $widgetService->getWidgetData($widget, $user);

        // Assert
        $this->assertIsArray($data);
        $this->assertEquals(100, $data['total']);
        $this->assertEquals(60, $data['completed']);
        $this->assertEquals(60.0, $data['completion_rate']);
    }

    public function testGetWidgetDataForRecentTasks(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('recent_tasks');
        $widget->setTitle('Последние задачи');
        $widget->setConfiguration(['limit' => 5]);

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $tasks = [];
        for ($i = 0; $i < 5; $i++) {
            $task = new \App\Entity\Task();
            $task->setTitle('Task ' . $i);
            $tasks[] = $task;
        }

        $this->taskRepo->expects(self::once())
            ->method('findByUserWithFilters')
            ->willReturn($tasks);

        // Act
        $data = $this->widgetService->getWidgetData($widget, $user);

        // Assert
        $this->assertCount(5, $data);
    }

    public function testGetWidgetDataForQuickActions(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('quick_actions');
        $widget->setTitle('Быстрые действия');

        $user = new User();

        // Act
        $data = $this->widgetService->getWidgetData($widget, $user);

        // Assert
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('label', $data[0]);
        $this->assertArrayHasKey('icon', $data[0]);
        $this->assertArrayHasKey('url', $data[0]);
    }

    public function testGetWidgetDataForUnknownType(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('unknown_type');

        $user = new User();

        // Act
        $data = $this->widgetService->getWidgetData($widget, $user);

        // Assert
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testCreateDefaultWidgets(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->widgetRepo->expects(self::exactly(4))
            ->method('findOneBy')
            ->willReturn(null); // Виджетов нет, создаём новые

        $this->em->expects(self::exactly(4))
            ->method('persist');

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $this->widgetService->createDefaultWidgets($user);

        // Assert
        // Ожидается создание 4 виджетов по умолчанию
        $this->assertTrue(true);
    }

    public function testUpdateWidgetPosition(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('stats_overview');
        $widget->setPosition(0);

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $this->widgetService->updateWidgetPosition($widget, 5);

        // Assert
        $this->assertEquals(5, $widget->getPosition());
    }

    public function testUpdateWidgetConfiguration(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('recent_tasks');
        $widget->setConfiguration(['limit' => 5]);

        $newConfig = ['limit' => 10, 'status' => 'pending'];

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $this->widgetService->updateWidgetConfiguration($widget, $newConfig);

        // Assert
        $this->assertEquals($newConfig, $widget->getConfiguration());
    }

    public function testRemoveWidget(): void
    {
        // Arrange
        $widget = new DashboardWidget();
        $widget->setType('stats_overview');
        $widget->setIsActive(true);

        $this->em->expects(self::once())
            ->method('flush');

        // Act
        $this->widgetService->removeWidget($widget);

        // Assert
        $this->assertFalse($widget->isActive());
    }

    public function testResetToDefaults(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $existingWidget = new DashboardWidget();
        $existingWidget->setIsActive(true);

        $this->widgetRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([$existingWidget]);

        $this->em->expects(self::atLeastOnce())
            ->method('flush');

        // Act
        $this->widgetService->resetToDefaults($user);

        // Assert
        $this->assertFalse($existingWidget->isActive());
    }

    public function testGetAvailableTypes(): void
    {
        // Act
        $types = DashboardWidget::getAvailableTypes();

        // Assert
        $this->assertIsArray($types);
        $this->assertArrayHasKey('stats_overview', $types);
        $this->assertArrayHasKey('task_progress', $types);
        $this->assertArrayHasKey('recent_tasks', $types);
        $this->assertCount(10, $types);
    }

    public function testGetSizes(): void
    {
        // Act
        $sizes = DashboardWidget::getSizes();

        // Assert
        $this->assertIsArray($sizes);
        $this->assertCount(3, $sizes);
        $this->assertEquals('small (1 колонка)', $sizes[1]);
        $this->assertEquals('medium (2 колонки)', $sizes[2]);
        $this->assertEquals('large (3 колонки)', $sizes[3]);
    }
}
