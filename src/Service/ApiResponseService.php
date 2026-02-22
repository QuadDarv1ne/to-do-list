<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseService
{
    /**
     * Успешный ответ
     */
    public function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ISO8601),
        ], $statusCode);
    }

    /**
     * Ответ об ошибке
     */
    public function error(string $message, int $statusCode = 400, mixed $details = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode,
            ],
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ISO8601),
        ];

        if ($details) {
            $response['error']['details'] = $details;
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Ответ с пагинацией
     */
    public function paginated(
        array $data,
        int $total,
        int $page,
        int $limit,
        string $message = 'OK'
    ): JsonResponse {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) ceil($total / $limit),
                'has_next' => $page * $limit < $total,
                'has_prev' => $page > 1,
            ],
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ISO8601),
        ]);
    }

    /**
     * Ответ "Создано"
     */
    public function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Ответ "Нет содержимого"
     */
    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * Ответ "Не авторизован"
     */
    public function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Ответ "Запрещено"
     */
    public function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Ответ "Не найдено"
     */
    public function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Ответ "Конфликт"
     */
    public function conflict(string $message = 'Conflict'): JsonResponse
    {
        return $this->error($message, 409);
    }

    /**
     * Ответ "Слишком много запросов"
     */
    public function tooManyRequests(string $message = 'Too many requests', ?int $retryAfter = null): JsonResponse
    {
        $response = $this->error($message, 429);
        
        if ($retryAfter) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Валидация ошибок
     */
    public function validationError(array $errors): JsonResponse
    {
        return $this->error('Validation failed', 422, $errors);
    }

    /**
     * Ответ серверной ошибки
     */
    public function serverError(string $message = 'Internal server error', bool $debug = false): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => 500,
            ],
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ISO8601),
        ];

        if ($debug) {
            $response['error']['debug'] = [
                'file' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null,
                'line' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['line'] ?? null,
            ];
        }

        return new JsonResponse($response, 500);
    }
}
