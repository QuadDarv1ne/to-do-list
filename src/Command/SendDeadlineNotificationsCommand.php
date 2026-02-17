<?php

namespace App\Command;

use App\Service\DeadlineNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-deadline-notifications',
    description: 'Send deadline notifications for upcoming tasks'
)]
class SendDeadlineNotificationsCommand extends Command
{
    private DeadlineNotificationService $deadlineNotificationService;

    public function __construct(DeadlineNotificationService $deadlineNotificationService)
    {
        $this->deadlineNotificationService = $deadlineNotificationService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Deadline Notifications');
        
        try {
            $results = $this->deadlineNotificationService->sendUpcomingDeadlineNotifications();
            
            $io->success('Deadline notifications sent successfully!');
            
            $io->table(
                ['Notification Type', 'Count'],
                [
                    ['Email Notifications', $results['email_sent']],
                    ['Push Notifications', $results['push_sent']],
                    ['SMS Notifications', $results['sms_sent']],
                    ['Failed Notifications', $results['failed']],
                    ['Total Tasks Processed', $results['tasks_processed']]
                ]
            );
            
            // Show statistics
            $stats = $this->deadlineNotificationService->getNotificationStatistics();
            $io->section('Upcoming Deadline Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Upcoming Tasks', $stats['total_upcoming']],
                    ['Urgent Tasks', $stats['urgent_count']],
                    ['High Priority Tasks', $stats['high_count']],
                    ['Medium Priority Tasks', $stats['medium_count']],
                    ['Low Priority Tasks', $stats['low_count']]
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send deadline notifications: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}