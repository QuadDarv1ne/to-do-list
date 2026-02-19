<?php

namespace App\Service;

use App\Entity\TaskNotification;
use App\Entity\User;
use App\Service\PerformanceMonitorService;
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
        private ?PerformanceMonitorService $performanceMonitor = null,
        private string $fromEmail = 'noreply@todolist.local'
    ) {
    }

    public function sendTaskNotification(TaskNotification $notification): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('email_notification_service_send_task_notification');
        }
        try {
            $recipient = $notification->getRecipient();
            $task = $notification->getTask();

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($recipient->getEmail())
                ->subject($notification->getSubject())
                ->html($this->renderTemplate($notification));

            $this->mailer->send($email);

            // Mark as sent
            $notification->setIsSent(true);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('email_notification_service_send_task_notification');
            }
        }
    }

    private function renderTemplate(TaskNotification $notification): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('email_notification_service_render_template');
        }
        try {
            $task = $notification->getTask();
            $recipient = $notification->getRecipient();
            $sender = $notification->getSender();

            return $this->twig->render('emails/task_notification.html.twig', [
                'notification' => $notification,
                'task' => $task,
                'recipient' => $recipient,
                'sender' => $sender,
            ]);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('email_notification_service_render_template');
            }
        }
    }

    public function sendTaskAssignmentNotification(User $assignee, User $assigner, $task): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('email_notification_service_send_task_assignment_notification');
        }
        try {
            $notification = new TaskNotification();
            $notification->setType('assignment');
            $notification->setRecipient($assignee);
            $notification->setSender($assigner);
            $notification->setTask($task);
            $notification->setSubject("Вам назначена задача: {$task->getName()}");
            $notification->setMessage("{$assigner->getFullName()} назначил(а) вам задачу \"{$task->getName()}\".\n\n{$task->getDescription()}");

            $this->sendTaskNotification($notification);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('email_notification_service_send_task_assignment_notification');
            }
        }
    }

    public function sendTaskUpdateNotification(User $recipient, User $updater, $task, string $changes): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('email_notification_service_send_task_update_notification');
        }
        try {
            $notification = new TaskNotification();
            $notification->setType('update');
            $notification->setRecipient($recipient);
            $notification->setSender($updater);
            $notification->setTask($task);
            $notification->setSubject("Задача обновлена: {$task->getName()}");
            $notification->setMessage("{$updater->getFullName()} внес изменения в задачу \"{$task->getName()}\".\n\nИзменения: {$changes}");

            $this->sendTaskNotification($notification);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('email_notification_service_send_task_update_notification');
            }
        }
    }

    public function sendTaskCompletionNotification(User $assigner, User $completer, $task): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('email_notification_service_send_task_completion_notification');
        }
        try {
            $notification = new TaskNotification();
            $notification->setType('completion');
            $notification->setRecipient($assigner);
            $notification->setSender($completer);
            $notification->setTask($task);
            $notification->setSubject("Задача выполнена: {$task->getName()}");
            $notification->setMessage("{$completer->getFullName()} отметил(а) задачу \"{$task->getName()}\" как выполненную.");

            $this->sendTaskNotification($notification);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('email_notification_service_send_task_completion_notification');
            }
        }
    }
}
