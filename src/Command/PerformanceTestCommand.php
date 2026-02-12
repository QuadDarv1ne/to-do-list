<?php

namespace App\Command;

use App\Service\PerformanceMonitorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:performance-test',
    description: 'Run performance tests and collect metrics'
)]
class PerformanceTestCommand extends Command
{
    private PerformanceMonitorService $performanceMonitor;

    public function __construct(PerformanceMonitorService $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting performance tests...</info>');
        
        $progressBar = new ProgressBar($output, 4);
        $progressBar->setFormat('verbose');
        
        // Test 1: Memory usage
        $progressBar->setMessage('Testing memory usage...');
        $progressBar->start();
        
        $initialMetrics = $this->performanceMonitor->collectMetrics();
        $output->writeln("\n<comment>Initial memory usage: {$initialMetrics['current_memory_usage']}</comment>");
        $output->writeln("<comment>Initial peak memory: {$initialMetrics['peak_memory_usage']}</comment>");
        
        $progressBar->advance();
        
        // Test 2: Simulate some operations to measure performance
        $progressBar->setMessage('Running performance benchmarks...');
        
        // Simulate some operations
        $this->performanceMonitor->startTimer('benchmark_operation');
        $result = $this->simulateOperations();
        $benchmarkMetrics = $this->performanceMonitor->stopTimer('benchmark_operation');
        
        $output->writeln("\n<comment>Benchmark execution time: {$benchmarkMetrics['execution_time']} ms</comment>");
        $output->writeln("<comment>Memory used in benchmark: {$benchmarkMetrics['memory_used']}</comment>");
        
        $progressBar->advance();
        
        // Test 3: Collect final metrics
        $progressBar->setMessage('Collecting final metrics...');
        
        $finalMetrics = $this->performanceMonitor->collectMetrics();
        $output->writeln("\n<comment>Final memory usage: {$finalMetrics['current_memory_usage']}</comment>");
        $output->writeln("<comment>Final peak memory: {$finalMetrics['peak_memory_usage']}</comment>");
        
        $progressBar->advance();
        
        // Test 4: Generate performance report
        $progressBar->setMessage('Generating performance report...');
        
        $report = $this->performanceMonitor->getPerformanceReport();
        $progressBar->advance();
        $progressBar->finish();
        
        $output->writeln("\n\n<info>Performance Report Generated</info>");
        $output->writeln("<info>=========================</info>");
        $output->writeln("Environment: {$report['environment']}");
        $output->writeln("Current Memory Usage: {$report['current_memory_usage']}");
        $output->writeln("Peak Memory Usage: {$report['peak_memory_usage']}");
        $output->writeln("Uptime: {$report['uptime']}");
        $output->writeln("PHP Version: {$report['server_info']['php_version']}");
        $output->writeln("OS: {$report['server_info']['os']}");
        
        $output->writeln("\n<info>Performance tests completed successfully!</info>");
        
        return Command::SUCCESS;
    }
    
    private function simulateOperations(): array
    {
        // Simulate some operations that would typically occur in the application
        $data = [];
        
        // Simulate creating some tasks
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'id' => $i,
                'title' => "Task {$i}",
                'status' => $i % 3 === 0 ? 'completed' : ($i % 2 === 0 ? 'in_progress' : 'pending'),
                'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                'created_at' => new \DateTime("-{$i} days")
            ];
        }
        
        // Simulate some processing
        usort($data, function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        
        // Filter data
        $filtered = array_filter($data, function($item) {
            return $item['status'] !== 'completed';
        });
        
        return $filtered;
    }
}