<?php

namespace App\Service;

use App\Entity\TaskNotification;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendTaskNotification(TaskNotification $notification): void
    {
        $recipient = $notification->getRecipient();
        $task = $notification->getTask();

        $email = (new Email())
            ->from('noreply@todolist.local')
            ->to($recipient->getEmail())
            ->subject($notification->getSubject())
            ->html($this->renderTemplate($notification));

        $this->mailer->send($email);

        // Mark as sent
        $notification->setIsSent(true);
    }

    private function renderTemplate(TaskNotification $notification): string
    {
        $task = $notification->getTask();
        $recipient = $notification->getRecipient();
        $sender = $notification->getSender();

        return $this->twig->render('emails/task_notification.html.twig', [
            'notification' => $notification,
            'task' => $task,
            'recipient' => $recipient,
            'sender' => $sender,
        ]);
    }

    public function sendTaskAssignmentNotification(User $assignee, User $assigner, $task): void
    {
        $notification = new TaskNotification();
        $notification->setType('assignment');
        $notification->setRecipient($assignee);
        $notification->setSender($assigner);
        $notification->setTask($task);
        $notification->setSubject("Вам назначена задача: {$task->getName()}");
        $notification->setMessage("{$assigner->getFullName()} назначил(а) вам задачу \"{$task->getName()}\".\n\n{$task->getDescription()}");

        $this->sendTaskNotification($notification);
    }

    public function sendTaskUpdateNotification(User $recipient, User $updater, $task, string $changes): void
    {
        $notification = new TaskNotification();
        $notification->setType('update');
        $notification->setRecipient($recipient);
        $notification->setSender($updater);
        $notification->setTask($task);
        $notification->setSubject("Задача обновлена: {$task->getName()}");
        $notification->setMessage("{$updater->getFullName()} внес изменения в задачу \"{$task->getName()}\".\n\nИзменения: {$changes}");

        $this->sendTaskNotification($notification);
    }

    public function sendTaskCompletionNotification(User $assigner, User $completer, $task): void
    {
        $notification = new TaskNotification();
        $notification->setType('completion');
        $notification->setRecipient($assigner);
        $notification->setSender($completer);
        $notification->setTask($task);
        $notification->setSubject("Задача выполнена: {$task->getName()}");
        $notification->setMessage("{$completer->getFullName()} отметил(а) задачу \"{$task->getName()}\" как выполненную.");

        $this->sendTaskNotification($notification);
    }
}