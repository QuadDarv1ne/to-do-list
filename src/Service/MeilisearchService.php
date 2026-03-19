<?php

namespace App\Service;

use Meilisearch\Client;
use Meilisearch\Contracts\Index;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;

/**
 * Сервис для поиска через Meilisearch
 *
 * Установка Meilisearch:
 *   docker run -it --rm -p 7700:7700 getmeili/meilisearch:latest
 *
 * Настройка:
 *   composer require meilisearch/search-bundle
 */
class MeilisearchService
{
    private ?Client $client = null;
    private array $indexes = [];

    public function __construct(
        private LoggerInterface $logger,
        private string $meilisearchUrl,
        private string $meilisearchKey,
    ) {
        // Инициализация клиента если настроено
        if ($meilisearchUrl && $meilisearchKey) {
            try {
                $this->client = new Client($meilisearchUrl, $meilisearchKey);
                $this->logger->info('Meilisearch client initialized', ['url' => $meilisearchUrl]);
            } catch (\Throwable $e) {
                $this->logger->warning('Meilisearch not available', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Поиск задач
     */
    public function searchTasks(string $query, array $filters = [], int $limit = 20): array
    {
        if (!$this->client) {
            return $this->fallbackSearch('tasks', $query, $filters, $limit);
        }

        try {
            $index = $this->getIndex('tasks');

            $searchParams = [
                'limit' => $limit,
                'filter' => $this->buildFilters($filters),
            ];

            $results = $index->search($query, $searchParams);

            return $results['hits'] ?? [];
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch error', ['error' => $e->getMessage()]);
            return $this->fallbackSearch('tasks', $query, $filters, $limit);
        }
    }

    /**
     * Поиск пользователей
     */
    public function searchUsers(string $query, int $limit = 20): array
    {
        if (!$this->client) {
            return $this->fallbackSearch('users', $query, [], $limit);
        }

        try {
            $index = $this->getIndex('users');
            $results = $index->search($query, ['limit' => $limit]);

            return $results['hits'] ?? [];
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch error', ['error' => $e->getMessage()]);
            return $this->fallbackSearch('users', $query, [], $limit);
        }
    }

    /**
     * Поиск сделок
     */
    public function searchDeals(string $query, array $filters = [], int $limit = 20): array
    {
        if (!$this->client) {
            return $this->fallbackSearch('deals', $query, $filters, $limit);
        }

        try {
            $index = $this->getIndex('deals');
            $searchParams = [
                'limit' => $limit,
                'filter' => $this->buildFilters($filters),
            ];

            $results = $index->search($query, $searchParams);

            return $results['hits'] ?? [];
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch error', ['error' => $e->getMessage()]);
            return $this->fallbackSearch('deals', $query, $filters, $limit);
        }
    }

    /**
     * Глобальный поиск по всем индексам
     */
    public function globalSearch(string $query, int $limit = 10): array
    {
        if (!$this->client) {
            return [
                'tasks' => $this->fallbackSearch('tasks', $query, [], $limit),
                'users' => $this->fallbackSearch('users', $query, [], $limit),
                'deals' => $this->fallbackSearch('deals', $query, [], $limit),
            ];
        }

        $results = [];

        foreach (['tasks', 'users', 'deals'] as $indexName) {
            try {
                $index = $this->getIndex($indexName);
                $searchResults = $index->search($query, ['limit' => $limit]);
                $results[$indexName] = $searchResults['hits'] ?? [];
            } catch (ApiException $e) {
                $this->logger->warning('Meilisearch index not available', [
                    'index' => $indexName,
                    'error' => $e->getMessage(),
                ]);
                $results[$indexName] = [];
            }
        }

        return $results;
    }

    /**
     * Индексировать задачу
     */
    public function indexTask(array $taskData): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('tasks');
            $index->addDocuments([$taskData]);
            $this->logger->debug('Task indexed', ['id' => $taskData['id']]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch index error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Индексировать пользователя
     */
    public function indexUser(array $userData): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('users');
            $index->addDocuments([$userData]);
            $this->logger->debug('User indexed', ['id' => $userData['id']]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch index error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Индексировать сделку
     */
    public function indexDeal(array $dealData): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('deals');
            $index->addDocuments([$dealData]);
            $this->logger->debug('Deal indexed', ['id' => $dealData['id']]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch index error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Удалить задачу из индекса
     */
    public function removeTask(int $taskId): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('tasks');
            $index->deleteDocument($taskId);
            $this->logger->debug('Task removed from index', ['id' => $taskId]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Удалить пользователя из индекса
     */
    public function removeUser(int $userId): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('users');
            $index->deleteDocument($userId);
            $this->logger->debug('User removed from index', ['id' => $userId]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Удалить сделку из индекса
     */
    public function removeDeal(int $dealId): void
    {
        if (!$this->client) {
            return;
        }

        try {
            $index = $this->getIndex('deals');
            $index->deleteDocument($dealId);
            $this->logger->debug('Deal removed from index', ['id' => $dealId]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Переиндексировать все задачи
     */
    public function reindexAllTasks(array $tasks): void
    {
        if (!$this->client || empty($tasks)) {
            return;
        }

        try {
            $index = $this->getIndex('tasks');
            $index->addDocuments($tasks);
            $this->logger->info('Tasks reindexed', ['count' => count($tasks)]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch reindex error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Переиндексировать всех пользователей
     */
    public function reindexAllUsers(array $users): void
    {
        if (!$this->client || empty($users)) {
            return;
        }

        try {
            $index = $this->getIndex('users');
            $index->addDocuments($users);
            $this->logger->info('Users reindexed', ['count' => count($users)]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch reindex error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Переиндексировать все сделки
     */
    public function reindexAllDeals(array $deals): void
    {
        if (!$this->client || empty($deals)) {
            return;
        }

        try {
            $index = $this->getIndex('deals');
            $index->addDocuments($deals);
            $this->logger->info('Deals reindexed', ['count' => count($deals)]);
        } catch (ApiException $e) {
            $this->logger->error('Meilisearch reindex error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Получить индекс
     */
    private function getIndex(string $name): Index
    {
        if (!isset($this->indexes[$name])) {
            $this->indexes[$name] = $this->client->index($name);
        }

        return $this->indexes[$name];
    }

    /**
     * Построить фильтры для Meilisearch
     */
    private function buildFilters(array $filters): array
    {
        $meiliFilters = [];

        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                if (is_array($value)) {
                    $meiliFilters[] = "$key IN " . json_encode($value);
                } else {
                    $meiliFilters[] = "$key = " . (is_string($value) ? "\"$value\"" : $value);
                }
            }
        }

        return $meiliFilters ? implode(' AND ', $meiliFilters) : [];
    }

    /**
     * Fallback поиск если Meilisearch недоступен
     */
    private function fallbackSearch(string $type, string $query, array $filters, int $limit): array
    {
        $this->logger->debug('Using fallback search', ['type' => $type, 'query' => $query]);

        // Возвращаем пустой массив - приложение должно использовать Doctrine поиск
        return [];
    }

    /**
     * Проверить доступность Meilisearch
     */
    public function isAvailable(): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $this->client->health();
            return true;
        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Получить статистику индексов
     */
    public function getStats(): array
    {
        if (!$this->client) {
            return ['available' => false];
        }

        try {
            $stats = [];
            foreach (['tasks', 'users', 'deals'] as $indexName) {
                try {
                    $index = $this->getIndex($indexName);
                    $stats[$indexName] = $index->fetchStats();
                } catch (ApiException $e) {
                    $stats[$indexName] = null;
                }
            }

            return [
                'available' => true,
                'indexes' => $stats,
            ];
        } catch (ApiException $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }
}
