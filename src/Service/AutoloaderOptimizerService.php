<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * Service for optimizing Composer autoloader
 */
class AutoloaderOptimizerService
{
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private string $projectDir;

    public function __construct(
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->projectDir = $container->getParameter('kernel.project_dir');
    }

    /**
     * Optimize Composer autoloader
     */
    public function optimizeAutoloader(bool $optimizePSR0 = false, bool $optimizeAPCu = false): array
    {
        $this->logger->info('Starting autoloader optimization');

        $command = ['composer', 'dump-autoload', '--optimize', '--classmap-authoritative'];
        
        if ($optimizePSR0) {
            $command[] = '--optimize-psr0';
        }
        
        if ($optimizeAPCu) {
            $command[] = '--apcu';
        }

        try {
            $process = new Process($command, $this->projectDir);
            $process->setTimeout(300); // 5 minutes timeout
            $startTime = microtime(true);
            
            $process->run(function ($type, $buffer) {
                // Output any messages during the process
                if (strpos($buffer, 'Deprecation') === false && 
                    strpos($buffer, 'Warning') === false) {
                    $this->logger->debug('Composer output: ' . trim($buffer));
                }
            });

            $executionTime = microtime(true) - $startTime;
            
            $result = [
                'success' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
                'execution_time' => round($executionTime, 2),
                'optimized_psr0' => $optimizePSR0,
                'optimized_apcu' => $optimizeAPCu
            ];

            if ($result['success']) {
                $this->logger->info('Autoloader optimization completed successfully', [
                    'execution_time' => $result['execution_time']
                ]);
            } else {
                $this->logger->error('Autoloader optimization failed', [
                    'exit_code' => $result['exit_code'],
                    'error_output' => $result['error_output']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to run autoloader optimization', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exit_code' => -1,
                'output' => '',
                'error_output' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate optimized classmap for specific directories
     */
    public function generateOptimizedClassmap(array $directories = []): array
    {
        $this->logger->info('Starting optimized classmap generation');

        // If no directories specified, use common directories
        if (empty($directories)) {
            $directories = [
                $this->projectDir . '/src',
                $this->projectDir . '/vendor'
            ];
        }

        $results = [
            'directories_processed' => [],
            'classes_found' => 0,
            'errors' => []
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $results['errors'][] = "Directory does not exist: {$directory}";
                continue;
            }

            $results['directories_processed'][] = $directory;

            // Count PHP files in directory
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory)
            );
            
            $phpFiles = 0;
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $phpFiles++;
                }
            }

            $results['classes_found'] += $phpFiles;
        }

        $this->logger->info('Classmap generation completed', [
            'directories_processed' => count($results['directories_processed']),
            'classes_found_estimate' => $results['classes_found']
        ]);

        return $results;
    }

    /**
     * Clear and regenerate opcache
     */
    public function clearAndRegenerateOpcache(): array
    {
        $this->logger->info('Attempting to clear and regenerate opcache');

        $result = [
            'opcache_exists' => extension_loaded('opcache'),
            'opcache_enabled' => false,
            'opcache_reset' => false,
            'opcache_status' => null,
            'message' => ''
        ];

        if (!$result['opcache_exists']) {
            $result['message'] = 'OPcache extension is not loaded';
            $this->logger->warning('OPcache not available');
            return $result;
        }

        $result['opcache_enabled'] = ini_get('opcache.enable') && function_exists('opcache_reset');

        if (!$result['opcache_enabled']) {
            $result['message'] = 'OPcache is not enabled';
            $this->logger->info('OPcache not enabled');
            return $result;
        }

        // Get opcache status before reset
        if (function_exists('opcache_get_status')) {
            $result['opcache_status'] = opcache_get_status(false);
        }

        // Attempt to reset opcache
        if (function_exists('opcache_reset')) {
            $result['opcache_reset'] = opcache_reset();
        }

        if ($result['opcache_reset']) {
            $result['message'] = 'OPcache cleared successfully';
            $this->logger->info('OPcache cleared');
        } else {
            $result['message'] = 'Failed to clear OPcache';
            $this->logger->error('Failed to clear OPcache');
        }

        return $result;
    }

