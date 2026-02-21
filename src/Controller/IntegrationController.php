<?php

namespace App\Controller;

use App\Service\IntegrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/integrations')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private IntegrationService $integrationService,
    ) {
    }

    #[Route('', name: 'app_integrations_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $available = $this->integrationService->getAvailableIntegrations();
        $connected = $this->integrationService->getUserIntegrations($user);
        $stats = $this->integrationService->getIntegrationStats($user);

        return $this->render('integrations/index.html.twig', [
            'available' => $available,
            'connected' => $connected,
            'stats' => $stats,
        ]);
    }

    /**
     * Slack OAuth: Начало процесса авторизации
     */
    #[Route('/slack/oauth/start', name: 'app_integrations_slack_oauth_start', methods: ['GET', 'POST'])]
    public function startSlackOAuth(Request $request): Response
    {
        $redirectUri = $this->generateUrl('app_integrations_slack_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $oauthUrl = $this->integrationService->getSlackOAuthUrl($redirectUri);

        return $this->redirect($oauthUrl);
    }

    /**
     * Slack OAuth: Callback после авторизации
     */
    #[Route('/slack/oauth/callback', name: 'app_integrations_slack_oauth_callback', methods: ['GET'])]
    public function slackOAuthCallback(Request $request): Response
    {
        $user = $this->getUser();
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Ошибка авторизации Slack: '.$error);
            return $this->redirectToRoute('app_integrations_index');
        }

        if (!$code) {
            $this->addFlash('error', 'Код авторизации не получен');
            return $this->redirectToRoute('app_integrations_index');
        }

        $redirectUri = $this->generateUrl('app_integrations_slack_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $result = $this->integrationService->connectSlackOAuth($user, $code, $redirectUri);

        if ($result['connected']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('app_integrations_index');
    }

    #[Route('/slack/connect', name: 'app_integrations_slack_connect', methods: ['POST'])]
    public function connectSlack(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $webhookUrl = $request->request->get('webhook_url');

        $result = $this->integrationService->connectSlack($user, $webhookUrl);

        return $this->json($result);
    }

    /**
     * Google Calendar OAuth: Начало процесса авторизации
     */
    #[Route('/google-calendar/oauth/start', name: 'app_integrations_google_oauth_start', methods: ['GET', 'POST'])]
    public function startGoogleOAuth(Request $request): Response
    {
        $redirectUri = $this->generateUrl('app_integrations_google_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $oauthUrl = $this->integrationService->getGoogleCalendarOAuthUrl($redirectUri);

        return $this->redirect($oauthUrl);
    }

    /**
     * Google Calendar OAuth: Callback после авторизации
     */
    #[Route('/google-calendar/oauth/callback', name: 'app_integrations_google_oauth_callback', methods: ['GET'])]
    public function googleOAuthCallback(Request $request): Response
    {
        $user = $this->getUser();
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Ошибка авторизации Google: '.$error);
            return $this->redirectToRoute('app_integrations_index');
        }

        if (!$code) {
            $this->addFlash('error', 'Код авторизации не получен');
            return $this->redirectToRoute('app_integrations_index');
        }

        $redirectUri = $this->generateUrl('app_integrations_google_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $result = $this->integrationService->connectGoogleCalendar($user, $code, $redirectUri);

        if ($result['connected']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('app_integrations_index');
    }

    #[Route('/github/connect', name: 'app_integrations_github_connect', methods: ['POST'])]
    public function connectGitHub(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $token = $request->request->get('token');

        $result = $this->integrationService->connectGitHub($user, $token);

        return $this->json($result);
    }

    #[Route('/jira/connect', name: 'app_integrations_jira_connect', methods: ['POST'])]
    public function connectJira(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $domain = $request->request->get('domain');
        $email = $request->request->get('email');
        $apiToken = $request->request->get('api_token');

        $result = $this->integrationService->connectJira($user, $domain, $email, $apiToken);

        return $this->json($result);
    }

    #[Route('/telegram/connect', name: 'app_integrations_telegram_connect', methods: ['POST'])]
    public function connectTelegram(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $botToken = $request->request->get('bot_token');
        $chatId = $request->request->get('chat_id');

        $result = $this->integrationService->connectTelegram($user, $botToken, $chatId);

        return $this->json($result);
    }

    #[Route('/{integration}/disconnect', name: 'app_integrations_disconnect', methods: ['POST'])]
    public function disconnect(string $integration): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->integrationService->disconnectIntegration($user, $integration);

        return $this->json(['success' => $result]);
    }

    #[Route('/{integration}/test', name: 'app_integrations_test', methods: ['POST'])]
    public function test(string $integration, Request $request): JsonResponse
    {
        $credentials = json_decode($request->getContent(), true);
        $result = $this->integrationService->testConnection($integration, $credentials);

        return $this->json($result);
    }
}
