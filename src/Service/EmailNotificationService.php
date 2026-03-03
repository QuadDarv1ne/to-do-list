<?php

namespace App\Service;

use App\Entity\TaskNotification;
use App\Entity\User;
use Psr\Log\LoggerInterface;
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
        private ?LoggerInterface $logger = null,
        private string $fromEmail = '',
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

            // Проверяем email получателя
            if (!$recipient->getEmail()) {
                if ($this->logger) {
                    $this->logger->warning('Получатель без email', [
                        'user_id' => $recipient->getId(),
                        'notification_id' => $notification->getId(),
                    ]);
                }

                return;
            }

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($recipient->getEmail())
                ->subject($notification->getSubject())
                ->html($this->renderTemplate($notification));

            $this->mailer->send($email);

            // Логируем отправку
            if ($this->logger) {
                $this->logger->info('Email уведомление отправлено', [
                    'notification_id' => $notification->getId(),
                    'recipient' => $recipient->getEmail(),
                    'type' => $notification->getType(),
                ]);
            }

            // Mark as sent
            $notification->setIsSent(true);
        } catch (\Exception $e) {
            // Логируем ошибку
            if ($this->logger) {
                $this->logger->error('Ошибка отправки email уведомления', [
                    'notification_id' => $notification->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => \get_class($e),
                ]);
            }

            throw $e;
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
