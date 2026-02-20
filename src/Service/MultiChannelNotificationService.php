<?php

namespace App\Service;

use App\Entity\Notification as NotificationEntity;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

class MultiChannelNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private NotificationTemplateService $templateService,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private ?NotifierInterface $notifier = null,
    ) {
    }

    /**
     * Send notification through multiple channels
     */
    public function sendNotification(
        User $user,
        string $title,
        string $message,
        string $type = NotificationEntity::TYPE_INFO,
        array $channels = [NotificationEntity::CHANNEL_IN_APP],
        ?array $metadata = null,
        ?string $templateKey = null,
        array $templateVariables = [],
    ): NotificationEntity {
        $notification = new NotificationEntity();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setIsRead(false);
        $notification->setMetadata($metadata);
        $notification->setTemplateKey($templateKey);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Send through each channel
        foreach ($channels as $channel) {
            $this->sendThroughChannel($notification, $channel, $templateVariables);
        }

        return $notification;
    }

    /**
     * Send notification through specific channel
     */
    private function sendThroughChannel(
        NotificationEntity $notification,
        string $channel,
        array $templateVariables = []
    ): void {
        try {
            switch ($channel) {
                case NotificationEntity::CHANNEL_IN_APP:
                    $this->sendInAppNotification($notification);
                    break;
                    
                case NotificationEntity::CHANNEL_EMAIL:
                    $this->sendEmailNotification($notification, $templateVariables);
                    break;
                    
                case NotificationEntity::CHANNEL_PUSH:
                    $this->sendPushNotification($notification, $templateVariables);
                    break;
                    
                case NotificationEntity::CHANNEL_SMS:
                    $this->sendSmsNotification($notification, $templateVariables);
                    break;
                    
                case NotificationEntity::CHANNEL_SLACK:
                    $this->sendSlackNotification($notification, $templateVariables);
                    break;
                    
                case NotificationEntity::CHANNEL_TELEGRAM:
                    $this->sendTelegramNotification($notification, $templateVariables);
                    break;
                    
                default:
                    $this->logger->warning("Unknown notification channel: {$channel}");
                    $notification->markAsFailed("Unknown channel: {$channel}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to send notification through channel {$channel}: " . $e->getMessage());
            $notification->markAsFailed($e->getMessage());
        }

        $this->entityManager->flush();
    }

    /**
     * Send in-app notification (database only)
     */
    private function sendInAppNotification(NotificationEntity $notification): void
    {
        $notification->markAsSent();
        $this->logger->info("In-app notification sent for user {$notification->getUser()->getId()}");
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(NotificationEntity $notification, array $variables = []): void
    {
        $user = $notification->getUser();
        
        if (!$user->getEmail()) {
            $notification->markAsFailed('User has no email address');
            return;
        }

        try {
            $subject = $notification->getTitle();
            $content = $notification->getMessage();

            // Use template if available
            if ($notification->getTemplateKey()) {
                $template = $this->templateService->renderTemplate(
                    $notification->getTemplateKey(),
                    NotificationEntity::CHANNEL_EMAIL,
                    array_merge($variables, [
                        'user_name' => $user->getFullName(),
                        'notification_title' => $notification->getTitle(),
                        'notification_message' => $notification->getMessage(),
                    ])
                );
                $subject = $template['subject'];
                $content = $template['content'];
            }

            $email = (new Email())
                ->from('noreply@crm-system.com')
                ->to($user->getEmail())
                ->subject($subject)
                ->html($content);

            $this->mailer->send($email);
            
            $notification->markAsSent();
            $this->logger->info("Email notification sent to {$user->getEmail()}");
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(NotificationEntity $notification, array $variables = []): void
    {
        if (!$this->notifier) {
            $notification->markAsFailed('Notifier service not configured');
            return;
        }

        try {
            $user = $notification->getUser();
            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            
            $message = $notification->getMessage();
            if ($notification->getTemplateKey()) {
                $template = $this->templateService->renderTemplate(
                    $notification->getTemplateKey(),
                    NotificationEntity::CHANNEL_PUSH,
                    $variables
                );
                $message = $template['content'];
            }

            $pushNotification = new \Symfony\Component\Notifier\Notification\Notification(
                $notification->getTitle(),
                ['push']
            );
            $pushNotification->content($message);

            $this->notifier->send($pushNotification, $recipient);
            
            $notification->markAsSent();
            $this->logger->info("Push notification sent to user {$user->getId()}");
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(NotificationEntity $notification, array $variables = []): void
    {
        if (!$this->notifier) {
            $notification->markAsFailed('Notifier service not configured');
            return;
        }

        try {
            $user = $notification->getUser();
            if (!$user->getPhone()) {
                $notification->markAsFailed('User has no phone number');
                return;
            }

            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            $message = $notification->getMessage();
            
            if ($notification->getTemplateKey()) {
                $template = $this->templateService->renderTemplate(
                    $notification->getTemplateKey(),
                    NotificationEntity::CHANNEL_SMS,
                    $variables
                );
                $message = $template['content'];
            }

            $smsNotification = new \Symfony\Component\Notifier\Notification\Notification(
                $message,
                ['sms']
            );

            $this->notifier->send($smsNotification, $recipient);
            
            $notification->markAsSent();
            $this->logger->info("SMS notification sent to user {$user->getId()}");
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(NotificationEntity $notification, array $variables = []): void
    {
        if (!$this->notifier) {
            $notification->markAsFailed('Notifier service not configured');
            return;
        }

        try {
            $user = $notification->getUser();
            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            
            $message = $notification->getMessage();
            if ($notification->getTemplateKey()) {
                $template = $this->templateService->renderTemplate(
                    $notification->getTemplateKey(),
                    NotificationEntity::CHANNEL_SLACK,
                    $variables
                );
                $message = $template['content'];
            }

            $slackNotification = new \Symfony\Component\Notifier\Notification\Notification(
                $notification->getTitle(),
                ['chat/slack']
            );
            $slackNotification->content($message);

            $this->notifier->send($slackNotification, $recipient);
            
            $notification->markAsSent();
            $this->logger->info("Slack notification sent to user {$user->getId()}");
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegramNotification(NotificationEntity $notification, array $variables = []): void
    {
        if (!$this->notifier) {
            $notification->markAsFailed('Notifier service not configured');
            return;
        }

        try {
            $user = $notification->getUser();
            $recipient = new Recipient($user->getEmail(), $user->getFullName());
            
            $message = $notification->getMessage();
            if ($notification->getTemplateKey()) {
                $template = $this->templateService->renderTemplate(
                    $notification->getTemplateKey(),
                    NotificationEntity::CHANNEL_TELEGRAM,
                    $variables
                );
                $message = $template['content'];
            }

            $telegramNotification = new \Symfony\Component\Notifier\Notification\Notification(
                $notification->getTitle(),
                ['chat/telegram']
            );
            $telegramNotification->content($message);

            $this->notifier->send($telegramNotification, $recipient);
            
            $notification->markAsSent();
            $this->logger->info("Telegram notification sent to user {$user->getId()}");
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's notification preferences
     */
    public function getUserPreferences(User $user): array
    {
        // This would typically fetch from database
        // For now, return default preferences
        return [
            NotificationEntity::CHANNEL_IN_APP => true,
            NotificationEntity::CHANNEL_EMAIL => $user->getEmail() ? true : false,
            NotificationEntity::CHANNEL_PUSH => true,
            NotificationEntity::CHANNEL_SMS => $user->getPhone() ? true : false,
            NotificationEntity::CHANNEL_SLACK => false,
            NotificationEntity::CHANNEL_TELEGRAM => false,
        ];
    }
}