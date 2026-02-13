<?php

namespace App\Command;

use App\Service\PerformanceMonitorService;
use App\Service\AnalyticsService;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:system-health-check',
    description: 'Perform comprehensive system health check and diagnostics'
)]
class SystemHealthCheckCommand extends Command
{
    private PerformanceMonitorService $performanceMonitor;
    private AnalyticsService $analyticsService;
    private TaskRepository $taskRepository;
    private UserRepository $userRepository;

    public function __construct(
        PerformanceMonitorService $performanceMonitor,
        AnalyticsService $analyticsService,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ) {
        $this->performanceMonitor = $performanceMonitor;
        $this->analyticsService = $analyticsService;
        $this->taskRepository = $taskRepository;
        $this->userRepository = $userRepository;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=cyan;options=bold>🚀 Starting System Health Check...</fg=cyan>');
        $output->writeln('');

        $progressBar = new ProgressBar($output, 8);
        $progressBar->setFormat('debug');
        $progressBar->start();

        // 1. Collect basic system metrics
        $progressBar->setMessage('Collecting system metrics...');
        $systemMetrics = $this->performanceMonitor->collectMetrics();
        $progressBar->advance();

        // 2. Analyze performance data
        $progressBar->setMessage('Analyzing performance data...');
        $performanceReport = $this->performanceMonitor->getPerformanceReport();
        $progressBar->advance();

        // 3. Check database health
        $progressBar->setMessage('Checking database health...');
        $dbHealth = $this->checkDatabaseHealth();
        $progressBar->advance();

        // 4. Analyze user statistics
        $progressBar->setMessage('Analyzing user statistics...');
        $userStats = $this->analyzeUserStats();
        $progressBar->advance();

        // 5. Analyze task statistics
        $progressBar->setMessage('Analyzing task statistics...');
        $taskStats = $this->analyzeTaskStats();
        $progressBar->advance();

        // 6. Check for slow queries
        $progressBar->setMessage('Checking for slow queries...');
        $slowQueries = $this->performanceMonitor->getSlowQueries();
        $progressBar->advance();

        // 7. Analyze aggregate metrics
        $progressBar->setMessage('Analyzing aggregate metrics...');
        $aggregateMetrics = $this->performanceMonitor->getAggregateMetrics();
        $progressBar->advance();

        // 8. Generate recommendations
        $progressBar->setMessage('Generating recommendations...');
        $recommendations = $this->generateRecommendations($systemMetrics, $performanceReport, $dbHealth, $slowQueries);
        $progressBar->advance();

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        // Display results
        $this->displaySystemMetrics($output, $systemMetrics);
        $output->writeln('');
        
        $this->displayPerformanceReport($output, $performanceReport);
        $output->writeln('');

        $this->displayDatabaseHealth($output, $dbHealth);
        $output->writeln('');

        $this->displayUserStats($output, $userStats);
        $output->writeln('');

        $this->displayTaskStats($output, $taskStats);
        $output->writeln('');

        $this->displaySlowQueries($output, $slowQueries);
        $output->writeln('');

        $this->displayAggregateMetrics($output, $aggregateMetrics);
        $output->writeln('');

        $this->displayRecommendations($output, $recommendations);
        $output->writeln('');

        $overallHealth = $this->calculateOverallHealth($systemMetrics, $performanceReport, $dbHealth, $slowQueries);
        $output->writeln("<fg=green;options=bold>📊 Overall System Health: {$overallHealth}%</fg=green>");

        return Command::SUCCESS;
    }

    private function checkDatabaseHealth(): array
    {
        $connection = $this->taskRepository->getEntityManager()->getConnection();
        
        try {
            $pingResult = $connection->executeStatement('SELECT 1');
            $dbStats = [
                'connected' => true,
                'tables_count' => 0, // Would need to query information_schema for actual count
                'response_time' => 0 // Placeholder
            ];

            // Get approximate table count
            $schemaManager = $connection->createSchemaManager();
            $tables = $schemaManager->listTables();
            $dbStats['tables_count'] = count($tables);

            return $dbStats;
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function analyzeUserStats(): array
    {
        $totalUsers = $this->userRepository->count([]);
        $activeUsers = $this->userRepository->count(['isActive' => true]); // assuming there's an isActive field
        
        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $totalUsers - $activeUsers,
            'activation_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
        ];
    }

    private function analyzeTaskStats(): array
    {
        $totalTasks = $this->taskRepository->count([]);
        $completedTasks = $this->taskRepository->countByStatus(null, true, 'completed');
        $pendingTasks = $this->taskRepository->countByStatus(null, false, 'pending');
        $inProgressTasks = $this->taskRepository->countByStatus(null, null, 'in_progress');
        
        $urgentTasks = $this->taskRepository->countByPriority(null, null, 'urgent');
        $highPriorityTasks = $this->taskRepository->countByPriority(null, null, 'high');

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
            'urgent_tasks' => $urgentTasks,
            'high_priority_tasks' => $highPriorityTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0
        ];
    }

