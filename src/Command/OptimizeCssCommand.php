<?php

namespace App\Command;

use App\Service\AssetOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-css',
    description: 'Optimize and combine CSS files',
)]
class OptimizeCssCommand extends Command
{
    public function __construct(
        private AssetOptimizerService $assetOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // No options needed - just optimize CSS
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('CSS Optimization');

        $result = $this->assetOptimizer->optimizeCSS();

        if (!empty($result['errors'])) {
            $io->warning(\sprintf(
                'Optimized %d/%d CSS files with %d errors',
                $result['optimized'],
                $result['total'],
                \count($result['errors']),
            ));

            foreach ($result['errors'] as $error) {
                $io->writeln('  ❌ ' . $error);
            }
        } else {
            $io->success(\sprintf(
                'Successfully optimized %d CSS files',
                $result['optimized'],
            ));
        }

        return Command::SUCCESS;
    }
}
