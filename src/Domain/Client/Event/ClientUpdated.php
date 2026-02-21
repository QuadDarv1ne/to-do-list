<?php

namespace App\Domain\Client\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Клиент обновлён
 */
final readonly class ClientUpdated implements DomainEventInterface
{
    public function __construct(
        private int $clientId,
        private array $changedFields,
        private int $changedByUserId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $clientId,
        array $changedFields,
        int $changedByUserId,
    ): self {
        return new self(
            $clientId,
            $changedFields,
            $changedByUserId,
            new \DateTimeImmutable(),
        );
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    public function getChangedByUserId(): int
    {
        return $this->changedByUserId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'client.updated';
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'changed_fields' => $this->changedFields,
            'changed_by_user_id' => $this->changedByUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
