<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

/**
 * Кастомные фильтры и тесты для Twig
 */
class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('time_diff', [$this, 'timeDiff']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('endswith', [$this, 'endsWith']),
        ];
    }

    /**
     * Вычисляет разницу во времени между датой и текущим моментом
     */
    public function timeDiff(\DateTimeInterface|string|null $date): string
    {
        if (!$date) {
            return '';
        }

        if (is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' ' . $this->declension($diff->y, ['год', 'года', 'лет']) . ' назад';
        }

        if ($diff->m > 0) {
            return $diff->m . ' ' . $this->declension($diff->m, ['месяц', 'месяца', 'месяцев']) . ' назад';
        }

        if ($diff->d > 0) {
            return $diff->d . ' ' . $this->declension($diff->d, ['день', 'дня', 'дней']) . ' назад';
        }

        if ($diff->h > 0) {
            return $diff->h . ' ' . $this->declension($diff->h, ['час', 'часа', 'часов']) . ' назад';
        }

        if ($diff->i > 0) {
            return $diff->i . ' ' . $this->declension($diff->i, ['минуту', 'минуты', 'минут']) . ' назад';
        }

        return 'только что';
    }

    /**
     * Проверка заканчивается ли строка на подстроку
     */
    public function endsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || str_ends_with($haystack, $needle);
    }

    /**
     * Склонение слов по числу
     */
    private function declension(int $number, array $words): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $index = $number % 100;
        
        if ($index >= 11 && $index <= 19) {
            $index = 2;
        } else {
            $index = $cases[$number % 10];
        }

        return $words[$index];
    }
}
