<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для завершения задачи
 */
final readonly class CompleteTaskDTO
{
    /**
     * @param positive-int $id
     * @param ?string      $comment Комментарий к завершению
     */
    private function __construct(
        private int $id,
        private ?string $comment,
        private bool $notify = true,
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
            comment: $data['comment'] ?? null,
            notify: $data['notify'] ?? true,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }
}
