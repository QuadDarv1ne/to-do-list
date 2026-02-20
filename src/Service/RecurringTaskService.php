<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskRecurrence;
use App\Entity\User;
use App\Repository\TaskRecurrenceRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RecurringTaskService
{
    public function __construct(
        private TaskRecurrenceRepository $recurrenceRepository,
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create recurring task rule
     */
    public function createRecurring(
        Task $template,
        User $user,
        string $frequency,
        int $interval = 1,
        ?\DateTimeImmutable $endDate = null,
        ?array $daysOfWeek = null,
        ?array $daysOfMonth = null,
    ): TaskRecurrence {
        $recurrence = new TaskRecurrence();
        $recurrence->setTask($template);
        $recurrence->setUser($user);
        $recurrence->setFrequency($frequency);
        $recurrence->setInterval($interval);
        $recurrence->setEndDate($endDate);
        
        if ($daysOfWeek !== null) {
            $recurrence->setDaysOfWeekFromArray($daysOfWeek);
        }
        
        if ($daysOfMonth !== null) {
            $recurrence->setDaysOfMonthFromArray($daysOfMonth);
        }

        $this->entityManager->persist($recurrence);
        $this->entityManager->flush();

        $this->logger->info('Recurring task created', [
            'recurrence_id' => $recurrence->getId(),
            'task_id' => $template->getId(),
            'frequency' => $frequency,
            'user_id' => $user->getId(),
        ]);

        return $recurrence;
    }

    /**
     * Process recurring tasks (should be run by cron)
     */
    public function processRecurringTasks(): array
    {
        $created = [];
        $now = new \DateTimeImmutable();

        // Get all active recurrences
        $recurrences = $this->recurrenceRepository->findActiveRecurrences();

        foreach ($recurrences as $recurrence) {
            /** @var TaskRecurrence $recurrence */
            
            // Check if end date has passed
            if ($recurrence->getEndDate() !== null && $recurrence->getEndDate() < $now) {
                continue;
            }

            // Check if we should create a new task
            if ($this->shouldCreateTask($recurrence, $now)) {
                try {
                    $task = $this->createTaskFromRecurrence($recurrence);
                    $created[] = $task;

                    // Update last generated date
                    $recurrence->setLastGenerated($now);
                    $this->entityManager->flush();

                    $this->logger->info('Recurring task created', [
                        'task_id' => $task->getId(),
                        'recurrence_id' => $recurrence->getId(),
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to create recurring task', [
                        'recurrence_id' => $recurrence->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $created;
    }

    /**
     * Check if task should be created
     */
    private function shouldCreateTask(TaskRecurrence $recurrence, \DateTimeImmutable $now): bool
    {
        $lastGenerated = $recurrence->getLastGenerated();
        $frequency = $recurrence->getFrequency();
        $interval = $recurrence->getInterval() ?? 1;

        // If never generated, create now
        if ($lastGenerated === null) {
            return true;
        }

        // Calculate next creation date based on frequency
        $nextDate = $this->calculateNextDate($lastGenerated, $frequency, $interval);

        // Check for specific days (weekly/monthly)
        if ($frequency === 'weekly' && $recurrence->getDaysOfWeekArray() !== null) {
            return $this->shouldCreateOnSpecificDays($now, $recurrence->getDaysOfWeekArray(), 'week');
        }

        if ($frequency === 'monthly' && $recurrence->getDaysOfMonthArray() !== null) {
            return $this->shouldCreateOnSpecificDays($now, $recurrence->getDaysOfMonthArray(), 'month');
        }

        return $now >= $nextDate;
    }

    /**
     * Check if current date matches specific days
     */
    private function shouldCreateOnSpecificDays(\DateTimeImmutable $now, array $days, string $type): bool
    {
        $currentDay = match($type) {
            'week' => (int) $now->format('N'), // 1 (Monday) to 7 (Sunday)
            'month' => (int) $now->format('j'), // 1 to 31
            default => 0
        };

        return in_array($currentDay, array_map('intval', $days));
    }

    /**
     * Calculate next date based on frequency
     */
    private function calculateNextDate(
        \DateTimeImmutable $from,
        string $frequency,
        int $interval = 1,
    ): \DateTimeImmutable {
        return match($frequency) {
            'daily' => $from->modify("+{$interval} days"),
            'weekly' => $from->modify("+{$interval} weeks"),
            'monthly' => $from->modify("+{$interval} months"),
            'yearly' => $from->modify("+{$interval} years"),
            default => $from->modify("+{$interval} days")
        };
    }

    /**
     * Create task from recurrence
     */
    private function createTaskFromRecurrence(TaskRecurrence $recurrence): Task
    {
        $template = $recurrence->getTask();

        $task = new Task();
        $task->setTitle($this->generateTitle($template, $recurrence));
        $task->setDescription($template->getDescription());
        $task->setPriority($template->getPriority());
        $task->setStatus('pending');
        $task->setUser($recurrence->getUser());
        $task->setCategory($template->getCategory());

        // Set deadline based on frequency
        $deadline = $this->calculateDeadline($recurrence->getFrequency(), $recurrence->getInterval() ?? 1);
        $task->setDueDate($deadline);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Generate title for recurring task
     */
    private function generateTitle(Task $template, TaskRecurrence $recurrence): string
    {
        $today = new \DateTimeImmutable();
        $dateStr = $today->format('d.m.Y');
        
        // Check if title already contains date
        if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $template->getTitle())) {
            return $template->getTitle();
        }

        return "{$template->getTitle()} ({$dateStr})";
    }

    /**
     * Calculate deadline for new task
     */
    private function calculateDeadline(string $frequency, int $interval = 1): \DateTimeImmutable
    {
        $deadline = new \DateTimeImmutable();

        return match($frequency) {
            'daily' => $deadline->modify("+{$interval} days"),
            'weekly' => $deadline->modify("+{$interval} weeks"),
            'monthly' => $deadline->modify("+{$interval} months"),
            'yearly' => $deadline->modify("+{$interval} years"),
            default => $deadline->modify("+{$interval} days")
        };
    }

    /**
     * Get recurring task patterns
     */
    public function getPatterns(): array
    {
        return [
            'daily' => [
                'name' => 'Ежедневно',
                'description' => 'Создавать задачу каждый день',
                'icon' => 'fa-calendar-day',
                'supportsInterval' => true,
            ],
            'weekly' => [
                'name' => 'Еженедельно',
                'description' => 'Создавать задачу каждую неделю',
                'icon' => 'fa-calendar-week',
                'supportsInterval' => true,
                'supportsDaysOfWeek' => true,
            ],
            'monthly' => [
                'name' => 'Ежемесячно',
                'description' => 'Создавать задачу каждый месяц',
                'icon' => 'fa-calendar-alt',
                'supportsInterval' => true,
                'supportsDaysOfMonth' => true,
            ],
            'yearly' => [
                'name' => 'Ежегодно',
                'description' => 'Создавать задачу каждый год',
                'icon' => 'fa-calendar',
                'supportsInterval' => true,
            ],
            'custom' => [
                'name' => 'Пользовательский',
                'description' => 'Настроить свой паттерн повторения',
                'icon' => 'fa-cog',
                'supportsInterval' => true,
            ],
        ];
    }

    /**
     * Get user's recurring tasks
     */
    public function getUserRecurringTasks(User $user): array
    {
        return $this->recurrenceRepository->findByUser($user);
    }

    /**
     * Get upcoming recurring tasks for user
     */
    public function getUpcomingRecurringTasks(User $user, int $limit = 5): array
    {
        return $this->recurrenceRepository->findUpcomingForUser($user, $limit);
    }

    /**
     * Delete recurring task
     */
    public function deleteRecurring(TaskRecurrence $recurrence, bool $deleteCreatedTasks = false): bool
    {
        if ($deleteCreatedTasks) {
            // Находим и удаляем все задачи, созданные из этой периодической задачи
            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->where('t.recurrence = :recurrence')
                ->setParameter('recurrence', $recurrence)
                ->getQuery()
                ->getResult();

            foreach ($tasks as $task) {
                $this->entityManager->remove($task);
            }
        }

        $this->entityManager->remove($recurrence);
        $this->entityManager->flush();

        $this->logger->info('Recurring task deleted', [
            'recurrence_id' => $recurrence->getId(),
            'deleted_tasks_count' => $deleteCreatedTasks ? \count($tasks) : 0,
        ]);

        return true;
    }

    /**
     * Update recurring task
     */
    public function updateRecurring(
        TaskRecurrence $recurrence,
        ?string $frequency = null,
        ?int $interval = null,
        ?\DateTimeImmutable $endDate = null,
        ?array $daysOfWeek = null,
        ?array $daysOfMonth = null,
    ): TaskRecurrence {
        if ($frequency !== null) {
            $recurrence->setFrequency($frequency);
        }

        if ($interval !== null) {
            $recurrence->setInterval($interval);
        }

        if ($endDate !== null) {
            $recurrence->setEndDate($endDate);
        }

        if ($daysOfWeek !== null) {
            $recurrence->setDaysOfWeekFromArray($daysOfWeek);
        }

        if ($daysOfMonth !== null) {
            $recurrence->setDaysOfMonthFromArray($daysOfMonth);
        }

        $recurrence->setUpdatedAt();
        $this->entityManager->flush();

        return $recurrence;
    }

    /**
     * Get statistics for user's recurring tasks
     */
    public function getStatistics(User $user): array
    {
        $recurrences = $this->getUserRecurringTasks($user);
        
        $total = count($recurrences);
        $active = 0;
        $byFrequency = [];

        foreach ($recurrences as $recurrence) {
            /** @var TaskRecurrence $recurrence */
            $freq = $recurrence->getFrequency();
            
            if (!isset($byFrequency[$freq])) {
                $byFrequency[$freq] = 0;
            }
            $byFrequency[$freq]++;

            // Check if active
            if ($recurrence->getEndDate() === null || $recurrence->getEndDate() >= new \DateTimeImmutable()) {
                $active++;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_frequency' => $byFrequency,
        ];
    }

    /**
     * Get days of week options
     */
    public function getDaysOfWeekOptions(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }

    /**
     * Get days of month options
     */
    public function getDaysOfMonthOptions(): array
    {
        $days = [];
        for ($i = 1; $i <= 31; $i++) {
            $days[$i] = "{$i}-е число";
        }
        return $days;
    }

    /**
     * Skip weekend option
     */
    public function skipWeekend(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N');
        
        // If Saturday (6), move to Friday (5)
        if ($dayOfWeek === 6) {
            return $date->modify('-1 day');
        }
        
        // If Sunday (7), move to Monday (1)
        if ($dayOfWeek === 7) {
            return $date->modify('+1 day');
        }

        return $date;
    }
}
