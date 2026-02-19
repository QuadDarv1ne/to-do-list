<?php

namespace App\Controller;

use App\Service\CalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    public function __construct(
        private CalendarService $calendarService,
    ) {
    }

    /**
     * Calendar page
     */
    #[Route('', name: 'app_calendar', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('calendar/index.html.twig');
    }

    /**
     * Get calendar events (FullCalendar format)
     */
    #[Route('/events', name: 'app_calendar_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $start = new \DateTime($request->query->get('start', 'now'));
        $end = new \DateTime($request->query->get('end', '+1 month'));

        $events = $this->calendarService->getCalendarEvents($user, $start, $end);

        return $this->json($events);
    }

    /**
     * Month view
     */
    #[Route('/month/{year}/{month}', name: 'app_calendar_month', methods: ['GET'])]
    public function month(int $year, int $month): Response
    {
        $user = $this->getUser();
        $data = $this->calendarService->getMonthView($user, $year, $month);

        return $this->render('calendar/month.html.twig', $data);
    }

    /**
     * Week view
     */
    #[Route('/week/{date}', name: 'app_calendar_week', methods: ['GET'])]
    public function week(string $date): Response
    {
        $user = $this->getUser();
        $weekStart = new \DateTime($date);
        $data = $this->calendarService->getWeekView($user, $weekStart);

        return $this->render('calendar/week.html.twig', $data);
    }

    /**
     * Day view
     */
    #[Route('/day/{date}', name: 'app_calendar_day', methods: ['GET'])]
    public function day(string $date): Response
    {
        $user = $this->getUser();
        $dayDate = new \DateTime($date);
        $data = $this->calendarService->getDayView($user, $dayDate);

        return $this->render('calendar/day.html.twig', $data);
    }

    /**
     * Upcoming deadlines
     */
    #[Route('/upcoming', name: 'app_calendar_upcoming', methods: ['GET'])]
    public function upcoming(Request $request): Response
    {
        $user = $this->getUser();
        $days = (int)$request->query->get('days', 7);

        $tasks = $this->calendarService->getUpcomingDeadlines($user, $days);

        return $this->render('calendar/upcoming.html.twig', [
            'tasks' => $tasks,
            'days' => $days,
        ]);
    }

    /**
     * Update task date via drag and drop
     */
    #[Route('/update-date', name: 'app_calendar_update_date', methods: ['POST'])]
    public function updateDate(Request $request): JsonResponse
    {
        $taskId = $request->request->get('taskId');
        $newDate = $request->request->get('newDate');

        if (!$taskId || !$newDate) {
            return $this->json(['success' => false, 'message' => 'Недостаточно данных'], 400);
        }

        try {
            $task = $this->calendarService->updateTaskDate($taskId, new \DateTime($newDate));

            return $this->json([
                'success' => true,
                'message' => 'Дата задачи обновлена',
                'task' => [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'newDate' => $task->getDeadline()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Export to iCal
     */
    #[Route('/export.ics', name: 'app_calendar_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $user = $this->getUser();

        $start = new \DateTime($request->query->get('start', 'now'));
        $end = new \DateTime($request->query->get('end', '+3 months'));

        $ical = $this->calendarService->exportToICal($user, $start, $end);

        $response = new Response($ical);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar.ics"');

        return $response;
    }
}
