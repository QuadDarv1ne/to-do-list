<?php

namespace App\Command;

use App\Service\CssOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-css',
    description: 'Optimize and combine CSS files'
)]
class OptimizeCssCommand extends Command
{
    public function __construct(
        private CssOptimizerService $cssOptimizer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('analyze', 'a', InputOption::VALUE_NONE, 'Analyze CSS usage')
            ->addOption('duplicates', 'd', InputOption::VALUE_NONE, 'Find duplicate rules')
            ->addOption('combine', 'c', InputOption::VALUE_NONE, 'Combine and minify CSS files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('analyze')) {
            return $this->analyzeCss($io);
        }

        if ($input->getOption('duplicates')) {
            return $this->findDuplicates($io);
        }

        if ($input->getOption('combine')) {
            return $this->combineCss($io);
        }

        // Default: run all optimizations
        $this->analyzeCss($io);
        $this->findDuplicates($io);
        $this->combineCss($io);

        return Command::SUCCESS;
    }

    private function analyzeCss(SymfonyStyle $io): int
    {
        $io->section('Analyzing CSS files');

        $result = $this->cssOptimizer->analyzeCssUsage();

        $io->table(
            ['File', 'Size (KB)', 'Lines'],
            array_map(fn($file) => [
                $file['file'],
                $file['size_kb'],
                $file['lines']
            ], $result['files'])
        );

        $io->info(sprintf(
            'Total: %d files, %.2f KB',
            $result['total_files'],
            $result['total_size_kb']
        ));

        return Command::SUCCESS;
    }

    private function findDuplicates(SymfonyStyle $io): int
    {
        $io->section('Finding duplicate CSS rules');

        $result = $this->cssOptimizer->removeDuplicates();

        if ($result['total_duplicates'] > 0) {
            $io->warning(sprintf('Found %d duplicate rules', $result['total_duplicates']));
            
            foreach ($result['duplicates_found'] as $file => $count) {
                $io->writeln(sprintf('  - %s: %d duplicates', $file, $count));
            }
        } else {
            $io->success('No duplicate rules found');
        }

        return Command::SUCCESS;
    }

    private function combineCss(SymfonyStyle $io): int
    {
        $io->section('Combining and minifying CSS files');

        $result = $this->cssOptimizer->optimizeAndCombine();

        if ($result['success']) {
            $io->success(sprintf(
                'CSS optimized successfully! Processed %d files, reduced size by %s%%',
                $result['files_processed'],
                $result['reduction_percent']
            ));

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Original Size', number_format($result['original_size']) . ' bytes'],
                    ['Final Size', number_format($result['final_size']) . ' bytes'],
                    ['Reduction', $result['reduction_percent'] . '%'],
                    ['Output File', basename($result['output_file'])]
                ]
            );
        } else {
            $io->error('CSS optimization failed: ' . $result['error']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
