<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskAutomationService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    /**
     * Auto-assign tasks based on rules
     */
    public function autoAssignTasks(): int
    {
        $assigned = 0;

        // Get unassigned tasks
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser IS NULL')
            ->andWhere('t.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $user = $this->findBestAssignee($task);
            if ($user) {
                $task->setAssignedUser($user);
                $assigned++;
            }
        }

        $this->entityManager->flush();

        return $assigned;
    }

    /**
     * Find best assignee for task
     */
    private function findBestAssignee(Task $task): ?User
    {
        // TODO: Implement smart assignment logic
        // - Check user workload
        // - Check user skills/categories
        // - Check past performance
        // - Check availability
        
        return null;
    }

    /**
     * Auto-escalate overdue tasks
     */
    public function autoEscalateOverdueTasks(): int
    {
        $escalated = 0;
        $now = new \DateTime();

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->andWhere('t.priority != :urgent')
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->setParameter('urgent', 'urgent')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // Escalate priority
            $oldPriority = $task->getPriority();
            $newPriority = match($oldPriority) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'urgent',
                default => 'urgent'
            };

            $task->setPriority($newPriority);
            
            // Notify
            if ($task->getAssignedUser()) {
                $this->notificationService->notifyTaskEscalated($task, $oldPriority, $newPriority);
            }

            $escalated++;
        }

        $this->entityManager->flush();

        return $escalated;
    }

    /**
     * Auto-complete tasks with all subtasks done
     */
    public function autoCompleteParentTasks(): int
    {
        $completed = 0;

        // TODO: Implement when subtasks are added
        
        return $completed;
    }

    /**
     * Auto-archive old completed tasks
     */
    public function autoArchiveOldTasks(int $daysOld = 90): int
    {
        $archived = 0;
        $cutoffDate = new \DateTime("-{$daysOld} days");

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.status = :completed')
            ->andWhere('t.completedAt < :cutoff')
            ->setParameter('completed', 'completed')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // TODO: Move to archive table or set archived flag
            $archived++;
        }

        return $archived;
    }

    /**
     * Auto-update task status based on activity
     */
    public function autoUpdateStaleTaskStatus(): int
    {
        $updated = 0;
        $staleDate = new \DateTime('-7 days');

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.status = :in_progress')
            ->andWhere('t.updatedAt < :staleDate')
            ->setParameter('in_progress', 'in_progress')
            ->setParameter('staleDate', $staleDate)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // Mark as stale or pending
            $task->setStatus('pending');
            
            // Notify assignee
            if ($task->getAssignedUser()) {
                $this->notificationService->notifyTaskStale($task);
            }

            $updated++;
        }

        $this->entityManager->flush();

        return $updated;
    }

    /**
     * Create automation rule
     */
    public function createRule(array $config): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'name' => $config['name'],
            'trigger' => $config['trigger'],
            'conditions' => $config['conditions'],
            'actions' => $config['actions'],
            'enabled' => true,
            'created_at' => new \DateTime()
        ];
    }

    /**
     * Get automation rules
     */
    public function getRules(): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Execute automation rules
     */
    public function executeRules(): int
    {
        $executed = 0;

        $rules = $this->getRules();

        foreach ($rules as $rule) {
            if ($rule['enabled']) {
                $this->executeRule($rule);
                $executed++;
            }
        }

        return $executed;
    }

    /**
     * Execute single rule
     */
    private function executeRule(array $rule): void
    {
        // TODO: Implement rule execution engine
    }

    /**
     * Get automation statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_rules' => count($this->getRules()),
            'active_rules' => 0,
            'total_executions' => 0,
            'last_execution' => null
        ];
    }
}
