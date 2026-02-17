<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskBatchOperationService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Batch update status
     */
    public function batchUpdateStatus(array $taskIds, string $status): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setStatus($status);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch update priority
     */
    public function batchUpdatePriority(array $taskIds, string $priority): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setPriority($priority);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch assign to user
     */
    public function batchAssign(array $taskIds, User $user): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setAssignedUser($user);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch delete
     */
    public function batchDelete(array $taskIds): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $this->entityManager->remove($task);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch add tags
     */
    public function batchAddTags(array $taskIds, array $tags): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                foreach ($tags as $tag) {
                    $task->addTag($tag);
                }
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch update deadline
     */
    public function batchUpdateDeadline(array $taskIds, \DateTime $deadline): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setDeadline($deadline);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch move to category
     */
    public function batchMoveToCategory(array $taskIds, $category): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setCategory($category);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Batch complete
     */
    public function batchComplete(array $taskIds): int
    {
        $count = 0;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $task->setStatus('completed');
                $task->setCompletedAt(new \DateTime());
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Get batch operation statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_operations' => 0, // TODO: Track in database
            'last_operation' => null,
            'most_common_operation' => null
        ];
    }
}
