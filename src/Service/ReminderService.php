<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReminderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealTimeNotificationService $notificationService,
    ) {
    }

    /**
     * Create reminder for task
     */
    public function createReminder(
        Task $task,
        User $user,
        \DateTime $reminderTime,
        string $type = 'deadline',
        ?string $customMessage = null,
    ): TaskNotification {
        $notification = new TaskNotification();
        $notification->setTask($task);
        $notification->setUser($user);
        $notification->setScheduledFor($reminderTime);
        $notification->setType($type);
        $notification->setIsSent(false);

        $message = $customMessage ?? $this->generateReminderMessage($task, $type);
        $notification->setMessage($message);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Create smart reminders based on task deadline
     */
    public function createSmartReminders(Task $task, User $user): array
    {
        if (!$task->getDeadline()) {
            return [];
        }

        $deadline = $task->getDeadline();
        $now = new \DateTime();
        $reminders = [];

        // Calculate time until deadline
        $hoursUntilDeadline = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;

        // 1 week before (if deadline is more than 1 week away)
        if ($hoursUntilDeadline > 168) {
            $reminderTime = (clone $deadline)->modify('-7 days');
            $reminders[] = $this->createReminder($task, $user, $reminderTime, 'week_before');
        }

        // 1 day before
        if ($hoursUntilDeadline > 24) {
            $reminderTime = (clone $deadline)->modify('-1 day');
            $reminders[] = $this->createReminder($task, $user, $reminderTime, 'day_before');
        }

        // 1 hour before
        if ($hoursUntilDeadline > 1) {
            $reminderTime = (clone $deadline)->modify('-1 hour');
            $reminders[] = $this->createReminder($task, $user, $reminderTime, 'hour_before');
        }

        return $reminders;
    }

    /**
     * Send due reminders
     */
    public function sendDueReminders(): int
    {
        $now = new \DateTime();

        $dueReminders = $this->entityManager->getRepository(TaskNotification::class)
            ->createQueryBuilder('tn')
            ->where('tn.isSent = :false')
            ->andWhere('tn.scheduledFor <= :now')
            ->setParameter('false', false)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                $sentCount++;
            } catch (\Exception $e) {
                // Log error but continue
                error_log('Failed to send reminder: ' . $e->getMessage());
            }
        }

        return $sentCount;
    }

    /**
     * Send individual reminder
     */
    private function sendReminder(TaskNotification $reminder): void
    {
        $task = $reminder->getTask();
        $user = $reminder->getUser();

        // Send notification
        $this->notificationService->sendNotification(
            $user,
            'Напоминание о задаче',
            $reminder->getMessage(),
            'reminder',
            [
                'task_id' => $task->getId(),
                'task_title' => $task->getTitle(),
                'reminder_type' => $reminder->getType(),
            ],
        );

        // Mark as sent
        $reminder->setIsSent(true);
        $reminder->setSentAt(new \DateTime());

        $this->entityManager->flush();
    }

    /**
     * Get upcoming reminders for user
     */
    public function getUpcomingReminders(User $user, int $days = 7): array
    {
        $now = new \DateTime();
        $future = (clone $now)->modify("+{$days} days");

        return $this->entityManager->getRepository(TaskNotification::class)
            ->createQueryBuilder('tn')
            ->where('tn.user = :user')
            ->andWhere('tn.isSent = :false')
            ->andWhere('tn.scheduledFor BETWEEN :now AND :future')
            ->orderBy('tn.scheduledFor', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('false', false)
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cancel reminder
     */
    public function cancelReminder(TaskNotification $reminder): void
    {
        $this->entityManager->remove($reminder);
        $this->entityManager->flush();
    }

    /**
     * Cancel all reminders for task
     */
    public function cancelTaskReminders(Task $task): int
    {
        $reminders = $this->entityManager->getRepository(TaskNotification::class)
            ->findBy(['task' => $task, 'isSent' => false]);

        $count = \count($reminders);

        foreach ($reminders as $reminder) {
            $this->entityManager->remove($reminder);
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Reschedule reminder
     */
    public function rescheduleReminder(TaskNotification $reminder, \DateTime $newTime): void
    {
        $reminder->setScheduledFor($newTime);
        $this->entityManager->flush();
    }

    /**
     * Get reminder statistics
     */
    public function getReminderStatistics(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Total reminders
        $total = $qb->select('COUNT(tn.id)')
            ->from(TaskNotification::class, 'tn')
            ->where('tn.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Sent reminders
        $sent = $this->entityManager->createQueryBuilder()
            ->select('COUNT(tn.id)')
            ->from(TaskNotification::class, 'tn')
            ->where('tn.user = :user')
            ->andWhere('tn.isSent = :true')
            ->setParameter('user', $user)
            ->setParameter('true', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Pending reminders
        $pending = $this->entityManager->createQueryBuilder()
            ->select('COUNT(tn.id)')
            ->from(TaskNotification::class, 'tn')
            ->where('tn.user = :user')
            ->andWhere('tn.isSent = :false')
            ->setParameter('user', $user)
            ->setParameter('false', false)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int)$total,
            'sent' => (int)$sent,
            'pending' => (int)$pending,
        ];
    }

    /**
     * Generate reminder message based on type
     */
    private function generateReminderMessage(Task $task, string $type): string
    {
        $title = $task->getTitle();
        $deadline = $task->getDeadline()?->format('d.m.Y H:i');

        return match($type) {
            'week_before' => "Напоминание: до дедлайна задачи \"{$title}\" осталась неделя ({$deadline})",
            'day_before' => "Напоминание: до дедлайна задачи \"{$title}\" остался день ({$deadline})",
            'hour_before' => "Срочно: до дедлайна задачи \"{$title}\" остался час ({$deadline})",
            'deadline' => "Дедлайн задачи \"{$title}\" наступил ({$deadline})",
            'overdue' => "Задача \"{$title}\" просрочена! Дедлайн был {$deadline}",
            default => "Напоминание о задаче: {$title}"
        };
    }

    /**
     * Create recurring reminders
     */
    public function createRecurringReminders(
        Task $task,
        User $user,
        string $frequency, // daily, weekly, monthly
        \DateTime $startDate,
        \DateTime $endDate,
    ): array {
        $reminders = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $reminders[] = $this->createReminder(
                $task,
                $user,
                clone $currentDate,
                'recurring',
            );

            switch ($frequency) {
                case 'daily':
                    $currentDate->modify('+1 day');

                    break;
                case 'weekly':
                    $currentDate->modify('+1 week');

                    break;
                case 'monthly':
                    $currentDate->modify('+1 month');

                    break;
            }
        }

        return $reminders;
    }
}