    /**
     * Get autoloader statistics
     */
    public function getAutoloaderStats(): array
    {
        $this->logger->info('Collecting autoloader statistics');

        $stats = [
            'project_dir' => $this->projectDir,
            'vendor_dir_exists' => is_dir($this->projectDir . '/vendor'),
            'autoload_file_exists' => file_exists($this->projectDir . '/vendor/autoload.php'),
            'composer_json_exists' => file_exists($this->projectDir . '/composer.json'),
            'src_dir_exists' => is_dir($this->projectDir . '/src'),
            'psr4_mappings' => [],
            'classmap_files' => [],
            'total_php_files' => 0,
            'total_directories' => 0
        ];

        // Count PHP files in src directory
        if ($stats['src_dir_exists']) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->projectDir . '/src')
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $stats['total_php_files']++;
                }
                if ($file->isDir()) {
                    $stats['total_directories']++;
                }
            }
        }

        // Count PHP files in vendor directory
        if ($stats['vendor_dir_exists']) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->projectDir . '/vendor')
            );
            
            $vendorPhpFiles = 0;
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $vendorPhpFiles++;
                }
            }
            $stats['total_php_files'] += $vendorPhpFiles;
        }

        $this->logger->info('Autoloader statistics collected', [
            'total_php_files' => $stats['total_php_files'],
            'total_directories' => $stats['total_directories']
        ]);

        return $stats;
    }

    /**
     * Warm up autoloader by loading common classes
     */
    public function warmUpAutoloader(array $classNames = []): array
    {
        $this->logger->info('Starting autoloader warmup');

        // Default set of common classes to load
        if (empty($classNames)) {
            $classNames = [
                'Symfony\\Component\\HttpFoundation\\Request',
                'Symfony\\Component\\HttpFoundation\\Response',
                'Symfony\\Component\\Routing\\Router',
                'Symfony\\Component\\DependencyInjection\\Container',
                'Doctrine\\ORM\\EntityManager',
                'Symfony\\Component\\Security\\Core\\Security',
                'Symfony\\Component\\HttpKernel\\Kernel',
                'Psr\\Log\\LoggerInterface',
            ];
        }

        $results = [
            'attempted_classes' => count($classNames),
            'loaded_classes' => [],
            'failed_classes' => [],
            'load_time' => 0
        ];

        $startTime = microtime(true);

        foreach ($classNames as $className) {
            try {
                if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                    $results['loaded_classes'][] = $className;
                } else {
                    $results['failed_classes'][] = $className;
                }
            } catch (\Throwable $e) {
                $results['failed_classes'][] = $className;
                $this->logger->warning('Failed to load class during warmup', [
                    'class' => $className,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $results['load_time'] = round(microtime(true) - $startTime, 4);

        $this->logger->info('Autoloader warmup completed', [
            'loaded_classes' => count($results['loaded_classes']),
            'failed_classes' => count($results['failed_classes']),
            'load_time' => $results['load_time']
        ]);

        return $results;
    }

    /**
     * Optimize Composer autoloader for production
     */
    public function optimizeForProduction(): array
    {
        $this->logger->info('Starting production autoloader optimization');

        // Run composer dump-autoload with production flags
        $result = $this->optimizeAutoloader(true, true);

        // Clear opcache after optimization
        $opcacheResult = $this->clearAndRegenerateOpcache();

        // Collect final stats
        $finalStats = $this->getAutoloaderStats();

        $combinedResult = [
            'autoloader_optimization' => $result,
            'opcache_result' => $opcacheResult,
            'final_stats' => $finalStats,
            'success' => $result['success'] && $opcacheResult['opcache_reset']
        ];

        $this->logger->info('Production autoloader optimization completed', [
            'success' => $combinedResult['success']
        ]);

        return $combinedResult;
    }
}