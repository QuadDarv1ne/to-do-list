<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;

class UserActivityService
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(User $user): array
    {
        $loginEvents = $this->activityLogRepository->findLoginEventsForUser($user, 10);
        $recentActivity = $this->activityLogRepository->findByUser($user);

        return [
            'recent_logins' => $loginEvents,
            'recent_activity' => $recentActivity,
            'total_logins' => count($loginEvents),
        ];
    }

    /**
     * Get platform-wide activity statistics
     */
    public function getPlatformActivityStats(): array
    {
        $recentLoginEvents = $this->activityLogRepository->findRecentLoginEvents(10);
        $userStats = $this->userRepository->getStatistics();

        return [
            'recent_logins' => $recentLoginEvents,
            'user_statistics' => $userStats,
        ];
    }
}
