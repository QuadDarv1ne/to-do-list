<?php

namespace App\Service;

/**
 * Сервис для работы с датами и временем
 * Централизует создание DateTime объектов для лучшей производительности
 */
class DateTimeService
{
    /**
     * Получить текущую дату и время
     */
    public function now(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * Получить текущую дату и время (immutable)
     */
    public function nowImmutable(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Создать DateTime из строки
     */
    public function create(string $datetime = 'now'): \DateTime
    {
        return new \DateTime($datetime);
    }

    /**
     * Создать DateTimeImmutable из строки
     */
    public function createImmutable(string $datetime = 'now'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime);
    }

    /**
     * Получить дату N дней назад
     */
    public function daysAgo(int $days): \DateTime
    {
        return (new \DateTime())->modify("-{$days} days");
    }

    /**
     * Получить дату N дней вперед
     */
    public function daysAhead(int $days): \DateTime
    {
        return (new \DateTime())->modify("+{$days} days");
    }

    /**
     * Получить начало дня
     */
    public function startOfDay(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->setTime(0, 0, 0);
    }

    /**
     * Получить конец дня
     */
    public function endOfDay(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->setTime(23, 59, 59);
    }

    /**
     * Проверить, просрочена ли дата
     */
    public function isOverdue(?\DateTime $date): bool
    {
        if (!$date) {
            return false;
        }
        
        return $date < new \DateTime();
    }

    /**
     * Получить количество дней до даты
     */
    public function daysUntil(\DateTime $date): int
    {
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Получить количество дней между датами
     */
    public function daysBetween(\DateTime $start, \DateTime $end): int
    {
        return $start->diff($end)->days;
    }

    /**
     * Форматировать дату для отображения
     */
    public function format(\DateTime $date, string $format = 'Y-m-d H:i:s'): string
    {
        return $date->format($format);
    }

    /**
     * Форматировать дату для API (ISO 8601)
     */
    public function formatISO(\DateTime $date): string
    {
        return $date->format('c');
    }

    /**
     * Получить человекочитаемое представление времени
     */
    public function humanReadable(\DateTime $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' ' . $this->pluralize($diff->y, 'год', 'года', 'лет') . ' назад';
        }

        if ($diff->m > 0) {
            return $diff->m . ' ' . $this->pluralize($diff->m, 'месяц', 'месяца', 'месяцев') . ' назад';
        }

        if ($diff->d > 0) {
            return $diff->d . ' ' . $this->pluralize($diff->d, 'день', 'дня', 'дней') . ' назад';
        }

        if ($diff->h > 0) {
            return $diff->h . ' ' . $this->pluralize($diff->h, 'час', 'часа', 'часов') . ' назад';
        }

        if ($diff->i > 0) {
            return $diff->i . ' ' . $this->pluralize($diff->i, 'минуту', 'минуты', 'минут') . ' назад';
        }

        return 'только что';
    }

    /**
     * Плюрализация русских слов
     */
    private function pluralize(int $number, string $one, string $two, string $five): string
    {
        $number = abs($number);
        $number %= 100;

        if ($number >= 5 && $number <= 20) {
            return $five;
        }

        $number %= 10;

        if ($number === 1) {
            return $one;
        }

        if ($number >= 2 && $number <= 4) {
            return $two;
        }

        return $five;
    }

    /**
     * Проверить, находится ли дата в диапазоне
     */
    public function isBetween(\DateTime $date, \DateTime $start, \DateTime $end): bool
    {
        return $date >= $start && $date <= $end;
    }

    /**
     * Получить начало недели
     */
    public function startOfWeek(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->modify('monday this week')->setTime(0, 0, 0);
    }

    /**
     * Получить конец недели
     */
    public function endOfWeek(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->modify('sunday this week')->setTime(23, 59, 59);
    }

    /**
     * Получить начало месяца
     */
    public function startOfMonth(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->modify('first day of this month')->setTime(0, 0, 0);
    }

    /**
     * Получить конец месяца
     */
    public function endOfMonth(?\DateTime $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        return (clone $date)->modify('last day of this month')->setTime(23, 59, 59);
    }
}
