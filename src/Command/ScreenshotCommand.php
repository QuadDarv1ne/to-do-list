<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ScreenshotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:screenshot',
    description: 'Создать скриншот страницы сайта',
)]
class ScreenshotCommand extends Command
{
    public function __construct(
        private readonly ScreenshotService $screenshotService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'URL страницы для скриншота')
            ->addArgument('filename', InputArgument::OPTIONAL, 'Имя файла (необязательно)')
            ->addOption('width', null, InputOption::VALUE_REQUIRED, 'Ширина viewport', '1920')
            ->addOption('height', null, InputOption::VALUE_REQUIRED, 'Высота viewport', '1080')
            ->addOption('full-page', null, InputOption::VALUE_NONE, 'Скриншот всей страницы')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Файл со списком URL (по одному в строке)')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Очистить старые скриншоты')
            ->addOption('cleanup-days', null, InputOption::VALUE_REQUIRED, 'Дней для очистки', '30')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'Показать список скриншотов')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Список скриншотов
        if ($input->getOption('list')) {
            return $this->listScreenshots($io);
        }

        // Очистка
        if ($input->getOption('cleanup')) {
            return $this->cleanupScreenshots($io, (int) $input->getOption('cleanup-days'));
        }

        // Пакетная обработка
        if ($input->getOption('batch')) {
            return $this->batchScreenshots($io, $input->getOption('batch'));
        }

        // Одиночный скриншот
        return $this->takeScreenshot($io, $input);
    }

    private function takeScreenshot(SymfonyStyle $io, InputInterface $input): int
    {
        $url = $input->getArgument('url');
        $filename = $input->getArgument('filename');
        
        $options = [
            'width' => (int) $input->getOption('width'),
            'height' => (int) $input->getOption('height'),
            'fullPage' => $input->getOption('full-page'),
        ];

        $io->section('Создание скриншота');
        $io->text("URL: <info>$url</info>");

        $result = $this->screenshotService->takeScreenshot($url, $filename, $options);

        if ($result['success']) {
            $io->success("Скриншот создан: {$result['file']}");
            
            if (isset($result['note'])) {
                $io->note($result['note']);
            }
            
            return Command::SUCCESS;
        }

        $io->error("Ошибка: {$result['error']}");
        return Command::FAILURE;
    }

    private function batchScreenshots(SymfonyStyle $io, string $batchFile): int
    {
        if (!file_exists($batchFile)) {
            $io->error("Файл не найден: $batchFile");
            return Command::FAILURE;
        }

        $urls = file($batchFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (empty($urls)) {
            $io->warning('Файл пуст');
            return Command::SUCCESS;
        }

        $io->section('Пакетное создание скриншотов');
        $io->text("Найдено URL: <info>" . count($urls) . "</info>");

        $progress = $io->createProgressBar(count($urls));
        $progress->start();

        $success = 0;
        $failed = 0;

        foreach ($urls as $url) {
            $result = $this->screenshotService->takeScreenshot(trim($url));
            
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
            
            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $io->success("Готово! Успешно: $success, Ошибок: $failed");

        return Command::SUCCESS;
    }

    private function cleanupScreenshots(SymfonyStyle $io, int $days): int
    {
        $io->section('Очистка старых скриншотов');
        
        $deleted = $this->screenshotService->cleanupOldScreenshots($days);
        
        $io->success("Удалено скриншотов: <info>$deleted</info> (старше $days дней)");
        
        return Command::SUCCESS;
    }

    private function listScreenshots(SymfonyStyle $io): int
    {
        $io->section('Список скриншотов');
        
        $screenshots = $this->screenshotService->getScreenshotList();
        
        if (empty($screenshots)) {
            $io->note('Скриншоты не найдены');
            return Command::SUCCESS;
        }

        $io->table(
            ['Файл', 'Размер', 'Дата'],
            array_map(fn($s) => [
                $s['filename'],
                round($s['size'] / 1024, 1) . ' КБ',
                date('d.m.Y H:i', $s['created']),
            ], $screenshots)
        );

        $io->text("Всего: <info>" . count($screenshots) . "</info>");

        return Command::SUCCESS;
    }
}
