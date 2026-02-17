<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

class TaskPriorityCalculatorService
{
    /**
     * Calculate smart priority score for task
     */
    public function calculatePriorityScore(Task $task): float
    {
        $score = 0;

        // Base priority weight
        $score += match($task->getPriority()) {
            'urgent' => 100,
            'high' => 75,
            'medium' => 50,
            'low' => 25,
            default => 50
        };

        // Deadline urgency
        if ($task->getDeadline()) {
            $now = new \DateTime();
            $daysUntil = $now->diff($task->getDeadline())->days;
            
            if ($daysUntil <= 1) {
                $score += 50; // Very urgent
            } elseif ($daysUntil <= 3) {
                $score += 30; // Urgent
            } elseif ($daysUntil <= 7) {
                $score += 15; // Soon
            }
            
            // Overdue penalty
            if ($task->getDeadline() < $now) {
                $score += 75; // Overdue is critical
            }
        }

        // Status weight
        $score += match($task->getStatus()) {
            'in_progress' => 20, // In progress tasks are important
            'pending' => 10,
            default => 0
        };

        // Has comments (active discussion)
        if ($task->getComments()->count() > 0) {
            $score += 10;
        }

        // Recently updated
        if ($task->getUpdatedAt()) {
            $daysSinceUpdate = (new \DateTime())->diff($task->getUpdatedAt())->days;
            if ($daysSinceUpdate <= 1) {
                $score += 15;
            }
        }

        return min(200, $score); // Cap at 200
    }

    /**
     * Get recommended tasks for user
     */
    public function getRecommendedTasks(User $user, int $limit = 5): array
    {
        // TODO: Get user's tasks from repository
        $tasks = [];

        // Calculate scores
        $scored = [];
        foreach ($tasks as $task) {
            if ($task->getStatus() !== 'completed') {
                $scored[] = [
                    'task' => $task,
                    'score' => $this->calculatePriorityScore($task),
                    'reason' => $this->getRecommendationReason($task)
                ];
            }
        }

        // Sort by score
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Get recommendation reason
     */
    private function getRecommendationReason(Task $task): string
    {
        $reasons = [];

        if ($task->getDeadline()) {
            $now = new \DateTime();
            $daysUntil = $now->diff($task->getDeadline())->days;
            
            if ($task->getDeadline() < $now) {
                $reasons[] = 'Просрочено';
            } elseif ($daysUntil <= 1) {
                $reasons[] = 'Дедлайн завтра';
            } elseif ($daysUntil <= 3) {
                $reasons[] = 'Скоро дедлайн';
            }
        }

        if ($task->getPriority() === 'urgent') {
            $reasons[] = 'Срочная задача';
        }

        if ($task->getStatus() === 'in_progress') {
            $reasons[] = 'В работе';
        }

        if ($task->getComments()->count() > 3) {
            $reasons[] = 'Активное обсуждение';
        }

        return implode(', ', $reasons) ?: 'Рекомендуется';
    }

    /**
     * Auto-adjust priorities based on deadlines
     */
    public function autoAdjustPriorities(array $tasks): array
    {
        $adjusted = [];

        foreach ($tasks as $task) {
            if ($task->getDeadline()) {
                $now = new \DateTime();
                $daysUntil = $now->diff($task->getDeadline())->days;

                $newPriority = $task->getPriority();

                // Auto-escalate if deadline is near
                if ($daysUntil <= 1 && $task->getPriority() !== 'urgent') {
                    $newPriority = 'urgent';
                    $adjusted[] = [
                        'task' => $task,
                        'old_priority' => $task->getPriority(),
                        'new_priority' => $newPriority,
                        'reason' => 'Дедлайн завтра'
                    ];
                } elseif ($daysUntil <= 3 && in_array($task->getPriority(), ['low', 'medium'])) {
                    $newPriority = 'high';
                    $adjusted[] = [
                        'task' => $task,
                        'old_priority' => $task->getPriority(),
                        'new_priority' => $newPriority,
                        'reason' => 'Приближается дедлайн'
                    ];
                }
            }
        }

        return $adjusted;
    }
}
