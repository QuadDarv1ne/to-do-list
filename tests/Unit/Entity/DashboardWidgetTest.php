<?php

namespace App\Tests\Unit\Entity;

use App\Entity\DashboardWidget;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class DashboardWidgetTest extends TestCase
{
    public function testCreateDashboardWidget(): void
    {
        $widget = new DashboardWidget();
        $widget->setType('task_stats');
        $widget->setTitle('Task Statistics');
        $widget->setSize('col-md-6');
        $widget->setPosition(1);

        $this->assertEquals('task_stats', $widget->getType());
        $this->assertEquals('Task Statistics', $widget->getTitle());
        $this->assertEquals('col-md-6', $widget->getSize());
        $this->assertEquals(1, $widget->getPosition());
        $this->assertTrue($widget->isActive());
    }

    public function testWidgetConfiguration(): void
    {
        $widget = new DashboardWidget();
        $widget->setConfiguration(['limit' => 10, 'showChart' => true]);

        $this->assertEquals(['limit' => 10, 'showChart' => true], $widget->getConfiguration());
        $this->assertEquals(10, $widget->getConfigurationValue('limit'));
        $this->assertTrue($widget->getConfigurationValue('showChart'));
        $this->assertEquals('default', $widget->getConfigurationValue('missing', 'default'));
    }

    public function testSetConfigurationValue(): void
    {
        $widget = new DashboardWidget();
        $widget->setConfiguration(['limit' => 5]);
        $widget->setConfigurationValue('showChart', true);

        $this->assertEquals(['limit' => 5, 'showChart' => true], $widget->getConfiguration());
    }

    public function testWidgetUserRelation(): void
    {
        $widget = new DashboardWidget();
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $widget->setUser($user);

        $this->assertSame($user, $widget->getUser());
    }

    public function testWidgetSizes(): void
    {
        $widget = new DashboardWidget();
        
        $widget->setSize('col-md-12');
        $this->assertEquals('col-md-12', $widget->getSize());
        
        $widget->setSize('col-md-4');
        $this->assertEquals('col-md-4', $widget->getSize());
    }

    public function testIsActiveCanBeChanged(): void
    {
        $widget = new DashboardWidget();
        $this->assertTrue($widget->isActive());

        $widget->setIsActive(false);
        $this->assertFalse($widget->isActive());
    }
}
