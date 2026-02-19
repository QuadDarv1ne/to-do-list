<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TaskRepository;

/**
 * Dashboard Statistics Service
 * Сбор и расчёт статистики для дашборда
 */
class DashboardStatisticsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaskRepository $taskRepository
    ) {
    }

    /**
     * Получить полную статистику для дашборда
     */
    public function getDashboardStats(\App\Entity\User $user): array
    {
        return [
            'summary' => $this->getSummaryStats($user),
            'tasksByStatus' => $this->getTasksByStatus($user),
            'tasksByPriority' => $this->getTasksByPriority($user),
            'completedTasksTrend' => $this->getCompletedTasksTrend($user),
            'productivityStats' => $this->getProductivityStats($user),
            'upcomingDeadlines' => $this->getUpcomingDeadlines($user),
            'recentActivity' => $this->getRecentActivity($user)
        ];
    }

    /**
     * Краткая сводка
     */
    private function getSummaryStats(\App\Entity\User $user): array
    {
        $conn = $this->em->getConnection();
        
        $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN due_date < DATE('now') AND status != 'completed' THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN priority = 'high' AND status != 'completed' THEN 1 ELSE 0 END) as high_priority
            FROM tasks 
            WHERE user_id = :user_id";
        
        $result = $conn->fetchAssociative($sql, ['user_id' => $user->getId()]);
        
        $total = (int)($result['total'] ?? 0);
        $completed = (int)($result['completed'] ?? 0);
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => (int)($result['pending'] ?? 0),
            'in_progress' => (int)($result['in_progress'] ?? 0),
            'overdue' => (int)($result['overdue'] ?? 0),
            'high_priority' => (int)($result['high_priority'] ?? 0),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0
        ];
    }

    /**
     * Задачи по статусам
     */
    private function getTasksByStatus(\App\Entity\User $user): array
    {
        $conn = $this->em->getConnection();
        
        $sql = "SELECT status, COUNT(*) as count 
                FROM tasks 
                WHERE user_id = :user_id 
                GROUP BY status";
        
        $results = $conn->fetchAllAssociative($sql, ['user_id' => $user->getId()]);
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['status']] = (int)$row['count'];
        }
        
        return $data;
    }

    /**
     * Задачи по приоритетам
     */
    private function getTasksByPriority(\App\Entity\User $user): array
    {
        $conn = $this->em->getConnection();
        
        $sql = "SELECT priority, COUNT(*) as count 
                FROM tasks 
                WHERE user_id = :user_id 
                GROUP BY priority";
        
        $results = $conn->fetchAllAssociative($sql, ['user_id' => $user->getId()]);
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['priority']] = (int)$row['count'];
        }
        
        return $data;
    }

    /**
     * Тренд выполнения задач (последние 7 дней)
     */
    private function getCompletedTasksTrend(\App\Entity\User $user): array
    {
        $conn = $this->em->getConnection();
        
        $sql = "SELECT 
                DATE(completed_at) as date,
                COUNT(*) as count
                FROM tasks 
                WHERE user_id = :user_id 
                AND status = 'completed'
                AND completed_at >= DATE('now', '-7 days')
                GROUP BY DATE(completed_at)
                ORDER BY date ASC";
        
        $results = $conn->fetchAllAssociative($sql, ['user_id' => $user->getId()]);
        
        // Заполняем все дни за последнюю неделю
        $trend = [];
        $now = new \DateTime();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = clone $now;
            $date->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            
            $count = 0;
            foreach ($results as $row) {
                if ($row['date'] === $dateStr) {
                    $count = (int)$row['count'];
                    break;
                }
            }
            
            $trend[] = [
                'date' => $dateStr,
                'label' => $date->format('d.m'),
                'count' => $count
            ];
        }
        
        return $trend;
    }

    /**
     * Статистика продуктивности
     */
    private function getProductivityStats(\App\Entity\User $user): array
    {
        $conn = $this->em->getConnection();
        
        // Среднее время выполнения задачи
        $sql = "SELECT 
                AVG(JULIANDAY(completed_at) - JULIANDAY(created_at)) as avg_completion_days
                FROM tasks 
                WHERE user_id = :user_id 
                AND status = 'completed'
                AND completed_at IS NOT NULL";
        
        $avgDays = $conn->fetchOne($sql, ['user_id' => $user->getId()]);
        
        // Задачи, выполненные сегодня
        $sql = "SELECT COUNT(*) FROM tasks 
                WHERE user_id = :user_id 
                AND status = 'completed' 
                AND DATE(completed_at) = DATE('now')";
        
        $completedToday = (int)$conn->fetchOne($sql, ['user_id' => $user->getId()]);
        
        // Задачи, выполненные на этой неделе
        $sql = "SELECT COUNT(*) FROM tasks 
                WHERE user_id = :user_id 
                AND status = 'completed' 
                AND DATE(completed_at) >= DATE('now', 'weekday 0')";
        
        $completedThisWeek = (int)$conn->fetchOne($sql, ['user_id' => $user->getId()]);
        
        return [
            'avg_completion_days' => $avgDays ? round((float)$avgDays, 1) : 0,
            'completed_today' => $completedToday,
            'completed_this_week' => $completedThisWeek,
            'streak_days' => $this->calculateStreak($user)
        ];
    }

    /**
     * Расчёт серии (дней подряд с выполненными задачами)
     */
    private function calculateStreak(\App\Entity\User $user): int
    {
        $conn = $this->em->getConnection();
        
        $sql = "SELECT DATE(completed_at) as date
                FROM tasks 
                WHERE user_id = :user_id 
                AND status = 'completed'
                GROUP BY DATE(completed_at)
                ORDER BY date DESC
                LIMIT 30";
        
        $dates = $conn->fetchFirstColumn($sql, ['user_id' => $user->getId()]);
        
        if (empty($dates)) {
            return 0;
        }
        
        $streak = 0;
        $expectedDate = new \DateTime();
        
        foreach ($dates as $dateStr) {
            $expectedDateStr = $expectedDate->format('Y-m-d');
            
            if ($dateStr === $expectedDateStr) {
                $streak++;
                $expectedDate->modify('-1 day');
            } elseif ($dateStr < $expectedDateStr) {
                break;
            }
        }
        
        return $streak;
    }

    /**
     * Предстоящие дедлайны
     */
    private function getUpcomingDeadlines(\App\Entity\User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->orderBy('t.dueDate', 'ASC')
            ->setMaxResults(5);
        
        $tasks = $qb->getQuery()->getResult();
        
        $deadlines = [];
        $now = new \DateTime();
        
        foreach ($tasks as $task) {
            $dueDate = $task->getDueDate();
            $diff = $now->diff($dueDate);
            $days = $diff->days;
            
            $urgency = 'normal';
            if ($days === 0 && $diff->invert === 0) {
                $urgency = 'today';
            } elseif ($days === 1 && $diff->invert === 0) {
                $urgency = 'tomorrow';
            } elseif ($days <= 3 && $diff->invert === 0) {
                $urgency = 'soon';
            } elseif ($diff->invert === 1) {
                $urgency = 'overdue';
            }
            
            $deadlines[] = [
                'task' => $task,
                'due_date' => $dueDate,
                'days_left' => $diff->invert ? -$days : $days,
                'urgency' => $urgency
            ];
        }
        
        return $deadlines;
    }

    /**
     * Недавняя активность
     */
    private function getRecentActivity(\App\Entity\User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user')
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults(10);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Получить данные для круговой диаграммы
     */
    public function getPieChartData(\App\Entity\User $user): array
    {
        return [
            'by_status' => $this->getTasksByStatus($user),
            'by_priority' => $this->getTasksByPriority($user)
        ];
    }

    /**
     * Получить данные для линейного графика
     */
    public function getLineChartData(\App\Entity\User $user): array
    {
        return [
            'trend' => $this->getCompletedTasksTrend($user),
            'labels' => array_column($this->getCompletedTasksTrend($user), 'label'),
            'data' => array_column($this->getCompletedTasksTrend($user), 'count')
        ];
    }
}