    private function generateRecommendations(array $systemMetrics, array $performanceReport, array $dbHealth, array $slowQueries): array
    {
        $recommendations = [];

        // Memory usage recommendation
        if ($systemMetrics['current_memory_usage_bytes'] > 500 * 1024 * 1024) { // > 500MB
            $recommendations[] = [
                'level' => 'warning',
                'message' => 'High memory usage detected (> 500MB). Consider optimizing queries or increasing server resources.'
            ];
        }

        // Slow query recommendations
        if (count($slowQueries) > 5) {
            $recommendations[] = [
                'level' => 'warning',
                'message' => 'Multiple slow queries detected (' . count($slowQueries) . '). Review database indexes and query optimization.'
            ];
        }

        // Database connection recommendation
        if (!$dbHealth['connected']) {
            $recommendations[] = [
                'level' => 'critical',
                'message' => 'Database connection failed. Check database configuration and connectivity.'
            ];
        }

        // Task completion rate recommendation
        $taskStats = $this->analyzeTaskStats();
        if ($taskStats['completion_rate'] < 50) {
            $recommendations[] = [
                'level' => 'info',
                'message' => 'Task completion rate is low (' . $taskStats['completion_rate'] . '%). Consider workflow improvements.'
            ];
        }

        // User activation rate recommendation
        $userStats = $this->analyzeUserStats();
        if ($userStats['activation_rate'] < 80) {
            $recommendations[] = [
                'level' => 'info',
                'message' => 'User activation rate is low (' . $userStats['activation_rate'] . '%). Consider improving onboarding process.'
            ];
        }

        // Add general recommendation if no specific issues found
        if (empty($recommendations)) {
            $recommendations[] = [
                'level' => 'success',
                'message' => 'System appears healthy! Consider running periodic maintenance tasks.'
            ];
        }

        return $recommendations;
    }

    private function calculateOverallHealth(array $systemMetrics, array $performanceReport, array $dbHealth, array $slowQueries): int
    {
        $score = 100;

        // Deduct points for issues
        if ($systemMetrics['current_memory_usage_bytes'] > 1000 * 1024 * 1024) { // > 1GB
            $score -= 30;
        } elseif ($systemMetrics['current_memory_usage_bytes'] > 750 * 1024 * 1024) { // > 750MB
            $score -= 20;
        } elseif ($systemMetrics['current_memory_usage_bytes'] > 500 * 1024 * 1024) { // > 500MB
            $score -= 10;
        }

        // Deduct points for slow queries
        $slowQueryCount = count($slowQueries);
        if ($slowQueryCount > 10) {
            $score -= 25;
        } elseif ($slowQueryCount > 5) {
            $score -= 15;
        } elseif ($slowQueryCount > 0) {
            $score -= 5;
        }

        // Deduct points for DB issues
        if (!$dbHealth['connected']) {
            $score -= 40;
        }

        // Ensure score is within bounds
        return max(0, min(100, $score));
    }

    private function displaySystemMetrics(OutputInterface $output, array $metrics): void
    {
        $table = new Table($output);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRows([
            ['Environment', $metrics['environment']],
            ['Current Memory Usage', $metrics['current_memory_usage']],
            ['Peak Memory Usage', $metrics['peak_memory_usage']],
            ['PHP Version', $metrics['server_info']['php_version']],
            ['Server Software', $metrics['server_info']['server_software']],
            ['OS Family', $metrics['server_info']['os']]
        ]);
        $table->render();

        $output->writeln('<fg=yellow;options=bold>📋 System Metrics</fg=yellow>');
    }

    private function displayPerformanceReport(OutputInterface $output, array $report): void
    {
        $table = new Table($output);
        $table->setHeaders(['Component', 'Value']);
        $table->addRows([
            ['Total Requests Handled', isset($report['aggregate_metrics']) ? count($report['aggregate_metrics']) : 0],
            ['Slow Queries', count($report['slow_queries'])],
            ['Cache Enabled', $report['cache_info']['enabled'] ?? 'Unknown']
        ]);
        $table->render();

        $output->writeln('<fg=yellow;options=bold>⚡ Performance Report</fg=yellow>');
    }

