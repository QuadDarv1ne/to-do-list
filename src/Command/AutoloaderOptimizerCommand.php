<?php

namespace App\Command;

use App\Service\AutoloaderOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-autoloader',
    description: 'Optimize Composer autoloader for better performance'
)]
class AutoloaderOptimizerCommand extends Command
{
    private AutoloaderOptimizerService $autoloaderOptimizerService;

    public function __construct(AutoloaderOptimizerService $autoloaderOptimizerService)
    {
        $this->autoloaderOptimizerService = $autoloaderOptimizerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: optimize, stats, warmup, production', 'optimize')
            ->addOption('optimize-psr0', null, InputOption::VALUE_NONE, 'Optimize PSR-0 loading')
            ->addOption('optimize-apcu', null, InputOption::VALUE_NONE, 'Enable APCu optimization')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');
        $optimizePsr0 = $input->getOption('optimize-psr0');
        $optimizeApcu = $input->getOption('optimize-apcu');

        $io->title('Autoloader Optimizer Tool');

        switch ($action) {
            case 'optimize':
                $io->writeln('Optimizing Composer autoloader...');
                
                $result = $this->autoloaderOptimizerService->optimizeAutoloader($optimizePsr0, $optimizeApcu);
                
                if ($format === 'json') {
                    $outputData = [
                        'optimization_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Optimization result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayOptimizationResult($io, $result);
                    
                    if ($outputFile) {
                        $textData = $this->getOptimizationTextReport($result);
                        file_put_contents($outputFile, $textData);
                        $io->success("Optimization result saved to {$outputFile}");
                    }
                }
                
                return $result['success'] ? 0 : 1;

            case 'stats':
                $io->writeln('Collecting autoloader statistics...');
                
                $stats = $this->autoloaderOptimizerService->getAutoloaderStats();
                
                if ($format === 'json') {
                    $outputData = [
                        'statistics' => $stats,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Statistics saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayStatsReport($io, $stats);
                    
                    if ($outputFile) {
                        $textData = $this->getStatsTextReport($stats);
                        file_put_contents($outputFile, $textData);
                        $io->success("Statistics saved to {$outputFile}");
                    }
                }
                break;

            case 'warmup':
                $io->writeln('Warming up autoloader...');
                
                $result = $this->autoloaderOptimizerService->warmUpAutoloader();
                
                if ($format === 'json') {
                    $outputData = [
                        'warmup_result' => $result,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Warmup result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayWarmupReport($io, $result);
                    
                    if ($outputFile) {
                        $textData = $this->getWarmupTextReport($result);
                        file_put_contents($outputFile, $textData);
                        $io->success("Warmup result saved to {$outputFile}");
                    }
                }
                break;

            case 'production':
                $io->writeln('Running production optimization...');
                
                $result = $this->autoloaderOptimizerService->optimizeForProduction();
                
                if ($format === 'json') {
                    $outputData = [
                        'production_optimization' => $result,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Production optimization result saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayProductionReport($io, $result);
                    
                    if ($outputFile) {
                        $textData = $this->getProductionTextReport($result);
                        file_put_contents($outputFile, $textData);
                        $io->success("Production optimization result saved to {$outputFile}");
                    }
                }
                
                return $result['success'] ? 0 : 1;

            default:
                $io->error("Unknown action: {$action}. Use optimize, stats, warmup, or production.");
                return 1;
        }

        return 0;
    }

    private function displayOptimizationResult(SymfonyStyle $io, array $result): void
    {
        if ($result['success']) {
            $io->success('Autoloader optimization completed successfully!');
        } else {
            $io->error('Autoloader optimization failed!');
        }
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Success', $result['success'] ? 'Yes' : 'No'],
                ['Exit Code', $result['exit_code']],
                ['Execution Time', $result['execution_time'] . ' seconds'],
                ['Optimized PSR-0', $result['optimized_psr0'] ? 'Yes' : 'No'],
                ['Optimized APCu', $result['optimized_apcu'] ? 'Yes' : 'No'],
            ]
        );
        
        if (!empty($result['error_output'])) {
            $io->section('Error Output');
            $io->writeln('<comment>' . $result['error_output'] . '</comment>');
        }
    }

    private function displayStatsReport(SymfonyStyle $io, array $stats): void
    {
        $io->section('Autoloader Statistics');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Project Directory', $stats['project_dir']],
                ['Vendor Directory Exists', $stats['vendor_dir_exists'] ? 'Yes' : 'No'],
                ['Autoload File Exists', $stats['autoload_file_exists'] ? 'Yes' : 'No'],
                ['Composer JSON Exists', $stats['composer_json_exists'] ? 'Yes' : 'No'],
                ['Src Directory Exists', $stats['src_dir_exists'] ? 'Yes' : 'No'],
                ['Total PHP Files', $stats['total_php_files']],
                ['Total Directories', $stats['total_directories']],
            ]
        );
    }

