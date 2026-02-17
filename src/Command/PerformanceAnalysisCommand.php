<?php

namespace App\Command;

use App\Service\PerformanceMonitorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:performance:analyze',
    description: 'Analyze application performance and index effectiveness'
)]
class PerformanceAnalysisCommand extends Command
{
    private PerformanceMonitorService $performanceMonitorService;

    public function __construct(PerformanceMonitorService $performanceMonitorService)
    {
        $this->performanceMonitorService = $performanceMonitorService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting performance analysis...</info>');
        $output->writeln('');

        // Monitor task query performance
        $output->writeln('<comment>Analyzing task query performance...</comment>');
        $taskMetrics = $this->performanceMonitorService->monitorTaskQueryPerformance([
            'status' => 'pending',
            'priority' => 'high'
        ]);
        
        $table = new Table($output);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRows([
            ['Execution Time (ms)', $taskMetrics['execution_time_ms']],
            ['Task Count', $taskMetrics['task_count']],
            ['Filters Applied', $taskMetrics['filters_applied']],
        ]);
        $table->render();

        $output->writeln('');
        
        // Get search performance metrics
        $output->writeln('<comment>Evaluating search performance...</comment>');
        $searchMetrics = $this->performanceMonitorService->getSearchPerformanceMetrics();
        
        $table = new Table($output);
        $table->setHeaders(['Search Type', 'Execution Time (ms)', 'Results Found']);
        foreach ($searchMetrics as $type => $metric) {
            $table->addRow([
                ucfirst(str_replace('_', ' ', $type)),
                $metric['execution_time_ms'],
                $metric['result_count']
            ]);
        }
        $table->render();

        $output->writeln('');

        // Check index effectiveness
        $output->writeln('<comment>Checking index effectiveness...</comment>');
        $indexReport = $this->performanceMonitorService->getIndexEffectivenessReport();
        
        if (isset($indexReport['index_utilization']['query_uses_optimized_indexes'])) {
            $indexUsesOptimized = $indexReport['index_utilization']['query_uses_optimized_indexes'];
            $status = $indexUsesOptimized ? '<fg=green>✓ Effective</>' : '<fg=red>⚠ Needs Improvement</>';
            
            $output->writeln("Index Utilization: {$status}");
            
            $output->writeln("\nAvailable Optimized Indexes:");
            foreach ($indexReport['index_utilization']['indexes_available'] as $index) {
                $output->writeln("  • {$index}");
            }
        } else {
            $output->writeln('<fg=yellow>Unable to determine index effectiveness (database may not support EXPLAIN QUERY PLAN)</>');
        }

        $output->writeln('');
        $output->writeln('<info>Performance analysis completed!</info>');

        return Command::SUCCESS;
    }
}