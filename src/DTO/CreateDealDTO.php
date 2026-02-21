<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для создания сделки
 */
final readonly class CreateDealDTO
{
    /**
     * @param positive-int     $clientId
     * @param non-empty-string $title
     * @param numeric-string   $amount
     * @param string           $stage lead|qualification|proposal|negotiation|closing
     * @param ?non-empty-string $description
     * @param ?string          $expectedCloseDate ISO 8601 format (Y-m-d)
     */
    private function __construct(
        private int $clientId,
        private string $title,
        private string $amount,
        private string $stage,
        private ?string $description,
        private ?string $expectedCloseDate,
    ) {
    }

    /**
     * Создать DTO из HTTP запроса
     */
    public static function fromRequest(Request $request): self
    {
        $data = $request->request->all();

        return new self(
            clientId: (int) ($data['client_id'] ?? 0),
            title: trim($data['title'] ?? ''),
            amount: (string) ($data['amount'] ?? '0.00'),
            stage: $data['stage'] ?? 'lead',
            description: isset($data['description']) ? trim($data['description']) : null,
            expectedCloseDate: $data['expected_close_date'] ?? null,
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     clientId: int,
     *     title: string,
     *     amount: string,
     *     stage?: string,
     *     description?: string|null,
     *     expectedCloseDate?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['clientId'],
            title: trim($data['title']),
            amount: (string) ($data['amount'] ?? '0.00'),
            stage: $data['stage'] ?? 'lead',
            description: $data['description'] ?? null,
            expectedCloseDate: $data['expectedCloseDate'] ?? null,
        );
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getStage(): string
    {
        return $this->stage;
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

    /**
     * Преобразовать в массив для создания сущности
     *
     * @return array{
     *     clientId: int,
     *     title: string,
     *     amount: string,
     *     stage: string,
     *     description: string|null,
     *     expectedCloseDate: \DateTimeInterface|null
     * }
     */
    public function toArray(): array
    {
        return [
            'clientId' => $this->clientId,
            'title' => $this->title,
            'amount' => $this->amount,
            'stage' => $this->stage,
            'description' => $this->description,
            'expectedCloseDate' => $this->getExpectedCloseDateAsDateTime(),
        ];
    }
}
