<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\NotificationTemplate;
use App\Repository\NotificationTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationTemplateService
{
    public function __construct(
        private NotificationTemplateRepository $templateRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get template by key and channel
     */
    public function getTemplate(string $key, string $channel): ?NotificationTemplate
    {
        return $this->templateRepository->findTemplateForNotification($key, $channel);
    }

    /**
     * Render notification content using template
     */
    public function renderTemplate(string $key, string $channel, array $variables = []): array
    {
        $template = $this->getTemplate($key, $channel);
        
        if (!$template) {
            $this->logger->warning("Template not found for key: {$key}, channel: {$channel}");
            return [
                'subject' => 'Notification',
                'content' => 'No template found',
            ];
        }

        return [
            'subject' => $template->renderSubject($variables),
            'content' => $template->renderContent($variables),
        ];
    }

    /**
     * Create default templates
     */
    public function createDefaultTemplates(): void
    {
        $templates = [
            // Task templates
            [
                'key' => 'task_assigned',
                'name' => 'Task Assigned',
                'channel' => Notification::CHANNEL_EMAIL,
                'subject' => 'New task assigned: {{task_title}}',
                'content' => 'Hello {{user_name}},<br><br>You have been assigned a new task: <strong>{{task_title}}</strong><br><br>Description: {{task_description}}<br>Due date: {{due_date}}<br><br>View task: <a href="{{task_url}}">{{task_url}}</a>',
                'variables' => ['user_name', 'task_title', 'task_description', 'due_date', 'task_url'],
            ],
            [
                'key' => 'task_completed',
                'name' => 'Task Completed',
                'channel' => Notification::CHANNEL_EMAIL,
                'subject' => 'Task completed: {{task_title}}',
                'content' => 'Task <strong>{{task_title}}</strong> has been completed by {{completed_by}}.<br><br>View task: <a href="{{task_url}}">{{task_url}}</a>',
                'variables' => ['task_title', 'completed_by', 'task_url'],
            ],
            [
                'key' => 'deadline_reminder',
                'name' => 'Deadline Reminder',
                'channel' => Notification::CHANNEL_EMAIL,
                'subject' => 'Deadline reminder: {{task_title}}',
                'content' => 'Reminder: Task <strong>{{task_title}}</strong> is due {{due_date}}.<br><br>Please complete it soon.<br><br>View task: <a href="{{task_url}}">{{task_url}}</a>',
                'variables' => ['task_title', 'due_date', 'task_url'],
            ],
            // System templates
            [
                'key' => 'system_alert',
                'name' => 'System Alert',
                'channel' => Notification::CHANNEL_EMAIL,
                'subject' => 'System Alert: {{alert_type}}',
                'content' => 'System alert: {{alert_message}}<br><br>Time: {{timestamp}}',
                'variables' => ['alert_type', 'alert_message', 'timestamp'],
            ],
        ];

        foreach ($templates as $templateData) {
            if (!$this->templateRepository->findByKey($templateData['key'])) {
                $template = new NotificationTemplate();
                $template->setKey($templateData['key']);
                $template->setName($templateData['name']);
                $template->setChannel($templateData['channel']);
                $template->setSubject($templateData['subject']);
                $template->setContent($templateData['content']);
                $template->setVariables($templateData['variables']);
                $template->setIsActive(true);

                $this->entityManager->persist($template);
                $this->logger->info("Created template: {$templateData['key']}");
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get all active templates by channel
     */
    public function getTemplatesByChannel(string $channel): array
    {
        return $this->templateRepository->findActiveByChannel($channel);
    }
}