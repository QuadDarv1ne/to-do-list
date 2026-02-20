<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class RecurringTaskService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Create recurring task
     * TODO: Реализовать повторяющиеся задачи
     * - Создать таблицу task_recurrence (task_id, frequency, interval, end_date, last_created, next_creation)
     * - Поддержка сложных паттернов (каждый второй понедельник, последний день месяца)
     * - Настройка времени создания задачи
     * - Пропуск выходных дней
     * - Автоматическое завершение серии при достижении end_date
     */
    public function createRecurring(Task $template, string $frequency, ?\DateTime $endDate = null): array
    {
        $config = [
            'template_id' => $template->getId(),
            'frequency' => $frequency, // daily, weekly, monthly, yearly
            'end_date' => $endDate,
            'last_created' => new \DateTime(),
            'next_creation' => $this->calculateNextDate(new \DateTime(), $frequency),
        ];

        // TODO: Save to database

        return $config;
    }

    /**
     * Calculate next creation date
     */
    private function calculateNextDate(\DateTime $from, string $frequency): \DateTime
    {
        $next = clone $from;

        return match($frequency) {
            'daily' => $next->modify('+1 day'),
            'weekly' => $next->modify('+1 week'),
            'monthly' => $next->modify('+1 month'),
            'yearly' => $next->modify('+1 year'),
            default => $next->modify('+1 day')
        };
    }

    /**
     * Process recurring tasks (should be run by cron)
     * TODO: Реализовать обработку повторяющихся задач
     * - Создать Symfony Command для запуска через cron
     * - Использовать Messenger для асинхронной обработки
     * - Обработка ошибок и retry логика
     * - Уведомления при создании новых задач
     * - Логирование созданных задач
     * - Добавить в crontab.example команду для запуска
     */
    public function processRecurringTasks(): array
    {
        $created = [];

        // TODO: Get recurring task configs from database
        $configs = [];

        foreach ($configs as $config) {
            if ($this->shouldCreateTask($config)) {
                $task = $this->createTaskFromConfig($config);
                $created[] = $task;
            }
        }

        return $created;
    }

    /**
     * Check if task should be created
     */
    private function shouldCreateTask(array $config): bool
    {
        $now = new \DateTime();

        // Check if next creation date has passed
        if ($config['next_creation'] > $now) {
            return false;
        }

        // Check if end date has passed
        return !($config['end_date'] && $config['end_date'] < $now)



        ;
    }

    /**
     * Create task from config
     */
    private function createTaskFromConfig(array $config): Task
    {
        $template = $this->taskRepository->find($config['template_id']);

        $task = new Task();
        $task->setTitle($template->getTitle());
        $task->setDescription($template->getDescription());
        $task->setPriority($template->getPriority());
        $task->setStatus('pending');
        $task->setUser($template->getUser());
        $task->setCategory($template->getCategory());

        // Set deadline based on frequency
        $deadline = $this->calculateDeadline($config['frequency']);
        $task->setDeadline($deadline);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Calculate deadline for new task
     */
    private function calculateDeadline(string $frequency): \DateTime
    {
        $deadline = new \DateTime();

        return match($frequency) {
            'daily' => $deadline->modify('+1 day'),
            'weekly' => $deadline->modify('+1 week'),
            'monthly' => $deadline->modify('+1 month'),
            'yearly' => $deadline->modify('+1 year'),
            default => $deadline->modify('+1 day')
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
            ],
            'weekly' => [
                'name' => 'Еженедельно',
                'description' => 'Создавать задачу каждую неделю',
                'icon' => 'fa-calendar-week',
            ],
            'monthly' => [
                'name' => 'Ежемесячно',
                'description' => 'Создавать задачу каждый месяц',
                'icon' => 'fa-calendar-alt',
            ],
            'yearly' => [
                'name' => 'Ежегодно',
                'description' => 'Создавать задачу каждый год',
                'icon' => 'fa-calendar',
            ],
        ];
    }

    /**
     * Get user's recurring tasks
     * TODO: Реализовать получение повторяющихся задач пользователя
     * - Запрос к таблице task_recurrence с JOIN к tasks
     * - Показывать следующую дату создания
     * - Статистика созданных задач по каждому правилу
     * - Возможность редактирования правил
     */
    public function getUserRecurringTasks(User $user): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Delete recurring task
     * TODO: Реализовать удаление повторяющихся задач
     * - Удаление записи из task_recurrence
     * - Опция: удалить все созданные задачи или только правило
     * - Подтверждение перед удалением
     * - Логирование удаления
     */
    public function deleteRecurring(int $configId): bool
    {
        // TODO: Delete from database
        return true;
    }
}
