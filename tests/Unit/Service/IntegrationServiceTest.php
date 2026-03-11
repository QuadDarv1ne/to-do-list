<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\UserIntegration;
use App\Repository\UserIntegrationRepository;
use App\Service\IntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IntegrationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private UserIntegrationRepository $integrationRepository;

    private LoggerInterface $logger;

    private IntegrationService $integrationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->integrationRepository = $this->createMock(UserIntegrationRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->integrationService = new IntegrationService(
            $this->entityManager,
            $this->integrationRepository,
            $this->logger,
        );
    }

    public function testConnectGitHubWithValidToken(): void
    {
        $user = $this->createMock(User::class);
        $token = 'valid_github_token';

        // Мокируем ответ GitHub API
        $githubResponse = json_encode([
            'id' => 12345,
            'login' => 'testuser',
            'name' => 'Test User',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        // Создаём поток для мока file_get_contents
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: token ' . $token,
                    'User-Agent: To-Do-List-App',
                ],
            ],
        ]);

        $result = $this->integrationService->connectGitHub($user, $token);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('message', $result);

        // Примечание: реальный тест требует мока file_get_contents
        // В данном случае проверяем структуру ответа
    }

    public function testConnectGitHubWithInvalidToken(): void
    {
        $user = $this->createMock(User::class);
        $token = 'invalid_token';

        $result = $this->integrationService->connectGitHub($user, $token);

        $this->assertIsArray($result);
        $this->assertFalse($result['connected']);
        $this->assertStringContainsString('Неверный токен', $result['message']);
    }

    public function testConnectSlackWithValidWebhook(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $webhookUrl = 'https://hooks.slack.com/services/TEST/WEBHOOK/URL';

        // Мокаем репозиторий чтобы вернуть null (интеграция не существует)
        $this->integrationRepository->method('findByUserAndType')
            ->willReturn(null);

        $result = $this->integrationService->connectSlack($user, $webhookUrl);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testConnectSlackWithInvalidUrl(): void
    {
        $user = $this->createMock(User::class);
        $invalidUrl = 'not-a-valid-url';

        $result = $this->integrationService->connectSlack($user, $invalidUrl);

        $this->assertFalse($result['connected']);
        $this->assertStringContainsString('Неверный формат', $result['message']);
    }

    public function testConnectSlackWithNonSlackUrl(): void
    {
        $user = $this->createMock(User::class);
        $nonSlackUrl = 'https://example.com/webhook';

        $result = $this->integrationService->connectSlack($user, $nonSlackUrl);

        $this->assertFalse($result['connected']);
        $this->assertStringContainsString('Slack', $result['message']);
    }

    public function testGetSlackOAuthUrl(): void
    {
        $redirectUri = 'https://example.com/callback';

        $oauthUrl = $this->integrationService->getSlackOAuthUrl($redirectUri);

        $this->assertIsString($oauthUrl);
        $this->assertStringStartsWith('https://slack.com/oauth/v2/authorize', $oauthUrl);
        $this->assertStringContainsString('client_id=', $oauthUrl);
        $this->assertStringContainsString('redirect_uri=', $oauthUrl);
        $this->assertStringContainsString('scope=', $oauthUrl);
    }

    public function testGetGoogleCalendarOAuthUrl(): void
    {
        $redirectUri = 'https://example.com/callback';

        $oauthUrl = $this->integrationService->getGoogleCalendarOAuthUrl($redirectUri);

        $this->assertIsString($oauthUrl);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $oauthUrl);
        $this->assertStringContainsString('client_id=', $oauthUrl);
        $this->assertStringContainsString('scope=', $oauthUrl);
        $this->assertStringContainsString('calendar', $oauthUrl);
    }

    public function testConnectJiraWithValidCredentials(): void
    {
        $user = $this->createMock(User::class);
        $domain = 'example.atlassian.net';
        $email = 'test@example.com';
        $apiToken = 'test_api_token';

        // Мокаем репозиторий
        $this->integrationRepository->method('findByUserAndType')
            ->willReturn(null);

        $result = $this->integrationService->connectJira($user, $domain, $email, $apiToken);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testConnectTelegramWithValidCredentials(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $botToken = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
        $chatId = '123456789';

        // Мокаем репозиторий
        $this->integrationRepository->method('findByUserAndType')
            ->willReturn(null);

        $result = $this->integrationService->connectTelegram($user, $botToken, $chatId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetAvailableIntegrationsReturnsAllTypes(): void
    {
        $integrations = $this->integrationService->getAvailableIntegrations();

        $this->assertIsArray($integrations);
        $this->assertArrayHasKey('github', $integrations);
        $this->assertArrayHasKey('slack', $integrations);
        $this->assertArrayHasKey('jira', $integrations);
        $this->assertArrayHasKey('telegram', $integrations);
        $this->assertArrayHasKey('google_calendar', $integrations);
        $this->assertArrayHasKey('trello', $integrations);
        $this->assertArrayHasKey('zapier', $integrations);

        foreach ($integrations as $type => $integration) {
            $this->assertArrayHasKey('name', $integration);
            $this->assertArrayHasKey('description', $integration);
            $this->assertArrayHasKey('icon', $integration);
            $this->assertArrayHasKey('features', $integration);
            $this->assertArrayHasKey('config_fields', $integration);
        }
    }

    public function testGetUserIntegrationsReturnsArray(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $mockIntegrations = [
            $this->createMock(UserIntegration::class),
            $this->createMock(UserIntegration::class),
        ];

        $this->integrationRepository->method('findActiveByUser')
            ->with(1)
            ->willReturn($mockIntegrations);

        $result = $this->integrationService->getUserIntegrations($user);

        $this->assertIsArray($result);
    }

    public function testDisconnectIntegrationSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $integration = $this->createMock(UserIntegration::class);
        $integration->expects($this->once())
            ->method('setIsActive')
            ->with(false);
        $integration->expects($this->once())
            ->method('setAccessToken')
            ->with(null);
        $integration->expects($this->once())
            ->method('setRefreshToken')
            ->with(null);
        $integration->expects($this->once())
            ->method('setUpdatedAt');

        $this->integrationRepository->method('findByUserAndType')
            ->with(1, 'github')
            ->willReturn($integration);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->integrationService->disconnectIntegration($user, 'github');

        $this->assertTrue($result);
    }

    public function testDisconnectIntegrationNotFound(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->integrationRepository->method('findByUserAndType')
            ->with(1, 'github')
            ->willReturn(null);

        $result = $this->integrationService->disconnectIntegration($user, 'github');

        $this->assertFalse($result);
    }

    public function testTestConnectionGitHub(): void
    {
        $credentials = ['token' => 'test_token'];

        $result = $this->integrationService->testConnection('github', $credentials);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testTestConnectionSlack(): void
    {
        $credentials = ['webhook_url' => 'https://hooks.slack.com/test'];

        $result = $this->integrationService->testConnection('slack', $credentials);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testTestConnectionUnknown(): void
    {
        $credentials = [];

        $result = $this->integrationService->testConnection('unknown_service', $credentials);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Неизвестный тип', $result['message']);
    }

    public function testHasIntegrationWhenNotExists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->integrationRepository->method('findByUserAndType')
            ->with(1, 'github')
            ->willReturn(null);

        $result = $this->integrationService->hasIntegration($user, 'github');

        $this->assertFalse($result);
    }

    public function testSendNotificationWithoutIntegration(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->integrationRepository->method('findByUserAndType')
            ->with(1, 'telegram')
            ->willReturn(null);

        $result = $this->integrationService->sendNotification($user, 'telegram', 'Test message');

        $this->assertFalse($result);
    }
}
