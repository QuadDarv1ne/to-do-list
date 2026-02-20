<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class PerformanceMetricsService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Get user performance metrics
     */
    public function getUserMetrics(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'productivity_score' => $this->calculateProductivityScore($user, $from, $to),
            'efficiency_score' => $this->calculateEfficiencyScore($user, $from, $to),
            'quality_score' => $this->calculateQualityScore($user, $from, $to),
            'velocity' => $this->calculateVelocity($user, $from, $to),
            'completion_rate' => $this->getCompletionRate($user, $from, $to),
            'average_task_time' => $this->getAverageTaskTime($user, $from, $to),
            'tasks_completed' => $this->getTasksCompleted($user, $from, $to),
            'tasks_created' => $this->getTasksCreated($user, $from, $to),
            'overdue_tasks' => $this->getOverdueTasks($user, $from, $to),
            'on_time_completion' => $this->getOnTimeCompletion($user, $from, $to),
        ];
    }

    /**
     * Calculate productivity score (0-100)
     */
    private function calculateProductivityScore(User $user, \DateTime $from, \DateTime $to): float
    {
        $completed = $this->getTasksCompleted($user, $from, $to);
        $created = $this->getTasksCreated($user, $from, $to);
        $overdue = $this->getOverdueTasks($user, $from, $to);

        if ($created === 0) {
            return 0;
        }

        $completionRate = ($completed / $created) * 100;
        $overdueRate = ($overdue / $created) * 100;

        return max(0, min(100, $completionRate - $overdueRate));
    }

    /**
     * Calculate efficiency score (0-100)
     */
    private function calculateEfficiencyScore(User $user, \DateTime $from, \DateTime $to): float
    {
        $avgTime = $this->getAverageTaskTime($user, $from, $to);
        $onTimeRate = $this->getOnTimeCompletion($user, $from, $to);

        // Lower average time and higher on-time rate = higher efficiency
        $timeScore = max(0, 100 - ($avgTime / 24)); // Assuming 24 hours is baseline
        $onTimeScore = $onTimeRate;

        return ($timeScore + $onTimeScore) / 2;
    }

    /**
     * Calculate quality score (0-100)
     */
    private function calculateQualityScore(User $user, \DateTime $from, \DateTime $to): float
    {
        // Factor in reopened tasks, comments, revisions
        $onTimeRate = $this->getOnTimeCompletion($user, $from, $to);
        
        // Получаем количество переоткрытых задач
        $reopened = $this->getReopenedTasksCount($user, $from, $to);
        $completed = $this->getTasksCompleted($user, $from, $to);
        
        $reopenPenalty = $completed > 0 ? ($reopened / $completed) * 20 : 0;
        
        return max(0, min(100, $onTimeRate - $reopenPenalty));
    }
    
    private function getReopenedTasksCount(User $user, \DateTime $from, \DateTime $to): int
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('COUNT(h.id)')
            ->from(\App\Entity\TaskHistory::class, 'h')
            ->join('h.task', 't')
            ->andWhere('t.assignedUser = :user')
            ->andWhere('h.action = :action')
            ->andWhere('h.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('action', 'reopened')
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Calculate velocity (tasks per day)
     */
    private function calculateVelocity(User $user, \DateTime $from, \DateTime $to): float
    {
        $completed = $this->getTasksCompleted($user, $from, $to);
        $days = max(1, $from->diff($to)->days);

        return round($completed / $days, 2);
    }

    /**
     * Get completion rate
     */
    private function getCompletionRate(User $user, \DateTime $from, \DateTime $to): float
    {
        $created = $this->getTasksCreated($user, $from, $to);
        if ($created === 0) {
            return 0;
        }

        $completed = $this->getTasksCompleted($user, $from, $to);

        return round(($completed / $created) * 100, 2);
    }

    /**
     * Get average task time (in hours)
     */
    private function getAverageTaskTime(User $user, \DateTime $from, \DateTime $to): float
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('AVG(t.completedAt - t.createdAt) as avg_time')
            ->from(\App\Entity\Task::class, 't')
            ->andWhere('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        
        $result = $qb->getQuery()->getOneOrNullResult();
        $avgSeconds = (float) ($result['avg_time'] ?? 0);
        
        // Конвертируем секунды в часы
        return round($avgSeconds / 3600, 2);
    }

    /**
     * Get tasks completed
     */
    private function getTasksCompleted(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get tasks created
     */
    private function getTasksCreated(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get overdue tasks
     */
    private function getOverdueTasks(User $user, \DateTime $from, \DateTime $to): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get on-time completion rate
     */
    private function getOnTimeCompletion(User $user, \DateTime $from, \DateTime $to): float
    {
        $completed = $this->getTasksCompleted($user, $from, $to);
        if ($completed === 0) {
            return 0;
        }

        // Count tasks completed before deadline
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('COUNT(t.id)')
            ->from(\App\Entity\Task::class, 't')
            ->andWhere('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt <= t.dueDate')
            ->andWhere('t.completedAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        
        $onTime = (int) $qb->getQuery()->getSingleScalarResult();

        return round(($onTime / $completed) * 100, 2);
    }

    /**
     * Get team metrics
     */
    public function getTeamMetrics(array $userIds, \DateTime $from, \DateTime $to): array
    {
        $teamMetrics = [];
        $userRepository = $this->em->getRepository(User::class);

        foreach ($userIds as $userId) {
            $user = $userRepository->find($userId);
            if ($user) {
                $teamMetrics[$userId] = $this->getUserMetrics($user, $from, $to);
            }
        }

        return [
            'members' => $teamMetrics,
            'team_average' => $this->calculateTeamAverage($teamMetrics),
            'top_performer' => $this->getTopPerformer($teamMetrics),
            'total_completed' => array_sum(array_column($teamMetrics, 'tasks_completed')),
        ];
    }

    /**
     * Calculate team average
     */
    private function calculateTeamAverage(array $teamMetrics): array
    {
        if (empty($teamMetrics)) {
            return [];
        }

        $count = \count($teamMetrics);

        return [
            'productivity_score' => array_sum(array_column($teamMetrics, 'productivity_score')) / $count,
            'efficiency_score' => array_sum(array_column($teamMetrics, 'efficiency_score')) / $count,
            'quality_score' => array_sum(array_column($teamMetrics, 'quality_score')) / $count,
            'velocity' => array_sum(array_column($teamMetrics, 'velocity')) / $count,
        ];
    }

    /**
     * Get top performer
     */
    private function getTopPerformer(array $teamMetrics): ?int
    {
        if (empty($teamMetrics)) {
            return null;
        }

        $topScore = 0;
        $topUserId = null;

        foreach ($teamMetrics as $userId => $metrics) {
            $score = $metrics['productivity_score'];
            if ($score > $topScore) {
                $topScore = $score;
                $topUserId = $userId;
            }
        }

        return $topUserId;
    }

    /**
     * Get performance trend
     */
    public function getPerformanceTrend(User $user, int $weeks = 4): array
    {
        $trend = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $from = new \DateTime("-$i weeks monday");
            $to = new \DateTime("-$i weeks sunday");

            $metrics = $this->getUserMetrics($user, $from, $to);
            $trend[] = [
                'week' => $from->format('Y-W'),
                'productivity_score' => $metrics['productivity_score'],
                'tasks_completed' => $metrics['tasks_completed'],
            ];
        }

        return $trend;
    }

    /**
     * Get performance comparison
     */
    public function comparePerformance(User $user1, User $user2, \DateTime $from, \DateTime $to): array
    {
        return [
            'user1' => $this->getUserMetrics($user1, $from, $to),
            'user2' => $this->getUserMetrics($user2, $from, $to),
            'winner' => $this->determineWinner($user1, $user2, $from, $to),
        ];
    }

    /**
     * Determine winner
     */
    private function determineWinner(User $user1, User $user2, \DateTime $from, \DateTime $to): int
    {
        $metrics1 = $this->getUserMetrics($user1, $from, $to);
        $metrics2 = $this->getUserMetrics($user2, $from, $to);

        $score1 = $metrics1['productivity_score'];
        $score2 = $metrics2['productivity_score'];

        return $score1 > $score2 ? $user1->getId() : $user2->getId();
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(array $userIds, \DateTime $from, \DateTime $to, int $limit = 10): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('u.id as user_id, u.username, COUNT(t.id) as completed_count')
            ->from(\App\Entity\User::class, 'u')
            ->leftJoin('u.tasks', 't')
            ->andWhere('t.assignedUser = u')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt BETWEEN :from AND :to')
            ->setParameter('completed', 'completed')
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        
        if (!empty($userIds)) {
            $qb->andWhere('u.id IN (:userIds)')
                ->setParameter('userIds', $userIds);
        }
        
        $qb->groupBy('u.id', 'u.username')
            ->orderBy('completed_count', 'DESC')
            ->setMaxResults($limit);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get achievements
     */
    public function getAchievements(User $user): array
    {
        $achievements = [];
        $totalCompleted = $this->getTotalTasksCompleted($user);
        
        // Достижения за количество выполненных задач
        if ($totalCompleted >= 100) {
            $achievements[] = [
                'id' => 'century',
                'name' => 'Столетие',
                'description' => 'Выполнить 100 задач',
                'icon' => 'fa-trophy',
                'unlocked' => true,
            ];
        }
        
        if ($totalCompleted >= 50) {
            $achievements[] = [
                'id' => 'half_century',
                'name' => 'Полвека',
                'description' => 'Выполнить 50 задач',
                'icon' => 'fa-medal',
                'unlocked' => true,
            ];
        }
        
        if ($totalCompleted >= 10) {
            $achievements[] = [
                'id' => 'first_steps',
                'name' => 'Первые шаги',
                'description' => 'Выполнить 10 задач',
                'icon' => 'fa-star',
                'unlocked' => true,
            ];
        }
        
        // Достижения за серию дней
        $streak = $this->calculateStreak($user);
        if ($streak >= 30) {
            $achievements[] = [
                'id' => 'month_streak',
                'name' => 'Месяц продуктивности',
                'description' => '30 дней подряд с выполненными задачами',
                'icon' => 'fa-fire',
                'unlocked' => true,
            ];
        }
        
        if ($streak >= 7) {
            $achievements[] = [
                'id' => 'week_streak',
                'name' => 'Неделя продуктивности',
                'description' => '7 дней подряд с выполненными задачами',
                'icon' => 'fa-bolt',
                'unlocked' => true,
            ];
        }
        
        return $achievements;
    }
    
    private function getTotalTasksCompleted(User $user): int
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('COUNT(t.id)')
            ->from(\App\Entity\Task::class, 't')
            ->andWhere('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    private function calculateStreak(User $user): int
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('t.completedAt')
            ->from(\App\Entity\Task::class, 't')
            ->andWhere('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt >= :dateFrom')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('dateFrom', (new \DateTime())->modify('-90 days'))
            ->orderBy('t.completedAt', 'DESC');
        
        $completedDates = $qb->getQuery()->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        
        if (empty($completedDates)) {
            return 0;
        }
        
        $streak = 0;
        $currentDate = new \DateTime();
        
        foreach ($completedDates as $task) {
            $taskDate = new \DateTime($task['completedAt']);
            $expectedDate = (clone $currentDate)->modify("-$streak days");
            
            if ($taskDate->format('Y-m-d') === $expectedDate->format('Y-m-d')) {
                $streak++;
            }
        }
        
        return $streak;
    }

    /**
     * Export metrics to CSV
     */
    public function exportMetricsToCSV(array $metrics): string
    {
        $csv = "Метрика,Значение\n";

        foreach ($metrics as $key => $value) {
            if (is_numeric($value)) {
                $csv .= "$key,$value\n";
            }
        }

        return $csv;
    }
}
