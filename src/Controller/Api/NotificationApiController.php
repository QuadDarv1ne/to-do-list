<?php

namespace App\Controller\Api;

use App\Entity\PushNotification;
use App\Service\PushNotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API для управления push-уведомлениями
 */
#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Notifications')]
class NotificationApiController extends AbstractController
{
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/notifications',
        summary: 'Получить список уведомлений',
        description: 'Возвращает список уведомлений пользователя',
        tags: ['Notifications'],
    )]
    #[OA\Parameter(
        name: 'unreadOnly',
        in: 'query',
        description: 'Только непрочитанные',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Количество уведомлений',
        schema: new OA\Schema(type: 'integer', default: 50, maximum: 200),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список уведомлений',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PushNotification')),
                new OA\Property(property: 'unreadCount', type: 'integer'),
            ],
        ),
    )]
    public function list(
        Request $request,
        PushNotificationService $pushService,
    ): JsonResponse {
        $user = $this->getUser();
        $unreadOnly = $request->query->getBoolean('unreadOnly', false);
        $limit = (int) $request->query->get('limit', 50);

        $notifications = $pushService->getNotifications($user, $limit, $unreadOnly);
        $unreadCount = $pushService->getUnreadCount($user);

        return $this->json([
            'success' => true,
            'data' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/count', name: 'api_notifications_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/notifications/count',
        summary: 'Получить количество непрочитанных',
        tags: ['Notifications'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Количество непрочитанных уведомлений',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'count', type: 'integer'),
            ],
        ),
    )]
    public function count(PushNotificationService $pushService): JsonResponse
    {
        $user = $this->getUser();
        $count = $pushService->getUnreadCount($user);

        return $this->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    #[Route('/{id}/read', name: 'api_notifications_mark_read', methods: ['POST'])]
    #[OA\Post(
        path: '/api/notifications/{id}/read',
        summary: 'Отметить уведомление как прочитанное',
        tags: ['Notifications'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID уведомления',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Уведомление отмечено как прочитанное')]
    #[OA\Response(response: 404, description: 'Уведомление не найдено')]
    public function markAsRead(
        PushNotification $notification,
        PushNotificationService $pushService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($notification->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $pushService->markAsRead($notification);

        return $this->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    #[OA\Post(
        path: '/api/notifications/read-all',
        summary: 'Отметить все уведомления как прочитанные',
        tags: ['Notifications'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Все уведомления отмечены как прочитанные',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'count', type: 'integer'),
            ],
        ),
    )]
    public function markAllAsRead(PushNotificationService $pushService): JsonResponse
    {
        $user = $this->getUser();
        $count = $pushService->markAllAsRead($user);

        return $this->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    #[Route('/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/notifications/{id}',
        summary: 'Удалить уведомление',
        tags: ['Notifications'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID уведомления',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Уведомление удалено')]
    public function delete(
        PushNotification $notification,
        PushNotificationService $pushService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($notification->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($notification);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    #[Route('/cleanup', name: 'api_notifications_cleanup', methods: ['POST'])]
    #[OA\Post(
        path: '/api/notifications/cleanup',
        summary: 'Удалить старые уведомления',
        description: 'Удаляет уведомления старше указанного количества дней',
        tags: ['Notifications'],
    )]
    #[OA\Parameter(
        name: 'days',
        in: 'query',
        description: 'Количество дней',
        schema: new OA\Schema(type: 'integer', default: 30),
    )]
    #[OA\Response(
        response: 200,
        description: 'Старые уведомления удалены',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'deleted', type: 'integer'),
            ],
        ),
    )]
    public function cleanup(
        Request $request,
        PushNotificationService $pushService,
    ): JsonResponse {
        $user = $this->getUser();
        $days = (int) $request->query->get('days', 30);

        $deleted = $pushService->cleanupOldNotifications($user, $days);

        return $this->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}
