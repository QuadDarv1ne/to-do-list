<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для обновления сделки
 */
final readonly class UpdateDealDTO
{
    /**
     * @param positive-int     $id
     * @param ?non-empty-string $title
     * @param ?numeric-string  $amount
     * @param ?string          $stage lead|qualification|proposal|negotiation|closing
     * @param ?string          $status in_progress|won|lost|postponed
     * @param ?non-empty-string $description
     * @param ?string          $expectedCloseDate ISO 8601 format (Y-m-d)
     * @param ?string          $lostReason Причина отмены (только для lost)
     */
    private function __construct(
        private int $id,
        private ?string $title,
        private ?string $amount,
        private ?string $stage,
        private ?string $status,
        private ?string $description,
        private ?string $expectedCloseDate,
        private ?string $lostReason,
    ) {
    }

    /**
     * Создать DTO из HTTP запроса
     */
    public static function fromRequest(Request $request, int $id): self
    {
        $data = $request->request->all();

        return new self(
            id: $id,
            title: isset($data['title']) ? trim($data['title']) : null,
            amount: $data['amount'] ?? null,
            stage: $data['stage'] ?? null,
            status: $data['status'] ?? null,
            description: isset($data['description']) ? trim($data['description']) : null,
            expectedCloseDate: $data['expected_close_date'] ?? null,
            lostReason: $data['lost_reason'] ?? null,
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     id: int,
     *     title?: string|null,
     *     amount?: string|null,
     *     stage?: string|null,
     *     status?: string|null,
     *     description?: string|null,
     *     expectedCloseDate?: string|null,
     *     lostReason?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: isset($data['title']) ? trim($data['title']) : null,
            amount: $data['amount'] ?? null,
            stage: $data['stage'] ?? null,
            status: $data['status'] ?? null,
            description: $data['description'] ?? null,
            expectedCloseDate: $data['expectedCloseDate'] ?? null,
            lostReason: $data['lostReason'] ?? null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function getStage(): ?string
    {
        return $this->stage;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExpectedCloseDate(): ?string
    {
        return $this->expectedCloseDate;
    }

    public function getExpectedCloseDateAsDateTime(): ?\DateTimeInterface
    {
        if (!$this->expectedCloseDate) {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->expectedCloseDate);
        } catch (\Exception) {
            return null;
        }
    }

    public function getLostReason(): ?string
    {
        return $this->lostReason;
    }

    /**
     * Проверить, есть ли данные для обновления
     */
    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->amount !== null
            || $this->stage !== null
            || $this->status !== null
            || $this->description !== null
            || $this->expectedCloseDate !== null
            || $this->lostReason !== null;
    }

    /**
     * Преобразовать в массив для обновления сущности
     *
     * @return array{
     *     title: string|null,
     *     amount: string|null,
     *     stage: string|null,
     *     status: string|null,
     *     description: string|null,
     *     expectedCloseDate: \DateTimeInterface|null,
     *     lostReason: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'amount' => $this->amount,
            'stage' => $this->stage,
            'status' => $this->status,
            'description' => $this->description,
            'expectedCloseDate' => $this->getExpectedCloseDateAsDateTime(),
            'lostReason' => $this->lostReason,
        ];
    }
}
