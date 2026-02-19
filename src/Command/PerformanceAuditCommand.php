<?php

namespace App\Command;

use App\Service\PerformanceAuditService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:performance-audit',
    description: 'Run automatic performance audit of the application',
)]
class PerformanceAuditCommand extends Command
{
    private PerformanceAuditService $performanceAuditService;

    public function __construct(PerformanceAuditService $performanceAuditService)
    {
        $this->performanceAuditService = $performanceAuditService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('checks', 'c', InputOption::VALUE_REQUIRED, 'Comma-separated list of checks to run: database, code-quality, configuration, security, performance-metrics')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $checksOption = $input->getOption('checks');
        $outputFile = $input->getOption('output-file');

        $io->title('Performance Audit Tool');

        // Determine which checks to run
        $availableChecks = ['database', 'code-quality', 'configuration', 'security', 'performance-metrics'];
        $checksToRun = $availableChecks;

        if ($checksOption) {
            $requestedChecks = array_map('trim', explode(',', $checksOption));
            $checksToRun = array_intersect($requestedChecks, $availableChecks);
        }

        $io->writeln('Running performance audit...');
        $io->writeln('Checks to run: ' . implode(', ', $checksToRun));

        // Run the audit
        $auditResults = $this->performanceAuditService->runAudit([
            'checks' => $checksToRun,
        ]);

        // Generate summary
        $summary = $this->performanceAuditService->generateSummaryReport($auditResults);

        // Format output based on selected format
        if ($format === 'json') {
            $outputData = [
                'summary' => $summary,
                'details' => $auditResults,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($outputFile) {
                file_put_contents($outputFile, $jsonData);
                $io->success("Audit results saved to {$outputFile}");
            } else {
                $output->writeln($jsonData);
            }
        } else {
            // Text format output
            $this->displayTextReport($io, $auditResults, $summary);

            if ($outputFile) {
                $textData = $this->getTextReport($auditResults, $summary);
                file_put_contents($outputFile, $textData);
                $io->success("Audit results saved to {$outputFile}");
            }
        }

        // Exit with error code if critical issues found
        return $summary['critical_issues'] > 0 ? 1 : 0;
    }

    private function displayTextReport(SymfonyStyle $io, array $auditResults, array $summary): void
    {
        // Display summary
        $io->section('Audit Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Overall Score', $summary['overall_score'] . '/100'],
                ['Status', $summary['status']],
                ['Duration', $auditResults['duration_ms'] . ' ms'],
                ['Environment', $auditResults['environment']],
                ['Critical Issues', $summary['critical_issues']],
                ['Warnings', $summary['warnings']],
                ['Passed Checks', $summary['passed_checks']],
            ],
        );

        // Display detailed results
        foreach ($auditResults['checks'] as $checkName => $results) {
            $io->section(ucfirst(str_replace('_', ' ', $checkName)) . ' Check');

            if (isset($results['error'])) {
                $io->error("Error in {$checkName} check: " . $results['error']);

                continue;
            }

            $this->displayCheckResults($io, $results);
        }
    }

    private function displayCheckResults(SymfonyStyle $io, array $results): void
    {
        foreach ($results as $key => $value) {
            if ($key === 'recommendations' && !empty($value)) {
                $io->writeln('<comment>Recommendations:</comment>');
                foreach ($value as $rec) {
                    if (\is_array($rec)) {
                        $io->writeln('  • ' . ($rec['reason'] ?? json_encode($rec)));
                    } else {
                        $io->writeln('  • ' . $rec);
                    }
                }
            } elseif ($key === 'potential_issues' && !empty($value)) {
                $io->writeln('<comment>Potential Issues:</comment>');
                foreach ($value as $issue) {
                    if (\is_array($issue)) {
                        $io->writeln('  • ' . ($issue['type'] ?? 'Unknown') . ': ' . ($issue['file'] ?? json_encode($issue)));
                    } else {
                        $io->writeln('  • ' . $issue);
                    }
                }
            } elseif ($key !== 'recommendations' && $key !== 'potential_issues') {
                if (\is_array($value)) {
                    $io->writeln($key . ': ' . json_encode($value));
                } else {
                    $io->writeln($key . ': ' . $value);
                }
            }
        }
    }

    private function getTextReport(array $auditResults, array $summary): string
    {
        $report = "PERFORMANCE AUDIT REPORT\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= "Timestamp: {$auditResults['timestamp']}\n";
        $report .= "Duration: {$auditResults['duration_ms']} ms\n";
        $report .= "Environment: {$auditResults['environment']}\n\n";

        $report .= "SUMMARY\n";
        $report .= str_repeat('-', 20) . "\n";
        $report .= "Overall Score: {$summary['overall_score']}/100\n";
        $report .= "Status: {$summary['status']}\n";
        $report .= "Critical Issues: {$summary['critical_issues']}\n";
        $report .= "Warnings: {$summary['warnings']}\n";
        $report .= "Passed Checks: {$summary['passed_checks']}\n\n";

        foreach ($auditResults['checks'] as $checkName => $results) {
            $report .= strtoupper(str_replace('_', ' ', $checkName)) . " CHECK\n";
            $report .= str_repeat('-', 30) . "\n";

            if (isset($results['error'])) {
                $report .= "Error: {$results['error']}\n\n";

                continue;
            }

            foreach ($results as $key => $value) {
                if ($key === 'recommendations' && !empty($value)) {
                    $report .= "Recommendations:\n";
                    foreach ($value as $rec) {
                        $report .= '  • ' . (\is_array($rec) ? ($rec['reason'] ?? json_encode($rec)) : $rec) . "\n";
                    }
                    $report .= "\n";
                } elseif ($key === 'potential_issues' && !empty($value)) {
                    $report .= "Potential Issues:\n";
                    foreach ($value as $issue) {
                        $report .= '  • ' . (\is_array($issue) ? ($issue['type'] ?? 'Unknown') . ': ' . ($issue['file'] ?? json_encode($issue)) : $issue) . "\n";
                    }
                    $report .= "\n";
                } elseif ($key !== 'recommendations' && $key !== 'potential_issues') {
                    $report .= "{$key}: ";
                    $report .= \is_array($value) ? json_encode($value) : $value;
                    $report .= "\n";
                }
            }
            $report .= "\n";
        }

        return $report;
    }
}
