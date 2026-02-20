<?php

namespace App\Controller;

use App\Controller\Traits\FlashMessageTrait;
use App\Entity\Task;
use App\Entity\TaskTimeTracking;
use App\Service\TimeTrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/time-tracking')]
#[IsGranted('ROLE_USER')]
class TimeTrackingController extends AbstractController
{
    use FlashMessageTrait;

    #[Route('/', name: 'app_time_tracking_index', methods: ['GET'])]
    public function index(TimeTrackingService $timeTrackingService, Request $request): Response
    {
        $user = $this->getUser();
        
        // Get date range from query params
        $from = new \DateTime($request->query->get('from', 'monday this week'));
        $to = new \DateTime($request->query->get('to', 'sunday this week'));

        $stats = $timeTrackingService->getStatistics($user, $from, $to);
        $todaySummary = $timeTrackingService->getTodaySummary($user);
        $weeklySummary = $timeTrackingService->getWeeklySummary($user);
        $activeSession = $timeTrackingService->getActiveSession($user);
        $recentSessions = $timeTrackingService->getRecentSessions($user, 10);

        return $this->render('time_tracking/index.html.twig', [
            'stats' => $stats,
            'todaySummary' => $todaySummary,
            'weeklySummary' => $weeklySummary,
            'activeSession' => $activeSession,
            'recentSessions' => $recentSessions,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    #[Route('/start/{taskId}', name: 'app_time_tracking_start', methods: ['POST'])]
    public function start(
        Task $task,
        TimeTrackingService $timeTrackingService,
        Request $request,
    ): Response {
        $user = $this->getUser();
        $description = $request->getPayload()->getString('description');

        // Check if task belongs to user
        if ($task->getUser() !== $user && $task->getAssignedUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $tracking = $timeTrackingService->startTracking($task, $user, $description);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'tracking_id' => $tracking->getId(),
                'started_at' => $tracking->getStartedAt()->format('c'),
                'message' => 'Учёт времени начат',
            ]);
        }

        $this->flashSuccess('Учёт времени начат для задачи "' . $task->getTitle() . '"');

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/stop/{trackingId}', name: 'app_time_tracking_stop', methods: ['POST'])]
    public function stop(
        int $trackingId,
        TimeTrackingService $timeTrackingService,
        Request $request,
    ): Response {
        $tracking = $timeTrackingService->stopTrackingById($trackingId);

        if ($tracking === null) {
            throw $this->createNotFoundException('Сессия не найдена');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'duration_seconds' => $tracking->getDurationSeconds(),
                'duration_formatted' => $tracking->getFormattedDuration(),
                'message' => 'Учёт времени остановлен',
            ]);
        }

        $this->flashSuccess('Учёт времени остановлен. Проведено: ' . $tracking->getFormattedDuration());

        return $this->redirectToRoute('app_task_show', ['id' => $tracking->getTask()->getId()]);
    }

    #[Route('/toggle/{taskId}', name: 'app_time_tracking_toggle', methods: ['POST'])]
    public function toggle(
        Task $task,
        TimeTrackingService $timeTrackingService,
        Request $request,
    ): Response {
        $user = $this->getUser();

        // Check if task belongs to user
        if ($task->getUser() !== $user && $task->getAssignedUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $result = $timeTrackingService->toggleTracking($task, $user);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'action' => $result['action'],
                'tracking_id' => $result['tracking']->getId() ?? null,
                'duration' => $result['duration'] ?? null,
                'duration_formatted' => $result['duration_formatted'] ?? null,
            ]);
        }

        if ($result['action'] === 'started') {
            $this->flashSuccess('Учёт времени начат');
        } else {
            $this->flashSuccess('Учёт времени остановлен. Проведено: ' . $result['duration_formatted']);
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/session/{id}', name: 'app_time_tracking_session_show', methods: ['GET'])]
    public function showSession(TaskTimeTracking $tracking): Response
    {
        $this->denyAccessUnlessGranted('view', $tracking);

        return $this->render('time_tracking/session.html.twig', [
            'tracking' => $tracking,
        ]);
    }

    #[Route('/session/{id}/edit', name: 'app_time_tracking_session_edit', methods: ['GET', 'POST'])]
    public function editSession(
        Request $request,
        TaskTimeTracking $tracking,
        TimeTrackingService $timeTrackingService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $tracking);

        if ($request->isMethod('POST')) {
            $description = $request->getPayload()->getString('description');
            $timeTrackingService->updateSessionDescription($tracking, $description);

            $this->flashSuccess('Описание обновлено');

            return $this->redirectToRoute('app_time_tracking_session_show', ['id' => $tracking->getId()]);
        }

        return $this->render('time_tracking/edit.html.twig', [
            'tracking' => $tracking,
        ]);
    }

    #[Route('/session/{id}', name: 'app_time_tracking_session_delete', methods: ['POST'])]
    public function deleteSession(
        Request $request,
        TaskTimeTracking $tracking,
        TimeTrackingService $timeTrackingService,
    ): Response {
        $this->denyAccessUnlessGranted('delete', $tracking);

        if ($this->isCsrfTokenValid('delete' . $tracking->getId(), $request->getPayload()->get('_token'))) {
            $timeTrackingService->deleteSession($tracking);
            $this->flashDanger('Запись удалена');
        }

        return $this->redirectToRoute('app_time_tracking_index');
    }

    #[Route('/report', name: 'app_time_tracking_report', methods: ['GET'])]
    public function report(TimeTrackingService $timeTrackingService, Request $request): Response
    {
        $user = $this->getUser();
        
        $from = new \DateTime($request->query->get('from', 'first day of this month'));
        $to = new \DateTime($request->query->get('to', 'last day of this month'));

        $report = $timeTrackingService->getReport($user, $from, $to);

        return $this->render('time_tracking/report.html.twig', [
            'report' => $report,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    #[Route('/api/active', name: 'app_time_tracking_api_active', methods: ['GET'])]
    public function getActive(TimeTrackingService $timeTrackingService): JsonResponse
    {
        $user = $this->getUser();
        $activeSession = $timeTrackingService->getActiveSession($user);

        if ($activeSession === null) {
            return new JsonResponse(['active' => false]);
        }

        return new JsonResponse([
            'active' => true,
            'tracking_id' => $activeSession->getId(),
            'task_id' => $activeSession->getTask()->getId(),
            'task_title' => $activeSession->getTask()->getTitle(),
            'started_at' => $activeSession->getStartedAt()->format('c'),
            'elapsed_seconds' => $activeSession->calculateDuration(),
        ]);
    }

    #[Route('/api/stop-all', name: 'app_time_tracking_api_stop_all', methods: ['POST'])]
    public function stopAll(TimeTrackingService $timeTrackingService): JsonResponse
    {
        $user = $this->getUser();
        $count = $timeTrackingService->stopAllActiveSessions($user);

        return new JsonResponse([
            'success' => true,
            'stopped_count' => $count,
        ]);
    }
}
