<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserIntegration;
use App\Repository\UserIntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;

class IntegrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserIntegrationRepository $integrationRepository,
    ) {
    }

    public function connectGitHub(User $user, string $token): array
    {
        try {
            // Проверяем токен через GitHub API
            $apiUrl = 'https://api.github.com/user';
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Authorization: token ' . $token,
                        'User-Agent: To-Do-List-App',
                    ],
                ],
            ]);

            $response = file_get_contents($apiUrl, false, $context);
            if ($response === false) {
                return [
                    'connected' => false,
                    'message' => 'Неверный токен GitHub',
                ];
            }

            $userData = json_decode($response, true);
            if (!isset($userData['login'])) {
                return [
                    'connected' => false,
                    'message' => 'Не удалось получить данные пользователя GitHub',
                ];
            }

            // Сохраняем интеграцию
            $integration = $this->saveIntegration($user, 'github', [
                'externalId' => (string) ($userData['id'] ?? ''),
                'accessToken' => $token,
                'metadata' => [
                    'username' => $userData['login'] ?? '',
                    'name' => $userData['name'] ?? '',
                    'avatar' => $userData['avatar_url'] ?? '',
                ],
            ]);

            return [
                'connected' => true,
                'message' => 'GitHub успешно подключён',
                'integration' => $integration,
                'github_username' => $userData['login'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Подключение Slack через OAuth 2.0
     */
    public function connectSlackOAuth(User $user, string $code, string $redirectUri): array
    {
        try {
            $clientId = $_ENV['SLACK_CLIENT_ID'] ?? '';
            $clientSecret = $_ENV['SLACK_CLIENT_SECRET'] ?? '';

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'connected' => false,
                    'message' => 'Slack интеграция не настроена в окружении',
                ];
            }

            // Получаем access token
            $tokenUrl = 'https://slack.com/api/oauth.v2.access';
            $payload = http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $payload,
                ],
            ]);

            $response = file_get_contents($tokenUrl, false, $context);
            if ($response === false) {
                return [
                    'connected' => false,
                    'message' => 'Ошибка получения токена Slack',
                ];
            }

            $data = json_decode($response, true);

            if (!isset($data['ok']) || !$data['ok']) {
                return [
                    'connected' => false,
                    'message' => $data['error'] ?? 'Ошибка авторизации Slack',
                ];
            }

            // Сохраняем интеграцию
            $integration = $this->saveIntegration($user, 'slack', [
                'externalId' => $data['team']['id'] ?? '',
                'accessToken' => $data['access_token'] ?? '',
                'refreshToken' => null, // Slack не использует refresh tokens для bot tokens
                'metadata' => [
                    'team_id' => $data['team']['id'] ?? '',
                    'team_name' => $data['team']['name'] ?? '',
                    'bot_user_id' => $data['bot_user_id'] ?? '',
                    'scope' => $data['scope'] ?? '',
                    'webhook_url' => $data['incoming_webhook']['configuration_url'] ?? null,
                    'channel' => $data['incoming_webhook']['channel'] ?? null,
                ],
            ]);

            return [
                'connected' => true,
                'message' => 'Slack успешно подключён',
                'integration' => $integration,
                'team_name' => $data['team']['name'] ?? '',
                'channel' => $data['incoming_webhook']['channel'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Подключение Slack через Incoming Webhook (упрощённый вариант)
     */
    public function connectSlack(User $user, string $webhookUrl): array
    {
        try {
            // Проверяем webhook URL
            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                return [
                    'connected' => false,
                    'message' => 'Неверный формат webhook URL',
                ];
            }

            // Проверяем, что это Slack webhook
            if (!str_contains($webhookUrl, 'hooks.slack.com')) {
                return [
                    'connected' => false,
                    'message' => 'URL должен быть Slack Incoming Webhook',
                ];
            }

            // Тестовое сообщение
            $payload = json_encode([
                'text' => '🔔 To-Do List: Проверка подключения к Slack',
                'username' => 'To-Do List Bot',
                'icon_emoji' => ':clipboard:',
            ]);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $payload,
                ],
            ]);

            $response = file_get_contents($webhookUrl, false, $context);

            $integration = $this->saveIntegration($user, 'slack', [
                'metadata' => [
                    'webhook_url' => $webhookUrl,
                    'connection_type' => 'webhook',
                ],
            ]);

            return [
                'connected' => true,
                'message' => 'Slack успешно подключён',
                'integration' => $integration,
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить URL для OAuth авторизации Slack
     */
    public function getSlackOAuthUrl(string $redirectUri): string
    {
        $clientId = $_ENV['SLACK_CLIENT_ID'] ?? '';
        $scopes = [
            'incoming-webhook',
            'chat:write',
            'channels:read',
            'groups:read',
            'users:read',
        ];

        return 'https://slack.com/oauth/v2/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'state' => bin2hex(random_bytes(16)), // CSRF protection
        ]);
    }

    /**
     * Получить URL для OAuth авторизации Google Calendar
     */
    public function getGoogleCalendarOAuthUrl(string $redirectUri): string
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $scopes = [
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.readonly',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent', // Получаем refresh token
            'state' => bin2hex(random_bytes(16)), // CSRF protection
        ]);
    }

    /**
     * Подключение Google Calendar через OAuth 2.0
     */
    public function connectGoogleCalendar(User $user, string $code, string $redirectUri): array
    {
        try {
            $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
            $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'connected' => false,
                    'message' => 'Google Calendar интеграция не настроена в окружении',
                ];
            }

            // Получаем access token
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $payload = http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $payload,
                ],
            ]);

            $response = file_get_contents($tokenUrl, false, $context);
            if ($response === false) {
                return [
                    'connected' => false,
                    'message' => 'Ошибка получения токена Google',
                ];
            }

            $data = json_decode($response, true);

            if (!isset($data['access_token'])) {
                return [
                    'connected' => false,
                    'message' => $data['error_description'] ?? $data['error'] ?? 'Ошибка авторизации Google',
                ];
            }

            // Получаем информацию о пользователе
            $userInfo = $this->getGoogleUserInfo($data['access_token']);

            // Вычисляем время истечения токена
            $expiresAt = new \DateTime();
            $expiresAt->modify('+'.($data['expires_in'] ?? 3600).' seconds');

            // Сохраняем интеграцию
            $integration = $this->saveIntegration($user, 'google_calendar', [
                'externalId' => $userInfo['id'] ?? '',
                'accessToken' => $data['access_token'],
                'refreshToken' => $data['refresh_token'] ?? null,
                'tokenExpiresAt' => $expiresAt,
                'metadata' => [
                    'email' => $userInfo['email'] ?? '',
                    'name' => $userInfo['name'] ?? '',
                    'picture' => $userInfo['picture'] ?? '',
                    'calendar_id' => 'primary',
                ],
            ]);

            return [
                'connected' => true,
                'message' => 'Google Calendar успешно подключён',
                'integration' => $integration,
                'email' => $userInfo['email'] ?? '',
                'name' => $userInfo['name'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить информацию о пользователе Google
     */
    private function getGoogleUserInfo(string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer '.$accessToken,
            ],
        ]);

        $response = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, $context);
        if ($response === false) {
            return [];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Обновить access token Google Calendar
     */
    public function refreshGoogleToken(UserIntegration $integration): bool
    {
        $refreshToken = $integration->getRefreshToken();
        if (!$refreshToken) {
            return false;
        }

        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $payload,
            ],
        ]);

        $response = file_get_contents($tokenUrl, false, $context);
        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            return false;
        }

        // Обновляем токены в БД
        $expiresAt = new \DateTime();
        $expiresAt->modify('+'.($data['expires_in'] ?? 3600).' seconds');

        $integration->setAccessToken($data['access_token']);
        $integration->setTokenExpiresAt($expiresAt);
        $integration->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return true;
    }

    /**
     * Создать событие в Google Calendar
     */
    public function createGoogleCalendarEvent(User $user, string $title, \DateTime $startTime, \DateTime $endTime, array $options = []): array
    {
        $integration = $this->getUserIntegration($user, 'google_calendar');

        if (!$integration || !$integration->isActive()) {
            return ['success' => false, 'message' => 'Google Calendar не подключён'];
        }

        // Проверяем валидность токена
        if (!$integration->isTokenValid()) {
            if (!$this->refreshGoogleToken($integration)) {
                return ['success' => false, 'message' => 'Требуется повторная авторизация'];
            }
        }

        $accessToken = $integration->getAccessToken();
        $calendarId = $integration->getMetadata()['calendar_id'] ?? 'primary';

        $eventData = [
            'summary' => $title,
            'start' => [
                'dateTime' => $startTime->format(\DateTime::RFC3339),
                'timeZone' => $options['timezone'] ?? 'Europe/Moscow',
            ],
            'end' => [
                'dateTime' => $endTime->format(\DateTime::RFC3339),
                'timeZone' => $options['timezone'] ?? 'Europe/Moscow',
            ],
            'description' => $options['description'] ?? '',
            'attendees' => $options['attendees'] ?? [],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => $options['reminder_minutes'] ?? 30],
                ],
            ],
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer '.$accessToken,
                    'Content-Type: application/json',
                ],
                'content' => json_encode($eventData),
            ],
        ]);

        $apiUrl = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events";
        $response = file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => 'Ошибка создания события'];
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']['message'] ?? 'Ошибка API Google'];
        }

        // Обновляем время синхронизации
        $integration->setLastSyncAt(new \DateTime());
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Событие успешно создано',
            'event_id' => $result['id'] ?? '',
            'html_link' => $result['htmlLink'] ?? '',
        ];
    }

    /**
     * Синхронизировать задачи с Google Calendar
     */
    public function syncTasksToCalendar(User $user, array $tasks): array
    {
        $integration = $this->getUserIntegration($user, 'google_calendar');

        if (!$integration || !$integration->isActive()) {
            return ['success' => false, 'message' => 'Google Calendar не подключён', 'created' => 0, 'errors' => 0];
        }

        $created = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            if (!isset($task['title']) || !isset($task['due_date'])) {
                $errors++;
                continue;
            }

            $startTime = new \DateTime($task['due_date']);
            $endTime = clone $startTime;
            $endTime->modify('+1 hour');

            $result = $this->createGoogleCalendarEvent(
                $user,
                $task['title'],
                $startTime,
                $endTime,
                [
                    'description' => $task['description'] ?? '',
                    'reminder_minutes' => 30,
                ]
            );

            if ($result['success']) {
                $created++;
            } else {
                $errors++;
            }
        }

        return [
            'success' => true,
            'message' => 'Синхронизация завершена',
            'created' => $created,
            'errors' => $errors,
        ];
    }

    public function connectJira(User $user, string $domain, string $email, string $apiToken): array
    {
        try {
            // Проверяем подключение к Jira API
            $apiUrl = "https://{$domain}/rest/api/2/myself";
            $auth = base64_encode("$email:$apiToken");

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Authorization: Basic ' . $auth,
                        'Accept: application/json',
                    ],
                ],
            ]);

            $response = file_get_contents($apiUrl, false, $context);
            if ($response === false) {
                return [
                    'connected' => false,
                    'message' => 'Неверные учётные данные Jira',
                ];
            }

            $jiraUser = json_decode($response, true);

            $integration = $this->saveIntegration($user, 'jira', [
                'externalId' => $jiraUser['key'] ?? '',
                'metadata' => [
                    'domain' => $domain,
                    'email' => $email,
                    'displayName' => $jiraUser['displayName'] ?? '',
                ],
                // Токен сохраняем зашифрованным в продакшене
                'accessToken' => $apiToken,
            ]);

            return [
                'connected' => true,
                'message' => 'Jira успешно подключена',
                'integration' => $integration,
                'jira_user' => $jiraUser['displayName'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    public function connectTelegram(User $user, string $botToken, string $chatId): array
    {
        try {
            // Проверяем токен бота
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getMe";
            $response = file_get_contents($apiUrl);
            $botData = json_decode($response, true);

            if (!isset($botData['ok']) || !$botData['ok']) {
                return [
                    'connected' => false,
                    'message' => 'Неверный токен бота Telegram',
                ];
            }

            // Проверяем chatId отправкой тестового сообщения
            $messageUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $payload = http_build_query([
                'chat_id' => $chatId,
                'text' => '🔔 To-Do List: Проверка подключения к Telegram',
            ]);

            $testResponse = file_get_contents($messageUrl . '?' . $payload);
            $testData = json_decode($testResponse, true);

            if (!isset($testData['ok']) || !$testData['ok']) {
                return [
                    'connected' => false,
                    'message' => 'Не удалось отправить сообщение в чат',
                ];
            }

            $integration = $this->saveIntegration($user, 'telegram', [
                'metadata' => [
                    'bot_token' => $botToken,
                    'chat_id' => $chatId,
                    'bot_username' => $botData['result']['username'] ?? '',
                ],
            ]);

            return [
                'connected' => true,
                'message' => 'Telegram успешно подключён',
                'integration' => $integration,
                'bot_username' => $botData['result']['username'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get all integrations
     */
    public function getAvailableIntegrations(): array
    {
        return [
            'github' => [
                'name' => 'GitHub',
                'description' => 'Синхронизация с GitHub Issues',
                'icon' => 'fab fa-github',
                'features' => [
                    'Создание issues из задач',
                    'Импорт issues в задачи',
                    'Двусторонняя синхронизация',
                ],
                'config_fields' => [
                    ['name' => 'token', 'type' => 'password', 'label' => 'Personal Access Token'],
                ],
            ],
            'slack' => [
                'name' => 'Slack',
                'description' => 'Уведомления и команды в Slack',
                'icon' => 'fab fa-slack',
                'features' => [
                    'Уведомления в каналы',
                    'Создание задач из Slack',
                    'Slash команды',
                ],
                'config_fields' => [
                    ['name' => 'webhook_url', 'type' => 'url', 'label' => 'Webhook URL'],
                ],
            ],
            'jira' => [
                'name' => 'Jira',
                'description' => 'Интеграция с Atlassian Jira',
                'icon' => 'fab fa-jira',
                'features' => [
                    'Импорт issues',
                    'Экспорт задач',
                    'Синхронизация статусов',
                ],
                'config_fields' => [
                    ['name' => 'domain', 'type' => 'text', 'label' => 'Domain (example.atlassian.net)'],
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
                    ['name' => 'api_token', 'type' => 'password', 'label' => 'API Token'],
                ],
            ],
            'trello' => [
                'name' => 'Trello',
                'description' => 'Импорт карточек из Trello',
                'icon' => 'fab fa-trello',
                'features' => [
                    'Импорт досок',
                    'Импорт карточек',
                    'Сохранение структуры',
                ],
                'config_fields' => [
                    ['name' => 'api_key', 'type' => 'text', 'label' => 'API Key'],
                    ['name' => 'token', 'type' => 'password', 'label' => 'Token'],
                ],
            ],
            'google_calendar' => [
                'name' => 'Google Calendar',
                'description' => 'Синхронизация с календарем',
                'icon' => 'fab fa-google',
                'features' => [
                    'Экспорт дедлайнов',
                    'Напоминания',
                    'Двусторонняя синхронизация',
                ],
                'config_fields' => [
                    ['name' => 'oauth', 'type' => 'oauth', 'label' => 'Connect with Google'],
                ],
            ],
            'telegram' => [
                'name' => 'Telegram',
                'description' => 'Уведомления в Telegram',
                'icon' => 'fab fa-telegram',
                'features' => [
                    'Мгновенные уведомления',
                    'Создание задач из чата',
                    'Бот команды',
                ],
                'config_fields' => [
                    ['name' => 'bot_token', 'type' => 'password', 'label' => 'Bot Token'],
                    ['name' => 'chat_id', 'type' => 'text', 'label' => 'Chat ID'],
                ],
            ],
            'zapier' => [
                'name' => 'Zapier',
                'description' => 'Подключение 3000+ приложений',
                'icon' => 'fa-bolt',
                'features' => [
                    'Автоматизация',
                    'Триггеры и действия',
                    'Неограниченные возможности',
                ],
                'config_fields' => [
                    ['name' => 'webhook_url', 'type' => 'url', 'label' => 'Zapier Webhook URL'],
                ],
            ],
        ];
    }

    public function getUserIntegrations(User $user): array
    {
        $integrations = $this->integrationRepository->findActiveByUser($user->getId());

        $result = [];
        foreach ($integrations as $integration) {
            $metadata = $integration->getMetadata() ?? [];
            
            $result[] = [
                'id' => $integration->getId(),
                'type' => $integration->getIntegrationType(),
                'name' => $integration->getIntegrationName(),
                'external_id' => $integration->getExternalId(),
                'is_active' => $integration->isActive(),
                'is_token_valid' => $integration->isTokenValid(),
                'created_at' => $integration->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $integration->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'last_sync_at' => $integration->getLastSyncAt()?->format('Y-m-d H:i:s'),
                'metadata' => [
                    'username' => $metadata['username'] ?? null,
                    'name' => $metadata['name'] ?? null,
                    'avatar' => $metadata['avatar'] ?? null,
                    'domain' => $metadata['domain'] ?? null,
                    'bot_username' => $metadata['bot_username'] ?? null,
                ],
            ];
        }

        return $result;
    }

    public function disconnectIntegration(User $user, string $integrationType): bool
    {
        $integration = $this->integrationRepository->findByUserAndType($user->getId(), $integrationType);

        if (!$integration) {
            return false;
        }

        // Помечаем как неактивную (мягкое удаление)
        $integration->setIsActive(false);
        $integration->setAccessToken(null);
        $integration->setRefreshToken(null);
        $integration->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return true;
    }

    public function testConnection(string $integrationType, array $credentials): array
    {
        return match($integrationType) {
            'github' => $this->testGitHubConnection($credentials),
            'slack' => $this->testSlackConnection($credentials),
            'jira' => $this->testJiraConnection($credentials),
            'telegram' => $this->testTelegramConnection($credentials),
            'google_calendar' => $this->testGoogleCalendarConnection($credentials),
            default => [
                'success' => false,
                'message' => 'Неизвестный тип интеграции: ' . $integrationType,
            ],
        };
    }

    public function getIntegrationStats(User $user): array
    {
        $integrations = $this->integrationRepository->findActiveByUser($user->getId());

        $totalSynced = 0;
        $syncErrors = 0;
        $lastSync = null;

        foreach ($integrations as $integration) {
            if ($integration->getLastSyncAt()) {
                $totalSynced++;
                
                if ($lastSync === null || $integration->getLastSyncAt() > $lastSync) {
                    $lastSync = $integration->getLastSyncAt();
                }
            }

            // Проверка на ошибки синхронизации (можно расширить)
            if (!$integration->isTokenValid()) {
                $syncErrors++;
            }
        }

        return [
            'total_synced' => $totalSynced,
            'last_sync' => $lastSync?->format('Y-m-d H:i:s'),
            'active_integrations' => \count($integrations),
            'sync_errors' => $syncErrors,
        ];
    }

    /**
     * Получить интеграцию пользователя
     */
    public function getUserIntegration(User $user, string $type): ?UserIntegration
    {
        return $this->integrationRepository->findByUserAndType($user->getId(), $type);
    }

    /**
     * Проверить наличие интеграции
     */
    public function hasIntegration(User $user, string $type): bool
    {
        $integration = $this->getUserIntegration($user, $type);
        return $integration && $integration->isActive() && $integration->isTokenValid();
    }

    /**
     * Отправить уведомление через интеграцию
     */
    public function sendNotification(User $user, string $type, string $message, array $context = []): bool
    {
        $integration = $this->getUserIntegration($user, $type);

        if (!$integration || !$integration->isActive()) {
            return false;
        }

        return match($type) {
            'telegram' => $this->sendTelegramNotification($integration, $message, $context),
            'slack' => $this->sendSlackNotification($integration, $message, $context),
            default => false,
        };
    }

    /**
     * Сохранить интеграцию
     */
    private function saveIntegration(User $user, string $type, array $data): UserIntegration
    {
        $integration = $this->integrationRepository->findByUserAndType($user->getId(), $type);

        if (!$integration) {
            $integration = new UserIntegration();
            $integration->setUser($user);
            $integration->setIntegrationType($type);
        }

        if (isset($data['externalId'])) {
            $integration->setExternalId($data['externalId']);
        }

        if (isset($data['accessToken'])) {
            $integration->setAccessToken($data['accessToken']);
        }

        if (isset($data['metadata'])) {
            $integration->setMetadata($data['metadata']);
        }

        $integration->setIsActive(true);
        $integration->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($integration);
        $this->entityManager->flush();

        return $integration;
    }

    /**
     * Тест подключения GitHub
     */
    private function testGitHubConnection(array $credentials): array
    {
        $token = $credentials['token'] ?? '';

        if (empty($token)) {
            return ['success' => false, 'message' => 'Требуется токен'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: token ' . $token,
                    'User-Agent: To-Do-List-App',
                ],
            ],
        ]);

        $response = @file_get_contents('https://api.github.com/user', false, $context);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'Не удалось подключиться к GitHub'];
        }

        $data = json_decode($response, true);
        
        return [
            'success' => isset($data['login']),
            'message' => isset($data['login']) ? 'Подключение успешно' : 'Неверный токен',
            'username' => $data['login'] ?? null,
        ];
    }

    /**
     * Тест подключения Slack
     */
    private function testSlackConnection(array $credentials): array
    {
        $webhookUrl = $credentials['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            return ['success' => false, 'message' => 'Требуется webhook URL'];
        }

        $payload = json_encode(['text' => 'Test']);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $payload,
            ],
        ]);

        $result = @file_get_contents($webhookUrl, false, $context);

        return [
            'success' => $result !== false,
            'message' => $result !== false ? 'Подключение успешно' : 'Неверный webhook URL',
        ];
    }

    /**
     * Тест подключения Jira
     */
    private function testJiraConnection(array $credentials): array
    {
        $domain = $credentials['domain'] ?? '';
        $email = $credentials['email'] ?? '';
        $apiToken = $credentials['api_token'] ?? '';

        if (empty($domain) || empty($email) || empty($apiToken)) {
            return ['success' => false, 'message' => 'Заполните все поля'];
        }

        $apiUrl = "https://{$domain}/rest/api/2/myself";
        $auth = base64_encode("$email:$apiToken");

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Basic ' . $auth,
                    'Accept: application/json',
                ],
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => 'Не удалось подключиться к Jira'];
        }

        $data = json_decode($response, true);

        return [
            'success' => isset($data['key']),
            'message' => isset($data['displayName']) ? 'Подключение успешно' : 'Ошибка авторизации',
            'user' => $data['displayName'] ?? null,
        ];
    }

    /**
     * Тест подключения Telegram
     */
    private function testTelegramConnection(array $credentials): array
    {
        $botToken = $credentials['bot_token'] ?? '';
        $chatId = $credentials['chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            return ['success' => false, 'message' => 'Заполните все поля'];
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/getMe";
        $response = @file_get_contents($apiUrl);

        if ($response === false) {
            return ['success' => false, 'message' => 'Неверный токен бота'];
        }

        $data = json_decode($response, true);

        if (!isset($data['ok']) || !$data['ok']) {
            return ['success' => false, 'message' => 'Неверный токен бота'];
        }

        // Тест отправки сообщения
        $messageUrl = "https://api.telegram.org/bot{$botToken}/sendMessage?" . http_build_query([
            'chat_id' => $chatId,
            'text' => 'Test message',
        ]);

        $testResponse = @file_get_contents($messageUrl);
        $testData = json_decode($testResponse, true);

        return [
            'success' => isset($testData['ok']) && $testData['ok'],
            'message' => isset($testData['ok']) && $testData['ok'] ? 'Подключение успешно' : 'Ошибка отправки сообщения',
            'bot_username' => $data['result']['username'] ?? null,
        ];
    }

    /**
     * Тест подключения Google Calendar
     */
    private function testGoogleCalendarConnection(array $credentials): array
    {
        $accessToken = $credentials['access_token'] ?? '';

        if (empty($accessToken)) {
            return ['success' => false, 'message' => 'Требуется access token'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer '.$accessToken,
            ],
        ]);

        // Проверяем токен, получая список календарей
        $apiUrl = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => 'Неверный токен или недостаточно прав'];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return [
                'success' => false,
                'message' => $data['error']['message'] ?? 'Ошибка API Google',
            ];
        }

        return [
            'success' => true,
            'message' => 'Подключение успешно',
            'calendars_count' => \count($data['items'] ?? []),
        ];
    }

    /**
     * Отправить уведомление в Telegram
     */
    private function sendTelegramNotification(UserIntegration $integration, string $message, array $context = []): bool
    {
        $metadata = $integration->getMetadata() ?? [];
        $botToken = $metadata['bot_token'] ?? '';
        $chatId = $metadata['chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            return false;
        }

        // Форматируем сообщение
        $formattedMessage = $this->formatTelegramMessage($message, $context);

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $formattedMessage,
            'parse_mode' => 'HTML',
        ]);

        $result = @file_get_contents($apiUrl . '?' . $payload);
        $data = json_decode($result, true);

        // Обновляем время последней синхронизации
        if (isset($data['ok']) && $data['ok']) {
            $integration->setLastSyncAt(new \DateTime());
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    /**
     * Отправить уведомление в Slack
     */
    private function sendSlackNotification(UserIntegration $integration, string $message, array $context = []): bool
    {
        $metadata = $integration->getMetadata() ?? [];
        $webhookUrl = $metadata['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            return false;
        }

        $payload = json_encode([
            'text' => $message,
            'username' => 'To-Do List Bot',
            'icon_emoji' => ':clipboard:',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $payload,
            ],
        ]);

        $result = @file_get_contents($webhookUrl, false, $context);

        // Обновляем время последней синхронизации
        if ($result !== false) {
            $integration->setLastSyncAt(new \DateTime());
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    /**
     * Форматировать сообщение для Telegram
     */
    private function formatTelegramMessage(string $message, array $context = []): string
    {
        // Добавляем контекст к сообщению
        if (isset($context['task_title'])) {
            $message = "<b>📋 Задача:</b> {$context['task_title']}\n\n" . $message;
        }

        if (isset($context['task_url'])) {
            $message .= "\n\n<a href=\"{$context['task_url']}\">Открыть задачу</a>";
        }

        return $message;
    }
}
