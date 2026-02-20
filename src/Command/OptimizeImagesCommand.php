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
    name: 'app:optimize-images',
    description: 'Оптимизация изображений в директории uploads',
)]
class OptimizeImagesCommand extends Command
{
    public function __construct(
        private AssetOptimizerService $assetOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // No options needed - just optimize images
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Оптимизация изображений');

        $result = $this->assetOptimizer->optimizeImages();

        if (!empty($result['errors'])) {
            $io->warning(\sprintf(
                'Оптимизировано %d/%d изображений с %d ошибками',
                $result['optimized'],
                $result['total'],
                \count($result['errors']),
            ));

            foreach ($result['errors'] as $error) {
                $io->writeln('  ❌ ' . $error);
            }
        } else {
            $io->success(\sprintf(
                'Успешно оптимизировано %d изображений',
                $result['optimized'],
            ));
        }

        if (!empty($result['images'])) {
            $totalSaved = array_sum(array_column($result['images'], 'saved'));
            $savedMB = round($totalSaved / 1024 / 1024, 2);
            $io->info("Сэкономлено: {$savedMB} MB");
        }

        return Command::SUCCESS;
    }
}
