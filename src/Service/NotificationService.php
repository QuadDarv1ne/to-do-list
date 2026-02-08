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
        
        if (!$assignedUser || $task->isDone()) {
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

    private function buildEmailContent(Task $task, User $assigner, string $action = 'назначена'): string
    {
        $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $html = "
            <h2>Новая задача {$action}</h2>
            <p>Здравствуйте, {$task->getAssignedUser()->getFullName()}!</p>
            <p>Вам {$action} задача: <strong>{$task->getName()}</strong></p>
            <p><strong>Описание:</strong> {$task->getDescription()}</p>
            <p><strong>Приоритет:</strong> {$task->getPriorityLabel()}</p>
        ";
        if ($task->getDeadline()) {
            $html .= "<p><strong>Срок выполнения:</strong> {$task->getDeadline()->format('d.m.Y')}</p>";
        }
        $html .= "
            <p><strong>Назначил(а):</strong> {$assigner->getFullName()}</p>
            <p><a href=\"{$taskUrl}\" style=\"background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Перейти к задаче</a></p>
            <p>С уважением,<br>Система управления задачами</p>
        ";
        
        return $html;
    }

    private function buildDeadlineEmailContent(Task $task): string
    {
        $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        return "
            <h2>Напоминание о сроке выполнения задачи</h2>
            <p>Здравствуйте, {$task->getAssignedUser()->getFullName()}!</p>
            <p>Срок выполнения задачи: <strong>{$task->getName()}</strong> приближается</p>
            <p><strong>Описание:</strong> {$task->getDescription()}</p>
            <p><strong>Приоритет:</strong> {$task->getPriorityLabel()}</p>
            <p><strong>Срок выполнения:</strong> {$task->getDeadline()?->format('d.m.Y')}</p>
            <p><a href=\"{$taskUrl}\" style=\"background-color: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Перейти к задаче</a></p>
            <p>С уважением,<br>Система управления задачами</p>
        ";
    }
}