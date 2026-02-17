<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

class SmartNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Send smart notification
     */
    public function sendSmartNotification(User $user, string $type, array $data): void
    {
        $preferences = $this->getUserPreferences($user);
        
        if (!$this->shouldSendNotification($user, $type, $preferences)) {
            return;
        }

        $notification = $this->buildNotification($type, $data);
        $this->deliverNotification($user, $notification, $preferences);
    }

    /**
     * Check if should send notification
     */
    private function shouldSendNotification(User $user, string $type, array $preferences): bool
    {
        // Check if type is enabled
        if (!($preferences['enabled_types'][$type] ?? true)) {
            return false;
        }

        // Check quiet hours
        if ($this->isQuietHours($preferences)) {
            return false;
        }

        // Check frequency limits
        if ($this->exceedsFrequencyLimit($user, $type, $preferences)) {
            return false;
        }

        return true;
    }

    /**
     * Check if quiet hours
     */
    private function isQuietHours(array $preferences): bool
    {
        if (!isset($preferences['quiet_hours'])) {
            return false;
        }

        $now = new \DateTime();
        $currentHour = (int)$now->format('H');
        
        $start = $preferences['quiet_hours']['start'] ?? 22;
        $end = $preferences['quiet_hours']['end'] ?? 8;

        if ($start < $end) {
            return $currentHour >= $start && $currentHour < $end;
        } else {
            return $currentHour >= $start || $currentHour < $end;
        }
    }

    /**
     * Check frequency limit
     */
    private function exceedsFrequencyLimit(User $user, string $type, array $preferences): bool
    {
        $limit = $preferences['frequency_limits'][$type] ?? 100;
        $period = $preferences['frequency_period'] ?? 'hour';
        
        // TODO: Check actual notification count from database
        return false;
    }

    /**
     * Build notification
     */
    private function buildNotification(string $type, array $data): array
    {
        return match($type) {
            'task_assigned' => [
                'title' => 'Новая задача',
                'message' => "Вам назначена задача: {$data['task_title']}",
                'icon' => 'fa-tasks',
                'priority' => 'normal',
                'action_url' => "/tasks/{$data['task_id']}"
            ],
            'task_completed' => [
                'title' => 'Задача завершена',
                'message' => "Задача завершена: {$data['task_title']}",
                'icon' => 'fa-check-circle',
                'priority' => 'low',
                'action_url' => "/tasks/{$data['task_id']}"
            ],
            'task_overdue' => [
                'title' => 'Просроченная задача',
                'message' => "Задача просрочена: {$data['task_title']}",
                'icon' => 'fa-exclamation-triangle',
                'priority' => 'high',
                'action_url' => "/tasks/{$data['task_id']}"
            ],
            'deadline_approaching' => [
                'title' => 'Приближается дедлайн',
                'message' => "Дедлайн через {$data['hours']} часов: {$data['task_title']}",
                'icon' => 'fa-clock',
                'priority' => 'high',
                'action_url' => "/tasks/{$data['task_id']}"
            ],
            'comment_added' => [
                'title' => 'Новый комментарий',
                'message' => "{$data['user_name']} оставил комментарий",
                'icon' => 'fa-comment',
                'priority' => 'normal',
                'action_url' => "/tasks/{$data['task_id']}#comments"
            ],
            'mentioned' => [
                'title' => 'Вас упомянули',
                'message' => "{$data['user_name']} упомянул вас в комментарии",
                'icon' => 'fa-at',
                'priority' => 'high',
                'action_url' => "/tasks/{$data['task_id']}#comments"
            ],
            'task_updated' => [
                'title' => 'Задача обновлена',
                'message' => "Задача обновлена: {$data['task_title']}",
                'icon' => 'fa-edit',
                'priority' => 'low',
                'action_url' => "/tasks/{$data['task_id']}"
            ],
            default => [
                'title' => 'Уведомление',
                'message' => $data['message'] ?? '',
                'icon' => 'fa-bell',
                'priority' => 'normal'
            ]
        };
    }

    /**
     * Deliver notification
     */
    private function deliverNotification(User $user, array $notification, array $preferences): void
    {
        $channels = $preferences['channels'] ?? ['web'];

        foreach ($channels as $channel) {
            match($channel) {
                'web' => $this->sendWebNotification($user, $notification),
                'email' => $this->sendEmailNotification($user, $notification),
                'push' => $this->sendPushNotification($user, $notification),
                'telegram' => $this->sendTelegramNotification($user, $notification),
                default => null
            };
        }
    }

    /**
     * Send web notification
     */
    private function sendWebNotification(User $user, array $notification): void
    {
        // TODO: Save to database for web display
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(User $user, array $notification): void
    {
        // TODO: Send email
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(User $user, array $notification): void
    {
        // TODO: Send push notification
    }

    /**
     * Send telegram notification
     */
    private function sendTelegramNotification(User $user, array $notification): void
    {
        // TODO: Send telegram message
    }

    /**
     * Get user preferences
     */
    private function getUserPreferences(User $user): array
    {
        // TODO: Get from database
        return [
            'channels' => ['web', 'email'],
            'enabled_types' => [
                'task_assigned' => true,
                'task_completed' => true,
                'task_overdue' => true,
                'deadline_approaching' => true,
                'comment_added' => true,
                'mentioned' => true,
                'task_updated' => false
            ],
            'quiet_hours' => [
                'enabled' => true,
                'start' => 22,
                'end' => 8
            ],
            'frequency_limits' => [
                'task_assigned' => 50,
                'comment_added' => 20
            ],
            'frequency_period' => 'hour'
        ];
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(User $user, array $preferences): void
    {
        // TODO: Save to database
    }

    /**
     * Get notification history
     */
    public function getNotificationHistory(User $user, int $limit = 50): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Mark as read
     */
    public function markAsRead(int $notificationId): void
    {
        // TODO: Update in database
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(User $user): void
    {
        // TODO: Update in database
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(User $user): int
    {
        // TODO: Get from database
        return 0;
    }

    /**
     * Batch send notifications
     */
    public function batchSendNotifications(array $users, string $type, array $data): int
    {
        $count = 0;
        foreach ($users as $user) {
            $this->sendSmartNotification($user, $type, $data);
            $count++;
        }
        return $count;
    }

    /**
     * Send digest
     */
    public function sendDigest(User $user, string $period = 'daily'): void
    {
        $tasks = $this->getDigestTasks($user, $period);
        
        $notification = [
            'title' => 'Дайджест задач',
            'message' => $this->buildDigestMessage($tasks, $period),
            'icon' => 'fa-newspaper',
            'priority' => 'normal'
        ];

        $this->deliverNotification($user, $notification, $this->getUserPreferences($user));
    }

    /**
     * Get digest tasks
     */
    private function getDigestTasks(User $user, string $period): array
    {
        // TODO: Get tasks for digest
        return [];
    }

    /**
     * Build digest message
     */
    private function buildDigestMessage(array $tasks, string $period): string
    {
        $periodName = match($period) {
            'daily' => 'сегодня',
            'weekly' => 'на этой неделе',
            'monthly' => 'в этом месяце',
            default => ''
        };

        return "Ваши задачи $periodName: " . count($tasks);
    }
}
