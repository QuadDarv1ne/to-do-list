<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\CommentRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityFeedService
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private TaskRepository $taskRepository,
        private CommentRepository $commentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get activity feed for user
     */
    public function getUserFeed(User $user, int $limit = 20): array
    {
        $activities = [];

        // Get user's task activities
        $taskActivities = $this->activityLogRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->orWhere('a.metadata LIKE :userId')
            ->setParameter('user', $user)
            ->setParameter('userId', '%"user_id":' . $user->getId() . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($taskActivities as $activity) {
            $activities[] = [
                'type' => 'activity',
                'action' => $activity->getAction(),
                'entity' => $activity->getEntityType(),
                'entity_id' => $activity->getEntityId(),
                'user' => $activity->getUser(),
                'metadata' => $activity->getMetadata(),
                'created_at' => $activity->getCreatedAt(),
                'icon' => $this->getActivityIcon($activity->getAction()),
                'color' => $this->getActivityColor($activity->getAction()),
            ];
        }

        // Sort by date
        usort($activities, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return \array_slice($activities, 0, $limit);
    }

    /**
     * Get team activity feed
     */
    public function getTeamFeed(int $limit = 50): array
    {
        $activities = $this->activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $feed = [];
        foreach ($activities as $activity) {
            $feed[] = [
                'type' => 'activity',
                'action' => $activity->getAction(),
                'entity' => $activity->getEntityType(),
                'entity_id' => $activity->getEntityId(),
                'user' => $activity->getUser(),
                'metadata' => $activity->getMetadata(),
                'created_at' => $activity->getCreatedAt(),
                'icon' => $this->getActivityIcon($activity->getAction()),
                'color' => $this->getActivityColor($activity->getAction()),
                'description' => $this->getActivityDescription($activity),
            ];
        }

        return $feed;
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(User $user, \DateTime $from, \DateTime $to): array
    {
        $qb = $this->activityLogRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $totalActivities = (int)$qb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Activities by action
        $byAction = $qb->select('a.action, COUNT(a.id) as count')
            ->groupBy('a.action')
            ->getQuery()
            ->getResult();

        // Activities by day
        $byDay = [];
        $current = clone $from;
        while ($current <= $to) {
            $nextDay = (clone $current)->modify('+1 day');

            $count = $this->activityLogRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.user = :user')
                ->andWhere('a.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $current)
                ->setParameter('end', $nextDay)
                ->getQuery()
                ->getSingleScalarResult();

            $byDay[] = [
                'date' => $current->format('Y-m-d'),
                'count' => (int)$count,
            ];

            $current = $nextDay;
        }

        return [
            'total' => $totalActivities,
            'by_action' => $byAction,
            'by_day' => $byDay,
            'average_per_day' => $totalActivities / max(1, $from->diff($to)->days),
        ];
    }

    /**
     * Get most active users
     */
    public function getMostActiveUsers(int $days = 7, int $limit = 10): array
    {
        $from = new \DateTime("-{$days} days");

        // Оптимизированный запрос с JOIN для избежания N+1 проблемы
        $results = $this->activityLogRepository->createQueryBuilder('a')
            ->select('u, COUNT(a.id) as activity_count')
            ->leftJoin('a.user', 'u')
            ->where('a.createdAt >= :from')
            ->andWhere('u IS NOT NULL')
            ->groupBy('u.id')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('from', $from)
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($results as $result) {
            $users[] = [
                'user' => $result[0], // Пользователь уже загружен через JOIN
                'activity_count' => (int)$result['activity_count'],
            ];
        }

        return $users;
    }

    /**
     * Get activity icon
     */
    private function getActivityIcon(string $action): string
    {
        return match($action) {
            'created' => 'fa-plus-circle',
            'updated' => 'fa-edit',
            'deleted' => 'fa-trash',
            'completed' => 'fa-check-circle',
            'assigned' => 'fa-user-plus',
            'commented' => 'fa-comment',
            'status_changed' => 'fa-exchange-alt',
            'priority_changed' => 'fa-flag',
            default => 'fa-circle'
        };
    }

    /**
     * Get activity color
     */
    private function getActivityColor(string $action): string
    {
        return match($action) {
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'danger',
            'completed' => 'success',
            'assigned' => 'primary',
            'commented' => 'secondary',
            'status_changed' => 'warning',
            'priority_changed' => 'warning',
            default => 'secondary'
        };
    }

    /**
     * Get activity description
     */
    private function getActivityDescription($activity): string
    {
        $user = $activity->getUser();
        $userName = $user ? $user->getFullName() : 'Пользователь';
        $entity = $activity->getEntityType();
        $action = $activity->getAction();

        return match($action) {
            'created' => "{$userName} создал(а) {$entity}",
            'updated' => "{$userName} обновил(а) {$entity}",
            'deleted' => "{$userName} удалил(а) {$entity}",
            'completed' => "{$userName} завершил(а) {$entity}",
            'assigned' => "{$userName} назначил(а) {$entity}",
            'commented' => "{$userName} прокомментировал(а) {$entity}",
            'status_changed' => "{$userName} изменил(а) статус {$entity}",
            'priority_changed' => "{$userName} изменил(а) приоритет {$entity}",
            default => "{$userName} выполнил(а) действие с {$entity}"
        };
    }
}
