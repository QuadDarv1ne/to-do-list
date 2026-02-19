<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Log an action
     */
    public function log(
        string $action,
        string $description,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
    ): ActivityLog {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setUser($user);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setCreatedAt(new \DateTime());

        // Add request metadata
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $requestMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
            ];

            if ($metadata) {
                $metadata = array_merge($metadata, $requestMetadata);
            } else {
                $metadata = $requestMetadata;
            }
        }

        if ($metadata) {
            $log->setMetadata($metadata);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * Log task creation
     */
    public function logTaskCreated(User $user, $task): void
    {
        $this->log(
            'task.created',
            \sprintf('Создана задача "%s"', $task->getTitle()),
            $user,
            'Task',
            $task->getId(),
            [
                'task_title' => $task->getTitle(),
                'priority' => $task->getPriority(),
                'status' => $task->getStatus(),
            ],
        );
    }

    /**
     * Log task update
     */
    public function logTaskUpdated(User $user, $task, array $changes): void
    {
        $this->log(
            'task.updated',
            \sprintf('Обновлена задача "%s"', $task->getTitle()),
            $user,
            'Task',
            $task->getId(),
            [
                'task_title' => $task->getTitle(),
                'changes' => $changes,
            ],
        );
    }

    /**
     * Log task deletion
     */
    public function logTaskDeleted(User $user, $task): void
    {
        $this->log(
            'task.deleted',
            \sprintf('Удалена задача "%s"', $task->getTitle()),
            $user,
            'Task',
            $task->getId(),
            [
                'task_title' => $task->getTitle(),
            ],
        );
    }

    /**
     * Log task status change
     */
    public function logTaskStatusChanged(User $user, $task, string $oldStatus, string $newStatus): void
    {
        $this->log(
            'task.status_changed',
            \sprintf('Изменен статус задачи "%s" с "%s" на "%s"', $task->getTitle(), $oldStatus, $newStatus),
            $user,
            'Task',
            $task->getId(),
            [
                'task_title' => $task->getTitle(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        );
    }

    /**
     * Log task assignment
     */
    public function logTaskAssigned(User $user, $task, User $assignedTo): void
    {
        $this->log(
            'task.assigned',
            \sprintf('Задача "%s" назначена пользователю %s', $task->getTitle(), $assignedTo->getFullName()),
            $user,
            'Task',
            $task->getId(),
            [
                'task_title' => $task->getTitle(),
                'assigned_to_id' => $assignedTo->getId(),
                'assigned_to_name' => $assignedTo->getFullName(),
            ],
        );
    }

    /**
     * Log user login
     */
    public function logUserLogin(User $user): void
    {
        $this->log(
            'user.login',
            \sprintf('Пользователь %s вошел в систему', $user->getFullName()),
            $user,
            'User',
            $user->getId(),
        );
    }

    /**
     * Log user logout
     */
    public function logUserLogout(User $user): void
    {
        $this->log(
            'user.logout',
            \sprintf('Пользователь %s вышел из системы', $user->getFullName()),
            $user,
            'User',
            $user->getId(),
        );
    }

    /**
     * Log failed login attempt
     */
    public function logFailedLogin(string $email): void
    {
        $this->log(
            'user.login_failed',
            \sprintf('Неудачная попытка входа для email: %s', $email),
            null,
            'User',
            null,
            ['email' => $email],
        );
    }

    /**
     * Log export action
     */
    public function logExport(User $user, string $exportType, int $recordCount): void
    {
        $this->log(
            'export.' . $exportType,
            \sprintf('Экспорт данных (%s): %d записей', $exportType, $recordCount),
            $user,
            null,
            null,
            [
                'export_type' => $exportType,
                'record_count' => $recordCount,
            ],
        );
    }

    /**
     * Get activity logs for entity
     */
    public function getEntityLogs(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->entityManager->getRepository(ActivityLog::class)
            ->findBy(
                ['entityType' => $entityType, 'entityId' => $entityId],
                ['createdAt' => 'DESC'],
                $limit,
            );
    }

    /**
     * Get user activity logs
     */
    public function getUserLogs(User $user, int $limit = 50): array
    {
        return $this->entityManager->getRepository(ActivityLog::class)
            ->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                $limit,
            );
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $limit = 20): array
    {
        return $this->entityManager->getRepository(ActivityLog::class)
            ->findBy(
                [],
                ['createdAt' => 'DESC'],
                $limit,
            );
    }

    /**
     * Get activity statistics
     */
    public function getActivityStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Total activities
        $totalActivities = $qb->select('COUNT(a.id)')
            ->from(ActivityLog::class, 'a')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        // Activities by action
        $activitiesByAction = $this->entityManager->createQueryBuilder()
            ->select('a.action, COUNT(a.id) as count')
            ->from(ActivityLog::class, 'a')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->groupBy('a.action')
            ->orderBy('count', 'DESC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        // Most active users
        $mostActiveUsers = $this->entityManager->createQueryBuilder()
            ->select('u.id, u.fullName, COUNT(a.id) as activity_count')
            ->from(ActivityLog::class, 'a')
            ->join('a.user', 'u')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->groupBy('u.id, u.fullName')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults(10)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        return [
            'total_activities' => (int)$totalActivities,
            'activities_by_action' => $activitiesByAction,
            'most_active_users' => $mostActiveUsers,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }
}
