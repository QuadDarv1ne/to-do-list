<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для создания комментария
 */
final readonly class CreateCommentDTO
{
    /**
     * @param positive-int $taskId ID задачи
     * @param non-empty-string $content Текст комментария
     */
    private function __construct(
        private int $taskId,
        private string $content,
    ) {
    }

    /**
     * Создать DTO из HTTP запроса
     */
    public static function fromRequest(Request $request, int $taskId): self
    {
        $data = $request->request->all();

        return new self(
            taskId: $taskId,
            content: trim($data['content'] ?? ''),
        );
    }

    /**
     * Создать DTO из массива данных
     *
     * @param array{
     *     taskId: int,
     *     content: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: $data['taskId'],
            content: trim($data['content']),
        );
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Преобразовать в массив для создания сущности
     *
     * @return array{
     *     taskId: int,
     *     content: string
     * }
     */
    public function toArray(): array
    {
        return [
            'taskId' => $this->taskId,
            'content' => $this->content,
        ];
    }
}
