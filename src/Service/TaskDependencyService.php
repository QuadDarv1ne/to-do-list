<?php

namespace App\Service;

use App\Entity\Task;
use App\Repository\TaskRepository;

class TaskDependencyService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Add dependency (task depends on another task)
     */
    public function addDependency(Task $task, Task $dependsOn): bool
    {
        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($task, $dependsOn)) {
            return false;
        }

        // TODO: Save to database
        // For now, store in task metadata
        
        return true;
    }

    /**
     * Remove dependency
     */
    public function removeDependency(Task $task, Task $dependsOn): bool
    {
        // TODO: Remove from database
        return true;
    }

    /**
     * Get task dependencies
     */
    public function getDependencies(Task $task): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Get tasks that depend on this task
     */
    public function getDependents(Task $task): array
    {
        // TODO: Get from database
        return [];
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
    public function getBlockedTasks(): array
    {
        // TODO: Implement query
        return [];
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

        if (in_array($source->getId(), $visited)) {
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
            'level' => $level
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
            if (count($path) > $maxLength) {
                $maxLength = count($path);
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
        if ($this->canStart($task) && $task->getStatus() === 'pending') {
            // All dependencies completed, task can be started
            // TODO: Send notification
            return true;
        }

        return false;
    }

    /**
     * Get dependency statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_dependencies' => 0, // TODO: Count from database
            'blocked_tasks' => count($this->getBlockedTasks()),
            'circular_dependencies' => 0, // TODO: Detect
            'average_chain_length' => 0 // TODO: Calculate
        ];
    }
}
