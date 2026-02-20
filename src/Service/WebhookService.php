<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Send webhook notification
     */
    public function send(string $url, string $event, array $data): bool
    {
        try {
            $payload = [
                'event' => $event,
                'timestamp' => time(),
                'data' => $data,
            ];

            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CRM-Webhook/1.0',
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Webhook sent successfully', [
                    'url' => $url,
                    'event' => $event,
                    'status' => $statusCode,
                ]);

                return true;
            }

            $this->logger->warning('Webhook failed', [
                'url' => $url,
                'event' => $event,
                'status' => $statusCode,
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Webhook error', [
                'url' => $url,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notify task created
     */
    public function notifyTaskCreated($task): void
    {
        $webhooks = $this->getActiveWebhooks('task.created');

        foreach ($webhooks as $webhook) {
            $this->send($webhook['url'], 'task.created', [
                'task_id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'created_at' => $task->getCreatedAt()?->format('c'),
            ]);
        }
    }

    /**
     * Notify task updated
     */
    public function notifyTaskUpdated($task, array $changes): void
    {
        $webhooks = $this->getActiveWebhooks('task.updated');

        foreach ($webhooks as $webhook) {
            $this->send($webhook['url'], 'task.updated', [
                'task_id' => $task->getId(),
                'title' => $task->getTitle(),
                'changes' => $changes,
                'updated_at' => $task->getUpdatedAt()?->format('c'),
            ]);
        }
    }

    /**
     * Notify task completed
     */
    public function notifyTaskCompleted($task): void
    {
        $webhooks = $this->getActiveWebhooks('task.completed');

        foreach ($webhooks as $webhook) {
            $this->send($webhook['url'], 'task.completed', [
                'task_id' => $task->getId(),
                'title' => $task->getTitle(),
                'completed_at' => $task->getCompletedAt()?->format('c'),
            ]);
        }
    }

    /**
     * Notify task deleted
     */
    public function notifyTaskDeleted(int $taskId, string $title): void
    {
        $webhooks = $this->getActiveWebhooks('task.deleted');

        foreach ($webhooks as $webhook) {
            $this->send($webhook['url'], 'task.deleted', [
                'task_id' => $taskId,
                'title' => $title,
                'deleted_at' => (new \DateTime())->format('c'),
            ]);
        }
    }

    /**
     * Get active webhooks for event
     * TODO: Реализовать управление webhooks
     * - Создать таблицу webhooks (id, user_id, url, events, is_active, secret)
     * - Фильтровать webhooks по типу события
     * - Поддержка wildcard событий (*)
     * - Проверка активности webhook перед отправкой
     */
    private function getActiveWebhooks(string $event): array
    {
        // TODO: Load from database
        // For now, return from config/environment
        $webhooks = [];

        // Example: Load from environment variable
        $webhookUrl = $_ENV['WEBHOOK_URL'] ?? null;

        if ($webhookUrl) {
            $webhooks[] = [
                'url' => $webhookUrl,
                'events' => ['*'], // Listen to all events
            ];
        }

        return $webhooks;
    }

    /**
     * Test webhook connection
     */
    public function testWebhook(string $url): array
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'event' => 'test',
                    'timestamp' => time(),
                    'data' => ['message' => 'Test webhook'],
                ],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'message' => 'Webhook test completed',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get webhook statistics
     * TODO: Реализовать статистику webhooks
     * - Создать таблицу webhook_logs (webhook_id, event, status, response_time, created_at)
     * - Подсчитывать успешные/неудачные отправки
     * - Средний response time
     * - График отправок по времени
     * - Алерты при высоком проценте ошибок
     */
    public function getWebhookStats(): array
    {
        // TODO: Implement statistics from database
        return [
            'total_sent' => 0,
            'successful' => 0,
            'failed' => 0,
            'last_sent' => null,
        ];
    }
}
