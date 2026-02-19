<?php

namespace App\Command;

use App\Service\QueryPerformanceMonitor;
use App\Service\RuntimePerformanceMonitor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monitor-performance',
    description: 'Мониторинг производительности в реальном времени'
)]
class MonitorPerformanceCommand extends Command
{
    public function __construct(
        private QueryPerformanceMonitor $queryMonitor,
        private RuntimePerformanceMonitor $runtimeMonitor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Интервал обновления (сек)', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = (int) $input->getOption('interval');

        $io->title('Мониторинг производительности');
        $io->text('Нажмите Ctrl+C для выхода');

        while (true) {
            $io->section('Статистика на ' . date('H:i:s'));

            // Статистика запросов
            $queryStats = $this->queryMonitor->getStatistics();
            $io->text([
                'Запросы к БД:',
                "  Всего: {$queryStats['total_queries']}",
                "  Медленных: {$queryStats['slow_queries']}",
                "  Общее время: {$queryStats['total_duration']}",
                "  Среднее время: {$queryStats['avg_duration']}"
            ]);

            // Статистика памяти
            $runtimeStats = $this->runtimeMonitor->getCurrentStats();
            $io->text([
                '',
                'Использование памяти:',
                "  Текущая: {$runtimeStats['current_memory']}",
                "  Пиковая: {$runtimeStats['peak_memory']}",
                "  Использовано: {$runtimeStats['memory_used']}"
            ]);

            // Предупреждения
            $warnings = $this->runtimeMonitor->checkLimits();
            if (!empty($warnings)) {
                $io->warning('Обнаружены проблемы:');
                foreach ($warnings as $warning) {
                    $io->text('  • ' . $warning['message']);
                }
            }

            sleep($interval);
            
            if ($output->isVerbose()) {
                $io->newLine();
            } else {
                // Очищаем экран для обновления
                $output->write("\033[2J\033[;H");
            }
        }

        return Command::SUCCESS;
    }
}
