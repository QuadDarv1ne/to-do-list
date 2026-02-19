<?php

namespace App\Tests\Unit\Service;

use App\Service\DateTimeService;
use PHPUnit\Framework\TestCase;

class DateTimeServiceTest extends TestCase
{
    private DateTimeService $dateTimeService;

    protected function setUp(): void
    {
        $this->dateTimeService = new DateTimeService();
    }

    public function testStartOfDayReturnsMidnight(): void
    {
        $date = new \DateTime('2026-01-15 14:30:45');
        $result = $this->dateTimeService->startOfDay($date);
        
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
        $this->assertEquals('2026-01-15', $result->format('Y-m-d'));
    }

    public function testStartOfDayWithNullCreatesNewDate(): void
    {
        $result = $this->dateTimeService->startOfDay(null);
        
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testEndOfDayReturnsEndOfDay(): void
    {
        $date = new \DateTime('2026-01-15 10:00:00');
        $result = $this->dateTimeService->endOfDay($date);
        
        $this->assertEquals('23:59:59', $result->format('H:i:s'));
        $this->assertEquals('2026-01-15', $result->format('Y-m-d'));
    }

    public function testIsOverdueReturnsTrueForPastDate(): void
    {
        $date = new \DateTime('-1 day');
        $this->assertTrue($this->dateTimeService->isOverdue($date));
    }

    public function testIsOverdueReturnsFalseForFutureDate(): void
    {
        $date = new \DateTime('+1 day');
        $this->assertFalse($this->dateTimeService->isOverdue($date));
    }

    public function testIsOverdueReturnsFalseForNull(): void
    {
        $this->assertFalse($this->dateTimeService->isOverdue(null));
    }

    public function testFormatReturnsFormattedDate(): void
    {
        $date = new \DateTime('2026-01-15 14:30:45');
        $result = $this->dateTimeService->format($date, 'Y-m-d');
        
        $this->assertEquals('2026-01-15', $result);
    }

    public function testFormatDefaultFormat(): void
    {
        $date = new \DateTime('2026-01-15 14:30:45');
        $result = $this->dateTimeService->format($date);
        
        $this->assertEquals('2026-01-15 14:30:45', $result);
    }

    public function testFormatISOReturnsISO8601(): void
    {
        $date = new \DateTime('2026-01-15 14:30:45+03:00');
        $result = $this->dateTimeService->formatISO($date);
        
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    public function testHumanReadableReturnsString(): void
    {
        $date = new \DateTime('-1 day');
        $result = $this->dateTimeService->humanReadable($date);
        
        $this->assertNotEmpty($result);
    }

    public function testStartOfWeekReturnsMonday(): void
    {
        $date = new \DateTime('2026-02-19'); // Thursday
        $result = $this->dateTimeService->startOfWeek($date);
        
        $this->assertEquals('Monday', $result->format('l'));
    }

    public function testEndOfWeekReturnsSunday(): void
    {
        $date = new \DateTime('2026-02-19'); // Thursday
        $result = $this->dateTimeService->endOfWeek($date);
        
        $this->assertEquals('Sunday', $result->format('l'));
    }

    public function testStartOfMonthReturnsFirstDay(): void
    {
        $date = new \DateTime('2026-02-15');
        $result = $this->dateTimeService->startOfMonth($date);
        
        $this->assertEquals('01', $result->format('d'));
    }

    public function testEndOfMonthReturnsLastDay(): void
    {
        $date = new \DateTime('2026-02-15');
        $result = $this->dateTimeService->endOfMonth($date);
        
        $this->assertEquals('28', $result->format('d')); // February 2026 has 28 days
    }

    public function testDaysUntilReturnsPositiveDays(): void
    {
        $now = new \DateTime();
        $future = clone $now;
        $future->modify('+10 days');
        $result = $this->dateTimeService->daysUntil($future);
        
        // Allow for time differences (should be 9 or 10)
        $this->assertGreaterThanOrEqual(9, $result);
        $this->assertLessThanOrEqual(10, $result);
    }

    public function testDaysUntilReturnsNegativeDays(): void
    {
        $date = new \DateTime('-10 days');
        $result = $this->dateTimeService->daysUntil($date);
        
        $this->assertEquals(-10, $result);
    }

    public function testDaysBetweenReturnsDifference(): void
    {
        $start = new \DateTime('2026-01-01');
        $end = new \DateTime('2026-01-11');
        $result = $this->dateTimeService->daysBetween($start, $end);
        
        $this->assertEquals(10, $result);
    }
}
