<?php

namespace App\Command;

use App\Service\AssetOptimizerService;
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
        private AssetOptimizerService $assetOptimizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Оптимизация производительности');

        // Unified asset optimization (CSS, Images, Twig, Themes)
        $io->section('Оптимизация всех ресурсов');
        try {
            $results = $this->assetOptimizer->optimizeAll();
            
            $io->success('CSS: ' . ($results['css']['optimized'] ?? 0) . ' файлов оптимизировано');
            $io->success('Изображения: ' . ($results['images']['optimized'] ?? 0) . ' файлов оптимизировано');
            $io->success('Twig: ' . ($results['twig']['templates_warmed'] ?? 0) . ' шаблонов прогрето');
            $io->success('Темы: оптимизированы');
        } catch (\Exception $e) {
            $io->error('Ошибка оптимизации: ' . $e->getMessage());
        }

        $io->success('Оптимизация завершена!');

        return Command::SUCCESS;
    }
}
