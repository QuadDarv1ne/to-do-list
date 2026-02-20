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
    name: 'app:optimize-twig',
    description: 'Optimize Twig templates',
)]
class OptimizeTwigCommand extends Command
{
    public function __construct(
        private AssetOptimizerService $assetOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('warmup', 'w', InputOption::VALUE_NONE, 'Warm up Twig cache')
            ->addOption('analyze', 'a', InputOption::VALUE_NONE, 'Analyze template usage');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('warmup')) {
            return $this->warmupCache($io);
        }

        if ($input->getOption('analyze')) {
            return $this->analyzeUsage($io);
        }

        // Default: warmup cache
        return $this->warmupCache($io);
    }

    private function warmupCache(SymfonyStyle $io): int
    {
        $io->section('Warming up Twig cache');

        $result = $this->assetOptimizer->warmupTwigCache();

        if (!empty($result['errors'])) {
            $io->warning(\sprintf(
                'Warmed up %d/%d templates with %d errors',
                $result['templates_warmed'],
                $result['total_templates'],
                \count($result['errors']),
            ));

            $io->table(
                ['Template', 'Error'],
                array_map(fn ($e) => [$e['template'], $e['error']], $result['errors']),
            );
        } else {
            $io->success(\sprintf(
                'Successfully warmed up %d templates',
                $result['templates_warmed'],
            ));
        }

        return Command::SUCCESS;
    }

    private function analyzeUsage(SymfonyStyle $io): int
    {
        $io->section('Analyzing template usage');

        $result = $this->assetOptimizer->analyzeTemplateUsage();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Templates', $result['total_templates']],
                ['Used Templates', $result['used_templates']],
                ['Unused Templates', \count($result['unused_templates'])],
                ['Usage Rate', $result['usage_percent'] . '%'],
            ],
        );

        if (!empty($result['unused_templates'])) {
            $io->warning('Potentially unused templates:');
            $io->listing(\array_slice($result['unused_templates'], 0, 20));

            if (\count($result['unused_templates']) > 20) {
                $io->note(\sprintf(
                    '... and %d more',
                    \count($result['unused_templates']) - 20,
                ));
            }
        }

        return Command::SUCCESS;
    }
}
