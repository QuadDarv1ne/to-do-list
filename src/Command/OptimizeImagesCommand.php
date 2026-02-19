<?php

namespace App\Command;

use App\Service\ImageOptimizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-images',
    description: 'Оптимизация изображений в директории uploads',
)]
class OptimizeImagesCommand extends Command
{
    public function __construct(
        private ImageOptimizationService $imageOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('webp', 'w', InputOption::VALUE_NONE, 'Создать WebP версии')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Показать только статистику');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Оптимизация изображений');

        // Статистика до оптимизации
        $statsBefore = $this->imageOptimizer->getStatistics();

        if ($input->getOption('stats')) {
            $this->displayStats($io, $statsBefore);

            return Command::SUCCESS;
        }

        // Оптимизация изображений
        $io->section('Оптимизация изображений');
        $results = $this->imageOptimizer->optimizeAll();

        $io->text(\sprintf(
            'Обработано: %d, Оптимизировано: %d, Ошибок: %d',
            $results['processed'],
            $results['optimized'],
            $results['errors'],
        ));

        if ($results['saved_bytes'] > 0) {
            $savedMB = round($results['saved_bytes'] / 1024 / 1024, 2);
            $io->success("Сэкономлено: {$savedMB} MB");
        }

        // Создание WebP версий
        if ($input->getOption('webp')) {
            $io->section('Создание WebP версий');
            $webpResults = $this->imageOptimizer->createWebPVersions();

            $io->text(\sprintf(
                'Обработано: %d, Создано: %d, Ошибок: %d',
                $webpResults['processed'],
                $webpResults['created'],
                $webpResults['errors'],
            ));
        }

        // Статистика после оптимизации
        $statsAfter = $this->imageOptimizer->getStatistics();
        $this->displayStats($io, $statsAfter);

        $io->success('Оптимизация завершена!');

        return Command::SUCCESS;
    }

    private function displayStats(SymfonyStyle $io, array $stats): void
    {
        $io->section('Статистика изображений');

        $totalSizeMB = round($stats['total_size'] / 1024 / 1024, 2);
        $io->text("Всего изображений: {$stats['total_images']}");
        $io->text("Общий размер: {$totalSizeMB} MB");

        if (!empty($stats['by_format'])) {
            $io->text('По форматам:');
            foreach ($stats['by_format'] as $format => $data) {
                $sizeMB = round($data['size'] / 1024 / 1024, 2);
                $io->text("  {$format}: {$data['count']} файлов, {$sizeMB} MB");
            }
        }
    }
}
