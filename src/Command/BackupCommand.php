<?php

namespace App\Command;

use App\Service\BackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup',
    description: 'Manage application backups',
)]
class BackupCommand extends Command
{
    private BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: create, list, restore, cleanup', 'create')
            ->addOption('include-database', null, InputOption::VALUE_NONE, 'Include database in backup')
            ->addOption('backup-path', 'p', InputOption::VALUE_OPTIONAL, 'Path for restore action')
            ->addOption('keep-days', 'k', InputOption::VALUE_REQUIRED, 'Number of days to keep backups for cleanup', '7')
            ->addOption('restore-files', null, InputOption::VALUE_NONE, 'Restore files during restore action')
            ->addOption('restore-database', null, InputOption::VALUE_NONE, 'Restore database during restore action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $includeDatabase = $input->getOption('include-database');
        $backupPath = $input->getOption('backup-path');
        $keepDays = (int)$input->getOption('keep-days');
        $restoreFiles = $input->getOption('restore-files');
        $restoreDatabase = $input->getOption('restore-database');

        $io->title('Backup Management Tool');

        switch ($action) {
            case 'create':
                $io->writeln('Creating backup...');

                $result = $this->backupService->createBackup();

                if ($result['success']) {
                    $io->success('Backup created successfully!');
                    $io->writeln('Path: ' . ($result['path'] ?? 'N/A'));
                    $io->writeln('Duration: ' . ($result['manifest']['duration'] ?? 0) . ' seconds');
                    $io->writeln('Size: ' . $this->formatFileSize($result['manifest']['size'] ?? 0));
                } else {
                    $io->error('Backup failed: ' . ($result['error'] ?? 'Unknown error'));

                    return 1;
                }

                break;

            case 'list':
                $io->writeln('Listing available backups...');

                $backups = $this->backupService->getBackups();

                if (empty($backups)) {
                    $io->writeln('No backups found.');
                } else {
                    $rows = [];
                    foreach ($backups as $backup) {
                        $rows[] = [
                            $backup['name'],
                            $backup['manifest']['timestamp'] ?? 'N/A',
                            $this->formatFileSize($backup['size']),
                            $backup['manifest']['type'] ?? 'N/A',
                            $backup['manifest']['includes_database'] ?? false ? 'Yes' : 'No',
                        ];
                    }

                    $io->table(
                        ['Name', 'Timestamp', 'Size', 'Type', 'DB Included'],
                        $rows,
                    );
                }

                break;

            case 'restore':
                if (!$backupPath) {
                    $io->error('Backup path is required for restore action');

                    return 1;
                }

                $io->writeln("Restoring from: {$backupPath}");

                $result = $this->backupService->restoreFromBackup($backupPath);

                if ($result['success']) {
                    $io->success('Restore completed successfully!');
                } else {
                    $io->error('Restore failed: ' . $result['error']);

                    return 1;
                }

                break;

            case 'cleanup':
                $io->writeln("Cleaning up backups older than {$keepDays} days...");

                $deletedCount = $this->backupService->cleanupOldBackups($keepDays);

                $io->success(\sprintf(
                    'Cleanup completed! Deleted %d backups.',
                    $deletedCount,
                ));

                break;

            default:
                $io->error("Unknown action: {$action}. Use create, list, restore, or cleanup.");

                return 1;
        }

        return 0;
    }

    private function formatFileSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
