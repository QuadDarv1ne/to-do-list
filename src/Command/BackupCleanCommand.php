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
    name: 'app:backup:clean',
    description: 'Удалить старые бэкапы',
)]
class BackupCleanCommand extends Command
{
    public function __construct(
        private BackupService $backupService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Удалить бэкапы старше N дней', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title('Очистка старых бэкапов');
        $io->info("Удаление бэкапов старше {$days} дней...");

        $deleted = $this->backupService->cleanOldBackups($days);

        if ($deleted > 0) {
            $io->success("Удалено бэкапов: {$deleted}");
        } else {
            $io->info('Нет бэкапов для удаления');
        }

        return Command::SUCCESS;
    }
}
