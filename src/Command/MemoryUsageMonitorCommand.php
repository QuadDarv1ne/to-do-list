<?php

namespace App\Command;

use App\Service\MemoryUsageMonitorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monitor-memory',
    description: 'Monitor and analyze memory usage in the application'
)]
class MemoryUsageMonitorCommand extends Command
{
    private MemoryUsageMonitorService $memoryUsageMonitorService;

    public function __construct(MemoryUsageMonitorService $memoryUsageMonitorService)
    {
        $this->memoryUsageMonitorService = $memoryUsageMonitorService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: snapshot, analysis, compare, track, leaks, trend, clear', 'snapshot')
            ->addOption('label', 'l', InputOption::VALUE_REQUIRED, 'Label for snapshot or comparison')
            ->addOption('from-label', null, InputOption::VALUE_REQUIRED, 'Source label for comparison')
            ->addOption('to-label', null, InputOption::VALUE_REQUIRED, 'Target label for comparison')
            ->addOption('point-name', null, InputOption::VALUE_REQUIRED, 'Name for memory tracking point')
            ->addOption('context', 'c', InputOption::VALUE_REQUIRED, 'Context for tracking point')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Threshold for leak detection', '50')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');
        $threshold = (int)$input->getOption('threshold');

        $io->title('Memory Usage Monitor');

        switch ($action) {
            case 'snapshot':
                $label = $input->getOption('label') ?: 'manual_' . date('His');
                
                $io->writeln("Taking memory snapshot with label: {$label}");
                
                $snapshot = $this->memoryUsageMonitorService->takeMemorySnapshot($label);
                
                if ($format === 'json') {
                    $outputData = [
                        'snapshot' => $snapshot,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Snapshot saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displaySnapshotReport($io, $snapshot);
                    
                    if ($outputFile) {
                        $textData = $this->getSnapshotTextReport($snapshot);
                        file_put_contents($outputFile, $textData);
                        $io->success("Snapshot saved to {$outputFile}");
                    }
                }
                break;

            case 'track':
                $pointName = $input->getOption('point-name') ?: 'point_' . date('His');
                $context = $input->getOption('context') ?: 'Manual tracking point';
                
                $io->writeln("Tracking memory at point: {$pointName} (context: {$context})");
                
                $this->memoryUsageMonitorService->trackMemoryPoint($pointName, $context);
                
                $io->success("Memory tracked at point: {$pointName}");
                break;

            case 'analysis':
                $io->writeln('Performing memory analysis...');
                
                $analysis = $this->memoryUsageMonitorService->getMemoryAnalysis();
                
                if (isset($analysis['error'])) {
                    $io->error($analysis['error']);
                    return 1;
                }
                
                if ($format === 'json') {
                    $outputData = [
                        'analysis' => $analysis,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Analysis saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayAnalysisReport($io, $analysis);
                    
                    if ($outputFile) {
                        $textData = $this->getAnalysisTextReport($analysis);
                        file_put_contents($outputFile, $textData);
                        $io->success("Analysis saved to {$outputFile}");
                    }
                }
                break;

            case 'compare':
                $fromLabel = $input->getOption('from-label');
                $toLabel = $input->getOption('to-label');
                
                if (!$fromLabel || !$toLabel) {
                    $io->error('Both --from-label and --to-label options are required for comparison');
                    return 1;
                }
                
                $io->writeln("Comparing memory usage: {$fromLabel} vs {$toLabel}");
                
                $comparison = $this->memoryUsageMonitorService->getMemoryComparison($fromLabel, $toLabel);
                
                if ($comparison === null) {
                    $io->error("Could not find snapshots with labels: {$fromLabel} and/or {$toLabel}");
                    return 1;
                }
                
                if ($format === 'json') {
                    $outputData = [
                        'comparison' => $comparison,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Comparison saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayComparisonReport($io, $comparison);
                    
                    if ($outputFile) {
                        $textData = $this->getComparisonTextReport($comparison);
                        file_put_contents($outputFile, $textData);
                        $io->success("Comparison saved to {$outputFile}");
                    }
                }
                break;

            case 'leaks':
                $io->writeln("Detecting potential memory leaks (threshold: {$threshold}%)");
                
                $leaks = $this->memoryUsageMonitorService->detectPotentialLeaks($threshold);
                
                if ($format === 'json') {
                    $outputData = [
                        'leaks' => $leaks,
                        'threshold' => $threshold,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Leak detection report saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayLeakReport($io, $leaks, $threshold);
                    
                    if ($outputFile) {
                        $textData = $this->getLeakTextReport($leaks, $threshold);
                        file_put_contents($outputFile, $textData);
                        $io->success("Leak detection report saved to {$outputFile}");
                    }
                }
                break;

            case 'trend':
                $io->writeln('Calculating memory usage trend...');
                
                $trend = $this->memoryUsageMonitorService->getMemoryTrend();
                
                if (isset($trend['error'])) {
                    $io->error($trend['error']);
                    return 1;
                }
                
                if ($format === 'json') {
                    $outputData = [
                        'trend' => $trend,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Trend report saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayTrendReport($io, $trend);
                    
                    if ($outputFile) {
                        $textData = $this->getTrendTextReport($trend);
                        file_put_contents($outputFile, $textData);
                        $io->success("Trend report saved to {$outputFile}");
                    }
                }
                break;

            case 'clear':
                $io->writeln('Clearing all memory snapshots...');
                
                $this->memoryUsageMonitorService->clearSnapshots();
                
                $io->success('Memory snapshots cleared successfully.');
                break;

            default:
                $io->error("Unknown action: {$action}. Use snapshot, analysis, compare, track, leaks, trend, or clear.");
                return 1;
        }

        return 0;
    }

    private function displaySnapshotReport(SymfonyStyle $io, array $snapshot): void
    {
        $io->section('Memory Snapshot');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Label', $snapshot['label']],
                ['Timestamp', date('Y-m-d H:i:s', (int)$snapshot['timestamp'])],
                ['Memory Usage', $snapshot['formatted_memory_usage']],
                ['Memory Peak', $snapshot['formatted_memory_peak']],
                ['Execution Time', round($snapshot['execution_time_since_start'], 4) . ' sec'],
            ]
        );
    }

    private function displayAnalysisReport(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Memory Analysis');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Snapshots', $analysis['total_snapshots']],
                ['Min Memory Usage', $analysis['min_memory_usage_formatted']],
                ['Max Memory Usage', $analysis['max_memory_usage_formatted']],
                ['Average Memory Usage', $analysis['average_memory_usage_formatted']],
                ['Min Memory Peak', $analysis['min_memory_peak_formatted']],
                ['Max Memory Peak', $analysis['max_memory_peak_formatted']],
                ['Current Memory Limit', $analysis['current_memory_limit']],
            ]
        );

