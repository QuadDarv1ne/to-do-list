<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskPriorityCalculatorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaskRepository $taskRepository,
    ) {
    }

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

        // Has dependencies - check if other tasks depend on this
        $dependents = $this->getDependentTasks($task);
        $score += \count($dependents) * 10;

        return min(200, $score); // Cap at 200
    }

    /**
     * Get tasks that depend on this task
     */
    private function getDependentTasks(Task $task): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('t')
            ->from(\App\Entity\TaskDependency::class, 'td')
            ->join('td.task', 't')
            ->andWhere('td.dependsOn = :task')
            ->setParameter('task', $task);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get recommended tasks for user
     */
    public function getRecommendedTasks(User $user, int $limit = 5): array
    {
        // Получаем активные задачи пользователя
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getResult();

        // Рассчитываем приоритет для каждой задачи
        $tasksWithScores = [];
        foreach ($tasks as $task) {
            $score = $this->calculatePriorityScore($task);
            $tasksWithScores[] = [
                'task' => $task,
                'score' => $score,
            ];
        }

        // Сортируем по убыванию приоритета
        usort($tasksWithScores, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Возвращаем топ-N задач
        return array_slice($tasksWithScores, 0, $limit);
    }

    /**
     * Auto-adjust priorities based on deadlines
     */
    public function autoAdjustPriorities(): int
    {
        $adjusted = 0;

        // Получаем все активные задачи
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.status != :completed')
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $oldPriority = $task->getPriority();
            $suggestedPriority = $this->suggestPriority($task);

            // Изменяем приоритет если он отличается
            if ($oldPriority !== $suggestedPriority) {
                $task->setPriority($suggestedPriority);
                $adjusted++;
            }
        }

        if ($adjusted > 0) {
            $this->em->flush();
        }

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
            'low' => 0,
        ];
    }

    /**
     * Suggest priority for new task
     */
    public function suggestPriority(Task $task): string
    {
        $score = $this->calculatePriorityScore($task);

        if ($score >= 150) {
            return 'urgent';
        }
        if ($score >= 100) {
            return 'high';
        }
        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }
}
