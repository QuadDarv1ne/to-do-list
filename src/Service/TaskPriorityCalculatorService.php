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
        }

        // Overdue penalty
        if ($task->getDeadline() && $task->getDeadline() < new \DateTime()) {
            $score += 75; // Overdue is critical
        }

        // Status weight
        $score += match($task->getStatus()) {
            'in_progress' => 20, // In progress tasks are important
            'pending' => 10,
            default => 0
        };

        // Has dependencies
        // TODO: Check if other tasks depend on this
        // $score += count($dependents) * 10;

        return min(200, $score); // Cap at 200
    }

    /**
     * Get recommended tasks for user
     */
    public function getRecommendedTasks(User $user, int $limit = 5): array
    {
        // TODO: Get user's tasks and calculate scores
        // For now, return empty array
        return [];
    }

    /**
     * Auto-adjust priorities based on deadlines
     */
    public function autoAdjustPriorities(): int
    {
        $adjusted = 0;
        
        // TODO: Get all tasks and adjust priorities
        // Tasks with approaching deadlines should be upgraded
        
        return $adjusted;
    }

    /**
     * Get priority distribution
     */
    public function getPriorityDistribution(User $user): array
    {
        return [
            'urgent' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];
    }

    /**
     * Suggest priority for new task
     */
    public function suggestPriority(Task $task): string
    {
        $score = $this->calculatePriorityScore($task);

        if ($score >= 150) return 'urgent';
        if ($score >= 100) return 'high';
        if ($score >= 50) return 'medium';
        return 'low';
    }
}
