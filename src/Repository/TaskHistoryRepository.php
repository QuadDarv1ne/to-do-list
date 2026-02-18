<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskHistory>
 */
class TaskHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskHistory::class);
    }

    public function save(TaskHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TaskHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Получить историю задачи
     */
    public function findByTask(Task $task, int $limit = 50): array
    {
        return $this->createQueryBuilder('th')
            ->andWhere('th.task = :task')
            ->setParameter('task', $task)
            ->orderBy('th.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить историю действий пользователя
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('th')
            ->andWhere('th.user = :user')
            ->setParameter('user', $user)
            ->orderBy('th.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить последние изменения
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('th')
            ->orderBy('th.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить статистику изменений за период
     */
    public function getStatsByPeriod(\DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('th')
            ->select('th.action, COUNT(th.id) as count')
            ->andWhere('th.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('th.action')
            ->getQuery()
            ->getResult();
    }

    /**
     * Очистить старую историю
     */
    public function deleteOlderThan(\DateTime $date): int
    {
        return $this->createQueryBuilder('th')
            ->delete()
            ->andWhere('th.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
