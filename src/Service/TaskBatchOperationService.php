<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskBatchOperationService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Batch update status (оптимизировано)
     */
    public function batchUpdateStatus(array $taskIds, string $status): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        // Загружаем все задачи одним запросом
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setStatus($status);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch update priority (оптимизировано)
     */
    public function batchUpdatePriority(array $taskIds, string $priority): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setPriority($priority);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch assign to user (оптимизировано)
     */
    public function batchAssign(array $taskIds, User $user): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setAssignedUser($user);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch delete (оптимизировано)
     */
    public function batchDelete(array $taskIds): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $this->em->remove($task);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch add tags (оптимизировано)
     */
    public function batchAddTags(array $taskIds, array $tags): int
    {
        if (empty($taskIds) || empty($tags)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            foreach ($tags as $tag) {
                $task->addTag($tag);
            }
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch update deadline (оптимизировано)
     */
    public function batchUpdateDeadline(array $taskIds, \DateTime $deadline): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setDeadline($deadline);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch move to category (оптимизировано)
     */
    public function batchMoveToCategory(array $taskIds, $category): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setCategory($category);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Batch complete (оптимизировано)
     */
    public function batchComplete(array $taskIds): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        $now = new \DateTime();
        foreach ($tasks as $task) {
            $task->setStatus('completed');
            $task->setCompletedAt($now);
        }

        $this->em->flush();

        return \count($tasks);
    }

    /**
     * Get batch operation statistics
     */
    public function getStatistics(): array
    {
        // Получаем статистику из истории задач
        $qb = $this->em->createQueryBuilder();
        
        // Количество изменений статусов
        $statusChanges = (int) $qb->select('COUNT(h.id)')
            ->from(\App\Entity\TaskHistory::class, 'h')
            ->where('h.action = :action')
            ->setParameter('action', 'status_changed')
            ->getQuery()
            ->getSingleScalarResult();

        // Последняя операция
        $lastOp = $qb->select('h')
            ->from(\App\Entity\TaskHistory::class, 'h')
            ->where('h.action = :action')
            ->setParameter('action', 'status_changed')
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Самая частая операция
        $mostCommon = $qb->select('h.action, COUNT(h.id) as count')
            ->from(\App\Entity\TaskHistory::class, 'h')
            ->groupBy('h.action')
            ->orderBy('count', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_operations' => $statusChanges,
            'last_operation' => $lastOp ? [
                'action' => $lastOp->getAction(),
                'created_at' => $lastOp->getCreatedAt()->format('Y-m-d H:i:s'),
            ] : null,
            'most_common_operation' => $mostCommon ? $mostCommon['action'] : null,
        ];
    }
}
