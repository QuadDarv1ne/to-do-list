<?php

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class RealTimeNotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications_list', methods: ['GET'])]
    public function listNotifications(NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        $notifications = $notificationService->getUnreadNotifications($user);
        
        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'is_read' => $notification->isRead(),
                'created_at' => $notification->getCreatedAt()->format('c'),
                'task_id' => $notification->getTask() ? $notification->getTask()->getId() : null
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/stream', name: 'app_notifications_stream', methods: ['GET'])]
    public function notificationStream(Request $request, NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $notificationService->createNotificationStream($user);
    }

    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    public function getUnreadCount(NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        $stats = $notificationService->getNotificationStats($user);
        
        return $this->json([
            'unread' => $stats['unread'],
            'total' => $stats['total']
        ]);
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        $count = $notificationService->markAllAsRead($user);
        
        return $this->json([
            'success' => true,
            'marked_count' => $count
        ]);
    }

    #[Route('/{id}/read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(
        int $id, 
        NotificationService $notificationService,
        \App\Repository\NotificationRepository $notificationRepository
    ): Response {
        $user = $this->getUser();
        
        $notification = $notificationRepository->find($id);
        
        if (!$notification || $notification->getUser() !== $user) {
            return $this->json(['error' => 'Notification not found'], 404);
        }
        
        $notificationService->markAsRead($notification);
        return $this->json(['success' => true]);
    }

    #[Route('/recent', name: 'app_notifications_recent', methods: ['GET'])]
    public function getRecentNotifications(NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        $notifications = $notificationService->getUnreadNotifications($user);
        
        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'is_read' => $notification->isRead(),
                'created_at' => $notification->getCreatedAt()->format('c'),
                'task_id' => $notification->getTask() ? $notification->getTask()->getId() : null
            ];
        }
        
        return $this->json($data);
    }
}
