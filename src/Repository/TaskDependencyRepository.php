<?php

namespace App\Repository;

use App\Entity\TaskDependency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskDependency>
 */
class TaskDependencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskDependency::class);
    }

    /**
     * Find dependencies for a task
     */
    public function findDependenciesForTask($task): array
    {
        return $this->createQueryBuilder('td')
            ->andWhere('td.dependentTask = :task')
            ->setParameter('task', $task)
            ->orderBy('td.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
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