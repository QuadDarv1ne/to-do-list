<?php

namespace App\Command;

use App\Service\DataLoaderOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data-loader-optimize',
    description: 'Optimize data loading operations and reduce N+1 queries'
)]
class DataLoaderOptimizeCommand extends Command
{
    private DataLoaderOptimizerService $dataLoaderOptimizer;

    public function __construct(DataLoaderOptimizerService $dataLoaderOptimizer)
    {
        $this->dataLoaderOptimizer = $dataLoaderOptimizer;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: preload, stats, clear-cache')
            ->addOption('user-ids', 'u', InputOption::VALUE_OPTIONAL, 'Comma-separated user IDs for specific operations')
            ->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'Page number for pagination tests', '1')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit for pagination tests', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');

        $io->title('Data Loader Optimization Tool');

        switch ($action) {
            case 'preload':
                $this->performPreloading($io);
                break;

            case 'stats':
                $this->showStats($io);
                break;

            case 'clear-cache':
                $this->clearCache($io);
                break;

            default:
                $this->showHelp($io);
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function performPreloading(SymfonyStyle $io): void
    {
        $io->section('Preloading Common Data');
        $io->writeln('Loading active users and common categories...');

        $startTime = microtime(true);
        $this->dataLoaderOptimizer->preloadCommonData();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $io->success("Preloading completed in " . round($executionTime, 2) . "ms");
    }

    private function showStats(SymfonyStyle $io): void
    {
        $io->section('Data Loader Cache Statistics');

        $stats = $this->dataLoaderOptimizer->getCacheStats();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Cached Entity Types', count($stats['cached_entity_types'])],
                ['Total Cached Entities', $stats['total_cached_entities']],
            ]
        );

        if (!empty($stats['breakdown'])) {
            $io->section('Cache Breakdown');
            $rows = [];
            foreach ($stats['breakdown'] as $type => $count) {
                $rows[] = [$type, $count];
            }
            $io->table(['Entity Type', 'Count'], $rows);
        }
    }

    private function clearCache(SymfonyStyle $io): void
    {
        $io->section('Clearing Data Loader Cache');
        
        $this->dataLoaderOptimizer->clearCache();
        
        $io->success('Data loader cache cleared successfully.');
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'preload - Preload commonly accessed data',
            'stats - Show cache statistics and optionally test loading performance',
            'clear-cache - Clear the data loader cache'
        ]);

        $io->section('Options');
        $io->listing([
            '--action (-a) - Action to perform',
            '--user-ids (-u) - Comma-separated user IDs for performance testing',
            '--page (-p) - Page number for pagination tests (default: 1)',
            '--limit (-l) - Limit for pagination tests (default: 20)'
        ]);

        $io->section('Examples');
        $io->listing([
            'php bin/console app:data-loader-optimize --action=preload',
            'php bin/console app:data-loader-optimize --action=stats',
            'php bin/console app:data-loader-optimize --action=clear-cache'
        ]);
    }
}
