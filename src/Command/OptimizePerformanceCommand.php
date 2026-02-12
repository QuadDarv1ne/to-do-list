<?php

namespace App\Command;

use App\Service\PerformanceMonitorService;
use App\Service\QueryCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:optimize-performance',
    description: 'Run performance optimization tasks and clean up unused resources'
)]
class OptimizePerformanceCommand extends Command
{
    private PerformanceMonitorService $performanceMonitor;
    private QueryCacheService $queryCacheService;

    public function __construct(
        PerformanceMonitorService $performanceMonitor,
        QueryCacheService $queryCacheService
    ) {
        $this->performanceMonitor = $performanceMonitor;
        $this->queryCacheService = $queryCacheService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting performance optimization process...</info>');
        
        $progressBar = new ProgressBar($output, 4);
        $progressBar->setFormat('verbose');
        
        // Task 1: Analyze performance metrics
        $progressBar->setMessage('Analyzing performance metrics...');
        $progressBar->start();
        
        $metrics = $this->performanceMonitor->getPerformanceReport();
        $aggregateMetrics = $this->performanceMonitor->getAggregateMetrics();
        
        $progressBar->advance();
        
        // Task 2: Report slow operations
        $progressBar->setMessage('Identifying slow operations...');
        $slowQueries = $this->performanceMonitor->getSlowQueries();
        
        if (!empty($slowQueries)) {
            $output->writeln('<comment>Found ' . count($slowQueries) . ' slow queries:</comment>');
            foreach (array_slice($slowQueries, 0, 5) as $query) { // Show top 5
                $output->writeln(sprintf(
                    '  - %s (%.2f ms)',
                    $query['source'] ?? 'unknown',
                    $query['execution_time_ms'] ?? 0
                ));
            }
        }
        
        $progressBar->advance();
        
        // Task 3: Analyze aggregate metrics
        $progressBar->setMessage('Analyzing aggregate metrics...');
        
        if (!empty($aggregateMetrics)) {
            $output->writeln('<comment>Aggregate metrics for operations:</comment>');
            foreach ($aggregateMetrics as $operation => $metrics) {
                if ($metrics['count'] > 0) {
                    $output->writeln(sprintf(
                        '  - %s: avg %.2f ms (%d calls)',
                        $operation,
                        $metrics['average_execution_time'],
                        $metrics['count']
                    ));
                }
            }
        }
        
        $progressBar->advance();
        
        // Task 4: Clear performance monitor cache
        $progressBar->setMessage('Cleaning up performance metrics...');
        $this->performanceMonitor->resetAggregateMetrics();
        
        $progressBar->finish();
        
        $output->writeln("\n".'<info>Performance optimization process completed!</info>');
        
        // Display summary
        $output->writeln(sprintf(
            '<info>Summary:</info> Analyzed %d operations, cleaned up metrics, identified %d slow queries',
            count($aggregateMetrics),
            count($slowQueries)
        ));
        
        return Command::SUCCESS;
    }
}