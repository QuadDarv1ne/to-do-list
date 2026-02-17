<?php

namespace App\Controller;

use App\Service\NotificationPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications/preferences')]
#[IsGranted('ROLE_USER')]
class NotificationPreferenceController extends AbstractController
{
    public function __construct(
        private NotificationPreferenceService $preferenceService
    ) {}

    /**
     * Notification preferences page
     */
    #[Route('', name: 'app_notification_preferences', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $preferences = $this->preferenceService->getPreferences($user);
        $channels = $this->preferenceService->getChannels();
        $types = $this->preferenceService->getTypes();
        $stats = $this->preferenceService->getStatistics($user);

        return $this->render('notifications/preferences.html.twig', [
            'preferences' => $preferences,
            'channels' => $channels,
            'types' => $types,
            'stats' => $stats
        ]);
    }

    /**
     * Update preferences
     */
    #[Route('/update', name: 'app_notification_preferences_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $success = $this->preferenceService->updatePreferences($user, $data);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Настройки сохранены' : 'Ошибка сохранения'
        ]);
    }

    /**
     * Test notification
     */
    #[Route('/test/{channel}', name: 'app_notification_preferences_test', methods: ['POST'])]
    public function test(string $channel): JsonResponse
    {
        $user = $this->getUser();
        $success = $this->preferenceService->sendTestNotification($user, $channel);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Тестовое уведомление отправлено' : 'Ошибка отправки'
        ]);
    }
}
