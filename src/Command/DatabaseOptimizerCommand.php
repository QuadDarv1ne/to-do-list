<?php

namespace App\Command;

use App\Service\DatabaseOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-database',
    description: 'Monitor and optimize database usage',
)]
class DatabaseOptimizerCommand extends Command
{
    private DatabaseOptimizerService $databaseOptimizerService;

    public function __construct(DatabaseOptimizerService $databaseOptimizerService)
    {
        $this->databaseOptimizerService = $databaseOptimizerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: analyze, optimize-table, optimize-all, stats, cleanup', 'analyze')
            ->addOption('table', 't', InputOption::VALUE_REQUIRED, 'Table name for specific operations')
            ->addOption('date-field', null, InputOption::VALUE_REQUIRED, 'Date field for cleanup operations', 'created_at')
            ->addOption('retention-days', null, InputOption::VALUE_REQUIRED, 'Retention period in days for cleanup', '30')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');

        $io->title('Database Optimizer Tool');

        switch ($action) {
            case 'analyze':
                $io->writeln('Analyzing database...');

                $result = $this->databaseOptimizerService->analyzeDatabase();

                if ($format === 'json') {
                    $outputData = [
                        'analysis_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Analysis saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    if ($result['success']) {
                        $this->displayAnalysisReport($io, $result['analysis']);

                        if ($outputFile) {
                            $textData = $this->getAnalysisTextReport($result['analysis']);
                            file_put_contents($outputFile, $textData);
                            $io->success("Analysis saved to {$outputFile}");
                        }
                    } else {
                        $io->error('Database analysis failed: ' . $result['error']);

                        return 1;
                    }
                }

                break;

            case 'optimize-table':
                $table = $input->getOption('table');

                if (!$table) {
                    $io->error('Table name is required for optimize-table action');

                    return 1;
                }

                $io->writeln("Optimizing table: {$table}");

                $result = $this->databaseOptimizerService->optimizeTable($table);

                if ($format === 'json') {
                    $outputData = [
                        'optimization_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Optimization result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    if ($result['success']) {
                        $io->success($result['message']);

                        if ($outputFile) {
                            $textData = "TABLE OPTIMIZATION RESULT\n\n{$result['message']}\n";
                            file_put_contents($outputFile, $textData);
                            $io->success("Result saved to {$outputFile}");
                        }
                    } else {
                        $io->error('Table optimization failed: ' . $result['error']);

                        return 1;
                    }
                }

                break;

            case 'optimize-all':
                $io->writeln('Optimizing all tables...');

                $result = $this->databaseOptimizerService->optimizeAllTables();

                if ($format === 'json') {
                    $outputData = [
                        'optimization_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Optimization result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $io->success('All tables optimization completed!');
                    $io->table(
                        ['Metric', 'Value'],
                        [
                            ['Total Tables', $result['total_tables']],
                            ['Optimized Tables', \count($result['optimized_tables'])],
                            ['Failed Tables', \count($result['failed_tables'])],
                        ],
                    );

                    if (!empty($result['failed_tables'])) {
                        $io->section('Failed Tables');
                        $io->error('The following tables failed to optimize:');
                        $failedTableNames = array_map(function ($table) {
                            return $table['table'];
                        }, $result['failed_tables']);
                        $io->listing($failedTableNames);
                    }

                    if ($outputFile) {
                        $textData = $this->getAllTablesOptimizationTextReport($result);
                        file_put_contents($outputFile, $textData);
                        $io->success("Result saved to {$outputFile}");
                    }
                }

                break;

            case 'stats':
                $io->writeln('Getting database statistics...');

                $result = $this->databaseOptimizerService->getDatabaseStats();

                if ($format === 'json') {
                    $outputData = [
                        'stats_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Statistics saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    if ($result['success']) {
                        $this->displayStatsReport($io, $result['stats']);

                        if ($outputFile) {
                            $textData = $this->getStatsTextReport($result['stats']);
                            file_put_contents($outputFile, $textData);
                            $io->success("Statistics saved to {$outputFile}");
                        }
                    } else {
                        $io->error('Failed to get database statistics: ' . $result['error']);

                        return 1;
                    }
                }

                break;

            case 'cleanup':
                $table = $input->getOption('table');
                $dateField = $input->getOption('date-field');
                $retentionDays = (int)$input->getOption('retention-days');

                if (!$table) {
                    $io->error('Table name is required for cleanup action');

                    return 1;
                }

                $io->writeln("Cleaning up old records from {$table}, older than {$retentionDays} days...");

                $result = $this->databaseOptimizerService->cleanupOldRecords($table, $dateField, $retentionDays);

                if ($format === 'json') {
                    $outputData = [
                        'cleanup_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Cleanup result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    if ($result['success']) {
                        $io->success("Cleaned up {$result['records_deleted']} old records from {$table}");

                        if ($outputFile) {
                            $textData = "CLEANUP RESULT\n\nCleaned up {$result['records_deleted']} old records from {$table}\n";
                            file_put_contents($outputFile, $textData);
                            $io->success("Result saved to {$outputFile}");
                        }
                    } else {
                        $io->error('Cleanup failed: ' . $result['error']);

                        return 1;
                    }
                }

                break;

            default:
                $io->error("Unknown action: {$action}. Use analyze, optimize-table, optimize-all, stats, or cleanup.");

                return 1;
        }

        return 0;
    }

    private function displayAnalysisReport(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Database Analysis');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Platform', $analysis['database_info']['platform']],
                ['Database Name', $analysis['database_info']['database_name']],
                ['Table Count', $analysis['database_info']['table_count']],
                ['Driver', $analysis['database_info']['driver']],
                ['Analysis Time', $analysis['analysis_time'] . ' seconds'],
            ],
        );

        if (!empty($analysis['table_sizes'])) {
            $io->section('Table Sizes (Top 10 Largest)');

            $tableSizes = \array_slice($analysis['table_sizes'], 0, 10);
            $rows = [];

            foreach ($tableSizes as $table) {
                $rows[] = [
                    $table['name'],
                    $table['row_count'],
                ];
            }

            $io->table(['Table', 'Row Count'], $rows);
        }

        if (!empty($analysis['indexes_info'])) {
            $io->section('Index Information');

            $indexCount = \count($analysis['indexes_info']);
            $uniqueCount = \count(array_filter($analysis['indexes_info'], function ($idx) {
                return $idx['is_unique'];
            }));
            $primaryCount = \count(array_filter($analysis['indexes_info'], function ($idx) {
                return $idx['is_primary'];
            }));

            $io->table(
                ['Index Type', 'Count'],
                [
                    ['Total Indexes', $indexCount],
                    ['Unique Indexes', $uniqueCount],
                    ['Primary Indexes', $primaryCount],
                ],
            );
        }

        if (!empty($analysis['recommendations'])) {
            $io->section('Recommendations');

            foreach ($analysis['recommendations'] as $rec) {
                $style = $rec['level'] === 'WARNING' ? 'error' : 'info';
                $io->writeln("<{$style}>{$rec['level']}: {$rec['message']}</{$style}>");
                $io->writeln("  Suggestion: {$rec['suggestion']}");
                $io->writeln('');
            }
        }
    }

    private function displayStatsReport(SymfonyStyle $io, array $stats): void
    {
        $io->section('Database Statistics');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Records', $stats['total_records']],
                ['Table Count', $stats['table_count']],
                ['Largest Table', $stats['largest_table'] ? $stats['largest_table']['table'] . ' (' . $stats['largest_table']['records'] . ' records)' : 'N/A'],
            ],
        );

        if (!empty($stats['table_record_counts'])) {
            $io->section('Table Record Counts (Top 10)');

            $tableCounts = \array_slice($stats['table_record_counts'], 0, 10);
            $rows = [];

            foreach ($tableCounts as $table) {
                $rows[] = [
                    $table['table'],
                    $table['records'],
                ];
            }

            $io->table(['Table', 'Records'], $rows);
        }
    }

    private function getAnalysisTextReport(array $analysis): string
    {
        $report = "DATABASE ANALYSIS REPORT\n";
        $report .= str_repeat('=', 30) . "\n\n";

        $report .= "Basic Info:\n";
        $report .= "  Platform: {$analysis['database_info']['platform']}\n";
        $report .= "  Database Name: {$analysis['database_info']['database_name']}\n";
        $report .= "  Table Count: {$analysis['database_info']['table_count']}\n";
        $report .= "  Driver: {$analysis['database_info']['driver']}\n";
        $report .= "  Analysis Time: {$analysis['analysis_time']} seconds\n\n";

        if (!empty($analysis['table_sizes'])) {
            $report .= "Table Sizes (Top 10 Largest):\n";
            $tableSizes = \array_slice($analysis['table_sizes'], 0, 10);
            foreach ($tableSizes as $table) {
                $report .= "  {$table['name']}: {$table['row_count']} rows\n";
            }
            $report .= "\n";
        }

        if (!empty($analysis['indexes_info'])) {
            $indexCount = \count($analysis['indexes_info']);
            $uniqueCount = \count(array_filter($analysis['indexes_info'], function ($idx) {
                return $idx['is_unique'];
            }));
            $primaryCount = \count(array_filter($analysis['indexes_info'], function ($idx) {
                return $idx['is_primary'];
            }));

            $report .= "Index Information:\n";
            $report .= "  Total Indexes: {$indexCount}\n";
            $report .= "  Unique Indexes: {$uniqueCount}\n";
            $report .= "  Primary Indexes: {$primaryCount}\n\n";
        }

        if (!empty($analysis['recommendations'])) {
            $report .= "Recommendations:\n";
            foreach ($analysis['recommendations'] as $rec) {
                $report .= "{$rec['level']}: {$rec['message']}\n";
                $report .= "  Suggestion: {$rec['suggestion']}\n\n";
            }
        }

        return $report;
    }

    private function getStatsTextReport(array $stats): string
    {
        $report = "DATABASE STATISTICS REPORT\n";
        $report .= str_repeat('=', 30) . "\n\n";

        $report .= "Total Records: {$stats['total_records']}\n";
        $report .= "Table Count: {$stats['table_count']}\n";
        $report .= 'Largest Table: ' . ($stats['largest_table'] ? $stats['largest_table']['table'] . " ({$stats['largest_table']['records']} records)" : 'N/A') . "\n\n";

        if (!empty($stats['table_record_counts'])) {
            $report .= "Table Record Counts (Top 10):\n";
            $tableCounts = \array_slice($stats['table_record_counts'], 0, 10);
            foreach ($tableCounts as $table) {
                $report .= "  {$table['table']}: {$table['records']} records\n";
            }
            $report .= "\n";
        }

        return $report;
    }

    private function getAllTablesOptimizationTextReport(array $result): string
    {
        $report = "ALL TABLES OPTIMIZATION REPORT\n";
        $report .= str_repeat('=', 40) . "\n\n";

        $report .= "Total Tables: {$result['total_tables']}\n";
        $report .= 'Optimized Tables: ' . \count($result['optimized_tables']) . "\n";
        $report .= 'Failed Tables: ' . \count($result['failed_tables']) . "\n\n";

        if (!empty($result['failed_tables'])) {
            $report .= "FAILED TABLES:\n";
            foreach ($result['failed_tables'] as $failedTable) {
                $report .= "- {$failedTable['table']}: {$failedTable['error']}\n";
            }
            $report .= "\n";
        }

        return $report;
    }
}
