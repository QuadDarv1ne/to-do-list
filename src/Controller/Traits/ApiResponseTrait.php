<?php

namespace App\Controller\Traits;

use App\Service\ApiResponseOptimizer;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API Response Trait - упрощает работу с API ответами
 */
trait ApiResponseTrait
{
    private ?ApiResponseOptimizer $apiOptimizer = null;

    /**
     * @required
     */
    public function setApiOptimizer(ApiResponseOptimizer $optimizer): void
    {
        $this->apiOptimizer = $optimizer;
    }

    protected function jsonSuccess(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $this->getOptimizer()->success($data, $message, $status);
    }

    protected function jsonError(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return $this->getOptimizer()->error($message, $status, $errors);
    }

    protected function jsonValidationError(array $errors): JsonResponse
    {
        return $this->getOptimizer()->validationError($errors);
    }

    protected function jsonPaginated(
        array $items,
        int $page,
        int $limit,
        int $total,
        array $groups = ['default'],
    ): JsonResponse {
        return $this->getOptimizer()->createPaginatedResponse($items, $page, $limit, $total, $groups);
    }

    protected function jsonOptimized(
        mixed $data,
        int $status = 200,
        array $groups = ['default'],
        array $headers = [],
    ): JsonResponse {
        return $this->getOptimizer()->createResponse($data, $status, $groups, $headers);
    }

    private function getOptimizer(): ApiResponseOptimizer
    {
        if ($this->apiOptimizer === null) {
            throw new \LogicException('ApiResponseOptimizer not injected. Make sure the trait is used in a controller with autowiring enabled.');
        }

        return $this->apiOptimizer;
    }
}
