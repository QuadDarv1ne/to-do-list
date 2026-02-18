<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Repository\TaskDependencyRepository;
use App\Repository\TaskRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tasks/{taskId}/dependencies')]
#[IsGranted('ROLE_USER')]
class TaskDependencyController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'app_task_dependency_list', methods: ['GET'])]
    public function list(
        int $taskId,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_list');
        }
        
        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_list');
                }
            }
        }

        // Check if user can access this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_list');
                }
            }
        }

        $dependencies = $dependencyRepository->findDependenciesForTaskMinimal($task);
        
        $data = [];
        foreach ($dependencies as $dependency) {
            $data[] = [
                'id' => $dependency['id'],
                'dependent_task_id' => $dependency['dependent_task_id'],
                'dependency_task_id' => $dependency['dependency_task_id'],
                'dependency_task_name' => $dependency['dependency_task_name'],
                'type' => $dependency['type'],
                'is_satisfied' => $dependency['is_satisfied'],
                'created_at' => (new \DateTimeImmutable($dependency['created_at']))->format('c')
            ];
        }

        try {
            return $this->json($data);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_list');
            }
        }
    }

    #[Route('', name: 'app_task_dependency_create', methods: ['POST'])]
    public function add(
        int $taskId,
        Request $request,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_add');
        }
        
        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        // Check if user can modify this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        $data = json_decode($request->getContent(), true);
        $dependencyTaskId = $data['dependency_task_id'] ?? null;
        $type = $data['type'] ?? 'blocking';

        if (!$dependencyTaskId) {
            try {
                return new JsonResponse(['error' => 'Dependency task ID is required'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        $dependencyTask = $taskRepository->find($dependencyTaskId);
        if (!$dependencyTask) {
            try {
                return new JsonResponse(['error' => 'Dependency task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        // Prevent circular dependencies
        if ($dependencyTaskId == $taskId) {
            try {
                return new JsonResponse(['error' => 'Cannot create dependency on the same task'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        // Check for circular dependency
        if ($this->wouldCreateCircularDependency($task, $dependencyTask, $dependencyRepository)) {
            try {
                return new JsonResponse(['error' => 'This would create a circular dependency'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        // Check if dependency already exists
        if ($dependencyRepository->dependencyExists($task, $dependencyTask)) {
            try {
                return new JsonResponse(['error' => 'Dependency already exists'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_add');
                }
            }
        }

        $dependency = new TaskDependency();
        $dependency->setDependentTask($task);
        $dependency->setDependencyTask($dependencyTask);
        $dependency->setType($type);

        $entityManager->persist($dependency);
        $entityManager->flush();

        try {
            return $this->json([
                'id' => $dependency->getId(),
                'dependent_task_id' => $dependency->getDependentTask()->getId(),
                'dependency_task_id' => $dependency->getDependencyTask()->getId(),
                'dependency_task_name' => $dependency->getDependencyTask()->getName(),
                'type' => $dependency->getType(),
                'is_satisfied' => $dependency->isSatisfied(),
                'created_at' => $dependency->getCreatedAt()->format('c')
            ], 201);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_add');
            }
        }
    }

    #[Route('/{dependencyId}', name: 'app_task_dependency_remove', methods: ['DELETE'])]
    public function remove(
        int $taskId,
        int $dependencyId,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_remove');
        }
        
        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_remove');
                }
            }
        }

        // Check if user can modify this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_remove');
                }
            }
        }

        $dependency = $dependencyRepository->find($dependencyId);
        if (!$dependency || $dependency->getDependentTask()->getId() != $taskId) {
            try {
                return new JsonResponse(['error' => 'Dependency not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_remove');
                }
            }
        }

        $entityManager->remove($dependency);
        $entityManager->flush();

        try {
            return $this->json(['success' => true]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_remove');
            }
        }
    }

    #[Route('/check-start', name: 'app_task_dependency_check_start', methods: ['GET'])]
    public function checkCanStart(
        Request $request,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_check_can_start');
        }
        
        $taskId = (int) $request->query->get('taskId');
        if (!$taskId) {
            try {
                return new JsonResponse(['error' => 'Task ID is required'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_check_can_start');
                }
            }
        }

        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_check_can_start');
                }
            }
        }

        // Check if user can access this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_check_can_start');
                }
            }
        }

        $canStart = $task->canStart();
        $unsatisfiedDependencies = [];

        if (!$canStart) {
            $blockingDependencies = $dependencyRepository->getBlockingDependencies($task);
            foreach ($blockingDependencies as $dependency) {
                if (!$dependency->isSatisfied()) {
                    $unsatisfiedDependencies[] = [
                        'id' => $dependency->getId(),
                        'task_id' => $dependency->getDependencyTask()->getId(),
                        'task_name' => $dependency->getDependencyTask()->getName(),
                        'status' => $dependency->getDependencyTask()->getStatus()
                    ];
                }
            }
        }

        try {
            return $this->json([
                'can_start' => $canStart,
                'unsatisfied_dependencies' => $unsatisfiedDependencies
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_check_can_start');
            }
        }
    }

    #[Route('/stats', name: 'app_task_dependency_stats', methods: ['GET'])]
    public function getDependencyStats(
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_get_stats');
        }
        
        $user = $this->getUser();
        
        // Get dependency statistics for the user's tasks
        
        // Total dependencies for user's tasks
        $totalDependencies = $this->entityManager->createQueryBuilder()
            ->select('COUNT(td.id)')
            ->from(TaskDependency::class, 'td')
            ->join('td.dependentTask', 'dt')
            ->where('dt.assignedUser = :user OR dt.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Blocking dependencies
        $blockingDependencies = $this->entityManager->createQueryBuilder()
            ->select('COUNT(td.id)')
            ->from(TaskDependency::class, 'td')
            ->join('td.dependentTask', 'dt')
            ->where('dt.assignedUser = :user OR dt.user = :user')
            ->andWhere('td.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', 'blocking')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Satisfied dependencies (dependencies on completed tasks)
        $satisfiedDependencies = $this->entityManager->createQueryBuilder()
            ->select('COUNT(td.id)')
            ->from(TaskDependency::class, 'td')
            ->join('td.dependencyTask', 'dtt')
            ->where('dtt.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Unsatisfied dependencies (dependencies on incomplete tasks)
        $unsatisfiedDependencies = $this->entityManager->createQueryBuilder()
            ->select('COUNT(td.id)')
            ->from(TaskDependency::class, 'td')
            ->join('td.dependencyTask', 'dtt')
            ->where('dtt.status != :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
        
        try {
            return $this->json([
                'total_dependencies' => (int) $totalDependencies,
                'blocking_dependencies' => (int) $blockingDependencies,
                'satisfied_dependencies' => (int) $satisfiedDependencies,
                'unsatisfied_dependencies' => (int) $unsatisfiedDependencies
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_get_stats');
            }
        }
    }

    #[Route('/bulk/add', name: 'app_task_dependency_bulk_add', methods: ['POST'])]
    public function bulkAdd(
        int $taskId,
        Request $request,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_bulk_add');
        }
        
        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_add');
                }
            }
        }

        // Check if user can modify this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_add');
                }
            }
        }

        $data = json_decode($request->getContent(), true);
        $dependencyTaskIds = $data['dependency_task_ids'] ?? [];
        $type = $data['type'] ?? 'blocking';
        
        if (empty($dependencyTaskIds) || !is_array($dependencyTaskIds)) {
            try {
                return new JsonResponse(['error' => 'Dependency task IDs are required and must be an array'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_add');
                }
            }
        }

        $results = [];
        $errors = [];
        
        // Оптимизация: загружаем все задачи одним запросом
        $dependencyTasks = $taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $dependencyTaskIds)
            ->getQuery()
            ->getResult();
        
        $dependencyTasksById = [];
        foreach ($dependencyTasks as $dt) {
            $dependencyTasksById[$dt->getId()] = $dt;
        }
        
        foreach ($dependencyTaskIds as $dependencyTaskId) {
            $dependencyTask = $dependencyTasksById[$dependencyTaskId] ?? null;
            if (!$dependencyTask) {
                $errors[] = ['task_id' => $dependencyTaskId, 'error' => 'Dependency task not found'];
                continue;
            }

            // Prevent circular dependencies
            if ($dependencyTaskId == $taskId) {
                $errors[] = ['task_id' => $dependencyTaskId, 'error' => 'Cannot create dependency on the same task'];
                continue;
            }

            // Check for circular dependency
            if ($this->wouldCreateCircularDependency($task, $dependencyTask, $dependencyRepository)) {
                $errors[] = ['task_id' => $dependencyTaskId, 'error' => 'This would create a circular dependency'];
                continue;
            }

            // Check if dependency already exists
            if ($dependencyRepository->dependencyExists($task, $dependencyTask)) {
                $errors[] = ['task_id' => $dependencyTaskId, 'error' => 'Dependency already exists'];
                continue;
            }

            // Create the dependency
            $dependency = new TaskDependency();
            $dependency->setDependentTask($task);
            $dependency->setDependencyTask($dependencyTask);
            $dependency->setType($type);
            
            $entityManager->persist($dependency);
            
            $results[] = [
                'task_id' => $dependencyTaskId,
                'status' => 'created',
                'dependency_id' => null // Will be set after flush
            ];
        }

        if (!empty($results)) {
            $entityManager->flush();
            
            // Оптимизация: загружаем все зависимости одним запросом
            $dependencies = $dependencyRepository->createQueryBuilder('d')
                ->where('d.dependentTask = :task')
                ->andWhere('d.dependencyTask IN (:tasks)')
                ->setParameter('task', $task)
                ->setParameter('tasks', array_values($dependencyTasksById))
                ->getQuery()
                ->getResult();
            
            $dependenciesMap = [];
            foreach ($dependencies as $dep) {
                $dependenciesMap[$dep->getDependencyTask()->getId()] = $dep;
            }
            
            foreach ($results as &$result) {
                $dependency = $dependenciesMap[$result['task_id']] ?? null;
                if ($dependency) {
                    $result['dependency_id'] = $dependency->getId();
                }
            }
        }

        try {
            return $this->json([
                'results' => $results,
                'errors' => $errors,
                'created_count' => count($results),
                'error_count' => count($errors)
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_bulk_add');
            }
        }
    }
    
    #[Route('/bulk/remove', name: 'app_task_dependency_bulk_remove', methods: ['DELETE'])]
    public function bulkRemove(
        int $taskId,
        Request $request,
        TaskRepository $taskRepository,
        TaskDependencyRepository $dependencyRepository,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_dependency_controller_bulk_remove');
        }
        
        $task = $taskRepository->find($taskId);
        
        if (!$task) {
            try {
                return new JsonResponse(['error' => 'Task not found'], 404);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_remove');
                }
            }
        }

        // Check if user can modify this task
        if ($task->getAssignedTo() !== $this->getUser() && $task->getCreatedBy() !== $this->getUser()) {
            try {
                return new JsonResponse(['error' => 'Access denied'], 403);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_remove');
                }
            }
        }

        $data = json_decode($request->getContent(), true);
        $dependencyIds = $data['dependency_ids'] ?? [];
        
        if (empty($dependencyIds) || !is_array($dependencyIds)) {
            try {
                return new JsonResponse(['error' => 'Dependency IDs are required and must be an array'], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_dependency_controller_bulk_remove');
                }
            }
        }

        $results = [];
        $errors = [];
        
        // Оптимизация: загружаем все зависимости одним запросом
        $dependencies = $dependencyRepository->createQueryBuilder('d')
            ->where('d.id IN (:ids)')
            ->setParameter('ids', $dependencyIds)
            ->getQuery()
            ->getResult();
        
        $dependenciesById = [];
        foreach ($dependencies as $dep) {
            $dependenciesById[$dep->getId()] = $dep;
        }
        
        foreach ($dependencyIds as $dependencyId) {
            $dependency = $dependenciesById[$dependencyId] ?? null;
            
            if (!$dependency) {
                $errors[] = ['dependency_id' => $dependencyId, 'error' => 'Dependency not found'];
                continue;
            }
            
            // Ensure this dependency belongs to the task we're modifying
            if ($dependency->getDependentTask()->getId() !== $taskId) {
                $errors[] = ['dependency_id' => $dependencyId, 'error' => 'Dependency does not belong to this task'];
                continue;
            }
            
            $entityManager->remove($dependency);
            $results[] = [
                'dependency_id' => $dependencyId,
                'status' => 'removed'
            ];
        }
        
        if (!empty($results)) {
            $entityManager->flush();
        }

        try {
            return $this->json([
                'results' => $results,
                'errors' => $errors,
                'removed_count' => count($results),
                'error_count' => count($errors)
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_dependency_controller_bulk_remove');
            }
        }
    }

    /**
     * Check if adding a dependency would create a circular dependency
     * Uses DFS algorithm to detect cycles in the dependency graph
     */
    private function wouldCreateCircularDependency(
        Task $task,
        Task $dependencyTask,
        TaskDependencyRepository $dependencyRepository
    ): bool {
        // If the dependency task is the same as the current task, it's a circular dependency
        if ($task->getId() === $dependencyTask->getId()) {
            return true;
        }
        
        // Use DFS to check if there's a path from dependencyTask to task
        $visited = [];
        return $this->dfsCheckCircular($dependencyTask, $task, $dependencyRepository, $visited);
    }
    
    /**
     * Depth-first search to check for circular dependencies
     */
    private function dfsCheckCircular(
        Task $currentTask,
        Task $targetTask,
        TaskDependencyRepository $dependencyRepository,
        array &$visited
    ): bool {
        // Mark current task as visited
        $visited[$currentTask->getId()] = true;
        
        // Get all dependencies of the current task
        $dependencies = $dependencyRepository->findDependenciesForTask($currentTask);
        
        foreach ($dependencies as $dependency) {
            $dependencyTask = $dependency->getDependencyTask();
            
            // If we found the target task, it's a circular dependency
            if ($dependencyTask->getId() === $targetTask->getId()) {
                return true;
            }
            
            // If this task hasn't been visited yet, check its dependencies
            if (!isset($visited[$dependencyTask->getId()])) {
                if ($this->dfsCheckCircular($dependencyTask, $targetTask, $dependencyRepository, $visited)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
