<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class CalendarService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * Get calendar events for user
     */
    public function getCalendarEvents(User $user, \DateTime $start, \DateTime $end): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $events = [];
        foreach ($tasks as $task) {
            $events[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'start' => $task->getDeadline()->format('Y-m-d'),
                'end' => $task->getDeadline()->format('Y-m-d'),
                'backgroundColor' => $this->getEventColor($task->getPriority(), $task->getStatus()),
                'borderColor' => $this->getEventColor($task->getPriority(), $task->getStatus()),
                'url' => '/tasks/' . $task->getId(),
                'extendedProps' => [
                    'priority' => $task->getPriority(),
                    'status' => $task->getStatus(),
                    'category' => $task->getCategory()?->getName(),
                ],
            ];
        }

        return $events;
    }

    /**
     * Get month view data
     */
    public function getMonthView(User $user, int $year, int $month): array
    {
        $start = new \DateTime("{$year}-{$month}-01");
        $end = (clone $start)->modify('last day of this month');

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by day
        $calendar = [];
        foreach ($tasks as $task) {
            $day = $task->getDeadline()->format('Y-m-d');
            if (!isset($calendar[$day])) {
                $calendar[$day] = [];
            }
            $calendar[$day][] = $task;
        }

        return [
            'year' => $year,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'tasks' => $calendar,
            'total_tasks' => \count($tasks),
        ];
    }

    /**
     * Get week view data
     */
    public function getWeekView(User $user, \DateTime $weekStart): array
    {
        $weekEnd = (clone $weekStart)->modify('+6 days');

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->orderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by day
        $week = [];
        $current = clone $weekStart;
        for ($i = 0; $i < 7; $i++) {
            $day = $current->format('Y-m-d');
            $week[$day] = [
                'date' => clone $current,
                'tasks' => [],
            ];
            $current->modify('+1 day');
        }

        foreach ($tasks as $task) {
            $day = $task->getDeadline()->format('Y-m-d');
            if (isset($week[$day])) {
                $week[$day]['tasks'][] = $task;
            }
        }

        return [
            'start' => $weekStart,
            'end' => $weekEnd,
            'days' => $week,
            'total_tasks' => \count($tasks),
        ];
    }

    /**
     * Get day view data
     */
    public function getDayView(User $user, \DateTime $date): array
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        return [
            'date' => $date,
            'tasks' => $tasks,
            'total_tasks' => \count($tasks),
            'by_priority' => $this->groupByPriority($tasks),
            'by_status' => $this->groupByStatus($tasks),
        ];
    }

    /**
     * Get upcoming deadlines
     */
    public function getUpcomingDeadlines(User $user, int $days = 7): array
    {
        $now = new \DateTime();
        $future = (clone $now)->modify("+{$days} days");

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :now AND :future')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->setParameter('completed', 'completed')
            ->orderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        return $tasks;
    }

    /**
     * Get event color based on priority and status
     */
    private function getEventColor(string $priority, string $status): string
    {
        if ($status === 'completed') {
            return '#28a745'; // Green
        }

        return match($priority) {
            'urgent' => '#dc3545',  // Red
            'high' => '#fd7e14',    // Orange
            'medium' => '#ffc107',  // Yellow
            'low' => '#6c757d',     // Gray
            default => '#007bff'    // Blue
        };
    }

    /**
     * Group tasks by priority
     */
    private function groupByPriority(array $tasks): array
    {
        $grouped = [
            'urgent' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        foreach ($tasks as $task) {
            $priority = $task->getPriority();
            if (isset($grouped[$priority])) {
                $grouped[$priority][] = $task;
            }
        }

        return $grouped;
    }

    /**
     * Group tasks by status
     */
    private function groupByStatus(array $tasks): array
    {
        $grouped = [
            'pending' => [],
            'in_progress' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        foreach ($tasks as $task) {
            $status = $task->getStatus();
            if (isset($grouped[$status])) {
                $grouped[$status][] = $task;
            }
        }

        return $grouped;
    }

    /**
     * Update task date
     */
    public function updateTaskDate(int $taskId, \DateTime $newDate): mixed
    {
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            throw new \Exception('Задача не найдена');
        }

        $task->setDeadline($newDate);
        $this->taskRepository->save($task, true);

        return $task;
    }

    /**
     * Export calendar to iCal format
     */
    public function exportToICal(User $user, \DateTime $start, \DateTime $end): string
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//CRM System//Tasks Calendar//RU\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Задачи CRM\r\n";
        $ical .= "X-WR-TIMEZONE:Europe/Moscow\r\n";

        foreach ($tasks as $task) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= 'UID:task-' . $task->getId() . "@crm-system.local\r\n";
            $ical .= 'DTSTAMP:' . (new \DateTime())->format('Ymd\\THis\\Z') . "\r\n";
            $ical .= 'DTSTART:' . $task->getDeadline()->format('Ymd') . "\r\n";
            $ical .= 'DTEND:' . $task->getDeadline()->format('Ymd') . "\r\n";
            $ical .= 'SUMMARY:' . $this->escapeICalText($task->getTitle()) . "\r\n";

            if ($task->getDescription()) {
                $ical .= 'DESCRIPTION:' . $this->escapeICalText($task->getDescription()) . "\r\n";
            }

            $ical .= 'PRIORITY:' . $this->getICalPriority($task->getPriority()) . "\r\n";
            $ical .= 'STATUS:' . $this->getICalStatus($task->getStatus()) . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Escape text for iCal format
     */
    private function escapeICalText(string $text): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';'], ['\\n', '\\n', '\\n', '\\,', '\\;'], $text);
    }

    /**
     * Get iCal priority
     */
    private function getICalPriority(string $priority): int
    {
        return match($priority) {
            'urgent' => 1,
            'high' => 3,
            'medium' => 5,
            'low' => 7,
            default => 5
        };
    }

    /**
     * Get iCal status
     */
    private function getICalStatus(string $status): string
    {
        return match($status) {
            'completed' => 'COMPLETED',
            'in_progress' => 'IN-PROCESS',
            'cancelled' => 'CANCELLED',
            default => 'NEEDS-ACTION'
        };
    }
}
