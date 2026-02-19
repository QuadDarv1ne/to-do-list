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
    description: 'Очистка устаревших данных'
)]
class CleanupDataCommand extends Command
{
    public function __construct(
        private DataCleanupService $cleanupService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Показать только статистику')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Выполнить без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Очистка устаревших данных');

        // Показываем статистику
        $stats = $this->cleanupService->getCleanupStats();
        
        $io->section('Данные для очистки');
        $io->text([
            "Старые логи активности (>90 дней): {$stats['old_activity_logs']}",
            "Прочитанные уведомления (>30 дней): {$stats['old_notifications']}",
            "Завершенные задачи (>365 дней): {$stats['old_completed_tasks']}"
        ]);

        if ($input->getOption('stats')) {
            return Command::SUCCESS;
        }

        // Подтверждение
        if (!$input->getOption('force')) {
            if (!$io->confirm('Продолжить очистку?', false)) {
                $io->info('Очистка отменена');
                return Command::SUCCESS;
            }
        }

        // Выполняем очистку
        $io->section('Выполнение очистки');
        $results = $this->cleanupService->cleanupAll();

        $io->success([
            'Очистка завершена:',
            "Удалено логов: {$results['activity_logs']}",
            "Удалено уведомлений: {$results['notifications']}",
            "Архивировано задач: {$results['archived_tasks']}",
            "Удалено истории: {$results['task_history']}"
        ]);

        return Command::SUCCESS;
    }
}
