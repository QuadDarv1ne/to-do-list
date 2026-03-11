<?php

namespace App\Controller;

use App\Entity\DashboardWidget;
use App\Repository\DashboardWidgetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard/widgets')]
class DashboardWidgetController extends AbstractController
{
    public function __construct(
        private readonly DashboardWidgetRepository $widgetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_api_dashboard_widgets', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        $widgets = $this->widgetRepository->findByUser($user->getId());

        $result = array_map(fn($widget) => [
            'id' => $widget->getId(),
            'type' => $widget->getType(),
            'title' => $widget->getTitle(),
            'configuration' => $widget->getConfiguration(),
            'position' => $widget->getPosition(),
            'size' => $widget->getSize(),
            'is_active' => $widget->isActive(),
        ], $widgets);

        return $this->json($result);
    }

    #[Route('', name: 'app_api_dashboard_widget_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $widget = new DashboardWidget();
        $widget->setType($data['type'] ?? 'default');
        $widget->setTitle($data['title'] ?? 'Widget');
        $widget->setConfiguration($data['configuration'] ?? []);
        $widget->setSize($data['size'] ?? 'col-md-6');
        $widget->setPosition($this->widgetRepository->findMaxPosition($user->getId()) + 1);
        $widget->setUser($user);

        $this->entityManager->persist($widget);
        $this->entityManager->flush();

        return $this->json([
            'id' => $widget->getId(),
            'position' => $widget->getPosition(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_api_dashboard_widget_update', methods: ['PUT'])]
    public function update(DashboardWidget $widget, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $widget);

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $widget->setTitle($data['title']);
        }
        if (isset($data['configuration'])) {
            $widget->setConfiguration($data['configuration']);
        }
        if (isset($data['size'])) {
            $widget->setSize($data['size']);
        }
        if (isset($data['is_active'])) {
            $widget->setIsActive($data['is_active']);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/position', name: 'app_api_dashboard_widget_position', methods: ['PUT'])]
    public function updatePosition(DashboardWidget $widget, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $widget);

        $data = json_decode($request->getContent(), true);
        $newPosition = $data['position'] ?? 0;

        $widget->setPosition($newPosition);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}', name: 'app_api_dashboard_widget_delete', methods: ['DELETE'])]
    public function delete(DashboardWidget $widget): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $widget);

        $this->entityManager->remove($widget);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
