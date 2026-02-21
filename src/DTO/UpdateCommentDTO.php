<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для обновления комментария
 */
final readonly class UpdateCommentDTO
{
    /**
     * @param positive-int $id ID комментария
     * @param ?non-empty-string $content Новый текст комментария
     */
    private function __construct(
        private int $id,
        private ?string $content,
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
            content: isset($data['content']) ? trim($data['content']) : null,
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     id: int,
     *     content?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            content: isset($data['content']) ? trim($data['content']) : null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Проверить, есть ли данные для обновления
     */
    public function hasChanges(): bool
    {
        return $this->content !== null;
    }

    /**
     * Преобразовать в массив для обновления сущности
     *
     * @return array{
     *     content: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
