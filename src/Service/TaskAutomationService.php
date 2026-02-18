<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskAutomation;
use App\Entity\User;
use App\Repository\TaskAutomationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskAutomationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskAutomationRepository $automationRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Выполнить автоматизации при создании задачи
     */
    public function executeOnTaskCreated(Task $task): void
    {
        $this->executeTrigger('task_created', $task);
    }

    /**
     * Выполнить автоматизации при изменении статуса
     */
    public function executeOnStatusChanged(Task $task, string $oldStatus, string $newStatus): void
    {
        $context = [
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ];
        
        $this->executeTrigger('status_changed', $task, $context);
    }

    /**
     * Выполнить автоматизации при изменении приоритета
     */
    public function executeOnPriorityChanged(Task $task, string $oldPriority, string $newPriority): void
    {
        $context = [
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority
        ];
        
        $this->executeTrigger('priority_changed', $task, $context);
    }

    /**
     * Выполнить автоматизации при приближении дедлайна
     */
    public function executeOnDeadlineApproaching(Task $task): void
    {
        $this->executeTrigger('deadline_approaching', $task);
    }

    /**
     * Выполнить автоматизации при просрочке
     */
    public function executeOnOverdue(Task $task): void
    {
        $this->executeTrigger('task_overdue', $task);
    }

    /**
     * Выполнить триггер
     */
    private function executeTrigger(string $trigger, Task $task, array $context = []): void
    {
        $automations = $this->automationRepository->findActiveByTrigger($trigger);

        foreach ($automations as $automation) {
            try {
                if ($this->checkConditions($automation, $task, $context)) {
                    $this->executeActions($automation, $task, $context);
                    $automation->incrementExecutionCount();
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                $this->logger->error('Automation execution failed', [
                    'automation_id' => $automation->getId(),
                    'task_id' => $task->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Проверить условия
     */
    private function checkConditions(TaskAutomation $automation, Task $task, array $context): bool
    {
        $conditions = $automation->getConditions();

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $task, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Оценить условие
     */
    private function evaluateCondition(array $condition, Task $task, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        $taskValue = $this->getTaskFieldValue($task, $field, $context);

        return match($operator) {
            'equals' => $taskValue == $value,
            'not_equals' => $taskValue != $value,
            'contains' => str_contains((string)$taskValue, (string)$value),
            'greater_than' => $taskValue > $value,
            'less_than' => $taskValue < $value,
            'in' => in_array($taskValue, (array)$value),
            'not_in' => !in_array($taskValue, (array)$value),
            default => false
        };
    }

    /**
     * Получить значение поля задачи
     */
    private function getTaskFieldValue(Task $task, ?string $field, array $context)
    {
        if (isset($context[$field])) {
            return $context[$field];
        }

        return match($field) {
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'assigned_user' => $task->getAssignedUser()?->getId(),
            'category' => $task->getCategory()?->getId(),
            'due_date' => $task->getDueDate(),
            default => null
        };
    }

    /**
     * Выполнить действия
     */
    private function executeActions(TaskAutomation $automation, Task $task, array $context): void
    {
        $actions = $automation->getActions();

        foreach ($actions as $action) {
            $this->executeAction($action, $task, $context);
        }
    }

    /**
     * Выполнить действие
     */
    private function executeAction(array $action, Task $task, array $context): void
    {
        $type = $action['type'] ?? null;

        match($type) {
            'change_status' => $this->actionChangeStatus($task, $action['value'] ?? null),
            'change_priority' => $this->actionChangePriority($task, $action['value'] ?? null),
            'assign_user' => $this->actionAssignUser($task, $action['value'] ?? null),
            'add_comment' => $this->actionAddComment($task, $action['value'] ?? null),
            'send_notification' => $this->actionSendNotification($task, $action),
            'add_tag' => $this->actionAddTag($task, $action['value'] ?? null),
            'set_due_date' => $this->actionSetDueDate($task, $action['value'] ?? null),
            default => null
        };

        $this->entityManager->flush();
    }

    /**
     * Действие: изменить статус
     */
    private function actionChangeStatus(Task $task, ?string $status): void
    {
        if ($status) {
            $task->setStatus($status);
        }
    }

    /**
     * Действие: изменить приоритет
     */
    private function actionChangePriority(Task $task, ?string $priority): void
    {
        if ($priority) {
            $task->setPriority($priority);
        }
    }

    /**
     * Действие: назначить пользователя
     */
    private function actionAssignUser(Task $task, ?int $userId): void
    {
        if ($userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user) {
                $task->setAssignedUser($user);
            }
        }
    }

    /**
     * Действие: добавить комментарий
     */
    private function actionAddComment(Task $task, ?string $text): void
    {
        if ($text) {
            // Создать комментарий от системы
            $this->logger->info('Auto-comment added', [
                'task_id' => $task->getId(),
                'text' => $text
            ]);
        }
    }

    /**
     * Действие: отправить уведомление
     */
    private function actionSendNotification(Task $task, array $action): void
    {
        $message = $action['message'] ?? 'Автоматическое уведомление';
        $recipients = $action['recipients'] ?? [];

        $this->logger->info('Auto-notification sent', [
            'task_id' => $task->getId(),
            'message' => $message,
            'recipients' => $recipients
        ]);
    }

    /**
     * Действие: добавить тег
     */
    private function actionAddTag(Task $task, ?string $tagName): void
    {
        if ($tagName) {
            $this->logger->info('Auto-tag added', [
                'task_id' => $task->getId(),
                'tag' => $tagName
            ]);
        }
    }

    /**
     * Действие: установить срок
     */
    private function actionSetDueDate(Task $task, ?string $dateString): void
    {
        if ($dateString) {
            try {
                $date = new \DateTime($dateString);
                $task->setDueDate($date);
            } catch (\Exception $e) {
                $this->logger->error('Invalid date format', [
                    'date_string' => $dateString,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Создать автоматизацию
     */
    public function createAutomation(
        string $name,
        string $trigger,
        array $conditions,
        array $actions,
        User $user,
        ?string $description = null
    ): TaskAutomation {
        $automation = new TaskAutomation();
        $automation->setName($name);
        $automation->setTrigger($trigger);
        $automation->setConditions($conditions);
        $automation->setActions($actions);
        $automation->setCreatedBy($user);
        $automation->setDescription($description);

        $this->automationRepository->save($automation, true);

        return $automation;
    }

    /**
     * Получить доступные триггеры
     */
    public function getAvailableTriggers(): array
    {
        return [
            'task_created' => 'Задача создана',
            'status_changed' => 'Статус изменен',
            'priority_changed' => 'Приоритет изменен',
            'deadline_approaching' => 'Приближается дедлайн',
            'task_overdue' => 'Задача просрочена',
            'task_completed' => 'Задача завершена',
            'task_assigned' => 'Задача назначена'
        ];
    }

    /**
     * Получить доступные действия
     */
    public function getAvailableActions(): array
    {
        return [
            'change_status' => 'Изменить статус',
            'change_priority' => 'Изменить приоритет',
            'assign_user' => 'Назначить пользователя',
            'add_comment' => 'Добавить комментарий',
            'send_notification' => 'Отправить уведомление',
            'add_tag' => 'Добавить тег',
            'set_due_date' => 'Установить срок'
        ];
    }
}
