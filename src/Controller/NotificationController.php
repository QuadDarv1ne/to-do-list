<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/', name: 'app_notification_index', methods: ['GET'])]
    public function index(
        NotificationRepository $notificationRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('notification_controller_index');
        }
        
        $user = $this->getUser();
        $notifications = $notificationRepository->findByUser($user);
        
        try {
            return $this->render('notification/index.html.twig', [
                'notifications' => $notifications,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('notification_controller_index');
            }
        }
    }
    
    #[Route('/mark-as-read/{id}', name: 'app_notification_mark_as_read', methods: ['POST'])]
    public function markAsRead(
        int $id, 
        NotificationRepository $notificationRepository, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('notification_controller_mark_as_read');
        }
        
        $notification = $notificationRepository->find($id);
        
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Notification not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('notification_controller_mark_as_read');
                }
            }
        }
        
        $notification->setIsRead(true);
        $entityManager->flush();
        
        try {
            return new JsonResponse(['success' => true]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('notification_controller_mark_as_read');
            }
        }
    }
    
    #[Route('/mark-all-as-read', name: 'app_notification_mark_all_as_read', methods: ['POST'])]
    public function markAllAsRead(
        NotificationRepository $notificationRepository, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('notification_controller_mark_all_as_read');
        }
        
        $user = $this->getUser();
        $notifications = $notificationRepository->findByUserUnread($user);
        
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        
        $entityManager->flush();
        
        try {
            return new JsonResponse(['success' => true, 'count' => count($notifications)]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('notification_controller_mark_all_as_read');
            }
        }
    }
    
    #[Route('/unread-count', name: 'app_notification_unread_count', methods: ['GET'])]
    public function getUnreadCount(
        NotificationRepository $notificationRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('notification_controller_unread_count');
        }
        
        $user = $this->getUser();
        $unreadCount = $notificationRepository->countUnreadByUser($user);
        
        try {
            return new JsonResponse(['count' => $unreadCount]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('notification_controller_unread_count');
            }
        }
    }
}