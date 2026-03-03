<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\UserDashboardLayout;
use App\Repository\UserDashboardLayoutRepository;
use App\Service\DashboardCustomizationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DashboardCustomizationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private UserDashboardLayoutRepository $layoutRepository;

    private DashboardCustomizationService $customizationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->layoutRepository = $this->createMock(UserDashboardLayoutRepository::class);
        $this->customizationService = new DashboardCustomizationService(
            $this->entityManager,
            $this->layoutRepository,
        );
    }

    public function testGetUserLayoutReturnsDefaultWhenNoLayoutExists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn(null);

        $layout = $this->customizationService->getUserLayout($user);

        $this->assertIsArray($layout);
        $this->assertArrayHasKey('widgets', $layout);
        $this->assertArrayHasKey('theme', $layout);
        $this->assertEquals('light', $layout['theme']);
        $this->assertFalse($layout['compact_mode']);
        $this->assertTrue($layout['show_empty_widgets']);
        $this->assertEquals(2, $layout['columns']);
    }

    public function testGetUserLayoutReturnsExistingLayout(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $existingLayout = $this->createMock(UserDashboardLayout::class);
        $existingLayout->method('getSortedWidgets')->willReturn([
            ['id' => 'task_stats', 'position' => 1, 'enabled' => true],
        ]);
        $existingLayout->method('getTheme')->willReturn('dark');
        $existingLayout->method('isCompactMode')->willReturn(true);
        $existingLayout->method('isShowEmptyWidgets')->willReturn(false);
        $existingLayout->method('getColumns')->willReturn(3);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($existingLayout);

        $layout = $this->customizationService->getUserLayout($user);

        $this->assertEquals('dark', $layout['theme']);
        $this->assertTrue($layout['compact_mode']);
        $this->assertFalse($layout['show_empty_widgets']);
        $this->assertEquals(3, $layout['columns']);
    }

    public function testSaveLayoutCreatesNewLayoutWhenNotExists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn(null);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn ($layout) => $layout instanceof UserDashboardLayout));

        $result = $this->customizationService->saveLayout($user, [
            'theme' => 'dark',
            'columns' => 3,
        ]);

        $this->assertTrue($result);
    }

    public function testSaveLayoutUpdatesExistingLayout(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $existingLayout = new UserDashboardLayout();
        $existingLayout->setUser($user);
        $existingLayout->setTheme('light');

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($existingLayout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($existingLayout);

        $result = $this->customizationService->saveLayout($user, [
            'theme' => 'dark',
            'compact_mode' => true,
        ]);

        $this->assertTrue($result);
        $this->assertEquals('dark', $existingLayout->getTheme());
        $this->assertTrue($existingLayout->isCompactMode());
    }

    public function testResetToDefaultResetsLayout(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $existingLayout = new UserDashboardLayout();
        $existingLayout->setUser($user);
        $existingLayout->setTheme('dark');
        $existingLayout->setIsCompactMode(true);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($existingLayout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($existingLayout);

        $layout = $this->customizationService->resetToDefault($user);

        $this->assertEquals('light', $existingLayout->getTheme());
        $this->assertFalse($existingLayout->isCompactMode());
        $this->assertArrayHasKey('widgets', $layout);
    }

    public function testEnableWidgetAddsNewWidget(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $layout = new UserDashboardLayout();
        $layout->setUser($user);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($layout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($layout);

        $result = $this->customizationService->enableWidget($user, 'new_widget', 5);

        $this->assertTrue($result);
        $widgets = $layout->getWidgets();
        $this->assertCount(4, $widgets); // 3 default + 1 new
    }

    public function testDisableWidgetRemovesWidget(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $layout = new UserDashboardLayout();
        $layout->setUser($user);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($layout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($layout);

        $result = $this->customizationService->disableWidget($user, 'task_stats');

        $this->assertTrue($result);
    }

    public function testUpdateWidgetPosition(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $layout = new UserDashboardLayout();
        $layout->setUser($user);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($layout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($layout);

        $result = $this->customizationService->updateWidgetPosition($user, 'task_stats', 10);

        $this->assertTrue($result);
    }

    public function testGetAvailableThemes(): void
    {
        $themes = $this->customizationService->getAvailableThemes();

        $this->assertIsArray($themes);
        $this->assertCount(3, $themes);
        $this->assertEquals('light', $themes[0]['id']);
        $this->assertEquals('dark', $themes[1]['id']);
        $this->assertEquals('auto', $themes[2]['id']);
    }

    public function testUpdateTheme(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn(null);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn ($layout) => $layout->getTheme() === 'dark'));

        $result = $this->customizationService->updateTheme($user, 'dark');

        $this->assertTrue($result);
    }

    public function testToggleCompactMode(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $layout = new UserDashboardLayout();
        $layout->setUser($user);
        $layout->setIsCompactMode(false);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn($layout);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save')
            ->with($layout);

        $result = $this->customizationService->toggleCompactMode($user);

        $this->assertTrue($result);
        $this->assertTrue($layout->isCompactMode());
    }

    public function testExportLayout(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn(null);

        $export = $this->customizationService->exportLayout($user);

        $this->assertIsString($export);
        $decoded = json_decode($export, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('widgets', $decoded);
    }

    public function testImportLayout(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->layoutRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with(1)
            ->willReturn(null);

        $this->layoutRepository
            ->expects($this->once())
            ->method('save');

        $json = json_encode([
            'theme' => 'dark',
            'widgets' => [['id' => 'test', 'position' => 1]],
        ]);

        $result = $this->customizationService->importLayout($user, $json);

        $this->assertTrue($result);
    }

    public function testImportInvalidJson(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        // Не ожидаем вызова findByUser, т.к. json_decode вернет null
        $result = $this->customizationService->importLayout($user, 'invalid json');

        $this->assertFalse($result);
    }
}
