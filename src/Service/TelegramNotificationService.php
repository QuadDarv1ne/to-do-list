<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

/**
 * Сервис для отправки уведомлений в Telegram
 */
class TelegramNotificationService
{
    public function __construct(
        private IntegrationService $integrationService,
    ) {
    }

    /**
     * Отправить уведомление о новой задаче
     */
    public function notifyNewTask(User $user, Task $task): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = '🆕 Новая задача создана!';

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление об обновлении задачи
     */
    public function notifyTaskUpdated(User $user, Task $task, array $changes = []): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = '✏️ Задача обновлена';

        if (!empty($changes)) {
            $message .= "\n\nИзменения:\n";
            foreach ($changes as $field => $change) {
                $message .= "• {$field}: {$change['old']} → {$change['new']}\n";
            }
        }

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление о завершении задачи
     */
    public function notifyTaskCompleted(User $user, Task $task): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = '✅ Задача завершена!';

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление о приближающемся дедлайне
     */
    public function notifyDeadlineApproaching(User $user, Task $task, int $hoursLeft): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = "⏰ Приближается дедлайн!\n\nОсталось: {$hoursLeft} ч.";

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление о просроченной задаче
     */
    public function notifyTaskOverdue(User $user, Task $task): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = '🚨 Задача просрочена!';

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление о назначении задачи
     */
    public function notifyTaskAssigned(User $user, Task $task, User $assignedBy): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = "👤 Вам назначена задача от {$assignedBy->getFullName()}";

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить уведомление о новом комментарии
     */
    public function notifyNewComment(User $user, Task $task, string $commentText, User $author): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = "💬 Новый комментарий от {$author->getFullName()}\n\n{$commentText}";

        $context = [
            'task_title' => $task->getTitle(),
            'task_url' => $this->getTaskUrl($task),
        ];

        return $this->integrationService->sendNotification($user, 'telegram', $message, $context);
    }

    /**
     * Отправить ежедневный отчет
     */
    public function sendDailyReport(User $user, array $stats): bool
    {
        if (!$this->integrationService->hasIntegration($user, 'telegram')) {
            return false;
        }

        $message = "📊 Ежедневный отчет\n\n";
        $message .= "✅ Завершено: {$stats['completed']}\n";
        $message .= "📝 В работе: {$stats['in_progress']}\n";
        $message .= "⏰ Просрочено: {$stats['overdue']}\n";
        $message .= "🆕 Новых: {$stats['new']}\n";

        return $this->integrationService->sendNotification($user, 'telegram', $message);
    }

    /**
     * Получить URL задачи
     */
    private function getTaskUrl(Task $task): string
    {
        // В production нужно использовать реальный домен
        return "http://localhost:8000/task/{$task->getId()}";
    }
}
