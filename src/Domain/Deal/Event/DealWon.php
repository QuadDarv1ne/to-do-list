<?php

namespace App\Domain\Deal\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Сделка выиграна
 */
final readonly class DealWon implements DomainEventInterface
{
    public function __construct(
        private int $dealId,
        private string $title,
        private string $amount,
        private int $managerId,
        private \DateTimeImmutable $closedAt,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $dealId,
        string $title,
        string $amount,
        int $managerId,
        \DateTimeImmutable $closedAt,
    ): self {
        return new self(
            $dealId,
            $title,
            $amount,
            $managerId,
            $closedAt,
            new \DateTimeImmutable(),
        );
    }

    public function getDealId(): int
    {
        return $this->dealId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getManagerId(): int
    {
        return $this->managerId;
    }

    public function getClosedAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'deal.won';
    }

    public function toArray(): array
    {
        return [
            'deal_id' => $this->dealId,
            'title' => $this->title,
            'amount' => $this->amount,
            'manager_id' => $this->managerId,
            'closed_at' => $this->closedAt->format(\DateTimeInterface::ATOM),
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
