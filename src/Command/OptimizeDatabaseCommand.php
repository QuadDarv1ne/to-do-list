<?php

namespace App\Command;

use App\Service\DatabaseOptimizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-database',
    description: 'Оптимизирует базу данных: анализ, индексы, очистка',
)]
class OptimizeDatabaseCommand extends Command
{
    public function __construct(
        private DatabaseOptimizationService $dbOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('analyze-only', 'a', InputOption::VALUE_NONE, 'Только анализ без изменений')
            ->addOption('cleanup-days', 'c', InputOption::VALUE_OPTIONAL, 'Дней для хранения старых данных', 365)
            ->addOption('skip-cleanup', null, InputOption::VALUE_NONE, 'Пропустить очистку данных');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $analyzeOnly = $input->getOption('analyze-only');
        $cleanupDays = (int) $input->getOption('cleanup-days');
        $skipCleanup = $input->getOption('skip-cleanup');

        $io->title('Оптимизация базы данных');

        // Анализ производительности
        $io->section('Анализ производительности БД');

        try {
            $analysis = $this->dbOptimizer->analyzeQueryPerformance();

            if (isset($analysis['error'])) {
                $io->error('Ошибка анализа: ' . $analysis['error']);
            } else {
                $this->displayAnalysisResults($io, $analysis);
            }
        } catch (\Exception $e) {
            $io->error('Ошибка анализа БД: ' . $e->getMessage());
        }

        if ($analyzeOnly) {
            $io->info('Режим только анализа - изменения не применяются');

            return Command::SUCCESS;
        }

        // Оптимизация таблиц
        $io->section('Оптимизация таблиц (VACUUM ANALYZE)');

        try {
            $tableResults = $this->dbOptimizer->optimizeTables();

            $optimized = 0;
            $errors = 0;

            foreach ($tableResults as $table => $result) {
                if ($result === 'optimized') {
                    $optimized++;
                } elseif (str_starts_with($result, 'error:')) {
                    $errors++;
                    $io->warning("Таблица {$table}: {$result}");
                }
            }

            $io->success("Оптимизировано таблиц: {$optimized}, ошибок: {$errors}");
        } catch (\Exception $e) {
            $io->error('Ошибка оптимизации таблиц: ' . $e->getMessage());
        }

        // Создание индексов
        $io->section('Создание оптимальных индексов');

        try {
            $indexResults = $this->dbOptimizer->createOptimalIndexes();

            $created = 0;
            $errors = 0;

            foreach ($indexResults as $index => $result) {
                if ($result === 'created') {
                    $created++;
                    $io->text("✓ Создан индекс: {$index}");
                } elseif (str_starts_with($result, 'error:')) {
                    $errors++;
                    $io->warning("Индекс {$index}: {$result}");
                }
            }

            $io->success("Создано индексов: {$created}, ошибок: {$errors}");
        } catch (\Exception $e) {
            $io->error('Ошибка создания индексов: ' . $e->getMessage());
        }

        // Очистка старых данных
        if (!$skipCleanup) {
            $io->section("Очистка данных старше {$cleanupDays} дней");

            if (!$io->confirm('Продолжить очистку старых данных?', false)) {
                $io->info('Очистка данных пропущена');
            } else {
                try {
                    $cleanupResults = $this->dbOptimizer->cleanupOldData($cleanupDays);

                    if (isset($cleanupResults['error'])) {
                        $io->error('Ошибка очистки: ' . $cleanupResults['error']);
                    } else {
                        $io->success(\sprintf(
                            'Удалено: %d логов активности, %d уведомлений',
                            $cleanupResults['activity_logs_deleted'] ?? 0,
                            $cleanupResults['notifications_deleted'] ?? 0,
                        ));
                    }
                } catch (\Exception $e) {
                    $io->error('Ошибка очистки данных: ' . $e->getMessage());
                }
            }
        }

        $io->success('Оптимизация базы данных завершена!');

        return Command::SUCCESS;
    }

    private function displayAnalysisResults(SymfonyStyle $io, array $analysis): void
    {
        // Статистика подключений
        if (isset($analysis['connection_stats'])) {
            $stats = $analysis['connection_stats'];
            $io->text(\sprintf(
                'Подключения: %d всего, %d активных, %d простаивающих',
                $stats['total_connections'] ?? 0,
                $stats['active_connections'] ?? 0,
                $stats['idle_connections'] ?? 0,
            ));
        }

        // Медленные запросы
        if (isset($analysis['slow_queries']) && \is_array($analysis['slow_queries'])) {
            if (\count($analysis['slow_queries']) > 0) {
                $io->warning('Найдены медленные запросы: ' . \count($analysis['slow_queries']));

                foreach (\array_slice($analysis['slow_queries'], 0, 3) as $query) {
                    if (isset($query['mean_exec_time'])) {
                        $io->text(\sprintf(
                            '• Среднее время: %.2f мс, вызовов: %d',
                            $query['mean_exec_time'],
                            $query['calls'] ?? 0,
                        ));
                    }
                }
            } else {
                $io->success('Медленные запросы не найдены');
            }
        }

        // Неиспользуемые индексы
        if (isset($analysis['index_usage']) && \is_array($analysis['index_usage'])) {
            if (\count($analysis['index_usage']) > 0) {
                $io->warning('Неиспользуемые индексы: ' . \count($analysis['index_usage']));
            } else {
                $io->success('Все индексы используются');
            }
        }

        // Размеры таблиц
        if (isset($analysis['table_sizes']) && \is_array($analysis['table_sizes'])) {
            $io->text('Топ-3 самые большие таблицы:');
            foreach (\array_slice($analysis['table_sizes'], 0, 3) as $table) {
                $io->text(\sprintf('• %s: %s', $table['tablename'], $table['size']));
            }
        }

        // Рекомендации
        if (isset($analysis['recommendations']) && \is_array($analysis['recommendations'])) {
            if (\count($analysis['recommendations']) > 0) {
                $io->section('Рекомендации по оптимизации');
                foreach ($analysis['recommendations'] as $rec) {
                    $priority = match($rec['priority']) {
                        'high' => '🔴',
                        'medium' => '🟡',
                        'low' => '🟢',
                        default => '•'
                    };
                    $io->text("{$priority} {$rec['message']} - {$rec['suggestion']}");
                }
            }
        }
    }
}
