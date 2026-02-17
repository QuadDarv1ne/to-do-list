<?php

namespace App\Service;

use App\Repository\TaskRepository;
use App\Entity\User;

class ReportGeneratorService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}
    
    /**
     * Generate productivity report for user
     */
    public function generateProductivityReport(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
        
        $completed = array_filter($tasks, fn($t) => $t->getStatus() === 'completed');
        $inProgress = array_filter($tasks, fn($t) => $t->getStatus() === 'in_progress');
        $pending = array_filter($tasks, fn($t) => $t->getStatus() === 'pending');
        $cancelled = array_filter($tasks, fn($t) => $t->getStatus() === 'cancelled');
        
        // Calculate completion rate
        $totalTasks = count($tasks);
        $completionRate = $totalTasks > 0 ? (count($completed) / $totalTasks) * 100 : 0;
        
        // Calculate average completion time
        $completionTimes = [];
        foreach ($completed as $task) {
            if ($task->getCreatedAt() && $task->getUpdatedAt()) {
                $diff = $task->getUpdatedAt()->getTimestamp() - $task->getCreatedAt()->getTimestamp();
                $completionTimes[] = $diff / 86400; // Convert to days
            }
        }
        $avgCompletionTime = !empty($completionTimes) ? array_sum($completionTimes) / count($completionTimes) : 0;
        
        // Priority distribution
        $priorityDistribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'urgent' => 0
        ];
        
        foreach ($tasks as $task) {
            $priority = $task->getPriority();
            if (isset($priorityDistribution[$priority])) {
                $priorityDistribution[$priority]++;
            }
        }
        
        // Tasks by day of week
        $tasksByDayOfWeek = array_fill(1, 7, 0);
        foreach ($tasks as $task) {
            $dayOfWeek = (int)$task->getCreatedAt()->format('N');
            $tasksByDayOfWeek[$dayOfWeek]++;
        }
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $startDate->diff($endDate)->days
            ],
            'summary' => [
                'total_tasks' => $totalTasks,
                'completed' => count($completed),
                'in_progress' => count($inProgress),
                'pending' => count($pending),
                'cancelled' => count($cancelled),
                'completion_rate' => round($completionRate, 2),
                'avg_completion_time_days' => round($avgCompletionTime, 2)
            ],
            'priority_distribution' => $priorityDistribution,
            'tasks_by_day_of_week' => $tasksByDayOfWeek,
            'productivity_score' => $this->calculateProductivityScore($completionRate, $avgCompletionTime, $totalTasks)
        ];
    }
    
    /**
     * Generate team performance report
     */
    public function generateTeamReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('u.id, u.fullName, u.email, COUNT(t.id) as total_tasks, 
                     SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks')
            ->leftJoin('t.assignedUser', 'u')
            ->where('t.createdAt BETWEEN :start AND :end')
            ->andWhere('u.id IS NOT NULL')
            ->groupBy('u.id, u.fullName, u.email')
            ->orderBy('completed_tasks', 'DESC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('completed', 'completed');
        
        $results = $qb->getQuery()->getResult();
        
        $teamMembers = [];
        foreach ($results as $result) {
            $completionRate = $result['total_tasks'] > 0 
                ? ($result['completed_tasks'] / $result['total_tasks']) * 100 
                : 0;
            
            $teamMembers[] = [
                'user_id' => $result['id'],
                'name' => $result['fullName'],
                'email' => $result['email'],
                'total_tasks' => (int)$result['total_tasks'],
                'completed_tasks' => (int)$result['completed_tasks'],
                'completion_rate' => round($completionRate, 2)
            ];
        }
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'team_members' => $teamMembers,
            'team_summary' => [
                'total_members' => count($teamMembers),
                'total_tasks' => array_sum(array_column($teamMembers, 'total_tasks')),
                'total_completed' => array_sum(array_column($teamMembers, 'completed_tasks')),
                'avg_completion_rate' => count($teamMembers) > 0 
                    ? round(array_sum(array_column($teamMembers, 'completion_rate')) / count($teamMembers), 2)
                    : 0
            ]
        ];
    }
    
    /**
     * Generate overdue tasks report
     */
    public function generateOverdueReport(): array
    {
        $now = new \DateTime();
        
        $overdueTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->andWhere('t.status != :cancelled')
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->setParameter('cancelled', 'cancelled')
            ->orderBy('t.deadline', 'ASC')
            ->getQuery()
            ->getResult();
        
        $groupedByUser = [];
        foreach ($overdueTasks as $task) {
            $userId = $task->getAssignedUser() ? $task->getAssignedUser()->getId() : 'unassigned';
            $userName = $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : 'Не назначено';
            
            if (!isset($groupedByUser[$userId])) {
                $groupedByUser[$userId] = [
                    'user_name' => $userName,
                    'tasks' => []
                ];
            }
            
            $daysOverdue = $now->diff($task->getDeadline())->days;
            
            $groupedByUser[$userId]['tasks'][] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'priority' => $task->getPriority(),
                'deadline' => $task->getDeadline()->format('Y-m-d H:i'),
                'days_overdue' => $daysOverdue
            ];
        }
        
        return [
            'total_overdue' => count($overdueTasks),
            'grouped_by_user' => array_values($groupedByUser),
            'generated_at' => $now->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Calculate productivity score (0-100)
     */
    private function calculateProductivityScore(float $completionRate, float $avgCompletionTime, int $totalTasks): float
    {
        // Completion rate weight: 50%
        $completionScore = $completionRate * 0.5;
        
        // Speed score (faster is better): 30%
        // Assuming ideal completion time is 3 days
        $speedScore = 0;
        if ($avgCompletionTime > 0) {
            $speedScore = min(100, (3 / $avgCompletionTime) * 100) * 0.3;
        }
        
        // Volume score (more tasks is better): 20%
        // Assuming 10 tasks per period is good
        $volumeScore = min(100, ($totalTasks / 10) * 100) * 0.2;
        
        return round($completionScore + $speedScore + $volumeScore, 2);
    }
}
