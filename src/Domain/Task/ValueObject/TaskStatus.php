<?php

namespace App\Domain\Task\ValueObject;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => \in_array($newStatus, [self::IN_PROGRESS, self::COMPLETED]),
            self::IN_PROGRESS => \in_array($newStatus, [self::PENDING, self::COMPLETED]),
            self::COMPLETED => \in_array($newStatus, [self::PENDING, self::IN_PROGRESS]),
        };
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
