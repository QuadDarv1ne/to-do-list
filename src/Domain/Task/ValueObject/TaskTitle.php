<?php

namespace App\Domain\Task\ValueObject;

final readonly class TaskTitle
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (empty($trimmed)) {
            throw new \InvalidArgumentException('Task title cannot be empty');
        }

        if (\strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Task title cannot exceed 255 characters');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
