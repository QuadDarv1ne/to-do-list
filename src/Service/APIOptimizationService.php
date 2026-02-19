<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class APIOptimizationService
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Кэширование API ответов с учетом параметров запроса
     */
    public function cacheAPIResponse(
        string $endpoint,
        array $parameters,
        callable $dataProvider,
        int $ttl = 300,
    ): array {
        $cacheKey = $this->generateCacheKey($endpoint, $parameters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($dataProvider, $ttl) {
            $item->expiresAfter($ttl);

            $startTime = microtime(true);
            $data = $dataProvider();
            $executionTime = microtime(true) - $startTime;

            $this->logger->info('API response cached', [
                'execution_time' => $executionTime,
                'data_size' => \strlen(json_encode($data)),
            ]);

            return [
                'data' => $data,
                'cached_at' => time(),
                'execution_time' => $executionTime,
            ];
        });
    }

    /**
     * Генерация ключа кэша на основе endpoint и параметров
     */
    private function generateCacheKey(string $endpoint, array $parameters): string
    {
        ksort($parameters); // Сортируем для консистентности
        $paramString = http_build_query($parameters);

        return 'api_' . md5($endpoint . '_' . $paramString);
    }

    /**
     * Пагинация с оптимизацией
     */
    public function optimizePagination(Request $request, int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min($maxLimit, max(1, $request->query->getInt('limit', $defaultLimit)));
        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Сжатие JSON ответов
     */
    public function compressResponse(array $data): array
    {
        // Удаляем null значения для уменьшения размера
        $compressed = $this->removeNullValues($data);

        // Сокращаем длинные строки если это массив объектов
        if (\is_array($compressed) && \count($compressed) > 0 && \is_array($compressed[0])) {
            $compressed = array_map([$this, 'truncateStrings'], $compressed);
        }

        return $compressed;
    }

    /**
     * Удаление null значений из массива
     */
    private function removeNullValues(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->removeNullValues($value);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Обрезка длинных строк в API ответах
     */
    private function truncateStrings(array $item, int $maxLength = 200): array
    {
        foreach ($item as $key => $value) {
            if (\is_string($value) && \strlen($value) > $maxLength) {
                $item[$key] = substr($value, 0, $maxLength) . '...';
            }
        }

        return $item;
    }

    /**
     * Батчинг запросов для уменьшения количества обращений к БД
     */
    public function batchRequests(array $requests, callable $batchProcessor): array
    {
        $batchSize = 50; // Обрабатываем по 50 запросов за раз
        $results = [];

        $batches = array_chunk($requests, $batchSize);

        foreach ($batches as $batch) {
            $batchResults = $batchProcessor($batch);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Добавление метаданных к API ответу
     */
    public function addResponseMetadata(array $data, array $metadata = []): array
    {
        return [
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => time(),
                'count' => \is_array($data) ? \count($data) : 1,
                'cached' => false,
            ], $metadata),
        ];
    }

    /**
     * Валидация и санитизация параметров API
     */
    public function sanitizeAPIParams(Request $request, array $allowedParams): array
    {
        $params = [];

        foreach ($allowedParams as $param => $config) {
            $value = $request->query->get($param);

            if ($value === null && isset($config['default'])) {
                $value = $config['default'];
            }

            if ($value !== null) {
                $params[$param] = $this->sanitizeValue($value, $config);
            }
        }

        return $params;
    }

    /**
     * Санитизация отдельного значения
     */
    private function sanitizeValue($value, array $config)
    {
        $type = $config['type'] ?? 'string';

        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'string':
                $value = trim(strip_tags($value));
                if (isset($config['max_length'])) {
                    $value = substr($value, 0, $config['max_length']);
                }

                return $value;
            case 'array':
                return \is_array($value) ? $value : explode(',', $value);
            default:
                return $value;
        }
    }

    /**
     * Создание ETag для кэширования на стороне клиента
     */
    public function generateETag(array $data): string
    {
        return md5(json_encode($data));
    }

    /**
     * Проверка условного запроса (If-None-Match)
     */
    public function checkConditionalRequest(Request $request, string $etag): bool
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');

        return $ifNoneMatch === $etag;
    }

    /**
     * Создание оптимизированного ответа с заголовками кэширования
     */
    public function createOptimizedResponse(array $data, int $maxAge = 300): Response
    {
        $etag = $this->generateETag($data);

        $response = new Response(json_encode($data));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}");

        return $response;
    }

    /**
     * Мониторинг производительности API
     */
    public function logAPIPerformance(string $endpoint, float $executionTime, int $dataSize): void
    {
        $this->logger->info('API Performance', [
            'endpoint' => $endpoint,
            'execution_time' => $executionTime,
            'data_size_bytes' => $dataSize,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ]);

        // Предупреждение о медленных запросах
        if ($executionTime > 1.0) {
            $this->logger->warning('Slow API request detected', [
                'endpoint' => $endpoint,
                'execution_time' => $executionTime,
            ]);
        }
    }

    /**
     * Инвалидация кэша по паттерну
     */
    public function invalidateCachePattern(string $pattern): void
    {
        // Примечание: Symfony Cache не поддерживает инвалидацию по паттерну из коробки
        // Это требует кастомной реализации или использования Redis/Memcached
        $this->logger->info('Cache invalidation requested', ['pattern' => $pattern]);
    }
}
