<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function notifyTaskAssignment(Task $task, User $assigner): void
    {
        $assignedUser = $task->getAssignedUser();
        
        if (!$assignedUser || $assignedUser === $assigner) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $notification->setTitle('Новая задача назначена')
            ->setMessage(sprintf(
                'Вам назначена новая задача "%s" пользователем %s', 
                $task->getName(),
                $assigner->getFullName()
            ))
            ->setUser($assignedUser)
            ->setTask($task);

        $this->entityManager->persist($notification);

        // Send email notification
        $email = (new Email())
            ->from('noreply@todo-list.local')
            ->to($assignedUser->getEmail())
            ->subject(sprintf('Новая задача: %s', $task->getName()))
            ->html($this->buildEmailContent($task, $assigner));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log('Failed to send notification email: ' . $e->getMessage());
        }

        $this->entityManager->flush();
    }
    
    /**
     * @deprecated Use notifyTaskAssignment instead
     */
    public function sendTaskAssignedNotification(Task $task): void
    {
        // For backward compatibility, we'll try to determine the assigner
        // In most contexts where this is called, the assigner should be passed explicitly
        // For now, assume the task creator is the assigner
        $assigner = $task->getUser() ?? $task->getAssignedUser();
        if ($assigner) {
            $this->notifyTaskAssignment($task, $assigner);
        }
    }

    public function notifyTaskReassignment(Task $task, User $changer): void
    {
        $assignedUser = $task->getAssignedUser();
        
        if (!$assignedUser) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $notification->setTitle('Задача переназначена')
            ->setMessage(sprintf(
                'Вам переназначена задача "%s" пользователем %s', 
                $task->getName(),
                $changer->getFullName()
            ))
            ->setUser($assignedUser)
            ->setTask($task);

        $this->entityManager->persist($notification);

        // Send email notification
        $email = (new Email())
            ->from('noreply@todo-list.local')
            ->to($assignedUser->getEmail())
            ->subject(sprintf('Задача переназначена: %s', $task->getName()))
            ->html($this->buildEmailContent($task, $changer, 'переназначена'));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log('Failed to send reassignment email: ' . $e->getMessage());
        }

        $this->entityManager->flush();
    }

    public function notifyTaskDeadline(Task $task): void
    {
        $assignedUser = $task->getAssignedUser();
        
        if (!$assignedUser || $task->isCompleted()) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $notification->setTitle('Приближается срок выполнения задачи')
            ->setMessage(sprintf(
                'Срок выполнения задачи "%s" истекает %s', 
                $task->getName(),
                $task->getDeadline()?->format('d.m.Y')
            ))
            ->setUser($assignedUser)
            ->setTask($task);

        $this->entityManager->persist($notification);

        // Send email notification
        $email = (new Email())
            ->from('noreply@todo-list.local')
            ->to($assignedUser->getEmail())
            ->subject(sprintf('Напоминание: срок задачи %s', $task->getName()))
            ->html($this->buildDeadlineEmailContent($task));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log('Failed to send deadline reminder email: ' . $e->getMessage());
        }

        $this->entityManager->flush();
    }

    public function notifyTaskStatusChange(Task $task, User $changer): void
    {
        $assignedUser = $task->getAssignedUser();
        $currentUser = $assignedUser ?? $task->getUser();
        
        // Don't notify the user who made the change
        if ($currentUser === $changer) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $statusLabels = [
            'pending' => 'в ожидании',
            'in_progress' => 'в процессе выполнения',
            'completed' => 'завершена'
        ];
        $statusLabel = $statusLabels[$task->getStatus()] ?? $task->getStatus();

        $notification->setTitle('Статус задачи изменен')
            ->setMessage(sprintf(
                'Статус задачи "%s" изменен на "%s" пользователем %s', 
                $task->getName(),
                $statusLabel,
                $changer->getFullName()
            ))
            ->setUser($currentUser)
            ->setTask($task);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyTaskComment(Task $task, User $commenter, string $commentContent): void
    {
        $assignedUser = $task->getAssignedUser();
        $creatorUser = $task->getUser();
        $currentUser = $assignedUser ?? $creatorUser;
        
        // Don't notify the user who made the comment
        if ($currentUser === $commenter) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $notification->setTitle('Новый комментарий к задаче')
            ->setMessage(sprintf(
                'К задаче "%s" добавлен новый комментарий пользователем %s: %s', 
                $task->getName(),
                $commenter->getFullName(),
                substr($commentContent, 0, 100) . (strlen($commentContent) > 100 ? '...' : '')
            ))
            ->setUser($currentUser)
            ->setTask($task);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyTaskPriorityChange(Task $task, User $changer): void
    {
        $assignedUser = $task->getAssignedUser();
        $currentUser = $assignedUser ?? $task->getUser();
        
        // Don't notify the user who made the change
        if ($currentUser === $changer) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $priorityLabels = [
            'low' => 'низкий',
            'medium' => 'средний',
            'high' => 'высокий',
            'urgent' => 'критический'
        ];
        $priorityLabel = $priorityLabels[$task->getPriority()] ?? $task->getPriority();

        $notification->setTitle('Приоритет задачи изменен')
            ->setMessage(sprintf(
                'Приоритет задачи "%s" изменен на "%s" пользователем %s', 
                $task->getName(),
                $priorityLabel,
                $changer->getFullName()
            ))
            ->setUser($currentUser)
            ->setTask($task);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyTaskDueDateChange(Task $task, User $changer): void
    {
        $assignedUser = $task->getAssignedUser();
        $currentUser = $assignedUser ?? $task->getUser();
        
        // Don't notify the user who made the change
        if ($currentUser === $changer) {
            return;
        }

        // Create database notification
        $notification = new Notification();
        $notification->setTitle('Срок выполнения задачи изменен')
            ->setMessage(sprintf(
                'Срок выполнения задачи "%s" изменен на %s пользователем %s', 
                $task->getName(),
                $task->getDueDate()?->format('d.m.Y'),
                $changer->getFullName()
            ))
            ->setUser($currentUser)
            ->setTask($task);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function buildEmailContent(Task $task, User $assigner, string $action = 'назначена'): string
    {
        $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $assignedUser = $task->getAssignedUser();
        $userName = $assignedUser ? $assignedUser->getFullName() : 'пользователь';
        
        $html = '<h2>Новая задача ' . $action . '</h2>' .
               '<p>Здравствуйте, ' . htmlspecialchars($userName) . '!</p>' .
               '<p>Вам ' . $action . ' задача: <strong>' . htmlspecialchars($task->getName()) . '</strong></p>' .
               '<p><strong>Описание:</strong> ' . htmlspecialchars($task->getDescription() ?? '') . '</p>' .
               '<p><strong>Приоритет:</strong> ' . htmlspecialchars($task->getPriorityLabel()) . '</p>';
        
        if ($task->getDeadline()) {
            $html .= '<p><strong>Срок выполнения:</strong> ' . $task->getDeadline()->format('d.m.Y') . '</p>';
        }
        
        $html .= '<p><strong>Назначил(а):</strong> ' . htmlspecialchars($assigner->getFullName()) . '</p>' .
               '<p><a href="' . $taskUrl . '" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Перейти к задаче</a></p>' .
               '<p>С уважением,<br>Система управления задачами</p>';
        
        return $html;
    }

    private function buildDeadlineEmailContent(Task $task): string
    {
        $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $assignedUser = $task->getAssignedUser();
        $userName = $assignedUser ? $assignedUser->getFullName() : 'пользователь';
        
        return '<h2>Напоминание о сроке выполнения задачи</h2>' .
               '<p>Здравствуйте, ' . htmlspecialchars($userName) . '!</p>' .
               '<p>Срок выполнения задачи: <strong>' . htmlspecialchars($task->getName()) . '</strong> приближается</p>' .
               '<p><strong>Описание:</strong> ' . htmlspecialchars($task->getDescription() ?? '') . '</p>' .
               '<p><strong>Приоритет:</strong> ' . htmlspecialchars($task->getPriorityLabel()) . '</p>' .
               '<p><strong>Срок выполнения:</strong> ' . ($task->getDeadline() ? $task->getDeadline()->format('d.m.Y') : 'не указан') . '</p>' .
               '<p><a href="' . $taskUrl . '" style="background-color: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Перейти к задаче</a></p>' .
               '<p>С уважением,<br>Система управления задачами</p>';
    }
}