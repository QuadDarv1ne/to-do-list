<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for exporting tasks to various formats
 */
class TaskExportService
{
    private EntityManagerInterface $entityManager;
    private TaskRepository $taskRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->logger = $logger;
    }

    /**
     * Export tasks to CSV format
     */
    public function exportToCsv(User $user, array $filters = []): string
    {
        $tasks = $this->getFilteredTasks($user, $filters);
        
        $csv = fopen('php://temp', 'r+');
        
        // Add UTF-8 BOM for proper Excel support
        fputs($csv, "\xEF\xBB\xBF");
        
        // Add headers
        fputcsv($csv, [
            'ID', 'Title', 'Description', 'Status', 'Priority', 'Category', 
            'Created At', 'Due Date', 'Completed At', 'Assigned To', 'Tags'
        ]);
        
        // Add data
        foreach ($tasks as $task) {
            $assignedUser = $task->getAssignedUser();
            $tags = $task->getTags()->map(function($tag) {
                return $tag->getName();
            })->toArray();
            
            fputcsv($csv, [
                $task->getId(),
                $task->getTitle(),
                $task->getDescription() ?? '',
                $task->getStatus(),
                $task->getPriority(),
                $task->getCategory() ? $task->getCategory()->getName() : '',
                $task->getCreatedAt()->format('Y-m-d H:i:s'),
                $task->getDueDate() ? $task->getDueDate()->format('Y-m-d H:i:s') : '',
                $task->getCompletedAt() ? $task->getCompletedAt()->format('Y-m-d H:i:s') : '',
                $assignedUser ? $assignedUser->getFullName() : '',
                implode(', ', $tags)
            ]);
        }
        
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        
        $this->logger->info("Exported tasks to CSV", [
            'user_id' => $user->getId(),
            'task_count' => count($tasks),
            'filters' => $filters
        ]);
        
        return $content;
    }

    /**
     * Export tasks to JSON format
     */
    public function exportToJson(User $user, array $filters = []): string
    {
        $tasks = $this->getFilteredTasks($user, $filters);
        
        $exportData = [
            'exported_at' => date('Y-m-d H:i:s'),
            'user' => $user->getUsername(),
            'total_tasks' => count($tasks),
            'filters_applied' => $filters,
            'tasks' => []
        ];
        
        foreach ($tasks as $task) {
            $assignedUser = $task->getAssignedUser();
            $tags = $task->getTags()->map(function($tag) {
                return [
                    'id' => $tag->getId(),
                    'name' => $tag->getName()
                ];
            })->toArray();
            
            $exportData['tasks'][] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'priority_label' => $task->getPriorityLabel(),
                'category' => $task->getCategory() ? [
                    'id' => $task->getCategory()->getId(),
                    'name' => $task->getCategory()->getName()
                ] : null,
                'created_at' => $task->getCreatedAt()->format('c'),
                'updated_at' => $task->getUpdatedAt() ? $task->getUpdatedAt()->format('c') : null,
                'due_date' => $task->getDueDate() ? $task->getDueDate()->format('c') : null,
                'completed_at' => $task->getCompletedAt() ? $task->getCompletedAt()->format('c') : null,
                'is_overdue' => $task->isOverdue(),
                'is_completed' => $task->isCompleted(),
                'assigned_to' => $assignedUser ? [
                    'id' => $assignedUser->getId(),
                    'username' => $assignedUser->getUsername(),
                    'full_name' => $assignedUser->getFullName()
                ] : null,
                'tags' => $tags,
                'completion_time_hours' => $task->getCompletionTimeInHours(),
                'completion_time_days' => $task->getCompletionTimeInDays(),
                'is_completed_late' => $task->isCompletedLate(),
                'overdue_days' => $task->getOverdueDays()
            ];
        }
        
        $this->logger->info("Exported tasks to JSON", [
            'user_id' => $user->getId(),
            'task_count' => count($tasks),
            'filters' => $filters
        ]);
        
        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export tasks to XML format
     */
    public function exportToXml(User $user, array $filters = []): string
    {
        $tasks = $this->getFilteredTasks($user, $filters);
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
        $xml->addAttribute('exported_at', date('Y-m-d H:i:s'));
        $xml->addAttribute('user', $user->getUsername());
        $xml->addAttribute('total_tasks', count($tasks));
        
        foreach ($tasks as $task) {
            $taskNode = $xml->addChild('task');
            $taskNode->addChild('id', $task->getId());
            $taskNode->addChild('title', htmlspecialchars($task->getTitle()));
            $taskNode->addChild('description', htmlspecialchars($task->getDescription() ?? ''));
            $taskNode->addChild('status', $task->getStatus());
            $taskNode->addChild('priority', $task->getPriority());
            $taskNode->addChild('priority_label', $task->getPriorityLabel());
            
            if ($task->getCategory()) {
                $categoryNode = $taskNode->addChild('category');
                $categoryNode->addChild('id', $task->getCategory()->getId());
                $categoryNode->addChild('name', htmlspecialchars($task->getCategory()->getName()));
            }
            
            $taskNode->addChild('created_at', $task->getCreatedAt()->format('c'));
            
            if ($task->getUpdatedAt()) {
                $taskNode->addChild('updated_at', $task->getUpdatedAt()->format('c'));
            }
            
            if ($task->getDueDate()) {
                $taskNode->addChild('due_date', $task->getDueDate()->format('c'));
            }
            
            if ($task->getCompletedAt()) {
                $taskNode->addChild('completed_at', $task->getCompletedAt()->format('c'));
            }
            
            $taskNode->addChild('is_overdue', $task->isOverdue() ? 'true' : 'false');
            $taskNode->addChild('is_completed', $task->isCompleted() ? 'true' : 'false');
            
            if ($task->getAssignedUser()) {
                $assignedNode = $taskNode->addChild('assigned_to');
                $assignedNode->addChild('id', $task->getAssignedUser()->getId());
                $assignedNode->addChild('username', $task->getAssignedUser()->getUsername());
                $assignedNode->addChild('full_name', htmlspecialchars($task->getAssignedUser()->getFullName()));
            }
            
            if ($task->getTags()->count() > 0) {
                $tagsNode = $taskNode->addChild('tags');
                foreach ($task->getTags() as $tag) {
                    $tagNode = $tagsNode->addChild('tag');
                    $tagNode->addChild('id', $tag->getId());
                    $tagNode->addChild('name', htmlspecialchars($tag->getName()));
                }
            }
        }
        
        $this->logger->info("Exported tasks to XML", [
            'user_id' => $user->getId(),
            'task_count' => count($tasks),
            'filters' => $filters
        ]);
        
        return $xml->asXML();
    }

    /**
     * Export tasks to PDF format (simplified)
     */
    public function exportToPdf(User $user, array $filters = []): string
    {
        $tasks = $this->getFilteredTasks($user, $filters);
        
        // This is a simplified PDF export - in real implementation you would use a PDF library
        $pdfContent = "Task Export - PDF Format\n";
        $pdfContent .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $pdfContent .= "User: " . $user->getUsername() . "\n";
        $pdfContent .= "Total Tasks: " . count($tasks) . "\n\n";
        
        foreach ($tasks as $task) {
            $pdfContent .= "Task #" . $task->getId() . "\n";
            $pdfContent .= "Title: " . $task->getTitle() . "\n";
            $pdfContent .= "Status: " . $task->getStatus() . "\n";
            $pdfContent .= "Priority: " . $task->getPriorityLabel() . "\n";
            if ($task->getDueDate()) {
                $pdfContent .= "Due Date: " . $task->getDueDate()->format('Y-m-d H:i:s') . "\n";
            }
            $pdfContent .= "Created: " . $task->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
            $pdfContent .= "---\n\n";
        }
        
        $this->logger->info("Exported tasks to PDF", [
            'user_id' => $user->getId(),
            'task_count' => count($tasks),
            'filters' => $filters
        ]);
        
        return $pdfContent;
    }

    /**
     * Get export statistics
     */
    public function getExportStatistics(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $stats = $qb->select('
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status != :completed THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN t.dueDate < :now AND t.status != :completed THEN 1 ELSE 0 END) as overdue_tasks,
                COUNT(DISTINCT t.category) as categories_used,
                COUNT(DISTINCT tt.id) as tags_used
            ')
            ->from(Task::class, 't')
            ->leftJoin('t.tags', 'tt')
            ->where('(t.user = :user OR t.assignedUser = :user)')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleResult();
            
        return [
            'total_tasks' => (int) $stats['total_tasks'],
            'completed_tasks' => (int) $stats['completed_tasks'],
            'pending_tasks' => (int) $stats['pending_tasks'],
            'overdue_tasks' => (int) $stats['overdue_tasks'],
            'categories_used' => (int) $stats['categories_used'],
            'tags_used' => (int) $stats['tags_used'],
            'completion_rate' => $stats['total_tasks'] > 0 ? 
                round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0
        ];
    }

    /**
     * Get available export formats
     */
    public function getAvailableFormats(): array
    {
        return [
            'csv' => [
                'name' => 'CSV',
                'description' => 'Comma-separated values format',
                'extension' => '.csv',
                'mime_type' => 'text/csv'
            ],
            'json' => [
                'name' => 'JSON',
                'description' => 'JavaScript Object Notation format',
                'extension' => '.json',
                'mime_type' => 'application/json'
            ],
            'xml' => [
                'name' => 'XML',
                'description' => 'eXtensible Markup Language format',
                'extension' => '.xml',
                'mime_type' => 'application/xml'
            ],
            'pdf' => [
                'name' => 'PDF',
                'description' => 'Portable Document Format',
                'extension' => '.pdf',
                'mime_type' => 'application/pdf'
            ]
        ];
    }

    /**
     * Private helper method to get filtered tasks
     */
    private function getFilteredTasks(User $user, array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('t')
           ->from(Task::class, 't')
           ->where('(t.user = :user OR t.assignedUser = :user)')
           ->setParameter('user', $user);
        
        // Apply filters
        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }
        
        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }
        
        if (!empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }
        
        if (!empty($filters['search'])) {
            $qb->andWhere('(
                LOWER(t.title) LIKE :search OR 
                LOWER(t.description) LIKE :search
            )')
               ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }
        
        if (!empty($filters['overdue']) && $filters['overdue']) {
            $qb->andWhere('t.dueDate < :now AND t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }
        
        if (!empty($filters['completed'])) {
            if ($filters['completed']) {
                $qb->andWhere('t.status = :completed')
                   ->setParameter('completed', 'completed');
            } else {
                $qb->andWhere('t.status != :completed')
                   ->setParameter('completed', 'completed');
            }
        }
        
        // Sort options
        $sortField = $filters['sort_by'] ?? 'createdAt';
        $sortDirection = strtoupper($filters['sort_direction'] ?? 'DESC');
        $qb->orderBy("t.{$sortField}", $sortDirection);
        
        // Limit results
        if (!empty($filters['limit'])) {
            $qb->setMaxResults((int) $filters['limit']);
        }
        
        // Add joins for related data
        $qb->leftJoin('t.category', 'c')
           ->leftJoin('t.assignedUser', 'au')
           ->leftJoin('t.tags', 'tg');
        
        return $qb->getQuery()->getResult();
    }
}