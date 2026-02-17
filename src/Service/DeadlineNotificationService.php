<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Service for handling deadline notifications and reminders
 */
class DeadlineNotificationService
{
    private EntityManagerInterface $entityManager;
    private TaskRepository $taskRepository;
    private MailerInterface $mailer;
    private NotifierInterface $notifier;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        MailerInterface $mailer,
        NotifierInterface $notifier,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->mailer = $mailer;
        $this->notifier = $notifier;
        $this->logger = $logger;
    }

    /**
     * Send deadline notifications for upcoming tasks
     */
    public function sendUpcomingDeadlineNotifications(): array
    {
        $this->logger->info('Starting deadline notification process');
        
        $results = [
            'email_sent' => 0,
            'push_sent' => 0,
            'sms_sent' => 0,
            'failed' => 0,
            'tasks_processed' => 0
        ];

        // Get tasks with deadlines in the next 24 hours
        $upcomingTasks = $this->taskRepository->findTasksWithUpcomingDeadlines();
        
        foreach ($upcomingTasks as $task) {
            $user = $task->getUser();
            if (!$user || !$user->isActive()) {
                continue;
            }

            $results['tasks_processed']++;
            
            // Send email notification
            if ($this->sendEmailNotification($task, $user)) {
                $results['email_sent']++;
            }
            
            // Send push notification if available
            if ($this->sendPushNotification($task, $user)) {
                $results['push_sent']++;
            }
            
            // Send SMS for critical deadlines (urgent priority)
            if ($task->getPriority() === 'urgent' && $this->sendSmsNotification($task, $user)) {
                $results['sms_sent']++;
            }
        }

        $this->logger->info('Deadline notifications completed', $results);
        return $results;
    }

    /**
     * Send email notification for upcoming deadline
     */
    private function sendEmailNotification(Task $task, User $user): bool
    {
        try {
            $email = (new Email())
                ->from('noreply@todo-app.com')
                ->to($user->getEmail())
                ->subject('Напоминание о дедлайне задачи')
                ->html($this->generateDeadlineEmailContent($task));

            $this->mailer->send($email);
            $this->logger->info("Email notification sent for task {$task->getId()}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send email notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification for upcoming deadline
     */
    private function sendPushNotification(Task $task, User $user): bool
    {
        try {
            // Check if user has enabled push notifications
            if (!$this->userHasPushNotificationsEnabled($user)) {
                return false;
            }

            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            
            $notification = new \Symfony\Component\Notifier\Notification\Notification(
                'Напоминание о дедлайне',
                ['email']
            );
            $notification->content("Задача '{$task->getTitle()}' истекает завтра");
            
            $this->notifier->send($notification, $recipient);

            $this->logger->info("Push notification sent for task {$task->getId()}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send push notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification for critical deadlines
     */
    private function sendSmsNotification(Task $task, User $user): bool
    {
        try {
            // Check if user has phone number and SMS enabled
            if (!$user->getPhoneNumber() || !$this->userHasSmsEnabled($user)) {
                return false;
            }

            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            
            // SMS notification using mailer as fallback
            $smsEmail = (new Email())
                ->from('sms@todo-app.com')
                ->to($user->getEmail())
                ->subject('SMS: Срочное напоминание')
                ->text("Срочно! Задача '{$task->getTitle()}' истекает завтра. Приоритет: {$task->getPriorityLabel()}");

            $this->mailer->send($smsEmail);

            $this->logger->info("SMS notification sent for task {$task->getId()}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send SMS notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML content for deadline email
     */
    private function generateDeadlineEmailContent(Task $task): string
    {
        $dueDate = $task->getDueDate();
        $timeLeft = $dueDate ? $dueDate->diff(new \DateTime())->format('%d дней %h часов') : 'не указан';
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 20px;'>
            <h2 style='color: #d32f2f;'>Напоминание о дедлайне</h2>
            <p>Здравствуйте, {$task->getUser()->getFullName()}!</p>
            <p>Срок выполнения задачи <strong>{$task->getTitle()}</strong> истекает завтра.</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #d32f2f; margin: 20px 0;'>
                <h3>Детали задачи:</h3>
                <ul>
                    <li><strong>Название:</strong> {$task->getTitle()}</li>
                    <li><strong>Приоритет:</strong> {$task->getPriorityLabel()}</li>
                    <li><strong>Срок:</strong> " . ($dueDate ? $dueDate->format('d.m.Y H:i') : 'Не указан') . "</li>
                    <li><strong>Осталось времени:</strong> {$timeLeft}</li>
                </ul>
            </div>
            
            <p>
                <a href='{$_ENV['APP_URL']}/tasks/{$task->getId()}' 
                   style='background-color: #1976d2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>
                    Перейти к задаче
                </a>
            </p>
            
            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                Это автоматическое уведомление. Пожалуйста, не отвечайте на это письмо.
            </p>
        </body>
        </html>
        ";
    }

    /**
     * Check if user has push notifications enabled
     */
    private function userHasPushNotificationsEnabled(User $user): bool
    {
        // This would check user preferences in database
        // For now, return true for all active users
        return $user->isActive();
    }

    /**
     * Check if user has SMS notifications enabled
     */
    private function userHasSmsEnabled(User $user): bool
    {
        // Check if user has SMS notifications enabled
        // For now, return false as we're using email fallback
        return false;
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(): array
    {
        $today = new \DateTime();
        $tomorrow = (clone $today)->modify('+1 day');
        
        $qb = $this->entityManager->createQueryBuilder();
        
        $stats = $qb->select('
                COUNT(t.id) as total_upcoming,
                SUM(CASE WHEN t.priority = :urgent THEN 1 ELSE 0 END) as urgent_count,
                SUM(CASE WHEN t.priority = :high THEN 1 ELSE 0 END) as high_count,
                SUM(CASE WHEN t.priority = :medium THEN 1 ELSE 0 END) as medium_count,
                SUM(CASE WHEN t.priority = :low THEN 1 ELSE 0 END) as low_count
            ')
            ->from(Task::class, 't')
            ->where('t.dueDate >= :today')
            ->andWhere('t.dueDate <= :tomorrow')
            ->andWhere('t.status != :completed')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('completed', 'completed')
            ->setParameter('urgent', 'urgent')
            ->setParameter('high', 'high')
            ->setParameter('medium', 'medium')
            ->setParameter('low', 'low')
            ->getQuery()
            ->getSingleResult();

        return [
            'total_upcoming' => (int) $stats['total_upcoming'],
            'urgent_count' => (int) $stats['urgent_count'],
            'high_count' => (int) $stats['high_count'],
            'medium_count' => (int) $stats['medium_count'],
            'low_count' => (int) $stats['low_count'],
            'timestamp' => new \DateTime()
        ];
    }
}
