<?php

namespace App\Domain\Client\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Клиент создан
 */
final readonly class ClientCreated implements DomainEventInterface
{
    public function __construct(
        private int $clientId,
        private string $name,
        private string $email,
        private ?string $phone,
        private int $managerId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $clientId,
        string $name,
        string $email,
        ?string $phone,
        int $managerId,
    ): self {
        return new self(
            $clientId,
            $name,
            $email,
            $phone,
            $managerId,
            new \DateTimeImmutable(),
        );
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getManagerId(): int
    {
        return $this->managerId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'client.created';
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'manager_id' => $this->managerId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
