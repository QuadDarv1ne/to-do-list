<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;

class MobileAPIService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * Get mobile dashboard
     */
    public function getMobileDashboard(User $user): array
    {
        return [
            'user' => $this->formatUserForMobile($user),
            'stats' => $this->getMobileStats($user),
            'today_tasks' => $this->getTodayTasks($user),
            'urgent_tasks' => $this->getUrgentTasks($user),
            'notifications' => $this->getRecentNotifications($user, 5),
        ];
    }

    /**
     * Format user for mobile
     */
    private function formatUserForMobile(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatar_url' => $user->getAvatarUrl(),
            'initials' => $user->getInitials(),
        ];
    }

    /**
     * Get mobile stats
     */
    private function getMobileStats(User $user): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return [
            'total_tasks' => $this->taskRepository->count(['assignedUser' => $user]),
            'today_tasks' => $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user')
                ->andWhere('t.deadline BETWEEN :today AND :tomorrow')
                ->setParameter('user', $user)
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow)
                ->getQuery()
                ->getSingleScalarResult(),
            'overdue_tasks' => $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user')
                ->andWhere('t.deadline < :now')
                ->andWhere('t.status != :completed')
                ->setParameter('user', $user)
                ->setParameter('now', new \DateTime())
                ->setParameter('completed', 'completed')
                ->getQuery()
                ->getSingleScalarResult(),
            'completed_today' => 0, // TODO: Calculate
        ];
    }

    /**
     * Get today tasks
     */
    private function getTodayTasks(User $user): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->andWhere('t.deadline BETWEEN :today AND :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('t.priority', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return array_map(fn ($task) => $this->formatTaskForMobile($task), $tasks);
    }

    /**
     * Get urgent tasks
     */
    private function getUrgentTasks(User $user): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->andWhere('t.priority = :urgent')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('urgent', 'urgent')
            ->setParameter('completed', 'completed')
            ->orderBy('t.deadline', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return array_map(fn ($task) => $this->formatTaskForMobile($task), $tasks);
    }

    /**
     * Format task for mobile
     */
    private function formatTaskForMobile(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'deadline' => $task->getDeadline()?->format('Y-m-d H:i:s'),
            'category' => $task->getCategory()?->getName(),
            'assigned_user' => $task->getAssignedUser() ? [
                'id' => $task->getAssignedUser()->getId(),
                'username' => $task->getAssignedUser()->getUsername(),
                'avatar_url' => $task->getAssignedUser()->getAvatarUrl(),
            ] : null,
            'created_at' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
            'is_overdue' => $task->getDeadline() && $task->getDeadline() < new \DateTime() && $task->getStatus() !== 'completed',
        ];
    }

    /**
     * Get recent notifications
     */
    private function getRecentNotifications(User $user, int $limit): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Quick create task (mobile)
     */
    public function quickCreateTask(User $user, array $data): array
    {
        $task = new Task();
        $task->setTitle($data['title']);
        $task->setUser($user);
        $task->setAssignedUser($user);
        $task->setStatus('pending');
        $task->setPriority($data['priority'] ?? 'medium');

        if (isset($data['deadline'])) {
            $task->setDeadline(new \DateTime($data['deadline']));
        }

        // TODO: Save to database

        return $this->formatTaskForMobile($task);
    }

    /**
     * Quick update task status (mobile)
     */
    public function quickUpdateStatus(int $taskId, string $status): array
    {
        // Оптимизированный запрос с предзагрузкой связанных данных
        $task = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au, c')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->where('t.id = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        $task->setStatus($status);
        if ($status === 'completed') {
            $task->setCompletedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->formatTaskForMobile($task);
    }

    /**
     * Get task details (mobile)
     */
    public function getTaskDetails(int $taskId): array
    {
        // Оптимизированный запрос с предзагрузкой всех связанных данных
        $task = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au, c, tags, comments, attachments')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tags')
            ->leftJoin('t.comments', 'comments')
            ->leftJoin('t.attachments', 'attachments')
            ->where('t.id = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        $details = $this->formatTaskForMobile($task);

        // Добавляем детали (уже загружены через JOIN)
        $details['comments'] = array_map(function ($comment) {
            return [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'author' => $comment->getAuthor()->getUsername(),
                'created_at' => $comment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $task->getComments()->toArray());

        $details['attachments'] = array_map(function ($attachment) {
            return [
                'id' => $attachment->getId(),
                'filename' => $attachment->getFilename(),
                'size' => $attachment->getSize(),
                'mime_type' => $attachment->getMimeType(),
            ];
        }, $task->getAttachments()->toArray());

        $details['tags'] = array_map(function ($tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
            ];
        }, $task->getTags()->toArray());

        return $details;
    }

    /**
     * Search tasks (mobile)
     */
    public function searchTasks(User $user, string $query, int $limit = 20): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user OR t.user = :user')
            ->andWhere('t.title LIKE :query OR t.description LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', "%$query%")
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn ($task) => $this->formatTaskForMobile($task), $tasks);
    }

    /**
     * Get filtered tasks (mobile)
     */
    public function getFilteredTasks(User $user, array $filters, int $page = 1, int $limit = 20): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user OR t.user = :user')
            ->setParameter('user', $user);

        if (isset($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (isset($filters['is_overdue']) && $filters['is_overdue']) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        $tasks = $qb->orderBy('t.createdAt', 'DESC')
                    ->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

        return [
            'tasks' => array_map(fn ($task) => $this->formatTaskForMobile($task), $tasks),
            'page' => $page,
            'limit' => $limit,
            'has_more' => \count($tasks) === $limit,
        ];
    }

    /**
     * Sync offline changes
     */
    public function syncOfflineChanges(User $user, array $changes): array
    {
        $results = [];

        foreach ($changes as $change) {
            try {
                $result = match($change['type']) {
                    'create' => $this->quickCreateTask($user, $change['data']),
                    'update' => $this->quickUpdateStatus($change['task_id'], $change['data']['status']),
                    'delete' => ['success' => true], // TODO: Implement
                    default => ['error' => 'Unknown change type']
                };

                $results[] = [
                    'local_id' => $change['local_id'],
                    'success' => true,
                    'data' => $result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'local_id' => $change['local_id'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced' => \count(array_filter($results, fn ($r) => $r['success'])),
            'failed' => \count(array_filter($results, fn ($r) => !$r['success'])),
            'results' => $results,
        ];
    }

    /**
     * Get app configuration
     */
    public function getAppConfig(): array
    {
        return [
            'version' => '3.1.0',
            'api_version' => '1.0',
            'features' => [
                'offline_mode' => true,
                'push_notifications' => true,
                'biometric_auth' => true,
                'dark_mode' => true,
                'widgets' => true,
            ],
            'limits' => [
                'max_file_size' => 10485760, // 10MB
                'max_attachments' => 5,
                'max_comment_length' => 5000,
            ],
        ];
    }

    /**
     * Register device for push notifications
     */
    public function registerDevice(User $user, array $deviceData): array
    {
        // TODO: Save device token to database
        return [
            'success' => true,
            'device_id' => uniqid(),
        ];
    }

    /**
     * Get widget data
     */
    public function getWidgetData(User $user, string $widgetType): array
    {
        return match($widgetType) {
            'today_tasks' => [
                'type' => 'today_tasks',
                'count' => $this->getMobileStats($user)['today_tasks'],
                'tasks' => \array_slice($this->getTodayTasks($user), 0, 3),
            ],
            'urgent_tasks' => [
                'type' => 'urgent_tasks',
                'count' => \count($this->getUrgentTasks($user)),
                'tasks' => \array_slice($this->getUrgentTasks($user), 0, 3),
            ],
            'stats' => [
                'type' => 'stats',
                'data' => $this->getMobileStats($user),
            ],
            default => ['error' => 'Unknown widget type']
        };
    }
}
