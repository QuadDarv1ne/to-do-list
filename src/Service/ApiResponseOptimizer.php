<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * API Response Optimizer - оптимизация JSON ответов
 * Best practices от крупных API (Spotify, Stripe)
 */
class ApiResponseOptimizer
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Create optimized JSON response with serialization groups
     */
    public function createResponse(
        mixed $data,
        int $status = 200,
        array $groups = ['default'],
        array $headers = [],
    ): JsonResponse {
        $context = [
            AbstractNormalizer::GROUPS => $groups,
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object->getId();
            },
            // Skip null values to reduce response size
            AbstractNormalizer::SKIP_NULL_VALUES => true,
        ];

        $json = $this->serializer->serialize($data, 'json', $context);

        // Add standard headers
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        return new JsonResponse($json, $status, $headers, true);
    }

    /**
     * Create paginated response
     */
    public function createPaginatedResponse(
        array $items,
        int $page,
        int $limit,
        int $total,
        array $groups = ['default'],
    ): JsonResponse {
        $data = [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];

        return $this->createResponse($data, 200, $groups);
    }

    /**
     * Create success response
     */
    public function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Create error response
     */
    public function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return new JsonResponse($response, $status);
    }

    /**
     * Create validation error response
     */
    public function validationError(array $errors): JsonResponse
    {
        return $this->error('Validation failed', 422, $errors);
    }

    /**
     * Optimize response size by removing unnecessary fields
     */
    public function optimizePayload(array $data, array $allowedFields): array
    {
        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Add cache headers to response
     */
    public function addCacheHeaders(JsonResponse $response, int $maxAge = 300): JsonResponse
    {
        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($maxAge);

        return $response;
    }

    /**
     * Add ETag for conditional requests
     */
    public function addETag(JsonResponse $response): JsonResponse
    {
        $etag = md5($response->getContent());
        $response->setEtag($etag);

        return $response;
    }
}
