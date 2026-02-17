<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service for automatic performance auditing
 */
class PerformanceAuditService
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private KernelInterface $kernel;

    public function __construct(
        EntityManagerInterface $entityManager,
        Connection $connection,
        LoggerInterface $logger,
        ContainerInterface $container,
        KernelInterface $kernel
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->container = $container;
        $this->kernel = $kernel;
    }

    /**
     * Run comprehensive performance audit
     */
    public function runAudit(array $options = []): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting performance audit');

        $auditResults = [
            'timestamp' => date('Y-m-d H:i:s'),
            'duration_ms' => 0,
            'environment' => $this->kernel->getEnvironment(),
            'checks' => [
                'database' => $this->auditDatabase(),
                'code_quality' => $this->auditCodeQuality(),
                'configuration' => $this->auditConfiguration(),
                'security' => $this->auditSecurity(),
                'performance_metrics' => $this->auditPerformanceMetrics()
            ]
        ];

        $auditResults['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info('Performance audit completed', ['duration_ms' => $auditResults['duration_ms']]);

        return $auditResults;
    }

    /**
     * Audit database performance
     */
    private function auditDatabase(): array
    {
        $results = [
            'tables_count' => 0,
            'recommendations' => []
        ];

        try {
            // Get table count
            $schemaManager = $this->connection->createSchemaManager();
            $tables = $schemaManager->listTables();
            $results['tables_count'] = count($tables);

            // Add recommendations for common optimizations
            $results['recommendations'][] = [
                'type' => 'index_recommendation',
                'reason' => 'Consider adding indexes on frequently queried columns like status, priority, and dates'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Database audit failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Audit code quality
     */
    private function auditCodeQuality(): array
    {
        $results = [
            'files_analyzed' => 0,
            'potential_issues' => [],
            'recommendations' => []
        ];

        try {
            $finder = new Finder();
            $finder->files()
                ->in($this->kernel->getProjectDir() . '/src')
                ->name('*.php');

            $results['files_analyzed'] = iterator_count($finder);

            // Look for potential performance issues in code
            foreach ($finder as $file) {
                $content = $file->getContents();
                
                // Check for raw SQL queries
                if (preg_match_all('/->executeQuery\(|->prepare\(|->query\(/', $content, $matches)) {
                    $results['potential_issues'][] = [
                        'type' => 'raw_sql_usage',
                        'file' => $file->getRelativePathname(),
                        'count' => count($matches[0])
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Code quality audit failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Audit configuration
     */
    private function auditConfiguration(): array
    {
        $results = [
            'cache_configured' => false,
            'debug_mode' => false,
            'recommended_settings' => []
        ];

        try {
            // Check if cache is properly configured
            $cacheEnabled = $this->container->has('cache.app');
            $results['cache_configured'] = $cacheEnabled;

            // Check debug mode
            $results['debug_mode'] = $this->container->getParameter('kernel.debug');

            // Recommendations based on environment
            if ($this->kernel->getEnvironment() === 'prod' && $results['debug_mode']) {
                $results['recommended_settings'][] = [
                    'setting' => 'kernel.debug',
                    'recommended_value' => 'false',
                    'reason' => 'Debug mode should be disabled in production for performance'
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Configuration audit failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Audit security configurations
     */
    private function auditSecurity(): array
    {
        $results = [
            'security_configured' => false,
            'csrf_enabled' => false,
            'recommendations' => []
        ];

        try {
            // Check if security is configured
            $results['security_configured'] = $this->container->hasParameter('security.firewalls');

            // Check for CSRF protection (if available)
            $results['csrf_enabled'] = $this->container->has('security.csrf.token_manager');

            if (!$results['security_configured']) {
                $results['recommendations'][] = [
                    'type' => 'missing_security',
                    'reason' => 'Security configuration is not properly set up'
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Security audit failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Audit current performance metrics
     */
    private function auditPerformanceMetrics(): array
    {
        $results = [
            'memory_usage' => memory_get_usage(),
            'peak_memory_usage' => memory_get_peak_usage(),
            'current_time' => microtime(true),
            'metrics' => []
        ];

        try {
            // Get current memory usage stats
            $results['metrics'] = [
                'memory_current_formatted' => $this->formatBytes($results['memory_usage']),
                'memory_peak_formatted' => $this->formatBytes($results['peak_memory_usage']),
                'memory_limit' => ini_get('memory_limit')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Performance metrics audit failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Generate summary report
     */
    public function generateSummaryReport(array $auditResults): array
    {
        $summary = [
            'overall_score' => 0,
            'critical_issues' => 0,
            'warnings' => 0,
            'passed_checks' => 0,
            'total_checks' => 0,
            'status' => 'unknown'
        ];

        // Count issues and calculate score
        foreach ($auditResults['checks'] as $category => $results) {
            $summary['total_checks']++;
            
            if (isset($results['error'])) {
                $summary['critical_issues']++;
            } elseif (!empty($results['recommendations']) || !empty($results['potential_issues'])) {
                $summary['warnings']++;
            } else {
                $summary['passed_checks']++;
            }
        }

        // Calculate overall score (0-100)
        if ($summary['total_checks'] > 0) {
            $passedRatio = $summary['passed_checks'] / $summary['total_checks'];
            $warningRatio = $summary['warnings'] / $summary['total_checks'];
            $criticalRatio = $summary['critical_issues'] / $summary['total_checks'];
            
            $score = 100 - ($criticalRatio * 50) - ($warningRatio * 10); // Deduct points for issues
            $summary['overall_score'] = max(0, min(100, round($score)));
        }

        // Determine status
        if ($summary['critical_issues'] > 0) {
            $summary['status'] = 'critical';
        } elseif ($summary['warnings'] > 0) {
            $summary['status'] = 'warning';
        } else {
            $summary['status'] = 'good';
        }

        return $summary;
    }
}