    private function displayDatabaseHealth(OutputInterface $output, array $health): void
    {
        $status = $health['connected'] ? '<fg=green>✓ Connected</fg=green>' : '<fg=red>✗ Disconnected</fg=red>';
        
        $table = new Table($output);
        $table->setHeaders(['Aspect', 'Status/Value']);
        $table->addRows([
            ['Connection Status', $status],
            ['Number of Tables', $health['tables_count'] ?? 'N/A']
        ]);
        
        if (isset($health['error'])) {
            $table->addRow(['Error', '<fg=red>' . $health['error'] . '</fg=red>']);
        }
        
        $table->render();
        
        $output->writeln('<fg=yellow;options=bold>🗄️  Database Health</fg=yellow>');
    }

    private function displayUserStats(OutputInterface $output, array $stats): void
    {
        $table = new Table($output);
        $table->setHeaders(['Metric', 'Count', 'Percentage']);
        $table->addRows([
            ['Total Users', $stats['total_users'], '100%'],
            ['Active Users', $stats['active_users'], $stats['activation_rate'] . '%'],
            ['Inactive Users', $stats['inactive_users'], (100 - $stats['activation_rate']) . '%']
        ]);
        $table->render();

        $output->writeln('<fg=yellow;options=bold>👥 User Statistics</fg=yellow>');
    }

    private function displayTaskStats(OutputInterface $output, array $stats): void
    {
        $table = new Table($output);
        $table->setHeaders(['Metric', 'Count', 'Percentage']);
        $table->addRows([
            ['Total Tasks', $stats['total_tasks'], '100%'],
            ['Completed', $stats['completed_tasks'], $stats['completion_rate'] . '%'],
            ['Pending', $stats['pending_tasks'], $stats['total_tasks'] > 0 ? round(($stats['pending_tasks'] / $stats['total_tasks']) * 100, 2) . '%' : '0%'],
            ['In Progress', $stats['in_progress_tasks'], $stats['total_tasks'] > 0 ? round(($stats['in_progress_tasks'] / $stats['total_tasks']) * 100, 2) . '%' : '0%'],
            ['Urgent Priority', $stats['urgent_tasks'], $stats['total_tasks'] > 0 ? round(($stats['urgent_tasks'] / $stats['total_tasks']) * 100, 2) . '%' : '0%'],
            ['High Priority', $stats['high_priority_tasks'], $stats['total_tasks'] > 0 ? round(($stats['high_priority_tasks'] / $stats['total_tasks']) * 100, 2) . '%' : '0%']
        ]);
        $table->render();

        $output->writeln('<fg=yellow;options=bold>✅ Task Statistics</fg=yellow>');
    }

    private function displaySlowQueries(OutputInterface $output, array $slowQueries): void
    {
        if (empty($slowQueries)) {
            $output->writeln('<fg=green>✓ No slow queries detected</fg=green>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Source', 'Execution Time (ms)', 'Timestamp']);
            
            foreach (array_slice($slowQueries, 0, 5) as $query) { // Show top 5
                $table->addRow([
                    $query['source'] ?? 'unknown',
                    number_format($query['execution_time_ms'] ?? 0, 2),
                    $query['timestamp'] ?? 'N/A'
                ]);
            }
            
            $table->render();
            $output->writeln('<fg=yellow;options=bold>🐌 Slow Queries (Top 5)</fg=yellow>');
            $output->writeln('<fg=red>Found ' . count($slowQueries) . ' slow queries. Consider optimization.</fg=red>');
        }
    }

    private function displayAggregateMetrics(OutputInterface $output, array $aggregateMetrics): void
    {
        if (empty($aggregateMetrics)) {
            $output->writeln('<fg=yellow>No aggregate metrics available</fg=yellow>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Operation', 'Avg Time (ms)', 'Calls', 'Max Time (ms)']);
            
            $count = 0;
            foreach ($aggregateMetrics as $operation => $metrics) {
                if ($count >= 5) break; // Show top 5
                
                $table->addRow([
                    $operation,
                    number_format($metrics['average_execution_time'], 2),
                    $metrics['count'],
                    number_format($metrics['max_execution_time'], 2)
                ]);
                
                $count++;
            }
            
            $table->render();
            $output->writeln('<fg=yellow;options=bold>📈 Aggregate Metrics (Top 5)</fg=yellow>');
        }
    }

    private function displayRecommendations(OutputInterface $output, array $recommendations): void
    {
        $output->writeln('<fg=yellow;options=bold>💡 Recommendations</fg=yellow>');
        
        foreach ($recommendations as $rec) {
            $color = match($rec['level']) {
                'critical' => 'red',
                'warning' => 'yellow',
                'info' => 'cyan',
                'success' => 'green',
                default => 'white'
            };
            
            $icon = match($rec['level']) {
                'critical' => '🚨',
                'warning' => '⚠️ ',
                'info' => 'ℹ️ ',
                'success' => '✨',
                default => '• '
            };
            
            $output->writeln("<fg={$color}>{$icon} {$rec['message']}</fg={$color}>");  
        }
    }
}