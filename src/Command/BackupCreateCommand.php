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
    name: 'app:backup:create',
    description: 'Создать бэкап базы данных',
)]
class BackupCreateCommand extends Command
{
    public function __construct(
        private BackupService $backupService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('incremental', 'i', InputOption::VALUE_NONE, 'Создать инкрементальный бэкап')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Количество дней для инкрементального бэкапа', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Создание бэкапа базы данных');

        if ($input->getOption('incremental')) {
            $days = (int) $input->getOption('days');
            $since = new \DateTime("-{$days} days");
            
            $io->info("Создание инкрементального бэкапа (изменения за последние {$days} дней)...");
            $result = $this->backupService->createIncrementalBackup($since);
        } else {
            $io->info('Создание полного бэкапа...');
            $result = $this->backupService->createFullBackup();
        }

        if ($result['success']) {
            $io->success([
                'Бэкап успешно создан!',
                'Файл: ' . $result['filename'],
                'Размер: ' . round($result['size'] / 1024 / 1024, 2) . ' MB'
            ]);
            return Command::SUCCESS;
        } else {
            $io->error('Ошибка создания бэкапа: ' . $result['error']);
            return Command::FAILURE;
        }
    }
}
