<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskAutomationService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Auto-assign tasks based on rules
     */
    public function autoAssignTasks(): int
    {
        $assigned = 0;

        // Get unassigned tasks
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser IS NULL')
            ->andWhere('t.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // TODO: Implement assignment logic
            // - Based on category expertise
            // - Based on workload
            // - Based on past performance
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Auto-close completed tasks after X days
     */
    public function autoCloseCompletedTasks(int $daysOld = 30): int
    {
        $closed = 0;
        $cutoffDate = new \DateTime("-{$daysOld} days");

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.status = :completed')
            ->andWhere('t.completedAt < :cutoff')
            ->setParameter('completed', 'completed')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // Archive or mark as closed
            $task->setStatus('archived');
            $closed++;
        }

        $this->entityManager->flush();

        return $closed;
    }

    /**
     * Auto-escalate overdue tasks
     */
    public function autoEscalateOverdueTasks(): int
    {
        $escalated = 0;
        $now = new \DateTime();

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.deadline < :now')
            ->andWhere('t.status != :completed')
            ->andWhere('t.priority != :urgent')
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->setParameter('urgent', 'urgent')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // Escalate priority
            if ($task->getPriority() === 'low') {
                $task->setPriority('medium');
            } elseif ($task->getPriority() === 'medium') {
                $task->setPriority('high');
            } elseif ($task->getPriority() === 'high') {
                $task->setPriority('urgent');
            }
            $escalated++;
        }

        $this->entityManager->flush();

        return $escalated;
    }

    /**
     * Auto-tag tasks based on content
     */
    public function autoTagTasks(): int
    {
        $tagged = 0;

        // Get tasks without tags
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.tags', 'tag')
            ->having('COUNT(tag.id) = 0')
            ->groupBy('t.id')
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            // Analyze title and description for keywords
            $keywords = $this->extractKeywords($task);
            
            // TODO: Create and add tags
            $tagged++;
        }

        return $tagged;
    }

    /**
     * Extract keywords from task
     */
    private function extractKeywords(Task $task): array
    {
        $text = strtolower($task->getTitle() . ' ' . $task->getDescription());
        
        $keywords = [
            'bug' => ['bug', 'ошибка', 'баг', 'error'],
            'feature' => ['feature', 'функция', 'новое'],
            'urgent' => ['urgent', 'срочно', 'asap'],
            'meeting' => ['meeting', 'встреча', 'созвон'],
            'documentation' => ['doc', 'документация', 'readme'],
            'testing' => ['test', 'тест', 'qa'],
            'deployment' => ['deploy', 'развертывание', 'релиз']
        ];

        $found = [];
        foreach ($keywords as $tag => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($text, $pattern)) {
                    $found[] = $tag;
                    break;
                }
            }
        }

        return array_unique($found);
    }

    /**
     * Auto-update task status based on activity
     */
    public function autoUpdateStatus(): int
    {
        $updated = 0;

        // Tasks in progress with no activity for 7 days -> back to pending
        $cutoffDate = new \DateTime('-7 days');
        
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.status = :in_progress')
            ->andWhere('t.updatedAt < :cutoff')
            ->setParameter('in_progress', 'in_progress')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $task->setStatus('pending');
            $updated++;
        }

        $this->entityManager->flush();

        return $updated;
    }

    /**
     * Get automation rules
     */
    public function getRules(): array
    {
        return [
            'auto_assign' => [
                'name' => 'Автоназначение задач',
                'description' => 'Автоматически назначать задачи на основе правил',
                'enabled' => true
            ],
            'auto_close' => [
                'name' => 'Автозакрытие завершенных',
                'description' => 'Закрывать завершенные задачи через 30 дней',
                'enabled' => true
            ],
            'auto_escalate' => [
                'name' => 'Автоэскалация просроченных',
                'description' => 'Повышать приоритет просроченных задач',
                'enabled' => true
            ],
            'auto_tag' => [
                'name' => 'Автотегирование',
                'description' => 'Автоматически добавлять теги на основе содержимого',
                'enabled' => false
            ],
            'auto_status' => [
                'name' => 'Автообновление статуса',
                'description' => 'Обновлять статус неактивных задач',
                'enabled' => true
            ]
        ];
    }

    /**
     * Run all automation rules
     */
    public function runAllAutomations(): array
    {
        $results = [
            'auto_assigned' => $this->autoAssignTasks(),
            'auto_closed' => $this->autoCloseCompletedTasks(),
            'auto_escalated' => $this->autoEscalateOverdueTasks(),
            'auto_tagged' => $this->autoTagTasks(),
            'auto_status_updated' => $this->autoUpdateStatus()
        ];

        return $results;
    }
}
