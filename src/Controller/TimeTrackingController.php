<?php

namespace App\Controller;

use App\Entity\Task;
use App\Service\TimeTrackingService;
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
    public function __construct(
        private TimeTrackingService $timeTrackingService,
    ) {
    }

    /**
     * Time tracking dashboard
     */
    #[Route('', name: 'app_time_tracking', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $activeSession = $this->timeTrackingService->getActiveSession();

        $from = new \DateTime('-7 days');
        $to = new \DateTime();
        $stats = $this->timeTrackingService->getStatistics($user, $from, $to);

        return $this->render('time_tracking/index.html.twig', [
            'active_session' => $activeSession,
            'stats' => $stats,
        ]);
    }

    /**
     * Start tracking
     */
    #[Route('/start/{id}', name: 'app_time_tracking_start', methods: ['POST'])]
    public function start(Task $task): JsonResponse
    {
        $user = $this->getUser();
        $session = $this->timeTrackingService->startTracking($task, $user);

        return $this->json([
            'success' => true,
            'session' => $session,
            'message' => 'Отслеживание времени начато',
        ]);
    }

    /**
     * Stop tracking
     */
    #[Route('/stop', name: 'app_time_tracking_stop', methods: ['POST'])]
    public function stop(): JsonResponse
    {
        $session = $this->timeTrackingService->stopTracking();

        if (!$session) {
            return $this->json([
                'success' => false,
                'message' => 'Нет активной сессии',
            ], 400);
        }

        return $this->json([
            'success' => true,
            'session' => $session,
            'duration_formatted' => $this->timeTrackingService->formatDuration($session['duration']),
            'message' => 'Отслеживание времени остановлено',
        ]);
    }

    /**
     * Get active session
     */
    #[Route('/active', name: 'app_time_tracking_active', methods: ['GET'])]
    public function active(): JsonResponse
    {
        $session = $this->timeTrackingService->getActiveSession();

        return $this->json([
            'active' => $session !== null,
            'session' => $session,
        ]);
    }

    /**
     * Time tracking report
     */
    #[Route('/report', name: 'app_time_tracking_report', methods: ['GET'])]
    public function report(Request $request): Response
    {
        $user = $this->getUser();

        $from = new \DateTime($request->query->get('from', '-30 days'));
        $to = new \DateTime($request->query->get('to', 'now'));

        $report = $this->timeTrackingService->getReport($user, $from, $to);

        return $this->render('time_tracking/report.html.twig', [
            'report' => $report,
        ]);
    }
}
