<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для обновления клиента
 */
final readonly class UpdateClientDTO
{
    /**
     * @param positive-int $id
     * @param ?non-empty-string $companyName
     * @param ?string $inn ИНН (10 или 12 цифр)
     * @param ?string $kpp КПП (9 цифр)
     * @param ?string $contactPerson Контактное лицо
     * @param ?string $phone Телефон (+79993332211)
     * @param ?string $email Email
     * @param ?string $address Адрес
     * @param ?string $segment retail|wholesale
     * @param ?string $category new|regular|vip|potential
     * @param ?string $notes Заметки
     */
    private function __construct(
        private int $id,
        private ?string $companyName,
        private ?string $inn,
        private ?string $kpp,
        private ?string $contactPerson,
        private ?string $phone,
        private ?string $email,
        private ?string $address,
        private ?string $segment,
        private ?string $category,
        private ?string $notes,
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
            companyName: isset($data['company_name']) ? trim($data['company_name']) : null,
            inn: $data['inn'] ?? null,
            kpp: $data['kpp'] ?? null,
            contactPerson: isset($data['contact_person']) ? trim($data['contact_person']) : null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            address: isset($data['address']) ? trim($data['address']) : null,
            segment: $data['segment'] ?? null,
            category: $data['category'] ?? null,
            notes: isset($data['notes']) ? trim($data['notes']) : null,
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     id: int,
     *     companyName?: string|null,
     *     inn?: string|null,
     *     kpp?: string|null,
     *     contactPerson?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     segment?: string|null,
     *     category?: string|null,
     *     notes?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            companyName: isset($data['companyName']) ? trim($data['companyName']) : null,
            inn: $data['inn'] ?? null,
            kpp: $data['kpp'] ?? null,
            contactPerson: $data['contactPerson'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            address: $data['address'] ?? null,
            segment: $data['segment'] ?? null,
            category: $data['category'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function getInn(): ?string
    {
        return $this->inn;
    }

    public function getKpp(): ?string
    {
        return $this->kpp;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getSegment(): ?string
    {
        return $this->segment;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Проверить, есть ли данные для обновления
     */
    public function hasChanges(): bool
    {
        return $this->companyName !== null
            || $this->inn !== null
            || $this->kpp !== null
            || $this->contactPerson !== null
            || $this->phone !== null
            || $this->email !== null
            || $this->address !== null
            || $this->segment !== null
            || $this->category !== null
            || $this->notes !== null;
    }

    /**
     * Преобразовать в массив для обновления сущности
     *
     * @return array{
     *     companyName: string|null,
     *     inn: string|null,
     *     kpp: string|null,
     *     contactPerson: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     segment: string|null,
     *     category: string|null,
     *     notes: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'companyName' => $this->companyName,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'contactPerson' => $this->contactPerson,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'segment' => $this->segment,
            'category' => $this->category,
            'notes' => $this->notes,
        ];
    }
}
