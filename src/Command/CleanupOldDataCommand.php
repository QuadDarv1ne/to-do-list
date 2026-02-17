<?php

namespace App\Command;

use App\Service\RealTimeNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-old-data',
    description: 'Cleanup old notifications and archived data'
)]
class CleanupOldDataCommand extends Command
{
    public function __construct(
        private RealTimeNotificationService $notificationService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('notifications-days', null, InputOption::VALUE_OPTIONAL, 'Delete read notifications older than X days', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without actually deleting data')
            ->setHelp('This command cleans up old data like read notifications and archived tasks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $notificationsDays = (int) $input->getOption('notifications-days');

        $io->title('Data Cleanup Process');

        if ($dryRun) {
            $io->warning('Running in DRY-RUN mode - no data will be deleted');
        }

        // Cleanup old notifications
        $io->section('Cleaning up old notifications');
        $io->text(sprintf('Deleting read notifications older than %d days...', $notificationsDays));
        
        if (!$dryRun) {
            $deletedNotifications = $this->notificationService->deleteOldNotifications($notificationsDays);
            $io->success(sprintf('Deleted %d old notifications', $deletedNotifications));
        } else {
            $io->info('Would delete old notifications (dry-run mode)');
        }

        // Cleanup old activity logs (if exists)
        $io->section('Cleaning up old activity logs');
        if (!$dryRun) {
            $deletedLogs = $this->cleanupOldActivityLogs(180); // 6 months
            $io->success(sprintf('Deleted %d old activity logs', $deletedLogs));
        } else {
            $io->info('Would delete old activity logs (dry-run mode)');
        }

        // Summary
        $io->section('Summary');
        $io->table(
            ['Category', 'Status'],
            [
                ['Notifications', $dryRun ? 'Simulated' : 'Cleaned'],
                ['Activity Logs', $dryRun ? 'Simulated' : 'Cleaned'],
            ]
        );

        $io->success('Cleanup process completed successfully!');

        return Command::SUCCESS;
    }

    private function cleanupOldActivityLogs(int $daysOld): int
    {
        $date = new \DateTime();
        $date->modify("-{$daysOld} days");

        try {
            $qb = $this->entityManager->createQueryBuilder();
            return $qb->delete('App\Entity\ActivityLog', 'a')
                ->where('a.createdAt < :date')
                ->setParameter('date', $date)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            // ActivityLog entity might not exist
            return 0;
        }
    }
}
