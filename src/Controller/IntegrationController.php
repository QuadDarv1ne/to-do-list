<?php

namespace App\Controller;

use App\Service\IntegrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/integrations')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private IntegrationService $integrationService
    ) {}

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
            'stats' => $stats
        ]);
    }

    #[Route('/github/connect', name: 'app_integrations_github_connect', methods: ['POST'])]
    public function connectGitHub(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $token = $request->request->get('token');
        
        $result = $this->integrationService->connectGitHub($user, $token);
        
        return $this->json($result);
    }

    #[Route('/slack/connect', name: 'app_integrations_slack_connect', methods: ['POST'])]
    public function connectSlack(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $webhookUrl = $request->request->get('webhook_url');
        
        $result = $this->integrationService->connectSlack($user, $webhookUrl);
        
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
