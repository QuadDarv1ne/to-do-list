<?php

namespace App\Command;

use App\Service\DataCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-data',
    description: 'Clean up old data from the application'
)]
class DataCleanupCommand extends Command
{
    private DataCleanupService $dataCleanupService;

    public function __construct(DataCleanupService $dataCleanupService)
    {
        $this->dataCleanupService = $dataCleanupService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: cleanup, stats', 'cleanup')
            ->addOption('activity-logs-days', null, InputOption::VALUE_REQUIRED, 'Days to keep activity logs', '30')
            ->addOption('notifications-days', null, InputOption::VALUE_REQUIRED, 'Days to keep notifications', '30')
            ->addOption('comments-days', null, InputOption::VALUE_REQUIRED, 'Days to keep comments', '60')
            ->addOption('time-tracking-days', null, InputOption::VALUE_REQUIRED, 'Days to keep time tracking', '90')
            ->addOption('password-reset-days', null, InputOption::VALUE_REQUIRED, 'Days to keep password reset requests', '7')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be cleaned without actually deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $dryRun = $input->getOption('dry-run');
        
        $options = [
            'activity_logs_days' => (int)$input->getOption('activity-logs-days'),
            'notifications_days' => (int)$input->getOption('notifications-days'),
            'comments_days' => (int)$input->getOption('comments-days'),
            'time_tracking_days' => (int)$input->getOption('time-tracking-days'),
            'password_reset_days' => (int)$input->getOption('password-reset-days'),
        ];

        $io->title('Data Cleanup Tool');

        switch ($action) {
            case 'cleanup':
                if ($dryRun) {
                    $io->writeln('<comment>Dry run mode - no data will be deleted</comment>');
                    
                    $stats = $this->dataCleanupService->getOldDataStatistics($options);
                    
                    $io->section('Data that would be cleaned:');
                    $io->table(
                        ['Data Type', 'Records to Clean'],
                        [
                            ['Activity Logs (older than ' . $options['activity_logs_days'] . ' days)', $stats['activity_logs_old']],
                            ['Notifications (older than ' . $options['notifications_days'] . ' days)', $stats['notifications_old']],
                            ['Comments (older than ' . $options['comments_days'] . ' days)', $stats['comments_old']],
                            ['Time Tracking (older than ' . $options['time_tracking_days'] . ' days)', $stats['time_tracking_old']],
                            ['Password Reset Requests (older than ' . $options['password_reset_days'] . ' days)', $stats['password_reset_requests_old']],
                        ]
                    );
                } else {
                    $io->writeln('Starting data cleanup...');
                    
                    $results = $this->dataCleanupService->performComprehensiveCleanup($options);
                    
                    $io->success('Data cleanup completed!');
                    $io->table(
                        ['Data Type', 'Records Deleted'],
                        [
                            ['Activity Logs', $results['activity_logs_deleted']],
                            ['Notifications', $results['notifications_deleted']],
                            ['Comments', $results['comments_deleted']],
                            ['Time Tracking', $results['time_tracking_deleted']],
                            ['Password Reset Requests', $results['password_reset_requests_deleted']],
                            ['Total', $results['total_deleted']],
                        ]
                    );
                    $io->writeln('Duration: ' . $results['duration'] . ' seconds');
                }
                break;

            case 'stats':
                $io->writeln('Getting old data statistics...');
                
                $stats = $this->dataCleanupService->getOldDataStatistics($options);
                
                $io->success('Old data statistics:');
                $io->table(
                    ['Data Type', 'Old Records Count'],
                    [
                        ['Activity Logs (older than ' . $options['activity_logs_days'] . ' days)', $stats['activity_logs_old']],
                        ['Notifications (older than ' . $options['notifications_days'] . ' days)', $stats['notifications_old']],
                        ['Comments (older than ' . $options['comments_days'] . ' days)', $stats['comments_old']],
                        ['Time Tracking (older than ' . $options['time_tracking_days'] . ' days)', $stats['time_tracking_old']],
                        ['Password Reset Requests (older than ' . $options['password_reset_days'] . ' days)', $stats['password_reset_requests_old']],
                    ]
                );
                break;

            default:
                $io->error("Unknown action: {$action}. Use cleanup or stats.");
                return 1;
        }

        return 0;
    }
}
