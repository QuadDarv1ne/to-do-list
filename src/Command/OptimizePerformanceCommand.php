<?php

namespace App\Command;

use App\Service\AssetOptimizationService;
use App\Service\CacheOptimizationService;
use App\Service\ThemeOptimizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-performance',
    description: 'Оптимизирует производительность приложения'
)]
class OptimizePerformanceCommand extends Command
{
    public function __construct(
        private AssetOptimizationService $assetOptimizer,
        private CacheOptimizationService $cacheOptimizer,
        private ThemeOptimizationService $themeOptimizer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Оптимизация производительности');

        // Оптимизация ресурсов
        $io->section('Оптимизация CSS и изображений');
        try {
            $this->assetOptimizer->optimizeAll();
            $io->success('Ресурсы оптимизированы');
        } catch (\Exception $e) {
            $io->error('Ошибка оптимизации ресурсов: ' . $e->getMessage());
        }

        // Прогрев кэша
        $io->section('Прогрев кэша');
        try {
            $this->cacheOptimizer->warmupCache();
            $io->success('Кэш прогрет');
        } catch (\Exception $e) {
            $io->error('Ошибка прогрева кэша: ' . $e->getMessage());
        }

        // Оптимизация тем
        $io->section('Оптимизация системы тем');
        try {
            $this->themeOptimizer->optimizeAll();
            $io->success('Темы оптимизированы');
        } catch (\Exception $e) {
            $io->error('Ошибка оптимизации тем: ' . $e->getMessage());
        }

        $io->success('Оптимизация завершена!');

        return Command::SUCCESS;
    }
}