<?php

namespace App\Service;

use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Repository\NotificationPreferenceRepository;

class NotificationPreferenceService
{
    public function __construct(
        private NotificationPreferenceRepository $preferenceRepository,
    ) {
    }

    /**
     * Get user notification preferences
     */
    public function getPreferences(User $user): array
    {
        $preference = $this->preferenceRepository->getOrCreateForUser($user);
        
        return [
            'email' => $preference->getEmailSettings(),
            'push' => $preference->getPushSettings(),
            'in_app' => $preference->getInAppSettings(),
            'quiet_hours' => $preference->getQuietHours(),
            'frequency' => $preference->getFrequency(),
        ];
    }

    /**
     * Update preferences
     */
    public function updatePreferences(User $user, array $preferences): bool
    {
        $preference = $this->preferenceRepository->getOrCreateForUser($user);

        if (isset($preferences['email'])) {
            $preference->setEmailSettings($preferences['email']);
        }
        if (isset($preferences['push'])) {
            $preference->setPushSettings($preferences['push']);
        }
        if (isset($preferences['in_app'])) {
            $preference->setInAppSettings($preferences['in_app']);
        }
        if (isset($preferences['quiet_hours'])) {
            $preference->setQuietHours($preferences['quiet_hours']);
        }
        if (isset($preferences['frequency'])) {
            $preference->setFrequency($preferences['frequency']);
        }

        $this->preferenceRepository->save($preference);
        
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
        // Отправляем тестовое уведомление в зависимости от канала
        return match($channel) {
            'email' => $this->sendEmailTest($user),
            'push' => $this->sendPushTest($user),
            'in_app' => $this->sendInAppTest($user),
            default => false,
        };
    }

    private function sendEmailTest(User $user): bool
    {
        // Здесь будет реальная отправка email
        // Для пока возвращаем true
        return true;
    }

    private function sendPushTest(User $user): bool
    {
        // Здесь будет реальная отправка push
        return true;
    }

    private function sendInAppTest(User $user): bool
    {
        // Создаём тестовое уведомление в приложении
        return true;
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(User $user): array
    {
        // Получаем статистику из базы уведомлений
        $notifRepo = $this->preferenceRepository->getEntityManager()
            ->getRepository(\App\Entity\Notification::class);
        
        $total = $notifRepo->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()[1] ?? 0;

        $read = $notifRepo->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()[1] ?? 0;

        return [
            'total_sent' => $total,
            'total_read' => $read,
            'total_unread' => $total - $read,
            'by_channel' => [
                'email' => 0, // Можно добавить подсчёт по типам
                'push' => 0,
                'in_app' => $total,
            ],
            'by_type' => [],
        ];
    }
}
