<?php

namespace App\Domain\Task\ValueObject;

final readonly class TaskId
{
    private function __construct(
        private int $value
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Task ID must be positive');
        }
        
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string)$this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
