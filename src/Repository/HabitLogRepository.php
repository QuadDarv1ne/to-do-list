<?php

namespace App\Repository;

use App\Entity\Habit;
use App\Entity\HabitLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HabitLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HabitLog::class);
    }

    public function findByHabitAndDate(Habit $habit, \DateTimeInterface $date): ?HabitLog
    {
        return $this->createQueryBuilder('hl')
            ->where('hl.habit = :habit')
            ->andWhere('hl.date = :date')
            ->setParameter('habit', $habit)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByHabitAndDateRange(Habit $habit, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('hl')
            ->where('hl.habit = :habit')
            ->andWhere('hl.date BETWEEN :startDate AND :endDate')
            ->setParameter('habit', $habit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('hl.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
