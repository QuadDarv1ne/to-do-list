<?php

namespace App\Command;

use App\Service\ApiPerformanceMonitorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monitor-api-performance',
    description: 'Monitor and analyze API performance metrics',
)]
class ApiPerformanceMonitorCommand extends Command
{
    private ApiPerformanceMonitorService $apiPerformanceMonitorService;

    public function __construct(ApiPerformanceMonitorService $apiPerformanceMonitorService)
    {
        $this->apiPerformanceMonitorService = $apiPerformanceMonitorService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: summary, slow-requests, bottlenecks, clear', 'summary')
            ->addOption('min-time', null, InputOption::VALUE_REQUIRED, 'Minimum execution time filter (in ms)')
            ->addOption('max-time', null, InputOption::VALUE_REQUIRED, 'Maximum execution time filter (in ms)')
            ->addOption('status-code', null, InputOption::VALUE_REQUIRED, 'Filter by status code')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'Filter by route')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit for results', '10')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');
        $limit = (int)$input->getOption('limit');

        $io->title('API Performance Monitor');

        // Build filters array
        $filters = [];
        if ($input->getOption('min-time')) {
            $filters['min_execution_time'] = (int)$input->getOption('min-time');
        }
        if ($input->getOption('max-time')) {
            $filters['max_execution_time'] = (int)$input->getOption('max-time');
        }
        if ($input->getOption('status-code')) {
            $filters['status_code'] = (int)$input->getOption('status-code');
        }
        if ($input->getOption('route')) {
            $filters['route'] = $input->getOption('route');
        }

        switch ($action) {
            case 'summary':
                $io->writeln('Generating API performance summary...');

                $summary = $this->apiPerformanceMonitorService->getPerformanceSummary($filters);

                if ($format === 'json') {
                    $outputData = [
                        'summary' => $summary,
                        'filters' => $filters,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Summary saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displaySummaryReport($io, $summary);

                    if ($outputFile) {
                        $textData = $this->getSummaryTextReport($summary);
                        file_put_contents($outputFile, $textData);
                        $io->success("Summary saved to {$outputFile}");
                    }
                }

                break;

            case 'slow-requests':
                $io->writeln("Getting slowest requests (top {$limit})...");

                $slowRequests = $this->apiPerformanceMonitorService->getSlowestRequests($limit);

                if ($format === 'json') {
                    $outputData = [
                        'slow_requests' => $slowRequests,
                        'limit' => $limit,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Slow requests saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displaySlowRequestsReport($io, $slowRequests);

                    if ($outputFile) {
                        $textData = $this->getSlowRequestsTextReport($slowRequests);
                        file_put_contents($outputFile, $textData);
                        $io->success("Slow requests saved to {$outputFile}");
                    }
                }

                break;

            case 'bottlenecks':
                $io->writeln('Identifying performance bottlenecks...');

                $bottlenecks = $this->apiPerformanceMonitorService->identifyBottlenecks();

                if ($format === 'json') {
                    $outputData = [
                        'bottlenecks' => $bottlenecks,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];

                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Bottlenecks report saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayBottlenecksReport($io, $bottlenecks);

                    if ($outputFile) {
                        $textData = $this->getBottlenecksTextReport($bottlenecks);
                        file_put_contents($outputFile, $textData);
                        $io->success("Bottlenecks report saved to {$outputFile}");
                    }
                }

                break;

            case 'clear':
                $io->writeln('Clearing API performance metrics...');

                $this->apiPerformanceMonitorService->clearMetrics();

                $io->success('API performance metrics cleared successfully.');

                break;

            default:
                $io->error("Unknown action: {$action}. Use summary, slow-requests, bottlenecks, or clear.");

                return 1;
        }

        return 0;
    }

    private function displaySummaryReport(SymfonyStyle $io, array $summary): void
    {
        $io->section('API Performance Summary');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $summary['total_requests']],
                ['Average Response Time', $summary['average_response_time_ms'] . ' ms'],
                ['Min Response Time', $summary['min_response_time_ms'] . ' ms'],
                ['Max Response Time', $summary['max_response_time_ms'] . ' ms'],
                ['Average Memory Used', $summary['average_memory_used_bytes'] . ' bytes'],
                ['Total Errors', $summary['total_errors']],
                ['Error Rate', $summary['error_rate_percent'] . '%'],
                ['Requests Per Minute', $summary['requests_per_minute']],
            ],
        );

        if (!empty($summary['top_slow_endpoints'])) {
            $io->section('Top Slow Endpoints');

            $rows = [];
            foreach ($summary['top_slow_endpoints'] as $endpoint => $data) {
                $rows[] = [
                    $endpoint,
                    round($data['average_time'], 2) . ' ms',
                    $data['count'],
                ];
            }

            $io->table(['Endpoint', 'Avg Time', 'Requests'], $rows);
        }
    }

    private function displaySlowRequestsReport(SymfonyStyle $io, array $slowRequests): void
    {
        if (empty($slowRequests)) {
            $io->writeln('No slow requests found.');

            return;
        }

        $io->section('Slowest API Requests');

        $rows = [];
        foreach ($slowRequests as $request) {
            $rows[] = [
                $request['route'],
                $request['uri'],
                $request['execution_time_ms'] . ' ms',
                $request['response_status'],
                $request['timestamp'],
            ];
        }

        $io->table(['Route', 'URI', 'Time', 'Status', 'Timestamp'], $rows);
    }

    private function displayBottlenecksReport(SymfonyStyle $io, array $bottlenecks): void
    {
        if (empty($bottlenecks)) {
            $io->success('No performance bottlenecks detected.');

            return;
        }

        foreach ($bottlenecks as $type => $bottleneck) {
            $io->section(ucfirst(str_replace('_', ' ', $type)));
            $io->writeln($bottleneck['description']);

            if ($type === 'slow_endpoints') {
                $rows = [];
                foreach ($bottleneck['endpoints'] as $endpoint => $data) {
                    $rows[] = [
                        $endpoint,
                        round($data['average_time'], 2) . ' ms',
                        $data['count'],
                    ];
                }

                $io->table(['Endpoint', 'Avg Time', 'Requests'], $rows);
            } elseif ($type === 'high_error_endpoints') {
                $rows = [];
                foreach ($bottleneck['endpoints'] as $endpoint => $data) {
                    $rows[] = [
                        $endpoint,
                        $data['error_rate_percent'] . '%',
                        $data['total_requests'],
                        $data['error_count'],
                    ];
                }

                $io->table(['Endpoint', 'Error Rate', 'Total Requests', 'Error Count'], $rows);
            }
        }
    }

    private function getSummaryTextReport(array $summary): string
    {
        $report = "API PERFORMANCE SUMMARY REPORT\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= 'Timestamp: ' . date('Y-m-d H:i:s') . "\n\n";

        $report .= "METRICS\n";
        $report .= str_repeat('-', 20) . "\n";
        $report .= "Total Requests: {$summary['total_requests']}\n";
        $report .= "Average Response Time: {$summary['average_response_time_ms']} ms\n";
        $report .= "Min Response Time: {$summary['min_response_time_ms']} ms\n";
        $report .= "Max Response Time: {$summary['max_response_time_ms']} ms\n";
        $report .= "Average Memory Used: {$summary['average_memory_used_bytes']} bytes\n";
        $report .= "Total Errors: {$summary['total_errors']}\n";
        $report .= "Error Rate: {$summary['error_rate_percent']}%\n";
        $report .= "Requests Per Minute: {$summary['requests_per_minute']}\n\n";

        if (!empty($summary['top_slow_endpoints'])) {
            $report .= "TOP SLOW ENDPOINTS\n";
            $report .= str_repeat('-', 20) . "\n";

            foreach ($summary['top_slow_endpoints'] as $endpoint => $data) {
                $report .= \sprintf(
                    "%s: avg %.2f ms (%d requests)\n",
                    $endpoint,
                    $data['average_time'],
                    $data['count'],
                );
            }

            $report .= "\n";
        }

        return $report;
    }

    private function getSlowRequestsTextReport(array $slowRequests): string
    {
        $report = "SLOWEST API REQUESTS REPORT\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= 'Timestamp: ' . date('Y-m-d H:i:s') . "\n\n";

        if (empty($slowRequests)) {
            $report .= "No slow requests found.\n";

            return $report;
        }

        $report .= "SLOWEST REQUESTS\n";
        $report .= str_repeat('-', 20) . "\n";

        foreach ($slowRequests as $request) {
            $report .= "Route: {$request['route']}\n";
            $report .= "URI: {$request['uri']}\n";
            $report .= "Execution Time: {$request['execution_time_ms']} ms\n";
            $report .= "Status: {$request['response_status']}\n";
            $report .= "Timestamp: {$request['timestamp']}\n";
            $report .= str_repeat('-', 20) . "\n";
        }

        return $report;
    }

    private function getBottlenecksTextReport(array $bottlenecks): string
    {
        $report = "API PERFORMANCE BOTTLENECKS REPORT\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= 'Timestamp: ' . date('Y-m-d H:i:s') . "\n\n";

        if (empty($bottlenecks)) {
            $report .= "No performance bottlenecks detected.\n";

            return $report;
        }

        foreach ($bottlenecks as $type => $bottleneck) {
            $report .= strtoupper(str_replace('_', ' ', $type)) . "\n";
            $report .= str_repeat('-', 20) . "\n";
            $report .= $bottleneck['description'] . "\n\n";

            if ($type === 'slow_endpoints') {
                foreach ($bottleneck['endpoints'] as $endpoint => $data) {
                    $report .= \sprintf(
                        "%s: avg %.2f ms (%d requests)\n",
                        $endpoint,
                        $data['average_time'],
                        $data['count'],
                    );
                }
            } elseif ($type === 'high_error_endpoints') {
                foreach ($bottleneck['endpoints'] as $endpoint => $data) {
                    $report .= \sprintf(
                        "%s: %s%% error rate (%d total, %d errors)\n",
                        $endpoint,
                        $data['error_rate_percent'],
                        $data['total_requests'],
                        $data['error_count'],
                    );
                }
            }

            $report .= "\n";
        }

        return $report;
    }
}
