<?php

namespace App\Controller;

use App\Service\EnhancedNotificationService;
use App\Service\NotificationTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class EnhancedNotificationController extends AbstractController
{
    #[Route('/stream', name: 'app_notifications_stream', methods: ['GET'])]
    public function notificationStream(EnhancedNotificationService $notificationService): StreamedResponse
    {
        $user = $this->getUser();
        return $notificationService->createNotificationStream($user);
    }

    #[Route('/unread', name: 'app_notifications_unread', methods: ['GET'])]
    public function unread(EnhancedNotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $notificationService->getUnreadNotifications($user);
        
        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'channel' => $notification->getChannel(),
                'is_read' => $notification->isRead(),
                'created_at' => $notification->getCreatedAt()->format('c'),
                'task_id' => $notification->getTask()?->getId(),
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/stats', name: 'app_notifications_stats', methods: ['GET'])]
    public function stats(EnhancedNotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        $stats = $notificationService->getNotificationStats($user);
        
        return $this->json($stats);
    }

    #[Route('/test', name: 'app_notifications_test', methods: ['POST'])]
    public function test(
        Request $request,
        EnhancedNotificationService $notificationService,
        NotificationTemplateService $templateService
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        
        $title = $data['title'] ?? 'Test Notification';
        $message = $data['message'] ?? 'This is a test notification';
        $type = $data['type'] ?? 'info';
        $channels = $data['channels'] ?? ['in_app'];
        $useTemplate = $data['use_template'] ?? false;
        
        $templateKey = null;
        $templateVariables = [];
        
        if ($useTemplate) {
            $templateKey = 'task_assigned';
            $templateVariables = [
                'user_name' => $user->getFullName() ?? $user->getUserIdentifier(),
                'task_title' => 'Test Task',
                'task_description' => 'This is a test task description',
                'due_date' => '2026-12-31',
                'task_url' => 'https://example.com/task/123',
            ];
        }
        
        $notification = $notificationService->createNotification(
            $user,
            $title,
            $message,
            null,
            $type,
            $channels,
            $templateKey,
            $templateVariables
        );
        
        return $this->json([
            'success' => true,
            'notification_id' => $notification->getId(),
            'message' => 'Notification created successfully',
        ]);
    }

    #[Route('/templates', name: 'app_notifications_templates', methods: ['GET'])]
    public function templates(NotificationTemplateService $templateService): JsonResponse
    {
        $templates = $templateService->getTemplatesByChannel('email');
        
        $data = [];
        foreach ($templates as $template) {
            $data[] = [
                'id' => $template->getId(),
                'key' => $template->getKey(),
                'name' => $template->getName(),
                'channel' => $template->getChannel(),
                'subject' => $template->getSubject(),
                'variables' => $template->getVariables(),
                'is_active' => $template->isActive(),
            ];
        }
        
        return $this->json($data);
    }
    
    #[Route('/test-page', name: 'app_notifications_test_page', methods: ['GET'])]
    public function testPage(): Response
    {
        return $this->render('notification/test.html.twig');
    }
}