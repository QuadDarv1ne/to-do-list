<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Webhook;
use App\Entity\WebhookLog;
use App\Repository\WebhookRepository;
use App\Repository\WebhookLogRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private WebhookRepository $webhookRepository,
        private WebhookLogRepository $webhookLogRepository,
    ) {
    }

    /**
     * Send webhook notification with HMAC signature
     */
    public function send(string $url, string $event, array $data, ?string $secret = null): bool
    {
        $startTime = microtime(true);
        
        $payload = [
            'event' => $event,
            'timestamp' => time(),
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'CRM-Webhook/1.0',
            'X-Webhook-Event' => $event,
            'X-Webhook-Timestamp' => (string) time(),
        ];

        // Add HMAC signature if secret is provided
        if ($secret) {
            $signature = hash_hmac('sha256', $jsonPayload, $secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => $headers,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $responseBody = $response->getContent();

            $isSuccess = $statusCode >= 200 && $statusCode < 300;

            if ($isSuccess) {
                $this->logger->info('Webhook sent successfully', [
                    'url' => $url,
                    'event' => $event,
                    'status' => $statusCode,
                    'response_time_ms' => $responseTime,
                ]);
            } else {
                $this->logger->warning('Webhook failed', [
                    'url' => $url,
                    'event' => $event,
                    'status' => $statusCode,
                    'response_time_ms' => $responseTime,
                ]);
            }

            return $isSuccess;

        } catch (\Exception $e) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            
            $this->logger->error('Webhook error', [
                'url' => $url,
                'event' => $event,
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
            ]);

            return false;
        }
    }

    /**
     * Send webhook and log the result
     */
    public function sendAndLog(Webhook $webhook, string $event, array $data): bool
    {
        $startTime = microtime(true);
        
        $payload = [
            'event' => $event,
            'timestamp' => time(),
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'CRM-Webhook/1.0',
            'X-Webhook-Event' => $event,
            'X-Webhook-Timestamp' => (string) time(),
        ];

        // Add HMAC signature if secret is provided
        $secret = $webhook->getSecret();
        if ($secret) {
            $signature = hash_hmac('sha256', $jsonPayload, $secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        try {
            $response = $this->httpClient->request('POST', $webhook->getUrl(), [
                'json' => $payload,
                'headers' => $headers,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $responseBody = $response->getContent();

            $isSuccess = $statusCode >= 200 && $statusCode < 300;

            // Log the delivery
            $this->webhookLogRepository->logDelivery(
                $webhook,
                $event,
                $payload,
                $statusCode,
                $responseTime,
                $isSuccess,
                null,
                json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR)
            );

            if ($isSuccess) {
                $this->logger->info('Webhook sent successfully', [
                    'webhook_id' => $webhook->getId(),
                    'url' => $webhook->getUrl(),
                    'event' => $event,
                    'status' => $statusCode,
                    'response_time_ms' => $responseTime,
                ]);
            } else {
                $this->logger->warning('Webhook failed', [
                    'webhook_id' => $webhook->getId(),
                    'url' => $webhook->getUrl(),
                    'event' => $event,
                    'status' => $statusCode,
                    'response_time_ms' => $responseTime,
                ]);
            }

            return $isSuccess;

        } catch (\Exception $e) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            
            // Log the error
            $this->webhookLogRepository->logDelivery(
                $webhook,
                $event,
                $payload,
                0,
                $responseTime,
                false,
                $e->getMessage(),
                null
            );
            
            $this->logger->error('Webhook error', [
                'webhook_id' => $webhook->getId(),
                'url' => $webhook->getUrl(),
                'event' => $event,
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
            ]);

            return false;
        }
    }

    /**
     * Notify task created
     */
    public function notifyTaskCreated($task): void
    {
        $webhooks = $this->webhookRepository->findByEvent('task.created');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'task.created', [
                    'task_id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'created_at' => $task->getCreatedAt()?->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify task updated
     */
    public function notifyTaskUpdated($task, array $changes): void
    {
        $webhooks = $this->webhookRepository->findByEvent('task.updated');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'task.updated', [
                    'task_id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'changes' => $changes,
                    'updated_at' => $task->getUpdatedAt()?->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify task completed
     */
    public function notifyTaskCompleted($task): void
    {
        $webhooks = $this->webhookRepository->findByEvent('task.completed');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'task.completed', [
                    'task_id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'completed_at' => $task->getCompletedAt()?->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify task deleted
     */
    public function notifyTaskDeleted(int $taskId, string $title, ?User $user = null): void
    {
        $webhooks = $this->webhookRepository->findByEvent('task.deleted');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'task.deleted', [
                    'task_id' => $taskId,
                    'title' => $title,
                    'deleted_at' => (new \DateTime())->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify deal won
     */
    public function notifyDealWon($deal): void
    {
        $webhooks = $this->webhookRepository->findByEvent('deal.won');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'deal.won', [
                    'deal_id' => $deal->getId(),
                    'name' => $deal->getName(),
                    'amount' => $deal->getAmount(),
                    'client' => $deal->getClient()?->getName(),
                    'won_at' => (new \DateTime())->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify deal lost
     */
    public function notifyDealLost($deal, ?string $reason = null): void
    {
        $webhooks = $this->webhookRepository->findByEvent('deal.lost');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'deal.lost', [
                    'deal_id' => $deal->getId(),
                    'name' => $deal->getName(),
                    'amount' => $deal->getAmount(),
                    'client' => $deal->getClient()?->getName(),
                    'reason' => $reason,
                    'lost_at' => (new \DateTime())->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify client created
     */
    public function notifyClientCreated($client): void
    {
        $webhooks = $this->webhookRepository->findByEvent('client.created');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'client.created', [
                    'client_id' => $client->getId(),
                    'name' => $client->getName(),
                    'email' => $client->getEmail(),
                    'phone' => $client->getPhone(),
                    'created_at' => $client->getCreatedAt()?->format('c'),
                ]);
            }
        }
    }

    /**
     * Notify comment added
     */
    public function notifyCommentAdded($comment): void
    {
        $webhooks = $this->webhookRepository->findByEvent('comment.added');

        foreach ($webhooks as $webhookData) {
            $webhook = $this->webhookRepository->find($webhookData['id']);
            if ($webhook) {
                $this->sendAndLog($webhook, 'comment.added', [
                    'comment_id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'author' => $comment->getAuthor()?->getUsername(),
                    'task_id' => $comment->getTask()?->getId(),
                    'created_at' => $comment->getCreatedAt()?->format('c'),
                ]);
            }
        }
    }

    /**
     * Test webhook connection
     */
    public function testWebhook(string $url, ?string $secret = null): array
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'event' => 'test',
                    'timestamp' => time(),
                    'data' => ['message' => 'Test webhook from CRM'],
                ],
                'headers' => $secret ? [
                    'X-Webhook-Signature' => 'sha256=' . hash_hmac('sha256', json_encode(['event' => 'test']), $secret),
                ] : [],
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
     */
    public function getWebhookStats(int $webhookId, int $periodDays = 7): array
    {
        return $this->webhookLogRepository->getStatistics($webhookId, $periodDays);
    }

    /**
     * Get success rate for webhook
     */
    public function getSuccessRate(int $webhookId, int $periodDays = 7): ?float
    {
        return $this->webhookLogRepository->getSuccessRate($webhookId, $periodDays);
    }

    /**
     * Get recent logs for webhook
     *
     * @return WebhookLog[]
     */
    public function getRecentLogs(int $webhookId, int $limit = 50): array
    {
        return $this->webhookLogRepository->findByWebhook($webhookId, $limit);
    }

    /**
     * Clean old webhook logs
     */
    public function cleanOldLogs(int $daysOld = 30): int
    {
        return $this->webhookLogRepository->cleanOldLogs($daysOld);
    }
}
