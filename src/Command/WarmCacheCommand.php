<?php

namespace App\Command;

use App\Service\CacheWarmerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:warm-cache',
    description: 'Warm up application caches with commonly accessed data'
)]
class WarmCacheCommand extends Command
{
    private CacheWarmerService $cacheWarmerService;

    public function __construct(CacheWarmerService $cacheWarmerService)
    {
        $this->cacheWarmerService = $cacheWarmerService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting cache warming process...</info>');
        
        $progressBar = new ProgressBar($output, 3);
        $progressBar->setFormat('verbose');
        
        $progressBar->setMessage('Warming up user statistics...');
        $progressBar->start();
        
        $this->cacheWarmerService->warmCaches();
        
        $progressBar->advance();
        $progressBar->setMessage('Warming up task statistics...');
        $progressBar->advance();
        $progressBar->setMessage('Warming up active users...');
        $progressBar->advance();
        $progressBar->finish();
        
        $output->writeln("\n".'<info>Cache warming process completed successfully!</info>');
        
        return Command::SUCCESS;
    }
}