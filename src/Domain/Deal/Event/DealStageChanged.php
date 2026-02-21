<?php

namespace App\Domain\Deal\Event;

use App\Domain\Task\Event\DomainEventInterface;

/**
 * Событие: Этап сделки изменён
 */
final readonly class DealStageChanged implements DomainEventInterface
{
    public function __construct(
        private int $dealId,
        private string $title,
        private string $oldStage,
        private string $newStage,
        private int $changedByUserId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(
        int $dealId,
        string $title,
        string $oldStage,
        string $newStage,
        int $changedByUserId,
    ): self {
        return new self(
            $dealId,
            $title,
            $oldStage,
            $newStage,
            $changedByUserId,
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

    public function getOldStage(): string
    {
        return $this->oldStage;
    }

    public function getNewStage(): string
    {
        return $this->newStage;
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
        return 'deal.stage_changed';
    }

    public function toArray(): array
    {
        return [
            'deal_id' => $this->dealId,
            'title' => $this->title,
            'old_stage' => $this->oldStage,
            'new_stage' => $this->newStage,
            'changed_by_user_id' => $this->changedByUserId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
