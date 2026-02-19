<?php

namespace App\Command;

use App\Service\DatabaseOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для оптимизации базы данных
 * 
 * Использование:
 *   php bin/console db:optimize                    # Полная оптимизация
 *   php bin/console db:optimize --table=tasks      # Оптимизация таблицы
 *   php bin/console db:optimize --analyze-only     # Только анализ
 *   php bin/console db:optimize --cleanup=30       # Очистка данных старше 30 дней
 */
#[AsCommand(
    name: 'db:optimize',
    description: 'Оптимизация базы данных: индексы, анализ, очистка'
)]
class DatabaseOptimizeCommand extends Command
{
    public function __construct(
        private DatabaseOptimizerService $optimizer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'Имя таблицы для оптимизации')
            ->addOption('analyze-only', null, InputOption::VALUE_NONE, 'Только анализ без изменений')
            ->addOption('cleanup', null, InputOption::VALUE_REQUIRED, 'Очистить данные старше N дней')
            ->addOption('create-indexes', null, InputOption::VALUE_NONE, 'Создать рекомендуемые индексы')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Выполнить все оптимизации');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Оптимизация базы данных');

        $tableName = $input->getOption('table');
        $analyzeOnly = $input->getOption('analyze-only');
        $cleanupDays = $input->getOption('cleanup');
        $createIndexes = $input->getOption('create-indexes');
        $runAll = $input->getOption('all');

        try {
            // Анализ таблицы
            if ($tableName || $runAll || !$analyzeOnly) {
                $tables = $tableName ? [$tableName] : $this->getImportantTables();
                
                foreach ($tables as $table) {
                    $io->section("📊 Анализ таблицы: {$table}");
                    
                    $result = $this->optimizer->analyzeTable($table);
                    
                    if (isset($result['error'])) {
                        $io->error("Ошибка анализа: {$result['error']}");
                        continue;
                    }
                    
                    // Вывод статистики
                    $io->table(
                        ['Метрика', 'Значение'],
                        [
                            ['Записей', number_format($result['stats']['row_count'] ?? 0)],
                            ['Индексов', count($result['indexes'] ?? [])],
                            ['Рекомендации', count($result['recommendations'] ?? [])]
                        ]
                    );
                    
                    // Вывод рекомендаций
                    if (!empty($result['recommendations'])) {
                        $io->writeln('💡 Рекомендации:');
                        foreach ($result['recommendations'] as $rec) {
                            $icon = $rec['type'] === 'critical' ? '🔴' : '🟡';
                            $io->writeln("  {$icon} {$rec['message']}");
                        }
                        $io->newLine();
                    }
                }
            }

            // Создание индексов
            if (($createIndexes || $runAll) && !$analyzeOnly) {
                $io->section('📑 Создание индексов');
                
                $results = $this->optimizer->createRecommendedIndexes();
                
                $successCount = count(array_filter($results, fn($r) => $r['success']));
                $io->success("Создано индексов: {$successCount} / " . count($results));
                
                $io->table(
                    ['Таблица', 'Индекс', 'Статус'],
                    array_map(fn($r) => [
                        $r['table'],
                        $r['index'],
                        $r['success'] ? '✅' : '❌'
                    ], $results)
                );
            }

            // Очистка старых данных
            if ($cleanupDays) {
                $io->section('🧹 Очистка старых данных');
                
                $tablesToCleanup = [
                    'activity_logs' => 'created_at',
                    'task_history' => 'created_at',
                    'notifications' => 'created_at'
                ];
                
                foreach ($tablesToCleanup as $table => $dateColumn) {
                    $deleted = $this->optimizer->cleanupOldData($table, $dateColumn, (int)$cleanupDays);
                    $io->writeln("  • {$table}: удалено {$deleted} записей");
                }
                
                $io->success("Очистка завершена (данные старше {$cleanupDays} дней)");
            }

            // Оптимизация хранилища
            if (!$analyzeOnly && ($runAll || $tableName)) {
                $io->section('💾 Оптимизация хранилища');
                
                $tables = $tableName ? [$tableName] : $this->getImportantTables();
                
                foreach ($tables as $table) {
                    $success = $this->optimizer->optimizeTableStorage($table);
                    $io->writeln("  • {$table}: " . ($success ? '✅' : '❌'));
                }
            }

            // Статистика запросов
            $io->section('📈 Статистика запросов');
            $stats = $this->optimizer->getQueryStats();
            
            $io->table(
                ['Метрика', 'Значение'],
                [
                    ['Всего запросов', $stats['total_queries'] ?? 0],
                    ['Среднее время', ($stats['avg_time'] ?? 0) . 's'],
                    ['Макс. время', ($stats['max_time'] ?? 0) . 's'],
                    ['Медленные запросы', $stats['slow_queries'] ?? 0]
                ]
            );

            $io->newLine();
            $io->success('✅ Оптимизация завершена!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Ошибка оптимизации: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getImportantTables(): array
    {
        return [
            'tasks',
            'users',
            'comments',
            'activity_logs',
            'task_history',
            'notifications'
        ];
    }
}
