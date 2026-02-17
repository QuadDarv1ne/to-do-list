<?php

namespace App\Command;

use App\Service\PerformanceMonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:generate-performance-report',
    description: 'Generate a detailed performance report for the application'
)]
class GeneratePerformanceReportCommand extends Command
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
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: console, json, or txt', 'console')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output file path (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');
        
        $output->writeln('<info>Generating performance report...</info>');
        
        $progressBar = new ProgressBar($output, 5);
        $progressBar->setFormat('verbose');
        
        // Collect basic metrics
        $progressBar->setMessage('Collecting basic metrics...');
        $progressBar->start();
        $basicMetrics = $this->performanceMonitor->getPerformanceReport();
        $progressBar->advance();
        
        // Collect detailed metrics
        $progressBar->setMessage('Collecting detailed metrics...');
        $detailedMetrics = $this->performanceMonitor->getDetailedMetrics();
        $progressBar->advance();
        
        // Collect slow queries
        $progressBar->setMessage('Analyzing slow queries...');
        $slowQueries = $this->performanceMonitor->getSlowQueries();
        $progressBar->advance();
        
        // Generate recommendations
        $progressBar->setMessage('Generating recommendations...');
        $recommendations = $this->generateRecommendations($detailedMetrics, $slowQueries);
        $progressBar->advance();
        
        // Finalize
        $progressBar->setMessage('Finalizing report...');
        $report = [
            'basic_metrics' => $basicMetrics,
            'detailed_metrics' => $detailedMetrics,
            'slow_queries' => $slowQueries,
            'recommendations' => $recommendations,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        $progressBar->advance();
        $progressBar->finish();
        
        $output->writeln("\n"); // New line after progress bar
        
        // Output the report based on format
        switch ($format) {
            case 'json':
                $reportJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($outputFile) {
                    file_put_contents($outputFile, $reportJson);
                    $output->writeln("<info>Report saved to: {$outputFile}</info>");
                } else {
                    $output->writeln($reportJson);
                }
                break;
                
            case 'txt':
                $reportTxt = $this->formatAsText($report);
                if ($outputFile) {
                    file_put_contents($outputFile, $reportTxt);
                    $output->writeln("<info>Report saved to: {$outputFile}</info>");
                } else {
                    $output->writeln($reportTxt);
                }
                break;
                
            case 'console':
            default:
                $this->displayConsoleReport($output, $report);
                break;
        }
        
        $output->writeln("\n<info>Performance report generated successfully!</info>");
        
        return Command::SUCCESS;
    }
    
    private function generateRecommendations(array $detailedMetrics, array $slowQueries): array
    {
        $recommendations = [];
        
        // Memory usage recommendation
        if ($detailedMetrics['current_memory_usage_bytes'] > 750 * 1024 * 1024) { // > 750MB
            $recommendations[] = [
                'level' => 'critical',
                'title' => 'High Memory Usage',
                'description' => 'Current memory usage is very high (' . $this->formatBytes($detailedMetrics['current_memory_usage_bytes']) . '). Consider optimizing queries or increasing server resources.'
            ];
        } elseif ($detailedMetrics['current_memory_usage_bytes'] > 500 * 1024 * 1024) { // > 500MB
            $recommendations[] = [
                'level' => 'warning',
                'title' => 'Moderate Memory Usage',
                'description' => 'Current memory usage is moderate (' . $this->formatBytes($detailedMetrics['current_memory_usage_bytes']) . '). Monitor for potential optimization opportunities.'
            ];
        }
        
        // Slow queries recommendation
        if (count($slowQueries) > 10) {
            $recommendations[] = [
                'level' => 'critical',
                'title' => 'Too Many Slow Queries',
                'description' => 'There are ' . count($slowQueries) . ' slow queries recorded. Consider optimizing database queries and adding proper indexes.'
            ];
        } elseif (count($slowQueries) > 5) {
            $recommendations[] = [
                'level' => 'warning',
                'title' => 'Several Slow Queries',
                'description' => 'There are ' . count($slowQueries) . ' slow queries recorded. Review and optimize the slowest queries.'
            ];
        }
        
        // System load recommendation
        if (isset($detailedMetrics['system_load']['load_avg_1min']) && $detailedMetrics['system_load']['load_avg_1min'] > 4.0) {
            $recommendations[] = [
                'level' => 'critical',
                'title' => 'High System Load',
                'description' => 'System load average is high (' . $detailedMetrics['system_load']['load_avg_1min'] . '). Consider scaling resources.'
            ];
        } elseif (isset($detailedMetrics['system_load']['load_avg_1min']) && $detailedMetrics['system_load']['load_avg_1min'] > 2.0) {
            $recommendations[] = [
                'level' => 'warning',
                'title' => 'Moderate System Load',
                'description' => 'System load average is moderate (' . $detailedMetrics['system_load']['load_avg_1min'] . '). Monitor performance closely.'
            ];
        }
        
        // Add positive recommendations if no issues found
        if (empty($recommendations)) {
            $recommendations[] = [
                'level' => 'info',
                'title' => 'Good Performance',
                'description' => 'Application is performing well with no major issues detected.'
            ];
        }
        
        return $recommendations;
    }
    
    private function formatAsText(array $report): string
    {
        $text = "APPLICATION PERFORMANCE REPORT\n";
        $text .= "===========================\n";
        $text .= "Generated at: " . $report['generated_at'] . "\n\n";
        
        // Basic metrics
        $text .= "BASIC METRICS\n";
        $text .= "-------------\n";
        $text .= "Environment: " . $report['basic_metrics']['environment'] . "\n";
        $text .= "Current Memory Usage: " . $report['basic_metrics']['current_memory_usage'] . "\n";
        $text .= "Peak Memory Usage: " . $report['basic_metrics']['peak_memory_usage'] . "\n";
        $text .= "Uptime: " . $report['basic_metrics']['uptime'] . "\n";
        $text .= "PHP Version: " . $report['basic_metrics']['server_info']['php_version'] . "\n";
        $text .= "OS: " . $report['basic_metrics']['server_info']['os'] . "\n\n";
        
        // Detailed metrics
        $text .= "DETAILED METRICS\n";
        $text .= "----------------\n";
        if (isset($report['detailed_metrics']['system_load'])) {
            $text .= "Load Average (1 min): " . $report['detailed_metrics']['system_load']['load_avg_1min'] . "\n";
            $text .= "Load Average (5 min): " . $report['detailed_metrics']['system_load']['load_avg_5min'] . "\n";
            $text .= "Load Average (15 min): " . $report['detailed_metrics']['system_load']['load_avg_15min'] . "\n";
        }
        if (isset($report['detailed_metrics']['database_info'])) {
            $text .= "Slow Query Count: " . $report['detailed_metrics']['database_info']['slow_query_count'] . "\n";
        }
        $text .= "\n";
        
        // Slow queries
        $text .= "SLOW QUERIES (" . count($report['slow_queries']) . ")\n";
        $text .= "--------------\n";
        foreach ($report['slow_queries'] as $index => $query) {
            $text .= ($index + 1) . ". Execution Time: " . $query['execution_time_ms'] . "ms\n";
            $text .= "   Source: " . $query['source'] . "\n";
            $text .= "   Memory Used: " . $this->formatBytes($query['memory_used_bytes']) . "\n";
            $text .= "   Query: " . substr($query['query'], 0, 100) . (strlen($query['query']) > 100 ? '...' : '') . "\n\n";
        }
        
        if (empty($report['slow_queries'])) {
            $text .= "No slow queries detected.\n\n";
        }
        
        // Recommendations
        $text .= "RECOMMENDATIONS\n";
        $text .= "---------------\n";
        foreach ($report['recommendations'] as $rec) {
            $level = strtoupper($rec['level']);
            $text .= "[{$level}] {$rec['title']}\n";
            $text .= "{$rec['description']}\n\n";
        }
        
        return $text;
    }
    
    private function displayConsoleReport(OutputInterface $output, array $report): void
    {
        $output->writeln("<comment>APPLICATION PERFORMANCE REPORT</comment>");
        $output->writeln("<comment>===========================</comment>");
        $output->writeln("Generated at: <info>{$report['generated_at']}</info>\n");
        
        // Basic metrics table
        $output->writeln("<comment>BASIC METRICS</comment>");
        $output->writeln("<comment>-------------</comment>");
        
        $basicTable = new Table($output);
        $basicTable->setHeaders(['Metric', 'Value']);
        $basicTable->addRows([
            ['Environment', $report['basic_metrics']['environment']],
            ['Current Memory Usage', $report['basic_metrics']['current_memory_usage']],
            ['Peak Memory Usage', $report['basic_metrics']['peak_memory_usage']],
            ['Uptime', $report['basic_metrics']['uptime']],
            ['PHP Version', $report['basic_metrics']['server_info']['php_version']],
            ['OS', $report['basic_metrics']['server_info']['os']]
        ]);
        $basicTable->render();
        
        $output->writeln("");
        
        // Detailed metrics
        $output->writeln("<comment>DETAILED METRICS</comment>");
        $output->writeln("<comment>----------------</comment>");
        
        $detailedTable = new Table($output);
        $detailedTable->setHeaders(['Metric', 'Value']);
        
        if (isset($report['detailed_metrics']['system_load'])) {
            $detailedTable->addRows([
                ['Load Average (1 min)', $report['detailed_metrics']['system_load']['load_avg_1min']],
                ['Load Average (5 min)', $report['detailed_metrics']['system_load']['load_avg_5min']],
                ['Load Average (15 min)', $report['detailed_metrics']['system_load']['load_avg_15min']],
            ]);
        }
        
        if (isset($report['detailed_metrics']['database_info'])) {
            $detailedTable->addRow(['Slow Query Count', $report['detailed_metrics']['database_info']['slow_query_count']]);
        }
        
        $detailedTable->render();
        
        $output->writeln("");
        
        // Slow queries
        $output->writeln("<comment>SLOW QUERIES (" . count($report['slow_queries']) . ")</comment>");
        $output->writeln("<comment>--------------</comment>");
        
        if (empty($report['slow_queries'])) {
            $output->writeln("<info>No slow queries detected.</info>\n");
        } else {
            $slowQueryTable = new Table($output);
            $slowQueryTable->setHeaders(['#', 'Execution Time (ms)', 'Source', 'Memory Used', 'Query']);
            
            foreach ($report['slow_queries'] as $index => $query) {
                $slowQueryTable->addRow([
                    $index + 1,
                    $query['execution_time_ms'],
                    $query['source'],
                    $this->formatBytes($query['memory_used_bytes']),
                    substr($query['query'], 0, 50) . (strlen($query['query']) > 50 ? '...' : '')
                ]);
            }
            
            $slowQueryTable->render();
        }
        
        // Recommendations
        $output->writeln("<comment>RECOMMENDATIONS</comment>");
        $output->writeln("<comment>---------------</comment>");
        
        foreach ($report['recommendations'] as $rec) {
            $levelTag = $this->getLevelTag($rec['level']);
            $output->writeln("{$levelTag} <comment>{$rec['title']}</comment>");
            $output->writeln("   {$rec['description']}\n");
        }
    }
    
    private function getLevelTag(string $level): string
    {
        switch ($level) {
            case 'critical':
                return '<error>[CRITICAL]</error>';
            case 'warning':
                return '<comment>[WARNING]</comment>';
            case 'info':
                return '<info>[INFO]</info>';
            default:
                return "[{$level}]";
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}