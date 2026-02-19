<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:analyze-code',
    description: 'Analyze code for optimization opportunities'
)]
class AnalyzeCodeCommand extends Command
{
    public function __construct(
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('unused-imports', 'u', InputOption::VALUE_NONE, 'Find unused imports')
            ->addOption('large-files', 'l', InputOption::VALUE_NONE, 'Find large files')
            ->addOption('complexity', 'c', InputOption::VALUE_NONE, 'Analyze code complexity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('unused-imports')) {
            $this->findUnusedImports($io);
        }

        if ($input->getOption('large-files')) {
            $this->findLargeFiles($io);
        }

        if ($input->getOption('complexity')) {
            $this->analyzeComplexity($io);
        }

        if (!$input->getOption('unused-imports') && 
            !$input->getOption('large-files') && 
            !$input->getOption('complexity')) {
            // Run all analyses
            $this->findLargeFiles($io);
            $this->analyzeComplexity($io);
        }

        return Command::SUCCESS;
    }

    private function findLargeFiles(SymfonyStyle $io): void
    {
        $io->section('Finding large files');

        $finder = new Finder();
        $finder->files()
            ->in($this->projectDir . '/src')
            ->name('*.php')
            ->size('>= 20K');

        $largeFiles = [];
        foreach ($finder as $file) {
            $largeFiles[] = [
                'file' => str_replace($this->projectDir . '/src/', '', $file->getRealPath()),
                'size' => round($file->getSize() / 1024, 2) . ' KB',
                'lines' => count(file($file->getRealPath()))
            ];
        }

        if (empty($largeFiles)) {
            $io->success('No large files found (>20KB)');
            return;
        }

        usort($largeFiles, fn($a, $b) => (float)$b['size'] <=> (float)$a['size']);

        $io->table(['File', 'Size', 'Lines'], $largeFiles);
        $io->warning(sprintf('Found %d large files that may need refactoring', count($largeFiles)));
    }

    private function analyzeComplexity(SymfonyStyle $io): void
    {
        $io->section('Analyzing code complexity');

        $finder = new Finder();
        $finder->files()
            ->in($this->projectDir . '/src')
            ->name('*.php');

        $complexFiles = [];
        
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            
            // Count methods
            preg_match_all('/\bfunction\s+\w+\s*\(/', $content, $methods);
            $methodCount = count($methods[0]);
            
            // Count if statements (cyclomatic complexity indicator)
            preg_match_all('/\b(if|else|elseif|for|foreach|while|case|catch)\b/', $content, $conditions);
            $conditionCount = count($conditions[0]);
            
            // Calculate complexity score
            $lines = count(file($file->getRealPath()));
            $complexityScore = $methodCount > 0 ? round($conditionCount / $methodCount, 2) : 0;
            
            if ($complexityScore > 5 || $methodCount > 20) {
                $complexFiles[] = [
                    'file' => str_replace($this->projectDir . '/src/', '', $file->getRealPath()),
                    'methods' => $methodCount,
                    'conditions' => $conditionCount,
                    'complexity' => $complexityScore,
                    'lines' => $lines
                ];
            }
        }

        if (empty($complexFiles)) {
            $io->success('No highly complex files found');
            return;
        }

        usort($complexFiles, fn($a, $b) => $b['complexity'] <=> $a['complexity']);

        $io->table(
            ['File', 'Methods', 'Conditions', 'Complexity', 'Lines'],
            array_slice($complexFiles, 0, 10)
        );
        
        $io->warning(sprintf(
            'Found %d files with high complexity (showing top 10)',
            count($complexFiles)
        ));
    }
}
