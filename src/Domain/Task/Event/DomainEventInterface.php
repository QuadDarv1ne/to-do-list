<?php

namespace App\Domain\Task\Event;

interface DomainEventInterface
{
    public function getOccurredAt(): \DateTimeImmutable;

    public function getEventName(): string;

    public function toArray(): array;
}
