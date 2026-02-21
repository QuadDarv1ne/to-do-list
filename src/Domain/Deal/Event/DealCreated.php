<?php

namespace App\Domain\Deal\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Сделка создана
 */
final readonly class DealCreated implements DomainEventInterface
{
    public function __construct(
        private int $dealId,
        private string $title,
        private string $amount,
        private int $managerId,
        private int $clientId,
        private string $stage,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $dealId,
        string $title,
        string $amount,
        int $managerId,
        int $clientId,
        string $stage,
    ): self {
        return new self(
            $dealId,
            $title,
            $amount,
            $managerId,
            $clientId,
            $stage,
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

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'deal.created';
    }

    public function toArray(): array
    {
        return [
            'deal_id' => $this->dealId,
            'title' => $this->title,
            'amount' => $this->amount,
            'manager_id' => $this->managerId,
            'client_id' => $this->clientId,
            'stage' => $this->stage,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
