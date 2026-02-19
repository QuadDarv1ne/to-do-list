<?php

namespace App\Service;

use App\Entity\User;

class NotificationPreferenceService
{
    /**
     * Get user notification preferences
     */
    public function getPreferences(User $user): array
    {
        // TODO: Get from database
        // For now, return defaults
        return [
            'email' => [
                'enabled' => true,
                'task_assigned' => true,
                'task_completed' => true,
                'task_commented' => true,
                'deadline_reminder' => true,
                'daily_digest' => false,
                'weekly_summary' => true,
            ],
            'push' => [
                'enabled' => true,
                'task_assigned' => true,
                'task_completed' => false,
                'task_commented' => true,
                'deadline_reminder' => true,
            ],
            'in_app' => [
                'enabled' => true,
                'task_assigned' => true,
                'task_completed' => true,
                'task_commented' => true,
                'deadline_reminder' => true,
                'mentions' => true,
            ],
            'quiet_hours' => [
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00',
            ],
            'frequency' => [
                'immediate' => true,
                'batched' => false,
                'batch_interval' => 60, // minutes
            ],
        ];
    }

    /**
     * Update preferences
     */
    public function updatePreferences(User $user, array $preferences): bool
    {
        // TODO: Save to database
        return true;
    }

    /**
     * Check if user should receive notification
     */
    public function shouldNotify(User $user, string $channel, string $type): bool
    {
        $preferences = $this->getPreferences($user);

        // Check if channel is enabled
        if (!($preferences[$channel]['enabled'] ?? false)) {
            return false;
        }

        // Check if notification type is enabled
        if (!($preferences[$channel][$type] ?? false)) {
            return false;
        }

        // Check quiet hours
        return !($this->isQuietHours($preferences))



        ;
    }

    /**
     * Check if current time is in quiet hours
     */
    private function isQuietHours(array $preferences): bool
    {
        if (!($preferences['quiet_hours']['enabled'] ?? false)) {
            return false;
        }

        $now = new \DateTime();
        $currentTime = $now->format('H:i');

        $start = $preferences['quiet_hours']['start'];
        $end = $preferences['quiet_hours']['end'];

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($start > $end) {
            return $currentTime >= $start || $currentTime <= $end;
        }

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Get notification channels
     */
    public function getChannels(): array
    {
        return [
            'email' => [
                'name' => 'Email',
                'description' => 'Уведомления на электронную почту',
                'icon' => 'fa-envelope',
            ],
            'push' => [
                'name' => 'Push',
                'description' => 'Push-уведомления в браузере',
                'icon' => 'fa-bell',
            ],
            'in_app' => [
                'name' => 'В приложении',
                'description' => 'Уведомления внутри системы',
                'icon' => 'fa-inbox',
            ],
        ];
    }

    /**
     * Get notification types
     */
    public function getTypes(): array
    {
        return [
            'task_assigned' => 'Назначение задачи',
            'task_completed' => 'Завершение задачи',
            'task_commented' => 'Новый комментарий',
            'deadline_reminder' => 'Напоминание о дедлайне',
            'mentions' => 'Упоминания',
            'daily_digest' => 'Ежедневная сводка',
            'weekly_summary' => 'Еженедельный отчет',
        ];
    }

    /**
     * Test notification
     */
    public function sendTestNotification(User $user, string $channel): bool
    {
        // TODO: Send actual test notification
        return true;
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(User $user): array
    {
        return [
            'total_sent' => 0, // TODO: Count from database
            'total_read' => 0,
            'total_unread' => 0,
            'by_channel' => [
                'email' => 0,
                'push' => 0,
                'in_app' => 0,
            ],
            'by_type' => [],
        ];
    }
}
