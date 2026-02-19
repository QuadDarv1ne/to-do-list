<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TaskBatchOperationService;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Batch update status
     */
    #[Route('/status', name: 'app_batch_status', methods: ['POST'])]
    public function updateStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $status = $data['status'] ?? '';

        $count = $this->batchService->batchUpdateStatus($taskIds, $status);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Обновлено %d задач', $count),
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

        $count = $this->batchService->batchUpdatePriority($taskIds, $priority);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Обновлено %d задач', $count),
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

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        $count = $this->batchService->batchAssign($taskIds, $user);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Назначено %d задач', $count),
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

        $count = $this->batchService->batchDelete($taskIds);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Удалено %d задач', $count),
        ]);
    }

    /**
     * Batch complete
     */
    #[Route('/complete', name: 'app_batch_complete', methods: ['POST'])]
    public function complete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];

        $count = $this->batchService->batchComplete($taskIds);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Завершено %d задач', $count),
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

        $category = $this->entityManager->getRepository('App\\Entity\\Category')->find($categoryId);
        if (!$category) {
            return $this->json(['success' => false, 'message' => 'Категория не найдена'], 404);
        }

        $count = $this->batchService->batchMoveToCategory($taskIds, $category);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Перемещено %d задач', $count),
        ]);
    }

    /**
     * Batch update deadline
     */
    #[Route('/deadline', name: 'app_batch_deadline', methods: ['POST'])]
    public function updateDeadline(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['task_ids'] ?? [];
        $deadline = new \DateTime($data['deadline'] ?? 'now');

        $count = $this->batchService->batchUpdateDeadline($taskIds, $deadline);

        return $this->json([
            'success' => true,
            'count' => $count,
            'message' => \sprintf('Обновлено %d задач', $count),
        ]);
    }
}
