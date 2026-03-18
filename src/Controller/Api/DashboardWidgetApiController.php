<?php

namespace App\Controller\Api;

use App\Entity\DashboardWidget;
use App\Service\DashboardWidgetService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API для управления виджетами дашборда
 */
#[Route('/api/dashboard/widgets')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Dashboard Widgets')]
class DashboardWidgetApiController extends AbstractController
{
    #[Route('', name: 'api_dashboard_widgets_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/dashboard/widgets',
        summary: 'Получить виджеты дашборда',
        description: 'Возвращает список виджетов пользователя с данными',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Список виджетов',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'widgets', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'type', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'width', type: 'integer'),
                        new OA\Property(property: 'position', type: 'integer'),
                    ],
                )),
            ],
        ),
    )]
    public function list(DashboardWidgetService $widgetService): JsonResponse
    {
        $user = $this->getUser();
        $widgets = $widgetService->getUserWidgets($user);

        $widgetData = [];
        foreach ($widgets as $widget) {
            $widgetData[] = [
                'id' => $widget->getId(),
                'type' => $widget->getType(),
                'title' => $widget->getTitle(),
                'width' => $widget->getWidth(),
                'position' => $widget->getPosition(),
                'configuration' => $widget->getConfiguration(),
                'data' => $widgetService->getWidgetData($widget, $user),
            ];
        }

        return $this->json([
            'success' => true,
            'widgets' => $widgetData,
        ]);
    }

    #[Route('/{id}/data', name: 'api_dashboard_widget_data', methods: ['GET'])]
    #[OA\Get(
        path: '/api/dashboard/widgets/{id}/data',
        summary: 'Получить данные виджета',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID виджета',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Данные виджета')]
    #[OA\Response(response: 404, description: 'Виджет не найден')]
    public function widgetData(
        DashboardWidget $widget,
        DashboardWidgetService $widgetService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($widget->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'success' => true,
            'data' => $widgetService->getWidgetData($widget, $user),
        ]);
    }

    #[Route('/{id}/position', name: 'api_dashboard_widget_position', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/dashboard/widgets/{id}/position',
        summary: 'Обновить позицию виджета',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'position', type: 'integer'),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Позиция обновлена')]
    public function updatePosition(
        Request $request,
        DashboardWidget $widget,
        DashboardWidgetService $widgetService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($widget->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);
        $position = $data['position'] ?? 0;

        $widgetService->updateWidgetPosition($widget, $position);

        return $this->json([
            'success' => true,
            'message' => 'Position updated',
        ]);
    }

    #[Route('/{id}/configure', name: 'api_dashboard_widget_configure', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/dashboard/widgets/{id}/configure',
        summary: 'Обновить конфигурацию виджета',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'configuration', type: 'object'),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Конфигурация обновлена')]
    public function configure(
        Request $request,
        DashboardWidget $widget,
        DashboardWidgetService $widgetService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($widget->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);
        $configuration = $data['configuration'] ?? [];

        $widgetService->updateWidgetConfiguration($widget, $configuration);

        return $this->json([
            'success' => true,
            'message' => 'Configuration updated',
        ]);
    }

    #[Route('/{id}', name: 'api_dashboard_widget_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/dashboard/widgets/{id}',
        summary: 'Удалить виджет',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Виджет удалён')]
    public function delete(
        DashboardWidget $widget,
        DashboardWidgetService $widgetService,
    ): JsonResponse {
        $user = $this->getUser();

        if ($widget->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $widgetService->removeWidget($widget);

        return $this->json([
            'success' => true,
            'message' => 'Widget removed',
        ]);
    }

    #[Route('/reset', name: 'api_dashboard_widgets_reset', methods: ['POST'])]
    #[OA\Post(
        path: '/api/dashboard/widgets/reset',
        summary: 'Сбросить виджеты к настройкам по умолчанию',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Response(response: 200, description: 'Виджеты сброшены')]
    public function reset(DashboardWidgetService $widgetService): JsonResponse
    {
        $user = $this->getUser();
        $widgetService->resetToDefaults($user);

        return $this->json([
            'success' => true,
            'message' => 'Widgets reset to defaults',
        ]);
    }

    #[Route('/available-types', name: 'api_dashboard_widget_types', methods: ['GET'])]
    #[OA\Get(
        path: '/api/dashboard/widgets/available-types',
        summary: 'Получить доступные типы виджетов',
        tags: ['Dashboard Widgets'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Список доступных типов',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'types', type: 'object'),
            ],
        ),
    )]
    public function availableTypes(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'types' => DashboardWidget::getAvailableTypes(),
            'sizes' => DashboardWidget::getSizes(),
        ]);
    }
}
