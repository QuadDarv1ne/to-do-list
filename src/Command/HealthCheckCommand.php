<?php

namespace App\Command;

use App\Service\HealthCheckService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:health-check',
    description: 'Perform system health checks'
)]
class HealthCheckCommand extends Command
{
    private HealthCheckService $healthCheckService;

    public function __construct(HealthCheckService $healthCheckService)
    {
        $this->healthCheckService = $healthCheckService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('checks', 'c', InputOption::VALUE_REQUIRED, 'Comma-separated list of checks to run: database, disk-space, memory, cache, configuration, dependencies, security')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $checksOption = $input->getOption('checks');
        $outputFile = $input->getOption('output-file');

        $io->title('System Health Check');

        // Determine which checks to run
        $availableChecks = ['database', 'disk-space', 'memory', 'cache', 'configuration', 'dependencies', 'security'];
        $checksToRun = $availableChecks;

        if ($checksOption) {
            $requestedChecks = array_map('trim', explode(',', $checksOption));
            $checksToRun = array_intersect($requestedChecks, $availableChecks);
        }

        $io->writeln("Running health checks...");
        $io->writeln("Checks to run: " . implode(', ', $checksToRun));

        // Run the health check
        $healthResults = $this->healthCheckService->performHealthCheck([
            'checks' => $checksToRun
        ]);

        // Format output based on selected format
        if ($format === 'json') {
            $outputData = [
                'health_results' => $healthResults,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($outputFile) {
                file_put_contents($outputFile, $jsonData);
                $io->success("Health check results saved to {$outputFile}");
            } else {
                $output->writeln($jsonData);
            }
        } else {
            // Text format output
            $this->displayTextReport($io, $healthResults);
            
            if ($outputFile) {
                $textData = $this->getTextReport($healthResults);
                file_put_contents($outputFile, $textData);
                $io->success("Health check results saved to {$outputFile}");
            }
        }

        // Exit with error code if critical issues found
        return $healthResults['overall_status'] === 'critical' ? 1 : 0;
    }

    private function displayTextReport(SymfonyStyle $io, array $healthResults): void
    {
        // Display overall status
        $overallStatus = $healthResults['overall_status'];
        $statusColor = $overallStatus === 'ok' ? 'green' : ($overallStatus === 'warning' ? 'yellow' : 'red');
        $io->writeln("<fg={$statusColor};options=bold>Overall Status: {$overallStatus}</>");

        // Display summary
        $io->section('Health Check Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Overall Status', $healthResults['overall_status']],
                ['Duration', $healthResults['duration_ms'] . ' ms'],
                ['Environment', $healthResults['environment']],
                ['Timestamp', $healthResults['timestamp']],
            ]
        );

        // Display detailed results
        foreach ($healthResults['checks'] as $checkName => $results) {
            $status = $results['status'];
            $statusColor = $status === 'ok' ? 'green' : ($status === 'warning' ? 'yellow' : 'red');
            
            $io->section("{$checkName} Check - <fg={$statusColor}>{$status}</>");
            
            $io->writeln($results['message']);
            
            if (!empty($results['details'])) {
                $io->writeln('');
                $io->writeln('<comment>Details:</comment>');
                
                foreach ($results['details'] as $key => $value) {
                    if (is_array($value)) {
                        $io->writeln("  {$key}: " . json_encode($value));
                    } else {
                        $io->writeln("  {$key}: {$value}");
                    }
                }
            }
            
            $io->writeln('');
        }
    }

    private function getTextReport(array $healthResults): string
    {
        $report = "SYSTEM HEALTH CHECK REPORT\n";
        $report .= str_repeat("=", 50) . "\n\n";

        $report .= "Timestamp: {$healthResults['timestamp']}\n";
        $report .= "Duration: {$healthResults['duration_ms']} ms\n";
        $report .= "Environment: {$healthResults['environment']}\n";
        $report .= "Overall Status: {$healthResults['overall_status']}\n\n";

        foreach ($healthResults['checks'] as $checkName => $results) {
            $report .= strtoupper(str_replace('-', ' ', $checkName)) . " CHECK\n";
            $report .= str_repeat("-", 30) . "\n";
            $report .= "Status: {$results['status']}\n";
            $report .= "Message: {$results['message']}\n";

            if (!empty($results['details'])) {
                $report .= "Details:\n";
                foreach ($results['details'] as $key => $value) {
                    if (is_array($value)) {
                        $report .= "  {$key}: " . json_encode($value) . "\n";
                    } else {
                        $report .= "  {$key}: {$value}\n";
                    }
                }
            }
            $report .= "\n";
        }

        return $report;
    }
}