<?php

namespace App\Repository;

use App\Entity\TaskDependency;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskDependency>
 */
class TaskDependencyRepository extends ServiceEntityRepository
{
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskDependency::class);
    }

    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }
        
    /**
     * Find dependencies for a task with optimized query including related entities
     */
    public function findDependenciesForTask($task): array
    {
        return $this->createQueryBuilder('td')
            ->leftJoin('td.dependentTask', 'depTask')
            ->leftJoin('td.dependencyTask', 'dtTask')
            ->andWhere('td.dependentTask = :task')
            ->setParameter('task', $task)
            ->orderBy('td.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
        
    /**
     * Find dependencies for a task with minimal data for API responses
     */
    public function findDependenciesForTaskMinimal($task): array
    {
        return $this->createQueryBuilder('td')
            ->select([
                'td.id as id',
                'td.type as type',
                'td.createdAt as created_at',
                'td.satisfied as is_satisfied',
                'depTask.id as dependent_task_id',
                'dtTask.id as dependency_task_id',
                'dtTask.title as dependency_task_name',
                'dtTask.status as dependency_task_status'
            ])
            ->leftJoin('td.dependentTask', 'depTask')
            ->leftJoin('td.dependencyTask', 'dtTask')
            ->andWhere('td.dependentTask = :task')
            ->setParameter('task', $task)
            ->orderBy('td.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Find tasks that depend on a given task
     */
    public function findDependentsOfTask($task): array
    {
        return $this->createQueryBuilder('td')
            ->andWhere('td.dependencyTask = :task')
            ->setParameter('task', $task)
            ->orderBy('td.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if dependency already exists
     */
    public function dependencyExists($dependentTask, $dependencyTask): bool
    {
        return $this->createQueryBuilder('td')
            ->select('COUNT(td.id)')
            ->andWhere('td.dependentTask = :dependentTask')
            ->andWhere('td.dependencyTask = :dependencyTask')
            ->setParameter('dependentTask', $dependentTask)
            ->setParameter('dependencyTask', $dependencyTask)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Get all blocking dependencies for a task
     */
    public function getBlockingDependencies($task): array
    {
        return $this->createQueryBuilder('td')
            ->andWhere('td.dependentTask = :task')
            ->andWhere('td.type = :type')
            ->setParameter('task', $task)
            ->setParameter('type', 'blocking')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if task has unsatisfied blocking dependencies
     */
    public function hasUnsatisfiedBlockingDependencies($task): bool
    {
        $blockingDependencies = $this->getBlockingDependencies($task);
        
        foreach ($blockingDependencies as $dependency) {
            if (!$dependency->isSatisfied()) {
                return true;
            }
        }
        
        return false;
    }
}
