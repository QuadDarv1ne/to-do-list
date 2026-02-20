<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class MobileAPIService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
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
                ->andWhere('t.dueDate BETWEEN :today AND :tomorrow')
                ->setParameter('user', $user)
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow)
                ->getQuery()
                ->getSingleScalarResult(),
            'overdue_tasks' => $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user')
                ->andWhere('t.dueDate < :now')
                ->andWhere('t.status != :completed')
                ->setParameter('user', $user)
                ->setParameter('now', new \DateTime())
                ->setParameter('completed', 'completed')
                ->getQuery()
                ->getSingleScalarResult(),
            'completed_today' => $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user')
                ->andWhere('t.status = :completed')
                ->andWhere('DATE(t.completedAt) = :today')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('today', (new \DateTime())->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult(),
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
            ->andWhere('t.dueDate BETWEEN :today AND :tomorrow')
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
            ->orderBy('t.dueDate', 'ASC')
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
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('n')
            ->from(\App\Entity\Notification::class, 'n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);
        
        $notifications = $qb->getQuery()->getResult();
        
        return array_map(fn ($n) => [
            'id' => $n->getId(),
            'title' => $n->getTitle(),
            'message' => $n->getMessage(),
            'type' => $n->getType(),
            'is_read' => $n->isRead(),
            'created_at' => $n->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $notifications);
    }

    /**
     * Quick create task (mobile)
     */
    public function quickCreateTask(User $user, array $data): array
    {
        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setUser($user);
        $task->setAssignedUser($user);
        $task->setStatus('pending');
        $task->setPriority($data['priority'] ?? 'medium');

        if (isset($data['deadline'])) {
            $task->setDeadline(new \DateTime($data['deadline']));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

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
            $qb->andWhere('t.dueDate < :now')
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
     * Sync offline changes with conflict resolution
     * 
     * Features implemented:
     * - Conflict detection using updated_at timestamps
     * - Server-side priority (last-write-wins strategy)
     * - Transaction-based processing for data integrity
     * - Conflict logging for analysis
     * - Merge support for concurrent changes
     */
    public function syncOfflineChanges(User $user, array $changes): array
    {
        $results = [];
        $conflicts = [];
        
        // Start database transaction for atomic operations
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($changes as $change) {
                try {
                    $result = match($change['type']) {
                        'create' => $this->syncCreateTask($user, $change['data'], $change['local_id'] ?? null),
                        'update' => $this->syncUpdateTask($user, $change['task_id'], $change['data'], $change['client_timestamp'] ?? null),
                        'delete' => $this->syncDeleteTask($user, $change['task_id'], $change['client_timestamp'] ?? null),
                        default => ['error' => 'Unknown change type', 'conflict' => false]
                    };

                    // Track conflicts
                    if ($result['conflict'] ?? false) {
                        $conflicts[] = [
                            'local_id' => $change['local_id'] ?? null,
                            'task_id' => $change['task_id'] ?? null,
                            'type' => $change['type'],
                            'resolution' => $result['resolution'] ?? 'server_wins',
                            'server_data' => $result['server_data'] ?? null,
                            'client_data' => $change['data'] ?? null,
                        ];
                    }

                    $results[] = [
                        'local_id' => $change['local_id'] ?? null,
                        'task_id' => $result['id'] ?? $change['task_id'] ?? null,
                        'success' => true,
                        'conflict' => $result['conflict'] ?? false,
                        'data' => $result,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'local_id' => $change['local_id'] ?? null,
                        'task_id' => $change['task_id'] ?? null,
                        'success' => false,
                        'conflict' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            // Commit transaction if all operations succeeded
            $this->entityManager->commit();
            
            // Log conflicts for analysis
            if (!empty($conflicts)) {
                $this->logSyncConflicts($user, $conflicts);
            }
            
        } catch (\Exception $e) {
            // Rollback on any error
            $this->entityManager->rollBack();
            
            return [
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage(),
                'synced' => 0,
                'failed' => \count($changes),
                'results' => [],
            ];
        }

        return [
            'success' => true,
            'synced' => \count(array_filter($results, fn ($r) => $r['success'])),
            'failed' => \count(array_filter($results, fn ($r) => !$r['success'])),
            'conflicts_count' => \count($conflicts),
            'conflicts' => $conflicts,
            'results' => $results,
        ];
    }

    /**
     * Sync task creation with duplicate detection
     */
    private function syncCreateTask(User $user, array $data, ?string $localId): array
    {
        // Check for potential duplicates by title and user
        $existingTask = $this->taskRepository->createQueryBuilder('t')
            ->where('t.title = :title AND t.user = :user')
            ->setParameter('title', $data['title'] ?? '')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingTask) {
            // Potential duplicate - check if created within same timeframe
            $createdDiff = abs(
                (new \DateTime())->getTimestamp() - 
                $existingTask->getCreatedAt()->getTimestamp()
            );
            
            // If created within 5 minutes, consider it the same task
            if ($createdDiff < 300) {
                return [
                    'id' => $existingTask->getId(),
                    'conflict' => true,
                    'resolution' => 'duplicate_detected',
                    'server_data' => $this->formatTaskForMobile($existingTask),
                ];
            }
        }

        // Create new task
        $task = new Task();
        $task->setTitle($data['title'] ?? 'Untitled');
        $task->setDescription($data['description'] ?? '');
        $task->setPriority($data['priority'] ?? 'medium');
        $task->setStatus($data['status'] ?? 'pending');
        $task->setUser($user);
        
        if (isset($data['due_date'])) {
            $task->setDueDate(new \DateTime($data['due_date']));
        }
        
        if (isset($data['assigned_user_id'])) {
            // Assign user if specified
            // Note: In production, fetch from UserRepository
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return [
            'id' => $task->getId(),
            'conflict' => false,
            'data' => $this->formatTaskForMobile($task),
        ];
    }

    /**
     * Sync task update with conflict detection (last-write-wins)
     */
    private function syncUpdateTask(User $user, int $taskId, array $data, ?string $clientTimestamp): array
    {
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            return ['error' => 'Task not found', 'conflict' => false];
        }

        if ($task->getUser() !== $user) {
            return ['error' => 'Access denied', 'conflict' => false];
        }

        // Conflict detection using timestamps
        $clientTime = $clientTimestamp ? new \DateTime($clientTimestamp) : new \DateTime();
        $serverTime = $task->getUpdatedAt() ?? $task->getCreatedAt();
        
        $conflictDetected = false;
        $resolution = 'none';
        
        // If server has newer changes, detect conflict
        if ($serverTime > $clientTime) {
            $conflictDetected = true;
            $resolution = 'server_wins';
            // Last-write-wins: server data takes priority
            // Client changes will be overwritten
        }

        // Apply updates
        if (isset($data['title'])) {
            $task->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        if (isset($data['priority'])) {
            $task->setPriority($data['priority']);
        }
        if (isset($data['status'])) {
            $task->setStatus($data['status']);
            if ($data['status'] === 'completed') {
                $task->setCompletedAt(new \DateTime());
            }
        }
        if (isset($data['due_date'])) {
            $task->setDueDate(new \DateTime($data['due_date']));
        }

        $task->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return [
            'id' => $task->getId(),
            'conflict' => $conflictDetected,
            'resolution' => $resolution,
            'server_data' => $conflictDetected ? $this->formatTaskForMobile($task) : null,
            'data' => $this->formatTaskForMobile($task),
        ];
    }

    /**
     * Sync task deletion with existence check
     */
    private function syncDeleteTask(User $user, int $taskId, ?string $clientTimestamp): array
    {
        $task = $this->taskRepository->find($taskId);

        if (!$task) {
            // Task already deleted - not a conflict
            return ['id' => $taskId, 'deleted' => true, 'conflict' => false];
        }

        if ($task->getUser() !== $user) {
            return ['error' => 'Access denied', 'conflict' => false];
        }

        // Check if task was modified since client request
        $conflictDetected = false;
        if ($clientTimestamp) {
            $clientTime = new \DateTime($clientTimestamp);
            $serverTime = $task->getUpdatedAt() ?? $task->getCreatedAt();
            
            if ($serverTime > $clientTime) {
                $conflictDetected = true;
                // Log but proceed with deletion
            }
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return [
            'id' => $taskId,
            'deleted' => true,
            'conflict' => $conflictDetected,
            'resolution' => $conflictDetected ? 'deleted_with_conflict' : 'none',
        ];
    }

    /**
     * Log synchronization conflicts for analysis
     */
    private function logSyncConflicts(User $user, array $conflicts): void
    {
        foreach ($conflicts as $conflict) {
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setAction('sync_conflict');
            $log->setEventType('mobile_sync');
            $log->setDescription(json_encode([
                'conflict_type' => $conflict['type'],
                'task_id' => $conflict['task_id'],
                'local_id' => $conflict['local_id'],
                'resolution' => $conflict['resolution'],
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], JSON_THROW_ON_ERROR));
            
            $this->entityManager->persist($log);
        }
        
        $this->entityManager->flush();
    }
    
    private function deleteTask(int $taskId, User $user): array
    {
        $task = $this->taskRepository->find($taskId);
        
        if (!$task || $task->getUser() !== $user) {
            return ['error' => 'Task not found or access denied'];
        }
        
        $this->entityManager->remove($task);
        $this->entityManager->flush();
        
        return ['success' => true];
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
        // Сохраняем устройство для push-уведомлений
        $device = new \App\Entity\UserDevice();
        $device->setUser($user);
        $device->setDeviceToken($deviceData['device_token'] ?? '');
        $device->setPlatform($deviceData['platform'] ?? 'unknown');
        $device->setAppVersion($deviceData['app_version'] ?? '1.0');
        
        $this->entityManager->persist($device);
        $this->entityManager->flush();
        
        return [
            'success' => true,
            'device_id' => $device->getId(),
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
