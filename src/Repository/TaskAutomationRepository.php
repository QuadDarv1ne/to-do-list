<?php

namespace App\Repository;

use App\Entity\TaskAutomation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskAutomation>
 */
class TaskAutomationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskAutomation::class);
    }

    public function save(TaskAutomation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TaskAutomation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти активные автоматизации по триггеру
     */
    public function findActiveByTrigger(string $trigger): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.trigger = :trigger')
            ->andWhere('a.isActive = :active')
            ->setParameter('trigger', $trigger)
            ->setParameter('active', true)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить все активные автоматизации
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить статистику выполнения
     */
    public function getExecutionStats(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.name, a.trigger, a.executionCount, a.lastExecutedAt')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.executionCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
