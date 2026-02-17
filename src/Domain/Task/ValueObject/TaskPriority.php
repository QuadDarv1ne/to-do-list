<?php

namespace App\Domain\Task\ValueObject;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function getWeight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }

    public function isHigherThan(self $other): bool
    {
        return $this->getWeight() > $other->getWeight();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Низкий',
            self::MEDIUM => 'Средний',
            self::HIGH => 'Высокий',
            self::URGENT => 'Критический',
        };
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
