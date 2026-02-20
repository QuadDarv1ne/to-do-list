<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Repository\TaskDependencyRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskDependencyService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private TaskDependencyRepository $dependencyRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ?NotificationService $notificationService = null,
    ) {
    }

    /**
     * Add dependency (task depends on another task)
     */
    public function addDependency(Task $task, Task $dependsOn, string $type = 'blocking'): bool
    {
        // Validate: task cannot depend on itself
        if ($task->getId() === $dependsOn->getId()) {
            $this->logger->warning('Task cannot depend on itself', [
                'task_id' => $task->getId(),
            ]);
            return false;
        }

        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($task, $dependsOn)) {
            $this->logger->warning('Circular dependency detected', [
                'task_id' => $task->getId(),
                'depends_on_id' => $dependsOn->getId(),
            ]);
            return false;
        }

        // Check if dependency already exists
        $existing = $this->dependencyRepository->findOneBy([
            'dependentTask' => $task,
            'dependencyTask' => $dependsOn,
        ]);

        if ($existing) {
            $this->logger->info('Dependency already exists', [
                'task_id' => $task->getId(),
                'depends_on_id' => $dependsOn->getId(),
            ]);
            return false;
        }

        // Create new dependency
        $dependency = new TaskDependency();
        $dependency->setDependentTask($task);
        $dependency->setDependencyTask($dependsOn);
        $dependency->setType($type);

        $this->entityManager->persist($dependency);
        $this->entityManager->flush();

        $this->logger->info('Dependency added successfully', [
            'task_id' => $task->getId(),
            'depends_on_id' => $dependsOn->getId(),
            'type' => $type,
        ]);

        return true;
    }

    /**
     * Remove dependency
     */
    public function removeDependency(Task $task, Task $dependsOn): bool
    {
        $dependency = $this->dependencyRepository->findOneBy([
            'dependentTask' => $task,
            'dependencyTask' => $dependsOn,
        ]);

        if (!$dependency) {
            return false;
        }

        $this->entityManager->remove($dependency);
        $this->entityManager->flush();

        $this->logger->info('Dependency removed', [
            'task_id' => $task->getId(),
            'depends_on_id' => $dependsOn->getId(),
        ]);

        return true;
    }

    /**
     * Get task dependencies (tasks that this task depends on)
     */
    public function getDependencies(Task $task): array
    {
        return $task->getDependencyTasks();
    }

    /**
     * Get tasks that depend on this task
     */
    public function getDependents(Task $task): array
    {
        return $task->getDependentTasks();
    }

    /**
     * Check if task can be started (all dependencies completed)
     */
    public function canStart(Task $task): bool
    {
        $dependencies = $this->getDependencies($task);

        foreach ($dependencies as $dependency) {
            if ($dependency->getStatus() !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get blocked tasks (tasks waiting for dependencies)
     */
    public function getBlockedTasks(?int $userId = null): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->innerJoin('t.dependencies', 'd')
            ->innerJoin('d.dependencyTask', 'dt')
            ->where('t.status != :completed')
            ->andWhere('dt.status != :completed')
            ->andWhere('d.type = :blocking')
            ->setParameter('completed', 'completed')
            ->setParameter('blocking', 'blocking')
            ->groupBy('t.id');

        if ($userId) {
            $qb->andWhere('t.user = :userId OR t.assignedUser = :userId')
               ->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check for circular dependencies
     */
    private function wouldCreateCircularDependency(Task $task, Task $dependsOn): bool
    {
        // Check if dependsOn already depends on task (directly or indirectly)
        $visited = [];

        return $this->hasPath($dependsOn, $task, $visited);
    }

    /**
     * Check if there's a path from source to target
     */
    private function hasPath(Task $source, Task $target, array &$visited): bool
    {
        if ($source->getId() === $target->getId()) {
            return true;
        }

        if (\in_array($source->getId(), $visited)) {
            return false;
        }

        $visited[] = $source->getId();

        $dependencies = $this->getDependencies($source);
        foreach ($dependencies as $dependency) {
            if ($this->hasPath($dependency, $target, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get dependency chain
     */
    public function getDependencyChain(Task $task): array
    {
        $chain = [];
        $this->buildChain($task, $chain, 0);

        return $chain;
    }

    /**
     * Build dependency chain recursively
     */
    private function buildChain(Task $task, array &$chain, int $level): void
    {
        $chain[] = [
            'task' => $task,
            'level' => $level,
        ];

        $dependencies = $this->getDependencies($task);
        foreach ($dependencies as $dependency) {
            $this->buildChain($dependency, $chain, $level + 1);
        }
    }

    /**
     * Get critical path (longest chain of dependencies)
     */
    public function getCriticalPath(Task $task): array
    {
        $allPaths = [];
        $this->findAllPaths($task, [], $allPaths);

        // Find longest path
        $criticalPath = [];
        $maxLength = 0;

        foreach ($allPaths as $path) {
            if (\count($path) > $maxLength) {
                $maxLength = \count($path);
                $criticalPath = $path;
            }
        }

        return $criticalPath;
    }

    /**
     * Find all paths from task to leaf nodes
     */
    private function findAllPaths(Task $task, array $currentPath, array &$allPaths): void
    {
        $currentPath[] = $task;
        $dependencies = $this->getDependencies($task);

        if (empty($dependencies)) {
            $allPaths[] = $currentPath;
        } else {
            foreach ($dependencies as $dependency) {
                $this->findAllPaths($dependency, $currentPath, $allPaths);
            }
        }
    }

    /**
     * Auto-update task status based on dependencies
     */
    public function autoUpdateStatus(Task $task): bool
    {
        if (!$this->canStart($task) || $task->getStatus() !== 'pending') {
            return false;
        }

        // All dependencies completed, notify assigned user
        if ($this->notificationService && $task->getAssignedUser()) {
            $this->notificationService->createTaskNotification(
                $task->getAssignedUser(),
                'Задача разблокирована',
                sprintf('Задача "%s" готова к выполнению - все зависимости завершены', $task->getTitle()),
                $task->getId(),
                $task->getTitle()
            );
        }

        $this->logger->info('Task unblocked - all dependencies satisfied', [
            'task_id' => $task->getId(),
        ]);

        return true;
    }

    /**
     * Check when task is completed and notify dependent tasks
     */
    public function notifyDependentTasks(Task $completedTask): void
    {
        $dependents = $this->getDependents($completedTask);

        foreach ($dependents as $dependentTask) {
            // Check if all dependencies are now satisfied
            if ($dependentTask->canStart() && $dependentTask->getStatus() === 'pending') {
                $this->autoUpdateStatus($dependentTask);
            }
        }
    }

    /**
     * Get dependency statistics
     */
    public function getStatistics(?int $userId = null): array
    {
        $qb = $this->dependencyRepository->createQueryBuilder('d');
        
        if ($userId) {
            $qb->innerJoin('d.dependentTask', 't')
               ->where('t.user = :userId OR t.assignedUser = :userId')
               ->setParameter('userId', $userId);
        }

        $totalDependencies = (int) $qb->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $blockedTasks = $this->getBlockedTasks($userId);
        
        // Calculate average chain length
        $avgChainLength = 0;
        if ($userId) {
            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->where('t.user = :userId OR t.assignedUser = :userId')
                ->setParameter('userId', $userId)
                ->getQuery()
                ->getResult();
        } else {
            $tasks = $this->taskRepository->findAll();
        }

        if (!empty($tasks)) {
            $totalChainLength = 0;
            foreach ($tasks as $task) {
                $chain = $this->getDependencyChain($task);
                $totalChainLength += count($chain);
            }
            $avgChainLength = round($totalChainLength / count($tasks), 2);
        }

        return [
            'total_dependencies' => $totalDependencies,
            'blocked_tasks' => count($blockedTasks),
            'average_chain_length' => $avgChainLength,
            'tasks_with_dependencies' => count($tasks),
        ];
    }

    /**
     * Get dependency graph data for visualization
     */
    public function getDependencyGraph(?int $userId = null): array
    {
        $qb = $this->dependencyRepository->createQueryBuilder('d')
            ->select('d, dt, dep')
            ->innerJoin('d.dependentTask', 'dt')
            ->innerJoin('d.dependencyTask', 'dep');

        if ($userId) {
            $qb->where('dt.user = :userId OR dt.assignedUser = :userId')
               ->setParameter('userId', $userId);
        }

        $dependencies = $qb->getQuery()->getResult();

        $nodes = [];
        $edges = [];

        foreach ($dependencies as $dependency) {
            $dependentTask = $dependency->getDependentTask();
            $dependencyTask = $dependency->getDependencyTask();

            // Add nodes
            if (!isset($nodes[$dependentTask->getId()])) {
                $nodes[$dependentTask->getId()] = [
                    'id' => $dependentTask->getId(),
                    'label' => $dependentTask->getTitle(),
                    'status' => $dependentTask->getStatus(),
                    'priority' => $dependentTask->getPriority(),
                ];
            }

            if (!isset($nodes[$dependencyTask->getId()])) {
                $nodes[$dependencyTask->getId()] = [
                    'id' => $dependencyTask->getId(),
                    'label' => $dependencyTask->getTitle(),
                    'status' => $dependencyTask->getStatus(),
                    'priority' => $dependencyTask->getPriority(),
                ];
            }

            // Add edge
            $edges[] = [
                'from' => $dependencyTask->getId(),
                'to' => $dependentTask->getId(),
                'type' => $dependency->getType(),
                'satisfied' => $dependency->isSatisfied(),
            ];
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }
}
