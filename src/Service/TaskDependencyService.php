<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing task dependencies and blocking logic
 */
class TaskDependencyService
{
    private EntityManagerInterface $entityManager;
    private TaskRepository $taskRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->logger = $logger;
    }

    /**
     * Add dependency between tasks
     */
    public function addDependency(Task $dependentTask, Task $dependencyTask): TaskDependency
    {
        // Check if dependency already exists
        if ($this->dependencyExists($dependentTask, $dependencyTask)) {
            throw new \InvalidArgumentException('Dependency already exists');
        }

        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($dependentTask, $dependencyTask)) {
            throw new \InvalidArgumentException('Cannot create circular dependency');
        }

        // Check if user has access to both tasks
        $currentUser = $dependentTask->getUser();
        if ($dependencyTask->getUser() !== $currentUser && 
            $dependencyTask->getAssignedUser() !== $currentUser) {
            throw new \InvalidArgumentException('No access to dependency task');
        }

        $dependency = new TaskDependency();
        $dependency->setDependentTask($dependentTask);
        $dependency->setDependencyTask($dependencyTask);
        $dependency->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($dependency);
        $this->entityManager->flush();

        $this->logger->info("Added dependency: Task {$dependentTask->getId()} depends on Task {$dependencyTask->getId()}");

        return $dependency;
    }

    /**
     * Remove dependency between tasks
     */
    public function removeDependency(Task $dependentTask, Task $dependencyTask): void
    {
        $dependency = $this->findDependency($dependentTask, $dependencyTask);
        
        if ($dependency) {
            $this->entityManager->remove($dependency);
            $this->entityManager->flush();
            
            $this->logger->info("Removed dependency: Task {$dependentTask->getId()} no longer depends on Task {$dependencyTask->getId()}");
        }
    }

    /**
     * Check if task can be started (all dependencies completed)
     */
    public function canStartTask(Task $task): bool
    {
        $dependencies = $this->getDependencies($task);
        
        foreach ($dependencies as $dependency) {
            if ($dependency->getDependencyTask()->getStatus() !== 'completed') {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if task can be completed (no dependent tasks in progress)
     */
    public function canCompleteTask(Task $task): bool
    {
        $dependents = $this->getDependents($task);
        
        foreach ($dependents as $dependent) {
            if ($dependent->getDependentTask()->getStatus() === 'in_progress') {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get all dependencies for a task
     */
    public function getDependencies(Task $task): array
    {
        return $this->entityManager->getRepository(TaskDependency::class)
            ->createQueryBuilder('td')
            ->select('td, dt')
            ->leftJoin('td.dependsOnTask', 'dt')
            ->where('td.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all tasks that depend on this task
     */
    public function getDependents(Task $task): array
    {
        return $this->entityManager->getRepository(TaskDependency::class)
            ->createQueryBuilder('td')
            ->select('td, t')
            ->leftJoin('td.task', 't')
            ->where('td.dependsOnTask = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get dependency chain for a task
     */
    public function getDependencyChain(Task $task): array
    {
        $chain = [];
        $this->buildDependencyChain($task, $chain, []);
        return $chain;
    }

    /**
     * Recursive helper to build dependency chain
     */
    private function buildDependencyChain(Task $task, array &$chain, array $visited): void
    {
        $taskId = $task->getId();
        
        if (in_array($taskId, $visited)) {
            return; // Prevent infinite loops
        }
        
        $visited[] = $taskId;
        $chain[$taskId] = [
            'task' => $task,
            'dependencies' => [],
            'dependents' => []
        ];
        
        // Get direct dependencies
        $dependencies = $this->getDependencies($task);
        foreach ($dependencies as $dependency) {
            $dependsOnTask = $dependency->getDependsOnTask();
            $chain[$taskId]['dependencies'][] = $dependsOnTask->getId();
            $this->buildDependencyChain($dependsOnTask, $chain, $visited);
        }
        
        // Get direct dependents
        $dependents = $this->getDependents($task);
        foreach ($dependents as $dependent) {
            $dependentTask = $dependent->getTask();
            $chain[$taskId]['dependents'][] = $dependentTask->getId();
        }
    }

    /**
     * Check if dependency already exists
     */
    private function dependencyExists(Task $dependentTask, Task $dependencyTask): bool
    {
        return $this->findDependency($dependentTask, $dependencyTask) !== null;
    }

    /**
     * Find existing dependency
     */
    private function findDependency(Task $dependentTask, Task $dependencyTask): ?TaskDependency
    {
        return $this->entityManager->getRepository(TaskDependency::class)
            ->findOneBy([
                'dependentTask' => $dependentTask,
                'dependencyTask' => $dependencyTask
            ]);
    }

    /**
     * Check if adding dependency would create circular dependency
     */
    private function wouldCreateCircularDependency(Task $dependentTask, Task $dependencyTask): bool
    {
        // Check if dependencyTask already depends on dependentTask (direct)
        if ($this->dependencyExists($dependencyTask, $dependentTask)) {
            return true;
        }

        // Check indirect circular dependencies
        $dependencyChain = $this->getDependencyChain($dependencyTask);
        return isset($dependencyChain[$dependentTask->getId()]);
    }

    /**
     * Get tasks that are blocking this task
     */
    public function getBlockingTasks(Task $task): array
    {
        $blockingTasks = [];
        $dependencies = $this->getDependencies($task);
        
        foreach ($dependencies as $dependency) {
            $dependsOnTask = $dependency->getDependsOnTask();
            if ($dependsOnTask->getStatus() !== 'completed') {
                $blockingTasks[] = $dependsOnTask;
            }
        }
        
        return $blockingTasks;
    }

    /**
     * Get tasks that this task is blocking
     */
    public function getBlockedTasks(Task $task): array
    {
        $blockedTasks = [];
        $dependents = $this->getDependents($task);
        
        foreach ($dependents as $dependent) {
            $dependentTask = $dependent->getTask();
            if ($dependentTask->getStatus() === 'pending' || $dependentTask->getStatus() === 'in_progress') {
                $blockedTasks[] = $dependentTask;
            }
        }
        
        return $blockedTasks;
    }

    /**
     * Auto-complete dependencies when task is completed
     */
    public function autoCompleteDependencies(Task $completedTask): void
    {
        $dependencies = $this->getDependencies($completedTask);
        
        foreach ($dependencies as $dependency) {
            $dependentTask = $dependency->getTask();
            
            // If dependent task is pending and all its dependencies are now complete,
            // we can auto-start it
            if ($dependentTask->getStatus() === 'pending' && $this->canStartTask($dependentTask)) {
                $dependentTask->setStatus('in_progress');
                $dependentTask->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($dependentTask);
                
                $this->logger->info("Auto-started task {$dependentTask->getId()} due to completed dependency");
            }
        }
        
        $this->entityManager->flush();
    }

    /**
     * Get dependency statistics for a user
     */
    public function getDependencyStatistics($user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $stats = $qb->select('
                COUNT(td.id) as total_dependencies,
                COUNT(CASE WHEN t.status != :completed THEN 1 END) as active_dependencies,
                COUNT(CASE WHEN dt.status = :completed THEN 1 END) as completed_dependencies
            ')
            ->from(TaskDependency::class, 'td')
            ->leftJoin('td.task', 't')
            ->leftJoin('td.dependsOnTask', 'dt')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleResult();

        return [
            'total_dependencies' => (int) $stats['total_dependencies'],
            'active_dependencies' => (int) $stats['active_dependencies'],
            'completed_dependencies' => (int) $stats['completed_dependencies'],
            'dependency_ratio' => $stats['total_dependencies'] > 0 ? 
                round(($stats['completed_dependencies'] / $stats['total_dependencies']) * 100, 1) : 0
        ];
    }
}