<?php

namespace App\Tests\Unit\Service;

use App\Service\DateTimeService;
use PHPUnit\Framework\TestCase;

class DateTimeServiceTest extends TestCase
{
    private DateTimeService $service;

    protected function setUp(): void
    {
        $this->service = new DateTimeService();
    }

    public function testGetCurrentDateTime(): void
    {
        $result = $this->service->getCurrentDateTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertEquals(date('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetCurrentDate(): void
    {
        $result = $this->service->getCurrentDate();
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertEquals(date('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testFormatDateTime(): void
    {
        $date = new \DateTime('2024-02-19 14:30:00');
        $result = $this->service->formatDateTime($date);
        $this->assertEquals('19.02.2024 14:30', $result);
    }

    public function testFormatDate(): void
    {
        $date = new \DateTime('2024-02-19');
        $result = $this->service->formatDate($date);
        $this->assertEquals('19.02.2024', $result);
    }

    public function testFormatTime(): void
    {
        $date = new \DateTime('2024-02-19 14:30:00');
        $result = $this->service->formatTime($date);
        $this->assertEquals('14:30', $result);
    }

    public function testGetRelativeTime(): void
    {
        $now = new \DateTime();
        $this->assertEquals('только что', $this->service->getRelativeTime($now));

        $minutesAgo = (clone $now)->modify('-5 minutes');
        $this->assertStringContainsString('минут', $this->service->getRelativeTime($minutesAgo));

        $hoursAgo = (clone $now)->modify('-2 hours');
        $this->assertStringContainsString('час', $this->service->getRelativeTime($hoursAgo));

        $daysAgo = (clone $now)->modify('-3 days');
        $this->assertStringContainsString('дн', $this->service->getRelativeTime($daysAgo));
    }

    public function testIsToday(): void
    {
        $today = new \DateTime();
        $this->assertTrue($this->service->isToday($today));

        $yesterday = (clone $today)->modify('-1 day');
        $this->assertFalse($this->service->isToday($yesterday));
    }

    public function testIsYesterday(): void
    {
        $yesterday = (new \DateTime())->modify('-1 day');
        $this->assertTrue($this->service->isYesterday($yesterday));

        $today = new \DateTime();
        $this->assertFalse($this->service->isYesterday($today));
    }

    public function testIsTomorrow(): void
    {
        $tomorrow = (new \DateTime())->modify('+1 day');
        $this->assertTrue($this->service->isTomorrow($tomorrow));

        $today = new \DateTime();
        $this->assertFalse($this->service->isTomorrow($today));
    }

    public function testGetDaysUntil(): void
    {
        $today = new \DateTime();
        $tomorrow = (clone $today)->modify('+1 day');
        $this->assertEquals(1, $this->service->getDaysUntil($today, $tomorrow));

        $nextWeek = (clone $today)->modify('+7 days');
        $this->assertEquals(7, $this->service->getDaysUntil($today, $nextWeek));
    }

    public function testIsPast(): void
    {
        $past = (new \DateTime())->modify('-1 day');
        $this->assertTrue($this->service->isPast($past));

        $future = (new \DateTime())->modify('+1 day');
        $this->assertFalse($this->service->isPast($future));
    }

    public function testIsFuture(): void
    {
        $future = (new \DateTime())->modify('+1 day');
        $this->assertTrue($this->service->isFuture($future));

        $past = (new \DateTime())->modify('-1 day');
        $this->assertFalse($this->service->isFuture($past));
    }

    public function testGetWeekNumber(): void
    {
        $date = new \DateTime('2024-02-19');
        $result = $this->service->getWeekNumber($date);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThanOrEqual(53, $result);
    }

    public function testGetQuarter(): void
    {
        $q1 = new \DateTime('2024-02-19');
        $this->assertEquals(1, $this->service->getQuarter($q1));

        $q2 = new \DateTime('2024-05-15');
        $this->assertEquals(2, $this->service->getQuarter($q2));

        $q3 = new \DateTime('2024-08-20');
        $this->assertEquals(3, $this->service->getQuarter($q3));

        $q4 = new \DateTime('2024-11-10');
        $this->assertEquals(4, $this->service->getQuarter($q4));
    }

    public function testGetBusinessDaysBetween(): void
    {
        $start = new \DateTime('2024-02-19'); // Monday
        $end = (clone $start)->modify('+1 week');
        $result = $this->service->getBusinessDaysBetween($start, $end);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testAddBusinessDays(): void
    {
        $date = new \DateTime('2024-02-19'); // Monday
        $result = $this->service->addBusinessDays($date, 5);
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertGreaterThan($date, $result);
    }

    public function testIsWeekend(): void
    {
        $saturday = new \DateTime('2024-02-17'); // Saturday
        $this->assertTrue($this->service->isWeekend($saturday));

        $monday = new \DateTime('2024-02-19'); // Monday
        $this->assertFalse($this->service->isWeekend($monday));
    }

    public function testIsBusinessHours(): void
    {
        $businessTime = new \DateTime('2024-02-19 10:00:00'); // Monday 10 AM
        $this->assertTrue($this->service->isBusinessHours($businessTime));

        $afterHours = new \DateTime('2024-02-19 20:00:00'); // Monday 8 PM
        $this->assertFalse($this->service->isBusinessHours($afterHours));
    }
}