    private function displayWarmupReport(SymfonyStyle $io, array $result): void
    {
        $io->section('Autoloader Warmup Report');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Attempted Classes', $result['attempted_classes']],
                ['Loaded Classes', count($result['loaded_classes'])],
                ['Failed Classes', count($result['failed_classes'])],
                ['Load Time', $result['load_time'] . ' seconds'],
            ]
        );
        
        if (!empty($result['loaded_classes'])) {
            $io->section('Successfully Loaded Classes');
            $loadedList = array_slice($result['loaded_classes'], 0, 10); // Show first 10
            $io->listing($loadedList);
            
            if (count($result['loaded_classes']) > 10) {
                $io->writeln('... and ' . (count($result['loaded_classes']) - 10) . ' more');
            }
        }
        
        if (!empty($result['failed_classes'])) {
            $io->section('Failed Classes');
            $io->error('The following classes failed to load:');
            $io->listing($result['failed_classes']);
        }
    }

    private function displayProductionReport(SymfonyStyle $io, array $result): void
    {
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';
        $statusColor = $result['success'] ? 'green' : 'red';
        
        $io->writeln("<fg={$statusColor};options=bold>Production Optimization: {$status}</>");
        
        $io->section('Autoloader Optimization');
        $this->displayOptimizationResult($io, $result['autoloader_optimization']);
        
        $io->section('OPcache Result');
        $opcacheResult = $result['opcache_result'];
        $io->table(
            ['Metric', 'Value'],
            [
                ['OPcache Exists', $opcacheResult['opcache_exists'] ? 'Yes' : 'No'],
                ['OPcache Enabled', $opcacheResult['opcache_enabled'] ? 'Yes' : 'No'],
                ['OPcache Reset', $opcacheResult['opcache_reset'] ? 'Yes' : 'No'],
                ['Message', $opcacheResult['message']],
            ]
        );
        
        $io->section('Final Statistics');
        $this->displayStatsReport($io, $result['final_stats']);
    }

    private function getOptimizationTextReport(array $result): string
    {
        $report = "AUTOLOADER OPTIMIZATION REPORT\n";
        $report .= str_repeat("=", 40) . "\n\n";
        
        $report .= "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        $report .= "Exit Code: {$result['exit_code']}\n";
        $report .= "Execution Time: {$result['execution_time']} seconds\n";
        $report .= "Optimized PSR-0: " . ($result['optimized_psr0'] ? 'Yes' : 'No') . "\n";
        $report .= "Optimized APCu: " . ($result['optimized_apcu'] ? 'Yes' : 'No') . "\n\n";
        
        if (!empty($result['output'])) {
            $report .= "OUTPUT:\n";
            $report .= $result['output'] . "\n\n";
        }
        
        if (!empty($result['error_output'])) {
            $report .= "ERROR OUTPUT:\n";
            $report .= $result['error_output'] . "\n\n";
        }
        
        return $report;
    }

    private function getStatsTextReport(array $stats): string
    {
        $report = "AUTOLOADER STATISTICS REPORT\n";
        $report .= str_repeat("=", 40) . "\n\n";
        
        $report .= "Project Directory: {$stats['project_dir']}\n";
        $report .= "Vendor Directory Exists: " . ($stats['vendor_dir_exists'] ? 'Yes' : 'No') . "\n";
        $report .= "Autoload File Exists: " . ($stats['autoload_file_exists'] ? 'Yes' : 'No') . "\n";
        $report .= "Composer JSON Exists: " . ($stats['composer_json_exists'] ? 'Yes' : 'No') . "\n";
        $report .= "Src Directory Exists: " . ($stats['src_dir_exists'] ? 'Yes' : 'No') . "\n";
        $report .= "Total PHP Files: {$stats['total_php_files']}\n";
        $report .= "Total Directories: {$stats['total_directories']}\n\n";
        
        return $report;
    }

    private function getWarmupTextReport(array $result): string
    {
        $report = "AUTOLOADER WARMUP REPORT\n";
        $report .= str_repeat("=", 35) . "\n\n";
        
        $report .= "Attempted Classes: {$result['attempted_classes']}\n";
        $report .= "Loaded Classes: " . count($result['loaded_classes']) . "\n";
        $report .= "Failed Classes: " . count($result['failed_classes']) . "\n";
        $report .= "Load Time: {$result['load_time']} seconds\n\n";
        
        if (!empty($result['loaded_classes'])) {
            $report .= "SUCCESSFULLY LOADED CLASSES:\n";
            foreach ($result['loaded_classes'] as $class) {
                $report .= "- {$class}\n";
            }
            $report .= "\n";
        }
        
        if (!empty($result['failed_classes'])) {
            $report .= "FAILED CLASSES:\n";
            foreach ($result['failed_classes'] as $class) {
                $report .= "- {$class}\n";
            }
            $report .= "\n";
        }
        
        return $report;
    }

    private function getProductionTextReport(array $result): string
    {
        $report = "PRODUCTION AUTOLOADER OPTIMIZATION REPORT\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        $report .= "Overall Success: " . ($result['success'] ? 'Yes' : 'No') . "\n\n";
        
        $report .= "AUTOLOADER OPTIMIZATION:\n";
        $report .= str_repeat("-", 25) . "\n";
        $optResult = $result['autoloader_optimization'];
        $report .= "Success: " . ($optResult['success'] ? 'Yes' : 'No') . "\n";
        $report .= "Exit Code: {$optResult['exit_code']}\n";
        $report .= "Execution Time: {$optResult['execution_time']} seconds\n\n";
        
        $report .= "OPCACHE RESULT:\n";
        $report .= str_repeat("-", 20) . "\n";
        $opcacheResult = $result['opcache_result'];
        $report .= "OPcache Exists: " . ($opcacheResult['opcache_exists'] ? 'Yes' : 'No') . "\n";
        $report .= "OPcache Enabled: " . ($opcacheResult['opcache_enabled'] ? 'Yes' : 'No') . "\n";
        $report .= "OPcache Reset: " . ($opcacheResult['opcache_reset'] ? 'Yes' : 'No') . "\n";
        $report .= "Message: {$opcacheResult['message']}\n\n";
        
        $report .= "FINAL STATISTICS:\n";
        $report .= str_repeat("-", 20) . "\n";
        $stats = $result['final_stats'];
        $report .= "Total PHP Files: {$stats['total_php_files']}\n";
        $report .= "Total Directories: {$stats['total_directories']}\n\n";
        
        return $report;
    }
}