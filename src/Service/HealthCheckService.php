<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service for system health checks
 */
class HealthCheckService
{
    private Connection $connection;
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private KernelInterface $kernel;
    private RequestStack $requestStack;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        ContainerInterface $container,
        KernelInterface $kernel,
        RequestStack $requestStack
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->container = $container;
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
    }

    /**
     * Perform comprehensive health check
     */
    public function performHealthCheck(array $options = []): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting system health check');

        $checks = [
            'database' => $this->checkDatabase(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
            'cache' => $this->checkCache(),
            'configuration' => $this->checkConfiguration(),
            'dependencies' => $this->checkDependencies(),
            'security' => $this->checkSecurity(),
        ];

        $overallStatus = $this->calculateOverallStatus($checks);
        
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'environment' => $this->kernel->getEnvironment(),
            'overall_status' => $overallStatus,
            'checks' => $checks,
        ];

        $this->logger->info('System health check completed', [
            'overall_status' => $overallStatus,
            'duration_ms' => $result['duration_ms']
        ]);

        return $result;
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): array
    {
        try {
            // Test database connection
            try {
                $this->connection->executeQuery('SELECT 1');
                $connected = true;
            } catch (\Exception $e) {
                $connected = false;
            }
            
            if (!$connected) {
                return [
                    'status' => 'critical',
                    'message' => 'Cannot connect to database',
                    'details' => []
                ];
            }

            // Test basic query performance
            $startTime = microtime(true);
            $stmt = $this->connection->prepare('SELECT 1');
            $result = $stmt->executeQuery();
            $queryTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Check if query took too long
            $status = $queryTime > 100 ? 'warning' : 'ok'; // Warning if query takes more than 100ms

            // Get database info
            $platform = 'unknown'; // Simplified to avoid method access issues
            $databaseName = $this->connection->getDatabase();

            return [
                'status' => $status,
                'message' => 'Database connection successful',
                'details' => [
                    'platform' => $platform,
                    'database' => $databaseName,
                    'query_time_ms' => round($queryTime, 2),
                    'connected' => true
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Database health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check disk space availability
     */
    private function checkDiskSpace(): array
    {
        try {
            $projectDir = $this->kernel->getProjectDir();
            $freeSpace = disk_free_space($projectDir);
            $totalSpace = disk_total_space($projectDir);
            
            if ($freeSpace === false || $totalSpace === false) {
                return [
                    'status' => 'warning',
                    'message' => 'Unable to determine disk space',
                    'details' => []
                ];
            }
            
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;
            
            // Determine status based on usage percentage
            if ($usagePercent > 90) {
                $status = 'critical';
                $message = 'Disk space critically low';
            } elseif ($usagePercent > 80) {
                $status = 'warning';
                $message = 'Disk space running low';
            } else {
                $status = 'ok';
                $message = 'Sufficient disk space available';
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'free_space' => $this->formatBytes($freeSpace),
                    'total_space' => $this->formatBytes($totalSpace),
                    'used_space' => $this->formatBytes($usedSpace),
                    'usage_percent' => round($usagePercent, 2)
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Disk space check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Disk space check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            $peakMemoryUsage = memory_get_peak_usage(true);
            
            // Parse memory limit
            $limitInBytes = $this->parseMemoryLimit($memoryLimit);
            
            if ($limitInBytes > 0) {
                $usagePercent = ($memoryUsage / $limitInBytes) * 100;
                
                if ($usagePercent > 80) {
                    $status = 'warning';
                    $message = 'Memory usage is high';
                } else {
                    $status = 'ok';
                    $message = 'Memory usage is normal';
                }
            } else {
                // Unlimited memory
                $status = 'ok';
                $message = 'Memory usage - unlimited limit';
                $usagePercent = 0;
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'memory_limit' => $memoryLimit,
                    'current_usage' => $this->formatBytes($memoryUsage),
                    'peak_usage' => $this->formatBytes($peakMemoryUsage),
                    'usage_percent' => round($usagePercent, 2)
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Memory check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Memory check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check cache status
     */
    private function checkCache(): array
    {
        try {
            $cacheAvailable = $this->container->has('cache.app');
            
            if (!$cacheAvailable) {
                return [
                    'status' => 'warning',
                    'message' => 'Cache is not available',
                    'details' => [
                        'cache_available' => false
                    ]
                ];
            }
            
            // Test cache read/write
            $cache = $this->container->get('cache.app');
            $testKey = 'health_check_test_' . uniqid();
            $testValue = 'test_value_' . time();
            
            // Set and get test value
            $cache->set($testKey, $testValue, 60); // 1 minute TTL
            $retrievedValue = $cache->get($testKey);
            
            $cacheWorking = $retrievedValue === $testValue;
            
            // Clean up test key
            $cache->delete($testKey);
            
            if ($cacheWorking) {
                return [
                    'status' => 'ok',
                    'message' => 'Cache is working properly',
                    'details' => [
                        'cache_available' => true,
                        'read_write_working' => true
                    ]
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache read/write test failed',
                    'details' => [
                        'cache_available' => true,
                        'read_write_working' => false
                    ]
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Cache check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Cache check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check configuration
     */
    private function checkConfiguration(): array
    {
        try {
            $debugMode = $this->container->getParameter('kernel.debug');
            $environment = $this->kernel->getEnvironment();
            
            $issues = [];
            
            // Check for production with debug enabled
            if ($environment === 'prod' && $debugMode) {
                $issues[] = 'Debug mode enabled in production environment';
            }
            
            // Check for common security misconfigurations
            if ($environment === 'prod' && $this->container->has('profiler')) {
                $issues[] = 'Profiler enabled in production environment';
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'ok',
                    'message' => 'Configuration appears healthy',
                    'details' => [
                        'environment' => $environment,
                        'debug_mode' => $debugMode
                    ]
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Configuration issues detected',
                    'details' => [
                        'environment' => $environment,
                        'debug_mode' => $debugMode,
                        'issues' => $issues
                    ]
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Configuration check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Configuration check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check dependencies
     */
    private function checkDependencies(): array
    {
        try {
            // Check if required services are available
            $requiredServices = [
                'doctrine.orm.entity_manager',
                'router',
                'security.helper',
                'twig'
            ];
            
            $missingServices = [];
            foreach ($requiredServices as $service) {
                if (!$this->container->has($service)) {
                    $missingServices[] = $service;
                }
            }
            
            if (empty($missingServices)) {
                return [
                    'status' => 'ok',
                    'message' => 'All required dependencies are available',
                    'details' => [
                        'checked_services' => $requiredServices,
                        'missing_services' => []
                    ]
                ];
            } else {
                return [
                    'status' => 'critical',
                    'message' => 'Missing required dependencies',
                    'details' => [
                        'checked_services' => $requiredServices,
                        'missing_services' => $missingServices
                    ]
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Dependencies check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Dependencies check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Check security aspects
     */
    private function checkSecurity(): array
    {
        try {
            $issues = [];
            
            // Check if security is properly configured
            if (!$this->container->hasParameter('security.firewalls')) {
                $issues[] = 'Security firewalls not configured';
            }
            
            // Check if CSRF protection is available
            if (!$this->container->has('security.csrf.token_manager')) {
                $issues[] = 'CSRF protection not available';
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'ok',
                    'message' => 'Security configuration appears healthy',
                    'details' => [
                        'issues_found' => 0
                    ]
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Security configuration issues detected',
                    'details' => [
                        'issues_found' => count($issues),
                        'issues' => $issues
                    ]
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Security check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'critical',
                'message' => 'Security check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Calculate overall health status based on individual checks
     */
    private function calculateOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        
        // If any check is critical, overall status is critical
        if (in_array('critical', $statuses)) {
            return 'critical';
        }
        
        // If any check is warning, overall status is warning
        if (in_array('warning', $statuses)) {
            return 'warning';
        }
        
        // Otherwise, all checks are ok
        return 'ok';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse memory limit from PHP ini format
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return 0; // Unlimited
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
}