<?php

namespace App\Controller;

use App\Service\QuickActionsService;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quick')]
#[IsGranted('ROLE_USER')]
class QuickActionsController extends AbstractController
{
    public function __construct(
        private QuickActionsService $quickActionsService,
        private UserRepository $userRepository
    ) {}

    /**
     * Quick create task
     */
    #[Route('/create', name: 'app_quick_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Неверный формат данных'
            ], 400);
        }
        
        $user = $this->getUser();

        $task = $this->quickActionsService->quickCreateTask(
            $data['title'] ?? '',
            $user,
            $data['options'] ?? []
        );

        return $this->json([
            'success' => true,
            'task' => [
                'id' => $task->getId(),
                'title' => $task->getTitle()
            ],
            'message' => 'Задача создана'
        ]);
    }

    /**
     * Quick complete
     */
    #[Route('/complete/{id}', name: 'app_quick_complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $task = $this->quickActionsService->getTask($id);
        
        if (!$task) {
            return $this->json(['success' => false, 'message' => 'Задача не найдена'], 404);
        }
        
        // Check access rights
        if ($task->getUser() !== $this->getUser() && $task->getAssignedUser() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Доступ запрещен'], 403);
        }
        
        $success = $this->quickActionsService->quickComplete($id);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Задача завершена' : 'Ошибка'
        ]);
    }

    /**
     * Quick delete
     */
    #[Route('/delete/{id}', name: 'app_quick_delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        $task = $this->quickActionsService->getTask($id);
        
        if (!$task) {
            return $this->json(['success' => false, 'message' => 'Задача не найдена'], 404);
        }
        
        // Only owner can delete
        if ($task->getUser() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Доступ запрещен'], 403);
        }
        
        $success = $this->quickActionsService->quickDelete($id);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Задача удалена' : 'Ошибка'
        ]);
    }

    /**
     * Quick assign
     */
    #[Route('/assign/{id}', name: 'app_quick_assign', methods: ['POST'])]
    public function assign(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->userRepository->find($data['user_id']);

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        $success = $this->quickActionsService->quickAssign($id, $user);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Задача назначена' : 'Ошибка'
        ]);
    }

    /**
     * Quick change priority
     */
    #[Route('/priority/{id}', name: 'app_quick_priority', methods: ['POST'])]
    public function changePriority(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $success = $this->quickActionsService->quickChangePriority($id, $data['priority']);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Приоритет изменен' : 'Ошибка'
        ]);
    }

    /**
     * Quick change status
     */
    #[Route('/status/{id}', name: 'app_quick_status', methods: ['POST'])]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $success = $this->quickActionsService->quickChangeStatus($id, $data['status']);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Статус изменен' : 'Ошибка'
        ]);
    }

    /**
     * Quick duplicate
     */
    #[Route('/duplicate/{id}', name: 'app_quick_duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        $task = $this->quickActionsService->quickDuplicate($id);

        return $this->json([
            'success' => $task !== null,
            'task' => $task ? ['id' => $task->getId(), 'title' => $task->getTitle()] : null,
            'message' => $task ? 'Задача дублирована' : 'Ошибка'
        ]);
    }

    /**
     * Quick move to today
     */
    #[Route('/move-today/{id}', name: 'app_quick_move_today', methods: ['POST'])]
    public function moveToToday(int $id): JsonResponse
    {
        $success = $this->quickActionsService->quickMoveToToday($id);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Перенесено на сегодня' : 'Ошибка'
        ]);
    }

    /**
     * Quick move to tomorrow
     */
    #[Route('/move-tomorrow/{id}', name: 'app_quick_move_tomorrow', methods: ['POST'])]
    public function moveToTomorrow(int $id): JsonResponse
    {
        $success = $this->quickActionsService->quickMoveToTomorrow($id);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Перенесено на завтра' : 'Ошибка'
        ]);
    }

    /**
     * Quick move to next week
     */
    #[Route('/move-next-week/{id}', name: 'app_quick_move_next_week', methods: ['POST'])]
    public function moveToNextWeek(int $id): JsonResponse
    {
        $success = $this->quickActionsService->quickMoveToNextWeek($id);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Перенесено на следующую неделю' : 'Ошибка'
        ]);
    }

    /**
     * Get available actions
     */
    #[Route('/actions', name: 'app_quick_actions', methods: ['GET'])]
    public function getActions(): JsonResponse
    {
        $actions = $this->quickActionsService->getAvailableActions();

        return $this->json($actions);
    }
}
