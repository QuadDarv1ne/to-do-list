<?php

namespace App\Controller;

use App\Service\TaskBatchOperationService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/batch')]
#[IsGranted('ROLE_USER')]
class BatchOperationController extends AbstractController
{
    public function __construct(
        private TaskBatchOperationService $batchService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Batch update status
     */
    #[Route('/status', name: 'app_batch_status', methods: ['POST'])]
    public function updateStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $status = $data['status'] ?? '';

        if (empty($taskIds) || empty($status)) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $count = $this->batchService->batchUpdateStatus($taskIds, $status);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => "Обновлено задач: {$count}"
        ]);
    }

    /**
     * Batch update priority
     */
    #[Route('/priority', name: 'app_batch_priority', methods: ['POST'])]
    public function updatePriority(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $priority = $data['priority'] ?? '';

        if (empty($taskIds) || empty($priority)) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $count = $this->batchService->batchUpdatePriority($taskIds, $priority);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => "Обновлено задач: {$count}"
        ]);
    }

    /**
     * Batch assign
     */
    #[Route('/assign', name: 'app_batch_assign', methods: ['POST'])]
    public function assign(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $userId = $data['user_id'] ?? null;

        if (empty($taskIds) || !$userId) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Пользователь не найден'], 404);
        }

        $count = $this->batchService->batchAssign($taskIds, $user);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => "Назначено задач: {$count}"
        ]);
    }

    /**
     * Batch delete
     */
    #[Route('/delete', name: 'app_batch_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];

        if (empty($taskIds)) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $count = $this->batchService->batchDelete($taskIds);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => "Удалено задач: {$count}"
        ]);
    }

    /**
     * Batch duplicate
     */
    #[Route('/duplicate', name: 'app_batch_duplicate', methods: ['POST'])]
    public function duplicate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];

        if (empty($taskIds)) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $user = $this->getUser();
        $duplicated = $this->batchService->batchDuplicate($taskIds, $user);

        return $this->json([
            'success' => true,
            'count' => count($duplicated),
            'message' => "Создано копий: " . count($duplicated)
        ]);
    }

    /**
     * Batch move to category
     */
    #[Route('/category', name: 'app_batch_category', methods: ['POST'])]
    public function moveToCategory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $categoryId = $data['category_id'] ?? null;

        if (empty($taskIds)) {
            return $this->json(['error' => 'Неверные параметры'], 400);
        }

        $category = $categoryId ? $this->entityManager->getRepository('App\Entity\Category')->find($categoryId) : null;
        $count = $this->batchService->batchMoveToCategory($taskIds, $category);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => "Перемещено задач: {$count}"
        ]);
    }
}
