<?php

namespace App\Command;

use App\Service\AdvancedCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache-management',
    description: 'Advanced cache management and optimization'
)]
class CacheManagementCommand extends Command
{
    private AdvancedCacheService $advancedCacheService;

    public function __construct(AdvancedCacheService $advancedCacheService)
    {
        $this->advancedCacheService = $advancedCacheService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: stats, clear-pool, warm-up')
            ->addOption('pool', 'p', InputOption::VALUE_REQUIRED, 'Cache pool name to operate on')
            ->addOption('warm-user', 'u', InputOption::VALUE_REQUIRED, 'User ID for warming up user-specific cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        
        $io->title('Advanced Cache Management');
        
        switch ($action) {
            case 'stats':
                $this->showCacheStats($io);
                break;
                
            case 'clear-pool':
                $pool = $input->getOption('pool');
                if (!$pool) {
                    $io->error('Pool name is required for clear-pool action');
                    return Command::FAILURE;
                }
                $this->clearCachePool($io, $pool);
                break;
                
            case 'warm-up':
                $userId = $input->getOption('warm-user');
                if ($userId) {
                    $this->warmUpUserCache($io, (int)$userId);
                } else {
                    $this->warmUpGeneralCache($io);
                }
                break;
                
            default:
                $this->showHelp($io);
                return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    private function showCacheStats(SymfonyStyle $io): void
    {
        $io->section('Cache Configuration Statistics');
        
        $stats = $this->advancedCacheService->getCacheStats();
        
        $io->table(
            ['Pool Name', 'Cache Pool', 'Default TTL (seconds)'],
            [
                ['Queries', $stats['pools']['queries'], $stats['default_ttls']['queries']],
                ['Statistics', $stats['pools']['statistics'], $stats['default_ttls']['statistics']],
                ['User Data', $stats['pools']['user_data'], $stats['default_ttls']['user_data']],
                ['Aggregate Metrics', $stats['pools']['aggregate_metrics'], $stats['default_ttls']['aggregate_metrics']],
                ['Performance', $stats['pools']['performance'], $stats['default_ttls']['performance']],
                ['Notifications', $stats['pools']['notifications'], $stats['default_ttls']['notifications']],
            ]
        );
        
        $io->success('Cache statistics displayed');
    }

    private function clearCachePool(SymfonyStyle $io, string $poolName): void
    {
        $io->section("Clearing cache pool: {$poolName}");
        
        try {
            $this->advancedCacheService->clearPool($poolName);
            $io->success("Cache pool '{$poolName}' cleared successfully");
        } catch (\Exception $e) {
            $io->error("Failed to clear cache pool: " . $e->getMessage());
        }
    }

    private function warmUpUserCache(SymfonyStyle $io, int $userId): void
    {
        $io->section("Warming up cache for user ID: {$userId}");
        
        // Simulate cache warming for user data
        $io->writeln('Warming up user statistics...');
        $this->advancedCacheService->cacheUserData($userId, 'statistics', function() {
            return ['tasks_count' => 10, 'completed_count' => 5];
        }, 900);
        
        $io->writeln('Warming up user notifications...');
        $this->advancedCacheService->cacheNotifications($userId, function() {
            return ['unread_count' => 3, 'recent_notifications' => []];
        }, 300);
        
        $io->success("User cache warmed up for user ID: {$userId}");
    }

    private function warmUpGeneralCache(SymfonyStyle $io): void
    {
        $io->section('Warming up general application cache');
        
        // Warm up statistics
        $io->writeln('Warming up application statistics...');
        $this->advancedCacheService->cacheStatistics('app_overview', function() {
            return [
                'total_users' => 100,
                'total_tasks' => 1000,
                'active_users' => 45
            ];
        }, 600);
        
        // Warm up performance metrics
        $io->writeln('Warming up performance metrics...');
        $this->advancedCacheService->cachePerformanceMetrics('system_metrics', function() {
            return [
                'memory_usage' => memory_get_usage(),
                'peak_memory' => memory_get_peak_usage(),
                'execution_time' => microtime(true)
            ];
        }, 1800);
        
        // Warm up real-time data
        $io->writeln('Warming up real-time data...');
        $this->advancedCacheService->cacheRealTimeData('current_time', function() {
            return [
                'timestamp' => time(),
                'formatted' => date('Y-m-d H:i:s')
            ];
        }, 120);
        
        $io->success('General cache warming completed');
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'stats - Show cache configuration and statistics',
            'clear-pool - Clear specific cache pool (requires --pool option)',
            'warm-up - Warm up cache (use --warm-user for specific user)'
        ]);
        
        $io->section('Examples');
        $io->listing([
            'php bin/console app:cache-management --action=stats',
            'php bin/console app:cache-management --action=clear-pool --pool=cache.app_queries',
            'php bin/console app:cache-management --action=warm-up',
            'php bin/console app:cache-management --action=warm-up --warm-user=1'
        ]);
    }
}