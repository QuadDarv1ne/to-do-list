<?php

namespace App\Controller;

use App\Entity\Task;
use App\Service\TaskHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-history')]
#[IsGranted('ROLE_USER')]
class TaskHistoryController extends AbstractController
{
    public function __construct(
        private TaskHistoryService $historyService
    ) {
    }

    #[Route('/task/{id}', name: 'app_task_history', methods: ['GET'])]
    public function taskHistory(Task $task): Response
    {
        $history = $this->historyService->getTaskHistory($task);

        return $this->render('task_history/index.html.twig', [
            'task' => $task,
            'history' => $history,
            'historyService' => $this->historyService
        ]);
    }

    #[Route('/api/task/{id}', name: 'app_api_task_history', methods: ['GET'])]
    public function apiTaskHistory(Task $task): JsonResponse
    {
        $history = $this->historyService->getTaskHistory($task);

        $data = array_map(function($item) {
            return [
                'id' => $item->getId(),
                'action' => $item->getAction(),
                'field' => $item->getField(),
                'oldValue' => $item->getOldValue(),
                'newValue' => $item->getNewValue(),
                'user' => [
                    'id' => $item->getUser()->getId(),
                    'name' => $item->getUser()->getFullName()
                ],
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'description' => $this->historyService->getChangeDescription($item)
            ];
        }, $history);

        return $this->json($data);
    }

    #[Route('/recent', name: 'app_recent_activity', methods: ['GET'])]
    public function recentActivity(): Response
    {
        $activity = $this->historyService->getRecentActivity(50);

        return $this->render('task_history/recent.html.twig', [
            'activity' => $activity,
            'historyService' => $this->historyService
        ]);
    }

    #[Route('/user-activity', name: 'app_user_activity', methods: ['GET'])]
    public function userActivity(): Response
    {
        $user = $this->getUser();
        $activity = $this->historyService->getUserActivity($user, 100);

        return $this->render('task_history/user_activity.html.twig', [
            'activity' => $activity,
            'historyService' => $this->historyService
        ]);
    }

    #[Route('/stats', name: 'app_activity_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function activityStats(): Response
    {
        $from = new \DateTime('-30 days');
        $to = new \DateTime();
        
        $stats = $this->historyService->getActivityStats($from, $to);

        return $this->render('task_history/stats.html.twig', [
            'stats' => $stats,
            'from' => $from,
            'to' => $to
        ]);
    }
}
