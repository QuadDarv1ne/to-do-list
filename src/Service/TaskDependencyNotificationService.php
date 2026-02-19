<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Entity\TaskNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskDependencyNotificationService
{
    private EntityManagerInterface $entityManager;

    private LoggerInterface $logger;

    private ?PerformanceMonitorService $performanceMonitor;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ?PerformanceMonitorService $performanceMonitor = null,
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Create notification for a task dependency event
     */
    public function createDependencyNotification(
        User $recipient,
        User $sender,
        Task $task,
        string $type,
        string $subject,
        string $message,
    ): TaskNotification {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_create_dependency_notification');
        }

        try {
            $notification = new TaskNotification();
            $notification->setRecipient($recipient);
            $notification->setSender($sender);
            $notification->setTask($task);
            $notification->setType($type);
            $notification->setSubject($subject);
            $notification->setMessage($message);
            $notification->setIsSent(false);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->logger->info("Created dependency notification for user {$recipient->getId()}: {$subject}");

            return $notification;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_create_dependency_notification');
            }
        }
    }

    /**
     * Send notification when a dependency is created
     */
    public function sendDependencyCreatedNotification(TaskDependency $dependency): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_send_dependency_created_notification');
        }

        try {
            $dependentTask = $dependency->getDependentTask();
            $dependencyTask = $dependency->getDependencyTask();

            // Notify the assigned user of the dependent task
            $assignedUser = $dependentTask->getAssignedUser();
            if ($assignedUser) {
                $subject = 'Добавлена новая зависимость';
                $message = \sprintf(
                    'Задача "%s" теперь зависит от задачи "%s". Необходимо дождаться выполнения зависимости перед началом работы.',
                    $dependentTask->getTitle(),
                    $dependencyTask->getTitle(),
                );

                $this->createDependencyNotification(
                    $assignedUser,
                    $assignedUser, // Using assigned user as sender for now
                    $dependentTask,
                    'dependency_created',
                    $subject,
                    $message,
                );
            }
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_send_dependency_created_notification');
            }
        }
    }

    /**
     * Send notification when a dependency is removed
     */
    public function sendDependencyRemovedNotification(TaskDependency $dependency): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_send_dependency_removed_notification');
        }

        try {
            $dependentTask = $dependency->getDependentTask();
            $dependencyTask = $dependency->getDependencyTask();

            // Notify the assigned user of the dependent task
            $assignedUser = $dependentTask->getAssignedUser();
            if ($assignedUser) {
                $subject = 'Зависимость удалена';
                $message = \sprintf(
                    'Зависимость задачи "%s" от задачи "%s" была удалена.',
                    $dependentTask->getTitle(),
                    $dependencyTask->getTitle(),
                );

                $this->createDependencyNotification(
                    $assignedUser,
                    $assignedUser, // Using assigned user as sender for now
                    $dependentTask,
                    'dependency_removed',
                    $subject,
                    $message,
                );
            }
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_send_dependency_removed_notification');
            }
        }
    }

    /**
     * Send notification when a dependency is satisfied (completed)
     */
    public function sendDependencySatisfiedNotification(TaskDependency $dependency): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_send_dependency_satisfied_notification');
        }

        try {
            $dependentTask = $dependency->getDependentTask();
            $dependencyTask = $dependency->getDependencyTask();

            // Notify the assigned user of the dependent task that they can now start
            $assignedUser = $dependentTask->getAssignedUser();
            if ($assignedUser) {
                $subject = 'Зависимость выполнена - можно начинать работу';
                $message = \sprintf(
                    'Зависимость "%s" выполнена. Теперь вы можете начать работу над задачей "%s".',
                    $dependencyTask->getTitle(),
                    $dependentTask->getTitle(),
                );

                $this->createDependencyNotification(
                    $assignedUser,
                    $dependencyTask->getAssignedUser() ?? $dependencyTask->getUser(), // Use creator of dependency task as sender
                    $dependentTask,
                    'dependency_satisfied',
                    $subject,
                    $message,
                );
            }
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_send_dependency_satisfied_notification');
            }
        }
    }

    /**
     * Send notification when a task becomes blocked due to dependencies
     */
    public function sendTaskBlockedNotification(Task $task): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_send_task_blocked_notification');
        }

        try {
            $assignedUser = $task->getAssignedUser();
            if (!$assignedUser) {
                return;
            }

            // Get blocking dependencies
            $blockingDependencies = [];
            foreach ($task->getDependencies() as $dependency) {
                if ($dependency->getType() === 'blocking' && !$dependency->isSatisfied()) {
                    $blockingDependencies[] = $dependency->getDependencyTask();
                }
            }

            if (empty($blockingDependencies)) {
                return;
            }

            $dependencyTitles = array_map(fn ($dep) => $dep->getTitle(), $blockingDependencies);
            $dependencyList = implode(', ', $dependencyTitles);

            $subject = 'Задача заблокирована';
            $message = \sprintf(
                'Задача "%s" заблокирована из-за невыполненных зависимостей: %s. Работа над задачей может начаться после выполнения зависимостей.',
                $task->getTitle(),
                $dependencyList,
            );

            $this->createDependencyNotification(
                $assignedUser,
                $assignedUser, // Using assigned user as sender for now
                $task,
                'task_blocked',
                $subject,
                $message,
            );
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_send_task_blocked_notification');
            }
        }
    }

    /**
     * Send notification when a task is unblocked (dependencies satisfied)
     */
    public function sendTaskUnblockedNotification(Task $task): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('task_dependency_notification_service_send_task_unblocked_notification');
        }

        try {
            $assignedUser = $task->getAssignedUser();
            if (!$assignedUser) {
                return;
            }

            $subject = 'Задача разблокирована - можно начинать работу';
            $message = \sprintf(
                'Задача "%s" разблокирована. Все зависимости выполнены, теперь можно начинать работу.',
                $task->getTitle(),
            );

            $this->createDependencyNotification(
                $assignedUser,
                $assignedUser, // Using assigned user as sender for now
                $task,
                'task_unblocked',
                $subject,
                $message,
            );
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('task_dependency_notification_service_send_task_unblocked_notification');
            }
        }
    }
}
