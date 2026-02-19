<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for handling bulk operations on tasks efficiently
 */
class BulkTaskOperationService
{
    private EntityManagerInterface $entityManager;

    private TaskRepository $taskRepository;

    private NotificationService $notificationService;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        NotificationService $notificationService,
        LoggerInterface $logger,
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    /**
     * Bulk update task statuses
     */
    public function bulkUpdateStatus(array $taskIds, string $newStatus, User $currentUser): array
    {
        // Оптимизированный запрос с предзагрузкой пользователей
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();
        $updatedCount = 0;
        $failedTasks = [];

        $this->entityManager->beginTransaction();

        try {
            foreach ($tasks as $task) {
                // Check if user has permission to edit this task
                if (!$this->hasEditPermission($task, $currentUser)) {
                    $failedTasks[] = [
                        'id' => $task->getId(),
                        'title' => $task->getTitle(),
                        'reason' => 'Insufficient permissions',
                    ];

                    continue;
                }

                $originalStatus = $task->getStatus();
                $task->setStatus($newStatus);

                // Set completion time if marking as completed
                if ($newStatus === 'completed' && $originalStatus !== 'completed') {
                    $task->setCompletedAt(new \DateTime());
                } elseif ($newStatus !== 'completed' && $originalStatus === 'completed') {
                    $task->setCompletedAt(null);
                }

                $updatedCount++;
            }

            if ($updatedCount > 0) {
                $this->entityManager->flush();
            }

            $this->entityManager->commit();

            // Send notifications for completed tasks (after commit)
            if ($newStatus === 'completed' && $updatedCount > 0) {
                foreach ($tasks as $task) {
                    $taskUser = $task->getUser();
                    if ($taskUser && $taskUser->getId() !== $currentUser->getId()) {
                        try {
                            $this->notificationService->sendTaskCompletionNotification(
                                $taskUser,
                                $currentUser,
                                $task->getId(),
                                $task->getTitle(),
                            );
                        } catch (\Exception $e) {
                            $this->logger->warning('Failed to send notification', [
                                'task_id' => $task->getId(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Bulk status update failed', [
                'user_id' => $currentUser->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->info('Bulk status update completed', [
            'user_id' => $currentUser->getId(),
            'updated_count' => $updatedCount,
            'failed_count' => \count($failedTasks),
            'new_status' => $newStatus,
        ]);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_tasks' => $failedTasks,
        ];
    }

    /**
     * Bulk update task priorities
     */
    public function bulkUpdatePriority(array $taskIds, string $newPriority, User $currentUser): array
    {
        // Оптимизированный запрос с предзагрузкой пользователей
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();
        $updatedCount = 0;
        $failedTasks = [];

        foreach ($tasks as $task) {
            // Check if user has permission to edit this task
            if (!$this->hasEditPermission($task, $currentUser)) {
                $failedTasks[] = [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'reason' => 'Insufficient permissions',
                ];

                continue;
            }

            $task->setPriority($newPriority);
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Bulk priority update completed', [
            'user_id' => $currentUser->getId(),
            'updated_count' => $updatedCount,
            'failed_count' => \count($failedTasks),
            'new_priority' => $newPriority,
        ]);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_tasks' => $failedTasks,
        ];
    }

    /**
     * Bulk assign tasks to a user
     */
    public function bulkAssignToUser(array $taskIds, User $assignedUser, User $currentUser): array
    {
        // Оптимизированный запрос с предзагрузкой пользователей
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();
        $updatedCount = 0;
        $failedTasks = [];

        foreach ($tasks as $task) {
            // Check if user has permission to edit this task
            if (!$this->hasEditPermission($task, $currentUser)) {
                $failedTasks[] = [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'reason' => 'Insufficient permissions',
                ];

                continue;
            }

            $originalAssignedUser = $task->getAssignedUser();
            $task->setAssignedUser($assignedUser);

            $updatedCount++;

            // Send notification if assignment changed to a different user
            if ($assignedUser->getId() !== $originalAssignedUser?->getId() &&
                $assignedUser->getId() !== $currentUser->getId()) {
                $this->notificationService->sendTaskAssignmentNotification(
                    $assignedUser,
                    $currentUser,
                    $task->getId(),
                    $task->getTitle(),
                    $task->getPriority(),
                );
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Bulk task assignment completed', [
            'user_id' => $currentUser->getId(),
            'assigned_to_user_id' => $assignedUser->getId(),
            'updated_count' => $updatedCount,
            'failed_count' => \count($failedTasks),
        ]);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_tasks' => $failedTasks,
        ];
    }

    /**
     * Bulk delete tasks
     */
    public function bulkDelete(array $taskIds, User $currentUser): array
    {
        // Оптимизированный запрос с предзагрузкой пользователей
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();
        $deletedCount = 0;
        $failedTasks = [];

        $this->entityManager->beginTransaction();

        try {
            foreach ($tasks as $task) {
                // Check if user has permission to delete this task
                if (!$this->hasDeletePermission($task, $currentUser)) {
                    $failedTasks[] = [
                        'id' => $task->getId(),
                        'title' => $task->getTitle(),
                        'reason' => 'Insufficient permissions',
                    ];

                    continue;
                }

                $this->entityManager->remove($task);
                $deletedCount++;
            }

            if ($deletedCount > 0) {
                $this->entityManager->flush();
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Bulk task deletion failed', [
                'user_id' => $currentUser->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->info('Bulk task deletion completed', [
            'user_id' => $currentUser->getId(),
            'deleted_count' => $deletedCount,
            'failed_count' => \count($failedTasks),
        ]);

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'failed_tasks' => $failedTasks,
        ];
    }

    /**
     * Bulk add tags to tasks
     */
    public function bulkAddTags(array $taskIds, array $tagIds, User $currentUser): array
    {
        // Оптимизированный запрос с предзагрузкой пользователей и тегов
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t, u, au')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();

        $tags = $this->entityManager->getRepository(\App\Entity\Tag::class)->findBy(['id' => $tagIds]);

        $updatedCount = 0;
        $failedTasks = [];

        foreach ($tasks as $task) {
            // Check if user has permission to edit this task
            if (!$this->hasEditPermission($task, $currentUser)) {
                $failedTasks[] = [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'reason' => 'Insufficient permissions',
                ];

                continue;
            }

            foreach ($tags as $tag) {
                if (!$task->getTags()->contains($tag)) {
                    $task->addTag($tag);
                }
            }

            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Bulk tag addition completed', [
            'user_id' => $currentUser->getId(),
            'updated_count' => $updatedCount,
            'added_tags_count' => \count($tagIds),
            'failed_count' => \count($failedTasks),
        ]);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_tasks' => $failedTasks,
        ];
    }

    /**
     * Check if user has edit permission for a task
     */
    private function hasEditPermission(Task $task, User $user): bool
    {
        // User can edit if they are the creator or assigned user, or if they are admin
        $isAdmin = \in_array('ROLE_ADMIN', $user->getRoles());
        $taskUser = $task->getUser();
        $isOwner = $taskUser && $taskUser->getId() === $user->getId();
        $isAssigned = $task->getAssignedUser() && $task->getAssignedUser()->getId() === $user->getId();

        return $isAdmin || $isOwner || $isAssigned;
    }

    /**
     * Check if user has delete permission for a task
     */
    private function hasDeletePermission(Task $task, User $user): bool
    {
        // User can delete if they are the creator or if they are admin
        $isAdmin = \in_array('ROLE_ADMIN', $user->getRoles());
        $taskUser = $task->getUser();
        $isOwner = $taskUser && $taskUser->getId() === $user->getId();

        return $isAdmin || $isOwner;
    }
}
