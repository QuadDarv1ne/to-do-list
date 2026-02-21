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

    /**
     * Получить логи за последние N дней
     */
    public function findLastDays(Habit $habit, int $days = 30): array
    {
        $startDate = (new \DateTime())->modify("-$days days");

        return $this->createQueryBuilder('hl')
            ->where('hl.habit = :habit')
            ->andWhere('hl.date >= :startDate')
            ->setParameter('habit', $habit)
            ->setParameter('startDate', $startDate)
            ->orderBy('hl.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить общую статистику по привычке
     */
    public function getHabitStats(Habit $habit): array
    {
        $qb = $this->createQueryBuilder('hl');

        $qb->select('
            COUNT(hl.id) as total_completions,
            SUM(hl.count) as total_count,
            MIN(hl.date) as first_date,
            MAX(hl.date) as last_date
        ')
            ->where('hl.habit = :habit')
            ->setParameter('habit', $habit);

        $result = $qb->getQuery()->getOneOrNullResult();

        return [
            'totalCompletions' => (int) ($result['total_completions'] ?? 0),
            'totalCount' => (int) ($result['total_count'] ?? 0),
            'firstDate' => $result['first_date'] ? new \DateTime($result['first_date']) : null,
            'lastDate' => $result['last_date'] ? new \DateTime($result['last_date']) : null,
        ];
    }

    /**
     * Получить статистику за период
     */
    public function getStatsForPeriod(Habit $habit, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('hl');

        $qb->select('
            COUNT(hl.id) as completions,
            SUM(hl.count) as total_count
        ')
            ->where('hl.habit = :habit')
            ->andWhere('hl.date BETWEEN :startDate AND :endDate')
            ->setParameter('habit', $habit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $result = $qb->getQuery()->getOneOrNullResult();

        return [
            'completions' => (int) ($result['completions'] ?? 0),
            'totalCount' => (int) ($result['total_count'] ?? 0),
        ];
    }

    /**
     * Получить текущую серию (streak)
     */
    public function getCurrentStreak(Habit $habit): int
    {
        $logs = $this->findLastDays($habit, 365);
        
        $streak = 0;
        $currentDate = new \DateTime();
        $currentDate->setTime(0, 0, 0);

        foreach ($logs as $log) {
            $logDate = clone $log->getDate();
            $logDate->setTime(0, 0, 0);

            $diff = $currentDate->diff($logDate)->days;

            if ($diff === $streak) {
                $streak++;
                $currentDate->modify('-1 day');
            } elseif ($diff > $streak) {
                // Пропущен день, серия прерывается
                break;
            }
        }

        return $streak;
    }

    /**
     * Получить лучшую серию
     */
    public function getLongestStreak(Habit $habit): int
    {
        $logs = $this->createQueryBuilder('hl')
            ->where('hl.habit = :habit')
            ->orderBy('hl.date', 'ASC')
            ->setParameter('habit', $habit)
            ->getQuery()
            ->getResult();

        $maxStreak = 0;
        $currentStreak = 0;
        $previousDate = null;

        foreach ($logs as $log) {
            $logDate = clone $log->getDate();
            $logDate->setTime(0, 0, 0);

            if ($previousDate === null) {
                $currentStreak = 1;
            } else {
                $diff = $previousDate->diff($logDate)->days;
                if ($diff === 1) {
                    $currentStreak++;
                } else {
                    $maxStreak = max($maxStreak, $currentStreak);
                    $currentStreak = 1;
                }
            }

            $previousDate = $logDate;
        }

        return max($maxStreak, $currentStreak);
    }

    /**
     * Получить процент выполнения за период
     */
    public function getCompletionRate(Habit $habit, int $days = 30): float
    {
        $startDate = (new \DateTime())->modify("-$days days");
        
        $stats = $this->getStatsForPeriod($habit, $startDate, new \DateTime());
        
        return $days > 0 ? ($stats['completions'] / $days) * 100 : 0;
    }

    /**
     * Создать или обновить лог
     */
    public function logCompletion(Habit $habit, \DateTimeInterface $date, int $count = 1, ?string $note = null): HabitLog
    {
        $log = $this->findByHabitAndDate($habit, $date);

        if (!$log) {
            $log = new HabitLog();
            $log->setHabit($habit);
            $log->setDate($date);
            $log->setCount($count);
            $log->setNote($note);
        } else {
            $log->setCount($log->getCount() + $count);
            if ($note) {
                $log->setNote($note);
            }
        }

        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();

        return $log;
    }

    /**
     * Удалить лог
     */
    public function deleteLog(HabitLog $log): void
    {
        $this->getEntityManager()->remove($log);
        $this->getEntityManager()->flush();
    }

    /**
     * Получить логи по пользователю
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('hl')
            ->innerJoin('hl.habit', 'h')
            ->where('h.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('hl.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
