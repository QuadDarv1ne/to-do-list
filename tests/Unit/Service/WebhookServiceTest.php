<?php

namespace App\Tests\Unit\Service;

use App\Service\WebhookService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WebhookServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private WebhookService $webhookService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->webhookService = new WebhookService(
            $this->httpClient,
            $this->logger,
            $this->createStub(\App\Repository\WebhookRepository::class),
            $this->createStub(\App\Repository\WebhookLogRepository::class),
        );
    }

    public function testSendWebhookSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('OK');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook')
            ->willReturn($response);

        $result = $this->webhookService->send(
            'https://example.com/webhook',
            'test.event',
            ['key' => 'value']
        );

        $this->assertTrue($result);
    }

    public function testSendWebhookWithSignature(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->webhookService->send(
            'https://example.com/webhook',
            'test.event',
            ['data' => 'test'],
            'secret-key'
        );

        $this->assertTrue($result);
    }

    public function testSendWebhookRetryOnFailure(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturn($response);

        $result = $this->webhookService->send(
            'https://example.com/webhook',
            'test.event',
            [],
            null,
            3
        );

        $this->assertFalse($result);
    }

    public function testSendWebhookSuccessAfterRetry(): void
    {
        $failResponse = $this->createMock(ResponseInterface::class);
        $failResponse->method('getStatusCode')->willReturn(500);

        $successResponse = $this->createMock(ResponseInterface::class);
        $successResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($failResponse, $successResponse);

        $result = $this->webhookService->send(
            'https://example.com/webhook',
            'test.event',
            [],
            null,
            3
        );

        $this->assertTrue($result);
    }
}