        if (!empty($analysis['snapshots'])) {
            $io->section('Recent Snapshots');
            
            $recentSnapshots = array_slice($analysis['snapshots'], -5); // Last 5 snapshots
            $rows = [];
            
            foreach ($recentSnapshots as $snapshot) {
                $rows[] = [
                    $snapshot['label'],
                    $snapshot['formatted_memory_usage'],
                    $snapshot['formatted_memory_peak'],
                    date('H:i:s', (int)$snapshot['timestamp'])
                ];
            }
            
            $io->table(['Label', 'Usage', 'Peak', 'Time'], $rows);
        }
    }

    private function displayComparisonReport(SymfonyStyle $io, array $comparison): void
    {
        $io->section('Memory Comparison');
        
        $io->table(
            ['Metric', 'From', 'To', 'Difference'],
            [
                [
                    'Memory Usage',
                    $comparison['from_snapshot']['formatted_memory_usage'],
                    $comparison['to_snapshot']['formatted_memory_usage'],
                    $comparison['memory_difference_formatted'] . ' (' . $comparison['direction'] . ')'
                ],
                [
                    'Memory Peak',
                    $comparison['from_snapshot']['formatted_memory_peak'],
                    $comparison['to_snapshot']['formatted_memory_peak'],
                    $comparison['peak_difference_formatted'] . ' (' . $comparison['peak_direction'] . ')'
                ],
            ]
        );
    }

    private function displayLeakReport(SymfonyStyle $io, array $leaks, int $threshold): void
    {
        if (empty($leaks)) {
            $io->success("No potential memory leaks detected (threshold: {$threshold}%).");
        } else {
            $io->error("Potential memory leaks detected (threshold: {$threshold}%):");
            
            $rows = [];
            foreach ($leaks as $leak) {
                $rows[] = [
                    $leak['from_snapshot'] ?? $leak['from_point'],
                    $leak['to_snapshot'] ?? $leak['to_point'],
                    $leak['increase_percent'] . '%',
                    $leak['from_memory'],
                    $leak['to_memory']
                ];
            }
            
            $io->table(['From', 'To', 'Increase', 'From Memory', 'To Memory'], $rows);
        }
    }

    private function displayTrendReport(SymfonyStyle $io, array $trend): void
    {
        if (isset($trend['error'])) {
            $io->error($trend['error']);
            return;
        }
        
        $io->section('Memory Usage Trend');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Slope (per second)', round($trend['slope_per_second'], 6)],
                ['Direction', $trend['slope_direction']],
                ['Description', $trend['slope_description']],
                ['Total Samples', $trend['total_samples']],
                ['First Sample', $trend['first_sample']['formatted_memory_usage']],
                ['Last Sample', $trend['last_sample']['formatted_memory_usage']],
            ]
        );
    }

    private function getSnapshotTextReport(array $snapshot): string
    {
        $report = "MEMORY SNAPSHOT REPORT\n";
        $report .= str_repeat("=", 30) . "\n\n";
        
        $report .= "Label: {$snapshot['label']}\n";
        $report .= "Timestamp: " . date('Y-m-d H:i:s', (int)$snapshot['timestamp']) . "\n";
        $report .= "Memory Usage: {$snapshot['formatted_memory_usage']}\n";
        $report .= "Memory Peak: {$snapshot['formatted_memory_peak']}\n";
        $report .= "Execution Time: " . round($snapshot['execution_time_since_start'], 4) . " sec\n";
        
        return $report;
    }

    private function getAnalysisTextReport(array $analysis): string
    {
        $report = "MEMORY ANALYSIS REPORT\n";
        $report .= str_repeat("=", 30) . "\n\n";
        
        $report .= "Total Snapshots: {$analysis['total_snapshots']}\n";
        $report .= "Min Memory Usage: {$analysis['min_memory_usage_formatted']}\n";
        $report .= "Max Memory Usage: {$analysis['max_memory_usage_formatted']}\n";
        $report .= "Average Memory Usage: {$analysis['average_memory_usage_formatted']}\n";
        $report .= "Min Memory Peak: {$analysis['min_memory_peak_formatted']}\n";
        $report .= "Max Memory Peak: {$analysis['max_memory_peak_formatted']}\n";
        $report .= "Current Memory Limit: {$analysis['current_memory_limit']}\n\n";
        
        if (!empty($analysis['snapshots'])) {
            $report .= "RECENT SNAPSHOTS\n";
            $report .= str_repeat("-", 20) . "\n";
            
            $recentSnapshots = array_slice($analysis['snapshots'], -5); // Last 5 snapshots
            foreach ($recentSnapshots as $snapshot) {
                $report .= sprintf("%s: %s (peak: %s) at %s\n",
                    $snapshot['label'],
                    $snapshot['formatted_memory_usage'],
                    $snapshot['formatted_memory_peak'],
                    date('H:i:s', (int)$snapshot['timestamp'])
                );
            }
        }
        
        return $report;
    }

    private function getComparisonTextReport(array $comparison): string
    {
        $report = "MEMORY COMPARISON REPORT\n";
        $report .= str_repeat("=", 30) . "\n\n";
        
        $report .= "Comparing: {$comparison['from_snapshot']['label']} vs {$comparison['to_snapshot']['label']}\n\n";
        
        $report .= "Memory Usage:\n";
        $report .= "  From: {$comparison['from_snapshot']['formatted_memory_usage']}\n";
        $report .= "  To: {$comparison['to_snapshot']['formatted_memory_usage']}\n";
        $report .= "  Difference: {$comparison['memory_difference_formatted']} ({$comparison['direction']})\n\n";
        
        $report .= "Memory Peak:\n";
        $report .= "  From: {$comparison['from_snapshot']['formatted_memory_peak']}\n";
        $report .= "  To: {$comparison['to_snapshot']['formatted_memory_peak']}\n";
        $report .= "  Difference: {$comparison['peak_difference_formatted']} ({$comparison['peak_direction']})\n";
        
        return $report;
    }

    private function getLeakTextReport(array $leaks, int $threshold): string
    {
        $report = "MEMORY LEAK DETECTION REPORT\n";
        $report .= str_repeat("=", 40) . "\n\n";
        
        $report .= "Threshold: {$threshold}%\n\n";
        
        if (empty($leaks)) {
            $report .= "No potential memory leaks detected.\n";
        } else {
            $report .= "Potential memory leaks detected:\n\n";
            
            foreach ($leaks as $leak) {
                $report .= sprintf("From %s to %s: %s increase (%.2f%%)\n",
                    $leak['from_snapshot'] ?? $leak['from_point'],
                    $leak['to_snapshot'] ?? $leak['to_point'],
                    $leak['increase_percent'] . '%',
                    $leak['from_memory'],
                    $leak['to_memory']
                );
                $report .= "  {$leak['from_memory']} → {$leak['to_memory']}\n\n";
            }
        }
        
        return $report;
    }

    private function getTrendTextReport(array $trend): string
    {
        $report = "MEMORY USAGE TREND REPORT\n";
        $report .= str_repeat("=", 30) . "\n\n";
        
        if (isset($trend['error'])) {
            $report .= "Error: {$trend['error']}\n";
            return $report;
        }
        
        $report .= "Slope (per second): " . round($trend['slope_per_second'], 6) . "\n";
        $report .= "Direction: {$trend['slope_direction']}\n";
        $report .= "Description: {$trend['slope_description']}\n";
        $report .= "Total Samples: {$trend['total_samples']}\n";
        $report .= "First Sample: {$trend['first_sample']['formatted_memory_usage']}\n";
        $report .= "Last Sample: {$trend['last_sample']['formatted_memory_usage']}\n";
        
        return $report;
    }
}