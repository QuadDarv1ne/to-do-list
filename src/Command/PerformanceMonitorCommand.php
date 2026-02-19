<?php

namespace App\Command;

use App\Service\PerformanceMonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:performance-monitor',
    description: 'Monitor and analyze application performance metrics',
)]
class PerformanceMonitorCommand extends Command
{
    private PerformanceMonitoringService $performanceMonitor;

    public function __construct(PerformanceMonitoringService $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: report, slow-ops, memory-stats, clear')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Threshold for slow operations (in ms)', '100')
            ->addOption('operation', 'o', InputOption::VALUE_REQUIRED, 'Specific operation to analyze');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');

        $io->title('Performance Monitoring Tool');

        switch ($action) {
            case 'report':
                $this->showPerformanceReport($io);

                break;

            case 'slow-ops':
                $threshold = (int) $input->getOption('threshold');
                $this->showSlowOperations($io, $threshold);

                break;

            case 'memory-stats':
                $this->showMemoryStats($io);

                break;

            case 'clear':
                $operation = $input->getOption('operation');
                $this->clearMetrics($io, $operation);

                break;

            default:
                $this->showHelp($io);

                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showPerformanceReport(SymfonyStyle $io): void
    {
        $io->section('Performance Report');

        $report = $this->performanceMonitor->getPerformanceReport();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Environment', $report['environment']],
                ['Generated At', $report['timestamp']],
                ['Total Operations Timed', $report['summary']['total_operations_timed']],
                ['Average Execution Time', $report['summary']['average_execution_time_ms'] . ' ms'],
                ['Slowest Operation', ($report['summary']['slowest_operation_ms'] ?? 0) . ' ms (' . ($report['summary']['slowest_operation_name'] ?? 'N/A') . ')'],
                ['Fastest Operation', ($report['summary']['fastest_operation_ms'] ?? 0) . ' ms (' . ($report['summary']['fastest_operation_name'] ?? 'N/A') . ')'],
                ['Total Memory Used', $this->formatBytes($report['summary']['total_memory_used_bytes'])],
            ],
        );

        // Show detailed metrics for operations
        if (!empty($report['detailed_metrics'])) {
            $io->section('Detailed Operation Metrics');

            $rows = [];
            foreach ($report['detailed_metrics'] as $operation => $metrics) {
                $rows[] = [
                    $operation,
                    $metrics['count'],
                    $metrics['avg_execution_time_ms'] . ' ms',
                    $metrics['min_execution_time_ms'] . ' ms',
                    $metrics['max_execution_time_ms'] . ' ms',
                    $metrics['total_execution_time_ms'] . ' ms',
                ];
            }

            $io->table(
                ['Operation', 'Count', 'Avg Time', 'Min Time', 'Max Time', 'Total Time'],
                $rows,
            );
        }

        // Show recommendations
        if (!empty($report['recommendations'])) {
            $io->section('Performance Recommendations');

            foreach ($report['recommendations'] as $rec) {
                $io->text("<comment>[{$rec['priority']}]</comment> {$rec['message']}");
            }
        } else {
            $io->success('No performance recommendations at this time.');
        }
    }

    private function showSlowOperations(SymfonyStyle $io, int $threshold): void
    {
        $io->section("Slow Operations (>{ $threshold}ms)");

        $slowOps = $this->performanceMonitor->getSlowOperations($threshold);

        if (empty($slowOps)) {
            $io->success("No operations found exceeding {$threshold}ms threshold.");

            return;
        }

        $rows = [];
        foreach ($slowOps as $op) {
            $rows[] = [
                $op['operation'],
                $op['execution_time_ms'] . ' ms',
                $this->formatBytes($op['memory_used_bytes']),
                date('Y-m-d H:i:s', (int)$op['timestamp']),
            ];
        }

        $io->table(
            ['Operation', 'Execution Time', 'Memory Used', 'Timestamp'],
            $rows,
        );

        $io->writeln('<info>Found ' . \count($slowOps) . ' slow operations.</info>');
    }

    private function showMemoryStats(SymfonyStyle $io): void
    {
        $io->section('Memory Statistics');

        $stats = $this->performanceMonitor->getMemoryStats();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Current Memory Usage', $stats['current_formatted']],
                ['Peak Memory Usage', $stats['peak_formatted']],
                ['Current Bytes', $stats['current_memory_usage']],
                ['Peak Bytes', $stats['peak_memory_usage']],
            ],
        );
    }

    private function clearMetrics(SymfonyStyle $io, ?string $operation): void
    {
        if ($operation) {
            $this->performanceMonitor->clearMetrics($operation);
            $io->success("Cleared metrics for operation: {$operation}");
        } else {
            $this->performanceMonitor->clearMetrics();
            $io->success('Cleared all performance metrics.');
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'report - Show comprehensive performance report',
            'slow-ops - Show operations exceeding threshold',
            'memory-stats - Show memory usage statistics',
            'clear - Clear metrics (optionally for specific operation)',
        ]);

        $io->section('Options');
        $io->listing([
            '--action (-a) - Action to perform',
            '--threshold (-t) - Threshold for slow operations (default: 100ms)',
            '--operation (-o) - Specific operation to clear',
        ]);

        $io->section('Examples');
        $io->listing([
            'php bin/console app:performance-monitor --action=report',
            'php bin/console app:performance-monitor --action=slow-ops --threshold=200',
            'php bin/console app:performance-monitor --action=memory-stats',
            'php bin/console app:performance-monitor --action=clear --operation=task_repository_findAll',
        ]);
    }
}
